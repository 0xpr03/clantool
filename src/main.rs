/*
Copyright 2017 Aron Heinecke

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

  http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
*/

extern crate reqwest;
extern crate json;
extern crate flate2;
#[macro_use]
extern crate log;
extern crate log4rs;
#[macro_use]
extern crate quick_error;
extern crate mysql;
extern crate regex;
extern crate chrono;
extern crate rustc_serialize;
extern crate toml;
extern crate timer;

mod http;
mod error;
mod parser;
mod db;
mod config;

use http::HeaderType;

use std::fs::{File,metadata};
use std::env::current_exe;
use std::fs::DirBuilder;
use std::io::Write;

use std::sync::Arc;

use chrono::naive::datetime::NaiveDateTime;
use chrono::offset::fixed::FixedOffset;
use chrono::naive::time::NaiveTime;
use chrono::offset::local::Local;
use chrono::duration::Duration;
use chrono::datetime::DateTime;
use chrono::Offset;
use chrono::TimeZone;

use mysql::conn::pool::Pool;

use error::Error;

use config::Config;

const USER_AGENT: &'static str = "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0";
const REFERER: &'static str = "http://crossfire.z8games.com/";
const CONFIG_PATH: &'static str = "config/config.toml";
const LOG_PATH: &'static str = "config/log.yml";
const INTERVALL_H: i64 = 24; // execute intervall

fn main() {
    match init_log() {
        Err(e) => println!("Error on config initialization: {}",e),
        Ok(_) => println!("Initialized log")
    }
    info!("Starting clan tools crawler v0.0.1");
    let config = Arc::new(config::init_config());
    let pool = match db::new(config.db.ip.clone(),
        config.db.port,
        config.db.user.clone(),
        config.db.password.clone(),
        config.db.db.clone()) {
        Err(e) => {error!("Can't connect to DB! {}",e); panic!();},
        Ok(v) => Arc::new(v)
    };
    let timer = timer::Timer::new();
    run_timer(pool.clone(), config.clone(),&timer);
    //let local_pool = &*pool;
    //let local_config = &*config;
    //debug!("Result: {:?}",run_update(local_pool,local_config));
    
    loop {
        std::thread::sleep(std::time::Duration::from_millis(1000));
    }
}

/// Initialize timed task
fn run_timer<'a>(pool: Arc<Pool>, config: Arc<Config>, timer: &'a timer::Timer) {
    let mut date_time = Local::now(); // get current datetime
    let target_time = match NaiveTime::parse_from_str(&config.main.time, "%H:%M") {
        Ok(v) => v,
        Err(e) => {error!("Unable to parse config time!: {}",e); panic!();}
    };
	
	// get retry intervall
	let retry_time = match NaiveTime::parse_from_str(&config.main.retry_interval, "%H:%M") {
		Ok(v) => v,
		Err(e) => {error!("Unable to parse config retry time!: {}",e); panic!();}
	};
	// little hack to parse it via NaiveTime but get a Duration
	let retry_duration = retry_time - NaiveTime::from_hms(0,0,0);
	let retry_duration = match retry_duration.to_std() {
		Ok(v) => v,
		Err(e) => {error!("Unable to convert retry_interval! {}",e); panic!();}
	};
	
    trace!("Parsed time: {}",target_time);
    if target_time < date_time.time() {
		debug!("Target time is tinier then current time");
        date_time = date_time.checked_add(Duration::hours(INTERVALL_H)).unwrap();
        date_time = date_time.checked_add(target_time - date_time.time()).unwrap();
    }else{
		debug!("Offset: {}",date_time.offset());
		// create new DateTime from current Date & NaiveTime specified
		date_time = DateTime::from_utc(NaiveDateTime::new(date_time.naive_utc().date(),target_time),
			 FixedOffset::from_offset(date_time.offset()));
		// substract the offset, otherwise we're one offset ahead
		date_time = date_time - date_time.offset().local_minus_utc();
	}
    info!("First execution will be on {}",date_time);
    
	let a = timer.schedule(date_time,Some(chrono::Duration::hours(INTERVALL_H)), move || {
        trace!("performing crawler");
		let local_pool = &*pool;
		let local_config = &*config;
		for x in 1..local_config.main.retries+1 {
			match run_update(local_pool,local_config) {
				Ok(_) => { debug!("Crawling successfull."); break;
					},
				Err(e) => { error!("Error at update: {}: {}",x,e);
					if x == local_config.main.retries {
						warn!("No dataset for this schedule, all retries failed!");
					}else{
						std::thread::sleep(retry_duration);
					}
				}
			}
		}
    });
    a.ignore();
}

/// get member url for ajax request
fn get_member_url(site: &u8, config: &Config) -> String {
    let _site = format!("{}",site);
    config.main.clan_ajax_url.replace(&config.main.clan_ajax_site_key, &_site)
}

/// Run crawl + db update
fn run_update(pool: &Pool, config: &Config) -> Result<(),Error> {
    let time = Local::now().naive_local();
    let mut conn = try!(pool.get_conn());
    let raw_http_clan = try!(http::get(&config.main.clan_url,HeaderType::Html));
    let clan = try!(parser::parse_clan(&raw_http_clan));
    
    let mut site = 0;
    let mut members = Vec::new();
    let mut members_temp;
    let mut received_total = 0;
    let min = config.main.clan_ajax_exptected_per_site.into();
    let mut received_last_req = min;
    while received_last_req >= min {
        site += 1;
        let raw_members_json = try!(http::get(&get_member_url(&site,config),HeaderType::Ajax));
        members_temp = try!(parser::parse_all_member(&raw_members_json));
        received_last_req = members_temp.len();
        received_total += received_last_req;
        members.append(&mut members_temp);
        trace!("fetched site {} with {} entries",site, received_last_req);
    }
    assert_eq!(members.len(),received_total,"mismatched amount of members compared to received");
    debug!("Fetched {} member entries",members.len());
    try!(db::insert_members(&mut conn,&members,&time));
    try!(db::insert_clan_update(&mut conn,&clan,&time));
    Ok(())
}

/// Init log system
/// Creating a default log file if not existing
fn init_log() -> Result<(),Error> {
    let mut log_path = try!(get_executable_folder());
    log_path.push(LOG_PATH);
    let mut log_dir = log_path.clone();
    println!("LogPath: {:?}",&log_path);
    log_dir.pop();
    try!(DirBuilder::new()
    .recursive(true)
    .create(log_dir));
    
    if !metadata(&log_path).is_ok() {
        let config = include_str!("../log_config.yml");
        let mut file = try!(File::create(&log_path));
        try!(file.write_all(config.as_bytes()));
    }
    try!(log4rs::init_file(log_path, Default::default()));
    Ok(())
}

/// Returns the current executable folder
pub fn get_executable_folder() -> Result<std::path::PathBuf, Error> {
    let mut folder = try!(current_exe());
    folder.pop();
    Ok(folder)
}

/// Clan data structure
#[derive(Debug,PartialEq, PartialOrd)]
pub struct Clan {
    members: u8,
    wins: u16,
    losses: u16,
    draws: u16
}

/// Member data structure
#[derive(Debug,PartialEq, PartialOrd,Clone)]
pub struct Member {
    name: String,
    id: u32,
    exp: u32,
    contribution: u32
}

#[cfg(test)]
mod tests {
    #[test]
    fn it_works() {
    }
}
