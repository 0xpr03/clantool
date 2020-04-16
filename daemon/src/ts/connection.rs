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

use crate::config::Config;
use crate::config::TSConfig;
use crate::*;
use ::std::time::{Duration, SystemTime, UNIX_EPOCH};
use ts3_query::*;

pub const ERR_NAME_TAKEN: usize = 513;
const MAX_LEN_NAME: usize = 20;

// Safety: see module tick interval
const TIMEOUT_CONN: Duration = Duration::from_millis(1500);
const TIMEOUT_CMD: Duration = Duration::from_millis(1500);
/// Same as super::CLIENT_CONN_ID, but TS returns a different one on whoami
const KEY_CLIENT_ID_SELF: &str = "client_id";

/// QueryClient wrapper with connection-check on access
pub struct Connection {
    conn: QueryClient,
    cfg: Config,
    last_ping: Instant,
    conn_id: Option<TsConID>,
    name: Option<&'static str>,
}

impl Connection {
    fn connect(cfg: &TSConfig, name: Option<&'static str>) -> Result<QueryClient> {
        // let mut conn = QueryClient::new((cfg.ip.as_ref(), cfg.port))?;
        let mut conn = QueryClient::with_timeout(
            (cfg.ip.as_ref(), cfg.port),
            Some(TIMEOUT_CONN),
            Some(TIMEOUT_CMD),
        )?;
        conn.login(&cfg.user, &cfg.password)?;
        conn.select_server_by_port(cfg.server_port)?;
        if let Some(n) = name {
            // prevent underflow in name fallback
            if n.len() > MAX_LEN_NAME {
                panic!("Invalid name length: {} max: {}!", n.len(), MAX_LEN_NAME);
            }
            Self::set_name_fallback(&mut conn, n)?;
        }
        Ok(conn)
    }

    /// Set name of client, fallback to name+last unix timestamp MS to make it unique
    fn set_name_fallback(conn: &mut QueryClient, name: &str) -> Result<()> {
        if let Err(e) = conn.rename(name) {
            if e.error_response().map_or(true, |r| r.id != ERR_NAME_TAKEN) {
                return Err(e.into());
            } else {
                let time = SystemTime::now()
                    .duration_since(UNIX_EPOCH)
                    .unwrap()
                    .as_millis()
                    .to_string();
                let remaining = MAX_LEN_NAME - name.len();
                let offset = if remaining > time.len() {
                    warn!("Name > time len");
                    warn!("name: {} time: {}, remaining: {}",name,time,remaining);
                    time.len()
                } else {
                    time.len() - remaining
                };
                conn.rename(&format!("{}{}", name, &time.as_str()[offset..]))?;
            }
        }
        Ok(())
    }

    /// Returns the current connection id (clid)
    pub fn conn_id(&mut self) -> Result<TsConID> {
        Ok(match self.conn_id {
            Some(v) => v,
            None => {
                let res = self.get()?.whoami(false)?;
                let clid = res
                    .get(KEY_CLIENT_ID_SELF)
                    .ok_or_else(|| Error::NoValue("No client id!"))?;
                let clid = clid.parse()?;
                self.conn_id = Some(clid);
                clid
            }
        })
    }

    /// Try creating a second connection
    pub fn clone(&self, name: Option<&'static str>) -> Result<Self> {
        let name = name.or(self.name);
        Self::new(self.cfg.clone(), name)
    }

    pub fn config(&self) -> &Config {
        &self.cfg
    }

    pub fn new(cfg: Config, name: Option<&'static str>) -> Result<Connection> {
        let conn = Self::connect(&cfg.ts, name)?;
        Ok(Self {
            conn,
            cfg,
            last_ping: Instant::now(),
            conn_id: None,
            name,
        })
    }

    /// Returns the active connection or tries to create a new one
    pub fn get(&mut self) -> Result<&mut QueryClient> {
        if self.last_ping.elapsed() < Duration::from_secs(0) {
            return Ok(&mut self.conn);
        }
        let conn = match self.conn.ping() {
            Ok(_) => &mut self.conn,
            Err(e) => {
                debug!("Previous connection died: {}", e);
                self.conn = Self::connect(&self.cfg.ts, self.name)?;
                self.conn_id = None;
                &mut self.conn
            }
        };
        self.last_ping = Instant::now();
        Ok(conn)
    }
}
