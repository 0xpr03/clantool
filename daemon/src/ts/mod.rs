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
use std::collections::HashSet;
use std::thread::sleep;
use std::time::Duration;
use ts3_query::*;

const CHANNEL_NAME: &str = "channel_name";
const CHANNEL_ID: &str = "cid";

const CLIENT_TYPE: &str = "client_type";
const CLIENT_TYPE_NORMAL: &str = "0";
const CLIENT_ID: &str = "client_database_id";
const CLIENT_GROUPS: &str = "client_servergroups";
const CLIENT_CHANNEL: &str = CHANNEL_ID;
const CLIENT_NAME: &str = "client_nickname";

mod connection;

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
pub fn get_online_clients(conn: &mut Connection) -> Result<HashSet<TsClient>> {
    let res = raw::parse_multi_hashmap(conn.get()?.raw_command("clientlist -groups")?, false);
    dbg!(res.len());
    dbg!(&res);
    let clients = res
        .into_iter()
        .filter(|e| e.get(CLIENT_TYPE).map(String::as_str) == Some(CLIENT_TYPE_NORMAL))
        .map(|e| {
            Ok(TsClient {
                name: e
                    .get(CLIENT_NAME)
                    .map(raw::unescape_val)
                    .ok_or_else(|| Error::TsMissingValue(CLIENT_NAME))?,
                db_id: e
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

/// Returns channels on TS.
pub fn get_channels(conn: &mut Connection) -> Result<Vec<Channel>> {
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
    use crate::config::TSConfig;

    #[test]
    #[ignore]
    fn perform_get_online_clients() {
        let cfg = TSConfig {
            ip: option_env!("ts_ip").unwrap_or("localhost").to_string(),
            port: option_env!("ts_port").unwrap_or("11001").parse().unwrap(),
            user: option_env!("ts_user").unwrap_or("serveradmin").to_string(),
            password: option_env!("ts_pw").unwrap_or("1234").to_string(),
            server_port: option_env!("ts_port_server")
                .unwrap_or("6678")
                .parse()
                .unwrap(),
            unknown_id_check_enabled: true,
        };
        dbg!(&cfg);
        let mut conn = Connection::new(&cfg).unwrap();
        let clients = get_online_clients(&mut conn).unwrap();
        dbg!(clients);
        let channels = get_channels(&mut conn).unwrap();
        dbg!(channels);
    }
}
