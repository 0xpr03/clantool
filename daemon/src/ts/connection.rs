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
use ::std::time::Duration;
use ts3_query::*;

// Safety: see module tick interval
const TIMEOUT_CONN: Duration = Duration::from_millis(1500);
const TIMEOUT_CMD: Duration = Duration::from_millis(1500);

/// QueryClient wrapper with connection-check on access
pub struct Connection {
    conn: QueryClient,
    cfg: Config,
    last_ping: Instant,
}

impl Connection {
    fn connect(cfg: &TSConfig) -> Result<QueryClient> {
        // let mut conn = QueryClient::new((cfg.ip.as_ref(), cfg.port))?;
        let mut conn = QueryClient::with_timeout(
            (cfg.ip.as_ref(), cfg.port),
            Some(TIMEOUT_CONN),
            Some(TIMEOUT_CMD),
        )?;
        conn.login(&cfg.user, &cfg.password)?;
        conn.select_server_by_port(cfg.server_port)?;
        Ok(conn)
    }

    /// Try creating a second connection
    pub fn clone(&self) -> Result<Self> {
        Self::new(self.cfg.clone())
    }

    pub fn config(&self) -> &Config {
        &self.cfg
    }

    pub fn new(cfg: Config) -> Result<Connection> {
        let conn = Self::connect(&cfg.ts)?;
        Ok(Self {
            conn,
            cfg,
            last_ping: Instant::now(),
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
                self.conn = Self::connect(&self.cfg.ts)?;
                &mut self.conn
            }
        };
        self.last_ping = Instant::now();
        Ok(conn)
    }
}
