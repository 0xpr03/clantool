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
    let mut conn = pool.get_conn()?;
    let group_ids = get_ts3_member_groups(&mut conn)?;

    trace!("TS3 group IDS: {:?}", group_ids);
    trace!("Connect ts3");
    let mut connection = QueryClient::new(format!("{}:{}", ts_cfg.ip, ts_cfg.port))?;
    sleep(Duration::from_millis(100));
    trace!("login");
    connection.login(&ts_cfg.user, &ts_cfg.password)?;
    trace!("server select");
    connection.select_server_by_port(ts_cfg.server_port)?;
    sleep(Duration::from_millis(100));
    trace!("TS3 server connection ready");
    let mut ids = Vec::new();
    for group in group_ids {
        ids.append(&mut connection.get_servergroup_client_list(group)?);
        trace!("Retrieved ts clients for {}",group);
        sleep(Duration::from_millis(100));
    }
    db::update_unknown_ts_ids(&mut conn, &ids)?;
    debug!("Performed TS identity check. {} IDs",ids.len());
    Ok(())
}

/// Get ts3 member groups settings
pub fn get_ts3_member_groups(conn: &mut PooledConn) -> Result<Vec<usize>> {
    Ok(db::read_list_setting(conn, crate::TS3_MEMBER_GROUP)?
        .ok_or(Error::MissingKey(crate::TS3_MEMBER_GROUP))?)
}
