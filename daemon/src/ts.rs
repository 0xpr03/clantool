use crate::config::TSConfig;
use crate::db;
use crate::error::Error;
use crate::Result;
use mysql::Pool;
use ts3_query::*;
use std::thread::sleep;
use std::time::Duration;

/// Check for unknown identities with member group and update unknown_ts_ids
pub fn find_unknown_identities(pool: &Pool, ts_cfg: &TSConfig) -> Result<()> {
    dbg!(&ts_cfg);
    let mut conn = pool.get_conn()?;
    let group_ids: Vec<usize> = db::read_list_setting(&mut conn, crate::TS3_MEMBER_GROUP)?
        .ok_or(Error::MissingKey(crate::TS3_MEMBER_GROUP))?;

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
        sleep(Duration::from_millis(100));
        dbg!(&ids);
    }
    db::update_unknown_ts_ids(&mut conn, &ids)?;
    Ok(())
}
