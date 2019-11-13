use crate::config::TSConfig;
use crate::db;
use crate::error::Error;
use crate::Result;
use mysql::Pool;
use ts3_query::*;

/// Check for unknown identities with member group and update unknown_ts_ids
pub fn find_unknown_identities(pool: &Pool, ts_cfg: &TSConfig) -> Result<()> {
    let mut conn = pool.get_conn()?;
    let group_id: usize = db::read_setting(&mut conn, crate::TS3_MEMBER_GROUP)?
        .ok_or(Error::MissingKey(crate::TS3_MEMBER_GROUP))?;

    let mut connection = QueryClient::new(format!("{}{}", ts_cfg.ip, ts_cfg.port))?;
    connection.login(&ts_cfg.user, &ts_cfg.password)?;
    connection.select_server_by_port(ts_cfg.server_port)?;
    let ids = connection.get_servergroup_client_list(group_id)?;
    db::update_unknown_ts_ids(&mut conn, &ids)?;
    Ok(())
}
