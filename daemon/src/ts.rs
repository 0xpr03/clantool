use crate::config::TSConfig;
use crate::db;
use crate::error::Error;
use crate::Result;
use crate::TsClient;
use mysql::{Pool, PooledConn};
use std::collections::HashSet;
use std::thread::sleep;
use std::time::Duration;
use ts3_query::*;

const CLIENT_TYPE: &str = "client_type";
const CLIENT_TYPE_NORMAL: &str = "0";
const CLIENT_ID: &str = "client_database_id";
const CLIENT_GROUPS: &str = "client_servergroups";
const CLIENT_CHANNEL: &str = "cid";
const CLIENT_NAME: &str = "client_nickname";

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
    db::update_unknown_ts_ids(&mut conn, &ids)?;

    debug!("Performed TS identity check. {} IDs", ids.len());
    Ok(())
}

/// Get ts3 member groups settings, return an optional vec of member group-ids
#[inline]
pub fn get_ts3_member_groups(conn: &mut PooledConn) -> Result<Option<Vec<usize>>> {
    db::read_list_setting(conn, crate::TS3_MEMBER_GROUP)
}

/// Get clients on ts
pub fn get_online_clients(ts_cfg: &TSConfig) -> Result<()> {
    //-> Result<Vec<TsClient>> {
    let mut connection = QueryClient::new(format!("{}:{}", ts_cfg.ip, ts_cfg.port))?;
    connection.login(&ts_cfg.user, &ts_cfg.password)?;
    connection.select_server_by_port(ts_cfg.server_port)?;
    //connection.rename("bot2134564651321")?;
    let res = raw::parse_multi_hashmap(connection.raw_command("clientlist -groups")?, false);
    dbg!(res.len());
    dbg!(&res);
    let clients = res
        .iter()
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
    dbg!(clients);
    Ok(())
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::config::TSConfig;
    use crate::TsClient;

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
        let clients = get_online_clients(&cfg).unwrap();
        dbg!(clients);
    }
}
