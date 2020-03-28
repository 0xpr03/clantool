use crate::config::TSConfig;
use crate::*;
use ts3_query::*;

/// QueryClient wrapper with connection-check on access
pub struct Connection<'a> {
    conn: QueryClient,
    cfg: &'a TSConfig,
}

impl<'a> Connection<'a> {
    fn connect(cfg: &TSConfig) -> Result<QueryClient> {
        let mut conn = QueryClient::new((cfg.ip.as_str(), cfg.port))?;
        conn.login(&cfg.user, &cfg.password)?;
        conn.select_server_by_port(cfg.server_port)?;
        Ok(conn)
    }

    pub fn new(ts_cfg: &'a TSConfig) -> Result<Connection> {
        Ok(Self {
            conn: Self::connect(ts_cfg)?,
            cfg: ts_cfg,
        })
    }

    /// Returns the active connection or tries to create a new one
    pub fn get(&mut self) -> Result<&mut QueryClient> {
        Ok(match self.conn.ping() {
            Ok(_) => &mut self.conn,
            Err(e) => {
                debug!("Previous connection died: {}",e);
                self.conn = Self::connect(self.cfg)?;
                &mut self.conn
            }
        })
    }
}
