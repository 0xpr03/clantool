use chrono::NaiveTime;
use config as config_rs;
use config_rs::{Config, File};
use serde::{self, Deserialize, Deserializer, Serialize};
use snafu::ResultExt;

use super::LoadConfig;
use super::Result;

#[derive(Debug, Serialize, Deserialize)]
pub struct Settings {
    pub main: Main,
    pub db: ConfigDB,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct Main {
    #[serde(deserialize_with = "de_naive_time")]
    pub hour: NaiveTime,
    pub bind_ip: String,
    pub bind_port: u16,
}

/// Custom NaiveTime deserializer
///
/// Required for the format, otherwise Config will fail in misleading ways (only default values, ignoring files..)
pub fn de_naive_time<'de, D>(deserializer: D) -> ::std::result::Result<NaiveTime, D::Error>
where
    D: Deserializer<'de>,
{
    let s = String::deserialize(deserializer)?;
    NaiveTime::parse_from_str(&s, "%H:%M").map_err(serde::de::Error::custom)
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ConfigDB {
    pub ip: String,
    pub database: String,
    pub user: String,
    pub password: String,
    pub port: u16,
}

impl Default for Settings {
    fn default() -> Self {
        Self {
            main: Main::default(),
            db: ConfigDB::default(),
        }
    }
}

impl Default for Main {
    fn default() -> Self {
        Self {
            hour: NaiveTime::parse_from_str("09:00", "%H:%M").unwrap(),
            bind_ip: "127.0.0.1".to_string(),
            bind_port: 1400,
        }
    }
}

impl Default for ConfigDB {
    fn default() -> Self {
        Self {
            ip: "localhost".to_string(),
            user: "ctd".to_string(),
            password: "".to_string(),
            port: 3306,
            database: "clantool".to_string(),
        }
    }
}

/// Read settings
pub fn read() -> Result<Settings> {
    // let mut s = Config::try_from(&Settings::default()).context(LoadConfig)?;
    //TODO: find error causing this to break try_into() for the Settings struct
    let mut s = Config::new();
    s.merge(File::with_name("ctd_config.toml").required(true))
        .context(LoadConfig)?;
    Ok(s.try_into().context(LoadConfig)?)
}

#[cfg(test)]
mod test {
    use super::*;
    #[test]
    fn load_config() {
        read().unwrap();
    }
}
