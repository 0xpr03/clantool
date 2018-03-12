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
extern crate toml;
extern crate timer;
#[macro_use]
extern crate serde_derive;
extern crate serde;
extern crate clap;
extern crate sendmail;

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

use sendmail::email;

use chrono::naive::{NaiveTime,NaiveDateTime};
use chrono::offset::Local;
use chrono::Timelike;

use mysql::Pool;

use error::Error;

use config::Config;

use clap::{Arg,App,SubCommand};

const USER_AGENT: &'static str = "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:55.0) Gecko/20100101 Firefox/55.0";
const REFERER: &'static str = "http://crossfire.z8games.com/";
const CONFIG_PATH: &'static str = "config/config.toml";
const LOG_PATH: &'static str = "config/log.yml";
const INTERVALL_H: i64 = 24; // execute intervall

fn main() {
    match init_log() {
        Err(e) => println!("Error on config initialization: {}",e),
        Ok(_) => println!("Initialized log")
    }
    info!("Clan tools crawler v0.2.1");
        
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
    
    let app = App::new("Clantool")
                    .version("2.1")
                    .author("Aron H. <aron.heinecke@t-online.de>")
                    .about("Gathers statistics about CF-NA clans. Starts as daemon per default")
                    .subcommand(SubCommand::with_name("fcrawl")
                        .about("force run crawl & exit"))
                    .subcommand(SubCommand::with_name("mail-test")
                        .about("Test mail sending")
                        /*.arg(Arg::with_name("mail")
                            .help("Required mail address")
                            .takes_value(true)
                            .required(true))
                        */    )
                    .subcommand(SubCommand::with_name("checkdb")
                        .about("checks DB for missing entries or doubles and corrects those")
                        .arg(Arg::with_name("simulate")
                            .short("s")
                            .help("simulation mode, leaves DB unchanged")))
                    .get_matches();
    trace!("{:?}",app);
    
    match app.subcommand() {
        ("checkdb", Some(sub_m)) => {
            info!("Performing check db");
            match run_checkdb(pool,sub_m.is_present("simulate")) {
                Ok(_) => {},
                Err(e) => error!("Error at checkdb: {}",e)
            }
        },
        ("fcrawl", _) => {
            info!("Performing force crawl");
            let local_pool = &*pool;
            let local_config = &*config;
            let rt_time = NaiveTime::from_num_seconds_from_midnight(20,0);
            debug!("Result: {:?}",run_update(local_pool,local_config, &rt_time));
        },
        ("mail-test", _) => {
            info!("Sending test mail");
            send_mail(&*config,"Clantool test mail","This is a manually triggered test mail.");
        },
        _ =>  {
            info!("Entering daemon mode");
            run_timer(pool.clone(), config.clone(),&timer);
            loop {
                std::thread::sleep(std::time::Duration::from_millis(1000));
            }
        }
    }
}

/// Check DB for missing entries
fn run_checkdb(pool: Arc<Pool>, simulate: bool) -> Result<(),Error> {
    let mut conn = pool.get_conn()?;
    let missing_dates = db::get_missing_dates(&mut conn)?;
    
    if simulate {
        info!("Simulation mode, discarding result");
    } else {
        db::insert_missing_dates(&mut conn)?;
    }
    
    for date in missing_dates {
        info!("Missing: {}",date);    
    }
    
    Ok(())
}


/// Initialize timed task
fn run_timer<'a>(pool: Arc<Pool>, config: Arc<Config>, timer: &'a timer::Timer) {
    let date_time = Local::now(); // get current datetime
    let today = Local::today();
    let target_naive_time = match NaiveTime::parse_from_str(&config.main.time, "%H:%M") {
        Ok(v) => v,
        Err(e) => {error!("Unable to parse config time!: {}",e); panic!();}
    };
    
    // get retry time
    let retry_time: NaiveTime = match NaiveTime::parse_from_str(&config.main.retry_interval, "%H:%M") {
        Ok(v) => v,
        Err(e) => {error!("Unable to parse config retry time!: {}",e); panic!();}
    };
    
    let schedule_time;
    trace!("Parsed time: {}",target_naive_time);
    if target_naive_time < date_time.time() {
        debug!("Target time is tinier then current time");
        // create from chrono::Duration, convert to std::time::Duration to add
        let c_duration = chrono::Duration::hours(INTERVALL_H);
        let tomorrow = today.checked_add_signed(c_duration).unwrap();
        schedule_time = tomorrow.and_time(target_naive_time).unwrap();
    } else {
        schedule_time = today.and_time(target_naive_time).unwrap();
    }
    info!("First execution will be on {}",schedule_time);
    
    let a = timer.schedule(schedule_time,Some(chrono::Duration::hours(INTERVALL_H)), move || {
        run_update(&*pool, &*config, &retry_time);
    });
    a.ignore();
}

