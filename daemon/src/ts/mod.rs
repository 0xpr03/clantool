// Copyright 2017-2020 Aron Heinecke
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//   http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

use crate::config::TSConfig;
use crate::db;
use crate::error::Error;
use crate::Result;
use crate::*;
use connection::Connection;
use mysql::{Pool, PooledConn};
use std::collections::HashMap;
use std::collections::HashSet;
use std::convert::TryInto;
use std::thread::sleep;
use std::time::Duration;
use std::{sync::RwLock, time::Instant};
use timer::*;
use ts3_query::*;

const CHANNEL_NAME: &str = "channel_name";
const CHANNEL_ID: &str = "cid";

const CLIENT_TYPE: &str = "client_type";
const CLIENT_TYPE_NORMAL: &str = "0";
const CLIENT_ID: &str = "client_database_id";
const CLIENT_GROUPS: &str = "client_servergroups";
const CLIENT_CHANNEL: &str = CHANNEL_ID;
const CLIENT_NAME: &str = "client_nickname";

/// Safety: connection timeout has to be short enough that no request blocks others!
const INTERVAL_ACTIVITY_S: i64 = 30;

mod connection;

/// Holds TS statistics data and cleans up on drop
///
/// Is used by daemon to hold user activity.
struct TsStatCtrl {
    pool: Pool,
    conn: Connection,
    names: HashMap<TsClDBID, String>,
    times: HashMap<(TsClDBID, ChannelID), i32>,
    last_channel: HashMap<TsClDBID, ChannelID>,
    last_date: chrono::naive::NaiveDate,
    last_guest_poke: Option<Instant>,
    last_update: Instant,
}

impl TsStatCtrl {
    fn new(pool: Pool, config: Config) -> Result<Self> {
        Ok(Self {
            pool,
            conn: Connection::new(config)?,
            last_date: Local::today().naive_local(),
            last_update: Instant::now(),
            names: Default::default(),
            times: Default::default(),
            last_channel: Default::default(),
            last_guest_poke: Default::default(),
        })
    }

    /// Check online clients, update activity & send notifications
    fn tick(&mut self) -> Result<()> {
        // store timestamp now to prevent delta loss by blocking operations
        let new_timestamp = Instant::now();
        let data = get_online_clients(&mut self.conn)?;
        // take elapsed after data, expecting server reponse to be fast and connection start possibly slow
        let elapsed: i32 = match self.last_update.elapsed().as_secs().try_into() {
            Ok(v) => v,
            Err(e) => panic!("TS activity elapsed time > i32::max! {}", e),
        };
        trace!("Elapsed: {} seconds", elapsed);
        self.last_update = new_timestamp;

        let mut new_channel = HashMap::with_capacity(data.len());
        for client in data {
            let id = client.clid;
            self.names.insert(id, client.name);
            // add elapsed time to last channel, or current if no previous is known
            let chan = self.last_channel.get(&id).unwrap_or(&client.channel);

            let k = (id, *chan);
            if let Some(time) = self.times.get_mut(&k) {
                *time = *time + elapsed;
            } else {
                self.times.insert(k, elapsed);
            }

            // remember current channel for next time
            new_channel.insert(id, client.channel);
            // TODO: group check
        }
        // excludes disconnected clients
        self.last_channel = new_channel;
        Ok(())
    }

    /// Flush data to DB
    fn flush_data(&mut self) -> Result<()> {
        let mut conn = self.pool.get_conn()?;
        // clear data only after successful update
        let values: Vec<(TsClDBID, &str)> = self
            .names
            .iter()
            .map(|(id, name)| (*id, name.as_str()))
            .collect();
        db::ts::update_ts_names(&mut conn, values.as_slice())?;
        trace!("Flushed {} name entries",self.names.len());
        self.names.clear();

        let values: Vec<_> = self
            .times
            .iter()
            .map(|((client, channel), time)| TsActivity {
                client: *client,
                channel: *channel,
                time: *time,
            })
            .collect();
        db::ts::update_ts_activity(&mut conn, &self.last_date, values.as_slice())?;
        trace!("Flushed {} time entries",self.times.len());
        self.times.clear();
        self.last_date = Local::today().naive_local();
        Ok(())
    }
}

