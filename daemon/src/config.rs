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

use serde::Deserialize;
use toml::de::from_str;

use std;
use std::fs::{metadata, File, OpenOptions};
use std::io::Read;
use std::io::Write;
use std::path::Path;
use std::process::exit;

use crate::CONFIG_PATH;

use crate::get_executable_folder;

use crate::error::Error;

// pub mod config;
// Config section

/// Custom expect function logging errors plus custom messages on panic
/// &'static str to prevent the usage of format!(), which would result in overhead
#[inline]
pub fn l_expect<T, E: std::fmt::Debug>(result: Result<T, E>, msg: &'static str) -> T {
    match result {
        Ok(v) => v,
        Err(e) => {
            error!("{}: {:?}", msg, e);
            panic!();
        }
    }
}

/// Config Error struct
#[derive(Debug)]
pub enum ConfigError {
    ReadError,
    WriteError,
    CreateError,
}

/// Config struct
pub type Config = ::std::sync::Arc<InnerConfig>;

#[derive(Debug, Deserialize)]
pub struct InnerConfig {
    pub db: DBConfig,
    pub main: MainConfig,
    pub ts: TSConfig,
}

/// TS config struct
#[derive(Debug, Deserialize)]
pub struct TSConfig {
    pub ip: String,
    pub port: u16,
    pub user: String,
    pub password: String,
    pub server_port: u16,
    pub unknown_id_check_enabled: bool,
    pub check_activity: bool,
}

/// Main config struct
#[derive(Debug, Deserialize)]
pub struct MainConfig {
    pub clan_ajax_url: String,
    pub clan_ajax_site_key: String,
    pub clan_ajax_exptected_per_site: u8,
    pub clan_ajax_start_row_key: String,
    pub clan_ajax_end_row_key: String,
    pub clan_ajax_max_sites: u8,
    pub clan_url: String,
    pub auto_leave_enabled: bool,
    pub auto_leave_max_age: u8,
    pub auto_leave_message_default: String,
    pub time: String,
    pub retries: u32,
    pub retry_interval: String,
    pub send_error_mail: bool,
    pub mail: Vec<String>,
    pub mail_from: String,
}

/// DB Config struct
#[derive(Debug, Deserialize)]
pub struct DBConfig {
    pub user: String,
    pub password: Option<String>,
    pub port: u16,
    pub db: String,
    pub ip: String,
}

/// Init config, reading from file or creating such
pub fn init_config() -> Config {
    let mut path = l_expect(get_executable_folder(), "config folder"); // PathBuf
    path.push(CONFIG_PATH); // set_file_name doesn't return smth -> needs to be run on mut path
    trace!("config path {:?}", path);
    let data: String;
    if metadata(&path).is_ok() {
        info!("Config file found.");
        data = l_expect(read_config(&path), "unable to read config!");
    } else {
        info!("Config file not found.");
        data = default_config();
        l_expect(write_config_file(&path, &data), "unable to write config");

        exit(0);
    }

    l_expect(parse_config(data), "unable to parse config")
}

/// Parse input toml to config struct
fn parse_config(input: String) -> Result<Config, Error> {
    let a = from_str(&input)?;
    Ok(a)
}

#[cfg(test)]
pub fn default_cfg_testing() -> Config {
    parse_config(default_config()).unwrap()
}

/// Read config from file.
pub fn read_config(file: &Path) -> Result<String, ConfigError> {
    let mut f = OpenOptions::new()
        .read(true)
        .open(file)
        .map_err(|_| ConfigError::ReadError)?;
    let mut data = String::new();
    f.read_to_string(&mut data)
        .map_err(|_| ConfigError::ReadError)?;
    Ok(data)
}

/// Writes the recived string into the file
fn write_config_file(path: &Path, data: &str) -> Result<(), ConfigError> {
    let mut file = File::create(path).map_err(|_| ConfigError::CreateError)?;
    file.write_all(data.as_bytes())
        .map_err(|_| ConfigError::WriteError)?;
    Ok(())
}

/// Create a new config.
fn default_config() -> String {
    trace!("Creating config..");
    let toml = r#"[db]
user = "user"
#comment out to login without password
password = "password"
db = "clantool"
port = 3306
ip = "127.0.0.1"

[ts]
ip = "127.0.0.1"
# query port
port = 1111
user = "user"
password = "something"
# port of the ts server instance to use
server_port = 1101
unknown_id_check_enabled = true
check_activity = true

[main]
clan_ajax_url = "http://crossfire.z8games.com/rest/clanmembers.json?clanID=68910&page=%Page&perPage=10&rankType=user&startrow=%StartRow&endrow=%EndRow"
clan_ajax_site_key = "%Page"
clan_ajax_exptected_per_site = 10
clan_ajax_start_row_key = "%StartRow"
clan_ajax_end_row_key = "%EndRow"
# maximum amount of sites, after which to abort
clan_ajax_max_sites = 10
# required for referer
clan_url = "http://crossfire.z8games.com/clan/68910"
# enable automatic leave handling
auto_leave_enabled = true
# max days distance of date to compare to for leave detection
auto_leave_max_age = 4
auto_leave_message_default = "auto leave detection"
# time of the day the crawler should run
time = "12:00"
retries = 5
# retry number * retry_interval = maximum time waited
retry_interval = "00:05"
# send mail on failure
send_error_mail = true
# list of email address to send errors
mail = ["root@localhost"]
mail_from = "noreply@localhost"
"#;

    toml.to_owned()
}

#[cfg(test)]
mod test {
    use super::*;
    #[test]
    fn test_default_parse() {
        parse_config(default_config()).unwrap();
    }
}