/// Performs a complete crawl
fn run_update(pool: &Pool, config: &Config, retry_time: &NaiveTime) {
    trace!("performing crawler");
    
    let mut member_success  = false;
    let mut clan_success = false;
    
    for x in 1..config.main.retries+1 {
        let time = Local::now().naive_local();
        
        match run_update_member(pool,config, &time) {
            Ok(_) => { debug!("Member crawling successfull."); member_success = true;
                },
            Err(e) => error!("Error at member update: {}: {}",x,e),
        }
        
        match run_update_clan(pool, config, &time) {
            Ok(_) => { debug!("Clan crawling successfull."); clan_success = true;
                },
            Err(e) => error!("Error at clan update: {}: {}",x,e),
        }
        
        if member_success && clan_success {
            info!("Crawling successfull");
            break;
        } else {
            if x == config.main.retries {
                warn!("No dataset for this schedule, all retries failed!");
                match write_missing(&time,pool) {
                    Ok(_) => {},
                    Err(e) => error!("Unable to write missing date! {}",e),
                }
                
                if config.main.send_error_mail {
                    send_mail(config,"Clantool error",
                        "Error at clantool update execution!");
                }
            }else{
                std::thread::sleep(std::time::Duration::from_secs(retry_time.num_seconds_from_midnight().into()));
            }
        }
    }
}

/// Send email, catch & log errors
fn send_mail(config: &Config, subject: &str, message: &str) {
    match email::send_new::<Vec<&str>>(
        &config.main.mail_from,
        config.main.mail.iter().map(|v| &v[..]).collect(),
        // Subject
        subject,
        // Body
        message
    ) {
        Err(e) => error!("Error at mail sending: {}",e),
        _ => ()
    }
}

/// wrapper to write missing date
/// allowing for error return
fn write_missing(timestamp: &NaiveDateTime, pool: &Pool) -> Result<(),Error> {
    db::insert_missing_entry(timestamp,&mut pool.get_conn()?)?;
    Ok(())
}

/// get member url for ajax request
fn get_member_url(site: &u8, config: &Config) -> String {
    let _site = format!("{}",site);
    let end_row = site*config.main.clan_ajax_exptected_per_site;
    let start_row = end_row - (config.main.clan_ajax_exptected_per_site -1);
    let _start_row = format!("{}", start_row);
    let _end_row = format!("{}", end_row);
    let mut output = config.main.clan_ajax_url.replace(&config.main.clan_ajax_site_key, &_site);
    output = output.replace(&config.main.clan_ajax_start_row_key, &_start_row);
    output = output.replace(&config.main.clan_ajax_end_row_key, &_end_row);
    debug!("Start: {} end: {} site: {}",start_row,end_row,site);
    output
}

/// run member data crawl & update
fn run_update_member(pool: &Pool, config: &Config, time: &NaiveDateTime) -> Result<(),Error> {
    trace!("run_update_member");
    let mut site = 0;
    let mut members = Vec::new();
    let mut to_receive = 100;
    while members.len() < to_receive {
        site += 1;
        if site > config.main.clan_ajax_max_sites {
            error!("Reaching site {}, aborting.",site);
            return Err(Error::Other("Site over limit."));
        }
        let raw_members_json = try!(http::get(&get_member_url(&site,config),HeaderType::Ajax));
        let (mut members_temp,t_total) = try!(parser::parse_all_member(&raw_members_json));
        to_receive = t_total as usize;
        members.append(&mut members_temp);
        trace!("fetched site {}",site);
    }
    debug!("Fetched {} member entries",members.len());
    try!(db::insert_members(&mut pool.get_conn()?,&members,time));
    Ok(())
}

/// run clan crawl & update
fn run_update_clan(pool: &Pool, config: &Config, time: &NaiveDateTime) -> Result<(),Error> {
    trace!("run_update_clan");
    let raw_http_clan = try!(http::get(&config.main.clan_url,HeaderType::Html));
    let clan = try!(parser::parse_clan(&raw_http_clan));
    try!(db::insert_clan_update(&mut pool.get_conn()?,&clan,&time));
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
        let config = include_str!("../log.yml");
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
    
    use chrono::naive::NaiveTime;
    use chrono::Local;
    
    #[test]
    fn test_chrono() {
        let date = Local::today();
        let parsed_time = NaiveTime::parse_from_str("12:00","%H:%M").unwrap();
        let parsed_time_2 = NaiveTime::parse_from_str("12:01","%H:%M").unwrap();
        let datetime_1 = date.and_time(parsed_time).unwrap();
        let datetime_2 = date.and_time(parsed_time_2).unwrap();
        //assert_eq!(datetime_1.cmp(datetime_2),Ordering::Less);
        assert_eq!(parsed_time_2 > parsed_time,true);
        assert_eq!(datetime_2 > datetime_1,true);
    }
}