impl Drop for TsStatCtrl {
    fn drop(&mut self) {
        if let Err(e) = self.flush_data() {
            error!("Error flushing TS data on cleanup: {}", e);
        }
    }
}

/// Start TS daemon, returns scheduler-guards
pub fn start_daemon(pool: Pool, config: Config) -> Result<Vec<Timer>> {
    if config.ts.check_activity {
        debug!("Starting TS activity check");
        let timer_1 = Timer::new();
        // TODO: better threading sync, quick hack currently that blocks ticks on flush
        let ts_handler = Arc::new(RwLock::new(TsStatCtrl::new(pool.clone(), config.clone())?));
        let handler_c = ts_handler.clone();
        timer_1
            .schedule_repeating(chrono::Duration::seconds(INTERVAL_ACTIVITY_S), move || {
                trace!("Performing ts handler tick");
                let mut guard = handler_c.write().unwrap();
                if let Err(e) = guard.tick() {
                    error!("{}", e);
                }
            })
            .ignore();
        let timer_2 = Timer::new();
        let mut conn = Connection::new(config)?;
        timer_2
            .schedule_repeating(chrono::Duration::minutes(15), move || {
                trace!("Performing channel update & data flush");
                if let Err(e) = update_channels(&pool, &mut conn) {
                    error!("Error performing TS channel update! {}", e);
                }
                let mut guard = ts_handler.write().unwrap();
                if let Err(e) = guard.flush_data() {
                    error!("Error flushing TS Data to DB! {}", e);
                }
            })
            .ignore();

        Ok(vec![timer_1, timer_2])
    } else {
        info!("TS activity check disabled, skipping");
        Ok(Vec::new())
    }
}

/// Error-Wrapper for updating TS channel list
fn update_channels(pool: &Pool, mut conn: &mut Connection) -> Result<()> {
    db::ts::upsert_channels(&mut pool.get_conn()?, &get_channels(&mut conn)?)?;
    Ok(())
}

/// Check for unknown identities with member group and update unknown_ts_ids
pub fn find_unknown_identities(pool: &Pool, ts_cfg: &TSConfig) -> Result<()> {
    let retry_time_secs = 60;
    let max_tries = 10;
    let mut conn = pool.get_conn()?;
    for i in 1..=max_tries {
        // don't retry if DB is missing values, only on DB connection problems
        let group_ids =
            get_ts3_member_groups(&mut conn)?.ok_or(Error::MissingKey(crate::TS3_MEMBER_GROUP))?;
        match find_unknown_inner(&group_ids, &mut conn, ts_cfg) {
            Ok(_) => return Ok(()),
            Err(e) => {
                if i == max_tries {
                    error!("Retried unknown-identity-check {} times, aborting.", i);
                    return Err(e);
                } else {
                    warn!("Failed unknown-identity-check, try no {}, error: {}", i, e);
                    sleep(Duration::from_secs(retry_time_secs * i));
                }
            }
        }
    }
    unreachable!();
}

// use try {} when #31436 is stable
fn find_unknown_inner(
    group_ids: &[usize],
    mut conn: &mut PooledConn,
    ts_cfg: &TSConfig,
) -> Result<()> {
    trace!("Connect ts3");
    let mut connection = QueryClient::new(format!("{}:{}", ts_cfg.ip, ts_cfg.port))?;
    trace!("login");
    connection.login(&ts_cfg.user, &ts_cfg.password)?;
    trace!("server select");
    connection.select_server_by_port(ts_cfg.server_port)?;
    trace!("TS3 server connection ready");

    let mut ids = Vec::new();
    for group in group_ids {
        ids.append(&mut connection.get_servergroup_client_list(*group)?);
        trace!("Retrieved ts clients for {}", group);
    }
    db::ts::update_unknown_ts_ids(&mut conn, &ids)?;

    debug!("Performed TS identity check. {} IDs", ids.len());
    Ok(())
}

