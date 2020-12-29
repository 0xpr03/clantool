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
                conn.rename(&Self::calc_name_retry(name))?;
            }
        }
        Ok(())
    }

    /// Calculate new name on retry
    fn calc_name_retry(name: &str) -> String {
        // leave room for 2 digits at least
        let name = if name.len() >= MAX_LEN_NAME - 2 {
            &name[0..MAX_LEN_NAME / 2]
        } else {
            name
        };
        let time = SystemTime::now()
            .duration_since(UNIX_EPOCH)
            .unwrap()
            .as_millis()
            .to_string();
        let reamining = MAX_LEN_NAME - name.len();
        let time = if reamining > time.len() {
            &time
        } else {
            &time.as_str()[time.len() - reamining..]
        };

        format!("{}{}", name, time)
    }

    /// Returns the current connection id (clid)
    pub fn conn_id(&mut self) -> Result<TsConID> {
        Ok(match self.conn_id {
            Some(v) => v,
            None => {
                let mut res = self.get()?.whoami(false)?;
                let clid = res
                    .remove(KEY_CLIENT_ID_SELF).flatten()
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

    /// Get config
    pub fn config(&self) -> &Config {
        &self.cfg
    }

    /// Create new TS-Connection with an optional name
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

    /// Force reconnect
    pub fn force_reconnect(&mut self) -> Result<()> {
        self.conn = Self::connect(&self.cfg.ts, self.name)?;
        self.conn_id = None;
        Ok(())
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
                self.force_reconnect()?;
                &mut self.conn
            }
        };
        self.last_ping = Instant::now();
        Ok(conn)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_name_empty() {
        let name = Connection::calc_name_retry("");
        assert!(name.len() <= MAX_LEN_NAME);
        assert!(name.len() > 0);
        dbg!(name);
    }

    #[test]
    fn test_name_fallback_normal() {
        // normal name, enough space for time-digits
        let name = Connection::calc_name_retry("ct_bot-fallback");

        assert!(name.starts_with("ct_bot-fallback"));
        assert!(name.len() <= MAX_LEN_NAME);
        assert!(name.len() > "ct_bot-fallback".len());
        dbg!(name);
    }

    #[test]
    fn test_name_fallback_underflow() {
        // don't take timeString[-1...], just timeStirng[0...] in that case
        let name = Connection::calc_name_retry("ct_bot");

        assert!(name.starts_with("ct_bot"));
        assert!(name.len() <= MAX_LEN_NAME);
        assert!(name.len() > "ct_bot".len());
        dbg!(name);
    }

    #[test]
    fn test_name_fallback_fit() {
        {
            // no space left, should make space for name
            let name_input = "1234567890123456789D";
            let name = Connection::calc_name_retry(name_input);
            dbg!(&name);
            assert!(name.starts_with(&name_input[..MAX_LEN_NAME / 2]));
            assert!(name.len() <= MAX_LEN_NAME);
        }

        // required for near-fit invariant
        assert!(MAX_LEN_NAME > 3);
        {
            // assert even for non-fit we have at least 2 random digits at the end
            let name_input = "123456789012345678";
            let name = Connection::calc_name_retry(name_input);
            dbg!(&name);
            assert!(name.starts_with(&name_input[..MAX_LEN_NAME / 2]));
            assert!(name.len() <= MAX_LEN_NAME);
        }
    }

    #[test]
    fn test_name_fallback_overflow() {
        // assert even for non-fit we have at least 2 random digits at the end
        let name_input = "1234567890123456789012345678901234567890";
        assert!(name_input.len() > MAX_LEN_NAME);
        let name = Connection::calc_name_retry(name_input);
        dbg!(&name);
        assert!(name.starts_with(&name_input[..MAX_LEN_NAME / 2]));
        assert!(name.len() <= MAX_LEN_NAME);
    }
}
