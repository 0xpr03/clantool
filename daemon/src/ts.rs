use crate::config::TSConfig;
use crate::db;
use crate::error::Error;
use crate::Result;
use mysql::{Pool, PooledConn};
use std::thread::sleep;
use std::time::Duration;
use ts3_query::*;

/// Check for unknown identities with member group and update unknown_ts_ids
pub fn find_unknown_identities(pool: &Pool, ts_cfg: &TSConfig) -> Result<()> {
    let retry_time_secs = 60;
    let max_tries = 10;
    let mut conn = pool.get_conn()?;
    for i in 1..=max_tries {
        // don't retry if DB is missing values, only on DB connection problems
        let group_ids = get_ts3_member_groups(&mut conn)?.ok_or(Error::MissingKey(crate::TS3_MEMBER_GROUP))?;
        match find_unknown_inner(&group_ids,&mut conn, ts_cfg) {
            Ok(_) => return Ok(()),
            Err(e) => {
                if i == max_tries {
                    error!("Retried unknown-identity-check {} times, aborting.", i);
                    return Err(e);
                } else {
                    warn!("Failed unknown-identity-check, try no {}, error: {}",i,e);
                    sleep(Duration::from_secs(retry_time_secs * i));
                }
            }
        }        
    }
    unreachable!();
}

// use try {} when #31436 is stable
fn find_unknown_inner(group_ids: &[usize], mut conn: &mut PooledConn, ts_cfg: &TSConfig) -> Result<()> {
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
        trace!("Retrieved ts clients for {}",group);
    }
    // Ok(ids)
    db::update_unknown_ts_ids(&mut conn, &ids)?;
    debug!("Performed TS identity check. {} IDs",ids.len());
    Ok(())
}

/// Get ts3 member groups settings, return an optional vec of member group-ids
pub fn get_ts3_member_groups(conn: &mut PooledConn) -> Result<Option<Vec<usize>>> {
    db::read_list_setting(conn, crate::TS3_MEMBER_GROUP)
}