/// Get ts3 member groups settings, return an optional vec of member group-ids
#[inline]
pub fn get_ts3_member_groups(conn: &mut PooledConn) -> Result<Option<Vec<usize>>> {
    db::read_list_setting(conn, crate::TS3_MEMBER_GROUP)
}

/// Get clients on ts. Returns last entry for multiple connection of same ID.
fn get_online_clients(conn: &mut Connection) -> Result<HashSet<TsClient>> {
    let res = raw::parse_multi_hashmap(conn.get()?.raw_command("clientlist -groups")?, false);
    //dbg!(res.len());
    //dbg!(&res);
    let clients = res
        .into_iter()
        .filter(|e| e.get(CLIENT_TYPE).map(String::as_str) == Some(CLIENT_TYPE_NORMAL))
        .map(|e| {
            Ok(TsClient {
                name: e
                    .get(CLIENT_NAME)
                    .map(raw::unescape_val)
                    .ok_or_else(|| Error::TsMissingValue(CLIENT_NAME))?,
                clid: e
                    .get(CLIENT_ID)
                    .ok_or_else(|| Error::TsMissingValue(CLIENT_ID))?
                    .parse()?,
                channel: e
                    .get(CLIENT_CHANNEL)
                    .ok_or_else(|| Error::TsMissingValue(CLIENT_CHANNEL))?
                    .parse()?,
                groups: e
                    .get(CLIENT_GROUPS)
                    .ok_or_else(|| Error::TsMissingValue(CLIENT_GROUPS))?
                    .split(',')
                    .map(|e| e.parse().map_err(From::from))
                    .collect::<Result<Vec<_>>>()?,
            })
        })
        .collect::<Result<HashSet<TsClient>>>()?;
    Ok(clients)
}

fn get_channels(conn: &mut Connection) -> Result<Vec<Channel>> {
    let res = raw::parse_multi_hashmap(conn.get()?.raw_command("channellist")?, false);
    res.into_iter()
        .map(|e| {
            Ok(Channel {
                id: e
                    .get(CHANNEL_ID)
                    .ok_or_else(|| Error::TsMissingValue(CHANNEL_ID))?
                    .parse()?,
                name: e
                    .get(CHANNEL_NAME)
                    .map(raw::unescape_val)
                    .ok_or_else(|| Error::TsMissingValue(CHANNEL_NAME))?,
            })
        })
        .collect::<Result<Vec<_>>>()
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::config::{default_cfg_testing, TSConfig};

    #[test]
    #[ignore]
    fn perform_get_online_clients() {
        let ts_cfg = TSConfig {
            ip: option_env!("ts_ip").unwrap_or("localhost").to_string(),
            port: option_env!("ts_port").unwrap_or("11001").parse().unwrap(),
            user: option_env!("ts_user").unwrap_or("serveradmin").to_string(),
            password: option_env!("ts_pw").unwrap_or("1234").to_string(),
            server_port: option_env!("ts_port_server")
                .unwrap_or("6678")
                .parse()
                .unwrap(),
            unknown_id_check_enabled: true,
            check_activity: true,
        };
        // create default cfg, change to use our ts config
        let mut def = default_cfg_testing();
        Arc::get_mut(&mut def).unwrap().ts = ts_cfg;
        dbg!(&def);

        let mut conn = Connection::new(def).unwrap();
        // let clients = get_online_clients(&mut conn).unwrap();
        // dbg!(clients);
        // let channels = get_channels(&mut conn).unwrap();
        // dbg!(channels);
        let snapshot = conn.get().unwrap().raw_command("serversnapshotcreate").unwrap();
        dbg!(snapshot);
    }
}
