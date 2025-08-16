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

#[macro_use]
extern crate lazy_static;
#[macro_use]
extern crate log;
#[macro_use]
extern crate quick_error;
#[macro_use]
extern crate mysql;

mod config;
mod crawler;
mod db;
mod error;
mod import;
mod ts;
mod types;

use lettre::{SmtpClient, Transport};
use lettre_email::EmailBuilder;
pub use types::*;

use crate::crawler::http::HeaderType;

use std::env::current_dir;
use std::fs::DirBuilder;
use std::fs::{metadata, File};
use std::io::Write;
use std::path::PathBuf;
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::Arc;
use std::thread::sleep;
use std::time::{Duration as Dur, Instant};

use chrono::naive::{NaiveDate, NaiveDateTime, NaiveTime};
use chrono::offset::Local;
use chrono::Duration;
use chrono::Timelike;

use mysql::{Pool, PooledConn};

pub use error::Error;

use config::Config;

use clap::{App, Arg, SubCommand};

const VERSION: &str = env!("CARGO_PKG_VERSION");
const USER_AGENT: &str = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:54.0) Gecko/20100101 Firefox/79.0";
const REFERER: &str = "https://crossfire.z8games.com/";
const CONFIG_PATH: &str = "config/config.toml";
const LOG_PATH: &str = "config/log.yml";
const INTERVALL_H: i64 = 24; // execute intervall

const DATE_FORMAT_DAY: &str = "%Y-%m-%d";

/// Auto missing account names fetching
const NAME_FETCHING_KE: &str = "auto_fetch_names";
/// Auto leave detection
const LEAVE_MSG_KEY: &str = "auto_leave_message";
const LEAVE_ENABLE_KEY: &str = "auto_leave_enable";
/// Check for unknown identities in member group
const TS3_UNKNOWN_CHECK_KEY: &str = "ts3_check_identities_enable";
const TS3_MEMBER_GROUP: &str = "ts3_check_member_groups";
/// Whether to poke on guests
const TS3_GUEST_NOTIFY_ENABLE_KEY: &str = "ts3_guest_notify_enable";
const TS3_GUEST_WATCHER_GROUP_KEY: &str = "ts3_guest_watcher_group";
const TS3_GUEST_GROUP_KEY: &str = "ts3_guest_group";
const TS3_GUEST_CHANNEL_KEY: &str = "ts3_guest_channel";
const TS3_GUEST_POKE_MSG: &str = "ts3_guest_poke_msg";
/// AFK move enabled
const TS3_AFK_MOVE_ENABLED_KEY: &str = "ts3_afk_move_enable";
const TS3_AFK_IGNORE_GROUP_KEY: &str = "ts3_afk_ignore_group";
const TS3_AFK_MOVE_CHANNEL_KEY: &str = "ts3_afk_move_channel";
const TS3_AFK_IGNORE_CHANNELS_KEY: &str = "ts3_afk_ignore_channel";
/// Time in ms
const TS3_AFK_TIME_KEY: &str = "ts3_afk_time";

pub type Result<T> = ::std::result::Result<T, Error>;

#[allow(clippy::cognitive_complexity)]
fn main() {
    match init_log() {
        Err(e) => eprintln!("Error on config initialization: {}", e),
        Ok(_) => println!("Initialized log"),
    }
    info!("Clan tools crawler v{}", VERSION);

    let config = config::init_config();
    let pool = init_db(&config, Dur::from_secs(60 * 10));
    let timer = timer::Timer::new();

    let app = cli().get_matches();

    match app.subcommand() {
        ("checkdb", Some(sub_m)) => {
            info!("Performing check db");
            match run_checkdb(pool, sub_m.is_present("simulate")) {
                Ok(_) => {}
                Err(e) => error!("Error at checkdb: {}", e),
            }
        }
        ("fcrawl", _) => {
            info!("Performing force crawl");
            let local_pool = &pool;
            let local_config = &config;
            let rt_time = NaiveTime::from_num_seconds_from_midnight_opt(20, 0).unwrap();
            debug!(
                "Result: {:?}",
                schedule_crawl_thread(local_pool, local_config, rt_time)
            );
            info!("Finished force crawl");
        }
        ("printconfig", _) => {
            let local_pool = &pool;
            let local_config = &config;
            let mut conn = match local_pool.get_conn() {
                Err(e) => {
                    error!("Unable to get db conn {}", e);
                    return;
                }
                Ok(v) => v,
            };
            info!("Leave message: {}", leave_message(&mut conn, &local_config));
            info!(
                "Auto Leave check: {}",
                leave_detection_enabled(&mut conn, &local_config)
            );
            info!(
                "TS3 member groups: {:?}",
                ts::get_ts3_member_groups(&mut conn).unwrap()
            );
            info!(
                "{}",
                ts::print_poke_config(&mut conn, &local_config).unwrap()
            );
            info!(
                "{}",
                ts::print_afk_move_config(&mut conn, &local_config).unwrap()
            );
            info!(
                "TS3 unknown IDs check: {}",
                ts3_check_enabled(&mut conn, &local_config)
            );
            info!(
                "Auto missing account names check: {}",
                auto_names_fetch_enabled(&mut conn, &local_config)
            );
        }
        ("mail-test", _) => {
            info!("Sending test mail");
            send_mail(
                &config,
                "Clantool test mail",
                "This is a manually triggered test mail.",
            );
        }
        ("check-leave", Some(sub_m)) => {
            info!("Manually performing leave check");
            let mut conn = match pool.get_conn() {
                Ok(v) => v,
                Err(e) => {
                    error!("DB Exception {}", e);
                    return;
                }
            };
            let simulate = sub_m.is_present("simulate");
            let date_s = sub_m.value_of("date").unwrap();
            let date = NaiveDate::parse_from_str(date_s, DATE_FORMAT_DAY).unwrap();
            let datetime = match db::crawler::check_date_for_data(&mut conn, date) {
                Ok(Some(v)) => {
                    info!("Using exact dataset {}", v);
                    v
                }
                Ok(None) => {
                    error!("No data for specified date!");
                    return;
                }
                Err(e) => {
                    error!("Unable to verify specified date {}", e);
                    return;
                }
            };
            let message: String = match sub_m.value_of("message") {
                Some(v) => v.to_owned(),
                None => format!("{} manual check", leave_message(&mut conn, &config)),
            };
            run_leave_detection(&pool, &config, &datetime, &message, simulate);
            info!("Finished");
        }
        ("init", _) => {
            info!("Setting up tables..");
            if let Err(e) = db::init_tables(&pool) {
                error!("Unable to initialize tables: {}", e);
                return;
            }
            info!("Initialized tables");
        }
        ("import", Some(sub_m)) => {
            let simulate = sub_m.is_present("simulate");
            let membership = sub_m.is_present("membership");
            let comment = sub_m.value_of("comment").unwrap();
            let date_format = sub_m.value_of("date-format").unwrap();
            let path = get_path_for_existing_file(sub_m.value_of("file").unwrap()).unwrap();
            import::import_cmd(simulate, membership, comment, date_format, path, &pool);
        }
        ("check-ts", _) => {
            info!("Performing manual ts group check");
            if let Err(e) = ts::find_unknown_identities(&pool, &config.ts) {
                error!("Error performing ts group check: {}", e);
            }
        }
        ("check-names", _) => {
            info!("Performing manual names check");
            let time: NaiveDateTime = Local::now().naive_local();
            if let Err(e) = run_missing_name_crawler(&pool, &time) {
                error!("Error peforming name crawl: {}", e);
            }
        }
        ("http-test", _) => {
            info!("Running http test");
            match crawler::http::get(&get_member_url(1, &config), HeaderType::Ajax) {
                Ok(result) => info!("Request response '{}'", result),
                Err(err) => error!("Failed to request: {:?}", err),
            }
        }
        _ => {
            info!("Entering daemon mode");
            if let Err(e) = run_daemon(pool, config, &timer) {
                let fmt = format!("Error starting daemon {}", e);
                error!("{}", &fmt);
                panic!("{}", fmt);
            }
        }
    }
    info!("Exit");
}

fn cli<'a, 'b>() -> clap::App<'a, 'b> {
    App::new("Clantool")
        .version(VERSION)
        .author("Aron Heinecke <aron.heinecke@t-online.de>")
        .about("Gathers statistics about CF-NA clans. Starts as daemon per default")
        .subcommand(SubCommand::with_name("fcrawl")
            .about("force run crawl & exit, no leave detection"))
        .subcommand(SubCommand::with_name("mail-test")
            .about("Test mail sending")
            /*.arg(Arg::with_name("mail")
                .help("Required mail address")
                .takes_value(true)
                .required(true))
            */    )
        .subcommand(SubCommand::with_name("run-daemon-ts")
            .about("Run only ts-daemon, has to be enabled in config also"))
        .subcommand(SubCommand::with_name("check-ts")
            .about("Manually check ts identities for unknown member IDs"))
        .subcommand(SubCommand::with_name("printconfig")
            .about("Print configuration settings in the Database"))
        .subcommand(SubCommand::with_name("init")
            .about("Initialize database on first execution"))
        .subcommand(SubCommand::with_name("import")
            .about("Import CSV data, expects id,name,vname,vip,comment\nThe first line has to be a header.")
            .arg(Arg::with_name("file")
                .required(true)
                .short("f")
                .validator(validator_path)
                .takes_value(true)
                .help("file to parse"))
            .arg(Arg::with_name("simulate")
                .short("s")
                .help("simulation mode, leaves DB unchanged"))
            .arg(Arg::with_name("comment")
                .short("c")
                .default_value(&import::DEFAULT_IMPORT_COMMENT)
                .takes_value(true)
                .help("file to parse"))
            .arg(Arg::with_name("date-format")
                .short("d")
                .default_value(import::DATE_DEFAULT_FORMAT)
                .takes_value(true)
                .help("date parse format")))
        .subcommand(SubCommand::with_name("check-leave")
            .about("Manually run leave detection for given date")
            .arg(Arg::with_name("date")
                .required(true)
                .validator(validator_date)
                .takes_value(true)
                .help("date to check for: 'YYYY-MM-DD'"))
            .arg(Arg::with_name("message")
                .short("m")
                .long("message")
                .takes_value(true)
                .help("custom leave message to use"))
            .arg(Arg::with_name("simulate")
                .short("s")
                .long("simulate")
                .help("Don't enter leaves, just print them")))
        .subcommand(SubCommand::with_name("checkdb")
            .about("checks DB for missing entries or doubles and corrects those")
            .arg(Arg::with_name("simulate")
                .short("s")
                .help("simulation mode, leaves DB unchanged")))
        .subcommand(SubCommand::with_name("check-names")
            .about("Check for missing names of account IDs and fetch them"))
        .subcommand(SubCommand::with_name("http-test"))
            .about("Check http connectivity")
}

fn init_db(config: &Config, retry_timeout: Dur) -> Pool {
    let sleep_time = match retry_timeout.as_secs() / 10 {
        x if x < 10 => 5,
        x if x > 30 => 60,
        x => x,
    };
    let start = Instant::now();
    while start.elapsed() < retry_timeout {
        match db::new(
            config.db.ip.clone(),
            config.db.port,
            config.db.user.clone(),
            config.db.password.clone(),
            config.db.db.clone(),
        ) {
            Err(e) => {
                warn!("Couldn't connect to DB, retrying! {}", e);
            }
            Ok(v) => return v,
        };
        sleep(Dur::from_secs(sleep_time));
    }
    let msg = format!(
        "Unable to connect to DB after {} seconds. Aborting!",
        start.elapsed().as_secs()
    );
    error!("{}", msg);
    panic!("{}", msg);
}

/// validate path input
fn validator_path(input: String) -> ::std::result::Result<(), String> {
    match get_path_for_existing_file(&input) {
        Ok(_) => Ok(()),
        Err(e) => Err(e),
    }
}

/// Get path for input if possible
fn get_path_for_existing_file(input: &str) -> ::std::result::Result<PathBuf, String> {
    let path_o = PathBuf::from(input);
    let path = if path_o.parent().is_some() && path_o.parent().unwrap().is_dir() {
        path_o
    } else {
        let mut path_w = std::env::current_dir().unwrap();
        path_w.push(input);
        path_w
    };

    if path.is_dir() {
        return Err(format!("Specified file is a directory {:?}", path));
    }

    if !path.exists() {
        return Err(format!("Specified file not existing {:?}", path));
    }

    Ok(path)
}

/// verify date input of YYYY-MM-DD
fn validator_date(input: String) -> ::std::result::Result<(), String> {
    match NaiveDate::parse_from_str(&input, DATE_FORMAT_DAY) {
        Ok(_) => Ok(()),
        Err(e) => Err(format!("Invalid date input: {}", e)),
    }
}

/// Check DB for missing entries
fn run_checkdb(pool: Pool, simulate: bool) -> Result<()> {
    let mut conn = pool.get_conn()?;
    let missing_dates = db::crawler::get_missing_dates(&mut conn)?;

    if simulate {
        info!("Simulation mode, discarding result");
    } else {
        db::crawler::insert_missing_dates(&mut conn)?;
    }

    for date in missing_dates {
        info!("Missing: {}", date);
    }

    Ok(())
}

/// Initialize timed task
fn run_daemon(pool: Pool, config: Config, timer: &timer::Timer) -> Result<()> {
    let date_time = Local::now(); // get current datetime
    let today = Local::now().date_naive();
    let target_naive_time = match NaiveTime::parse_from_str(&config.main.time, "%H:%M") {
        Ok(v) => v,
        Err(e) => {
            error!("Unable to parse config time!: {}", e);
            panic!();
        }
    };

    // get retry time
    let retry_time: NaiveTime =
        match NaiveTime::parse_from_str(&config.main.retry_interval, "%H:%M") {
            Ok(v) => v,
            Err(e) => {
                error!("Unable to parse config retry time!: {}", e);
                panic!();
            }
        };

    let schedule_time;
    trace!("Parsed time: {}", target_naive_time);
    if target_naive_time < date_time.time() {
        debug!("Target time is tinier then current time");
        // create from chrono::Duration, convert to std::time::Duration to add
        let tomorrow = today.checked_add_signed(chrono::Duration::days(1)).unwrap();
        schedule_time = tomorrow.and_time(target_naive_time);
    } else {
        schedule_time = today.and_time(target_naive_time);
    }
    info!("First execution will be on {}", schedule_time);

    let pool_c = pool.clone();
    let config_c = config.clone();
    let _guard = timer.schedule(
        schedule_time.and_utc(),
        Some(chrono::Duration::hours(INTERVALL_H)),
        move || {
            if let Err(e) = schedule_crawl_thread(&pool_c, &config_c, retry_time) {
                error!("Error in crawler thread {}", e);
            }
        },
    );
    let _guard_ts = ts::start_daemon(pool, config)?;

    let term = Arc::new(AtomicBool::new(false));
    for sig in signal_hook::consts::TERM_SIGNALS {
        signal_hook::flag::register(*sig, Arc::clone(&term))?;
    }
    while !term.load(Ordering::Relaxed) {
        std::thread::sleep(std::time::Duration::from_millis(1000));
    }
    info!("SIGTERM/SIGINT/SIGQUIT, Exiting");
    Ok(())
}

fn schedule_crawl_thread(pool: &Pool, config: &Config, retry_time: NaiveTime) -> Result<()> {
    if let Some(time) = run_update(pool, config, retry_time) {
        debug!("{}", time);
        let mut conn = pool.get_conn()?;
        if leave_detection_enabled(&mut conn, config) {
            debug!("Leave detection enabled");
            run_leave_detection(
                pool,
                config,
                &time,
                &leave_message(&mut conn, config),
                false,
            );
        } else {
            info!("Leave detection disabled, skipping");
            db::log_message(&mut conn, "Leave detection disabled.");
        }

        if ts3_check_enabled(&mut conn, config) {
            debug!("Unknown-ts-identity-check enabled");
            if let Err(e) = ts::find_unknown_identities(&pool, &config.ts) {
                error!("Error performing ts group check: {}", e);
                db::log_message(&mut conn, "Failed to check ts-identities.");
            }
        } else {
            info!("Unknown-ts-identity-check disabled, skipping");
            db::log_message(&mut conn, "Unknown-ts-identity-check disabled.");
        }

        if auto_names_fetch_enabled(&mut conn, config) {
            debug!("unknown names fetching enabled");
            if let Err(e) = run_missing_name_crawler(pool, &time) {
                error!("Performing missing names crawler: {}", e);
            }
        } else {
            info!("unknown names fetching disabled, skipping");
        }
    }
    Ok(())
}

/// Read leave-message from DB or default to config value
fn leave_message(conn: &mut PooledConn, config: &Config) -> String {
    match db::read_string_setting(conn, LEAVE_MSG_KEY) {
        Ok(Some(v)) => v,
        Ok(None) => {
            warn!("No leave message value found in db");
            config.main.auto_leave_message_default.clone()
        }
        Err(e) => {
            error!("Error retrieving leave message from db: {}", e);
            config.main.auto_leave_message_default.clone()
        }
    }
}

/// Read auto missing names fetchting setting
fn auto_names_fetch_enabled(conn: &mut PooledConn, config: &Config) -> bool {
    match db::read_bool_setting(conn, NAME_FETCHING_KE) {
        Ok(Some(v)) => v,
        Ok(None) => config.main.auto_fetch_unknown_names,
        Err(e) => {
            error!("Error retrieving leave setting {}", e);
            config.main.auto_fetch_unknown_names
        }
    }
}

/// Read leave detection setting from DB
fn leave_detection_enabled(conn: &mut PooledConn, config: &Config) -> bool {
    match db::read_bool_setting(conn, LEAVE_ENABLE_KEY) {
        Ok(Some(v)) => v,
        Ok(None) => config.main.auto_leave_enabled,
        Err(e) => {
            error!("Error retrieving leave setting {}", e);
            config.main.auto_leave_enabled
        }
    }
}

/// Read ts3 unknown identity settings from DB
fn ts3_check_enabled(conn: &mut PooledConn, config: &Config) -> bool {
    match db::read_bool_setting(conn, TS3_UNKNOWN_CHECK_KEY) {
        Ok(Some(v)) => v,
        Ok(None) => config.ts.unknown_id_check_enabled,
        Err(e) => {
            error!("Error retrieving unknown-ts check setting {}", e);
            config.ts.unknown_id_check_enabled
        }
    }
}

/// Detect leaves & process these
/// date: current date for which to look back
fn run_leave_detection(
    pool: &Pool,
    config: &Config,
    date: &NaiveDateTime,
    leave_cause: &str,
    simulate: bool,
) {
    let max_age: NaiveDate = date
        .date()
        .checked_sub_signed(Duration::days(config.main.auto_leave_max_age as i64))
        .unwrap();

    let mut conn = match pool.get_conn() {
        Ok(v) => v,
        Err(e) => {
            error!("Error on db connection! {}", e);
            return;
        }
    };

    match db::crawler::get_next_older_date(&mut conn, date, max_age) {
        Err(e) => {
            db::log_message(
                &mut conn,
                "Unable to get older dates, skipping leave detection",
            );
            error!("Unable to get older date! {}", e);
        }
        Ok(Some(previous_date)) => {
            debug!("Date1 {} Date2 {}", previous_date, date);
            match db::crawler::get_member_left(&mut conn, &previous_date, date) {
                Err(e) => {
                    db::log_message(&mut conn, &format!("Unable to query left members! {}", e));
                    error!("Unable to retrieve left members {}", e)
                }
                Ok(left) => {
                    for m in left {
                        match m.membership_nr {
                            Some(nr) => {
                                if simulate {
                                    info!("Found leave for {} {}", m.id, m.get_name());
                                } else {
                                    match db::crawler::insert_member_leave(
                                        &mut conn,
                                        m.id,
                                        nr,
                                        previous_date.date(),
                                        leave_cause,
                                    ) {
                                        Ok(trial) => db::log_message(
                                            &mut conn,
                                            &format!(
                                        "Detected leave for {} {} nr:{} terminated trials: {}",
                                            m.get_name(),m.id,nr,trial),
                                        ),
                                        Err(e) => {
                                            error!("Unable to insert member leave! nr:{} {}", nr, e)
                                        }
                                    }
                                }
                            }
                            None => {
                                if simulate {
                                    info!("No open membership for {} {}", m.id, m.get_name());
                                } else {
                                    db::log_message(
                                        &mut conn,
                                        &format!(
                                            "No open membership for {} {}, can't auto-leave!",
                                            m.get_name(),
                                            m.id
                                        ),
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
        Ok(None) => {
            if simulate {
                warn!("No older entries to compare found, skipping leave detection");
            } else {
                db::log_message(
                    &mut conn,
                    "No older entries to compare found, skipping leave detection",
                );
            }
        }
    }
    if simulate {
        info!("Finished simulation of leave detection.");
    }
}

/// Search for missing account names and fetch them from z8. Ignore invalid IDs.
fn run_missing_name_crawler(pool: &Pool, time: &NaiveDateTime) -> Result<()> {
    let mut conn = pool.get_conn()?;
    let ids = db::crawler::get_missing_name_ids(&mut conn)?;
    debug!("Found {:?} IDs with missing account names", ids);

    let names: Vec<(i32, String)> = ids
        .into_iter()
        .map(|id| {
            let url = format!(
                "https://crossfire.z8games.com/rest/userprofile.json?command=header&usn={}",
                id
            );
            let profile = crawler::http::get(&url, HeaderType::Ajax)?;
            trace!("name fetch response: {:?}", profile);
            Ok(match crawler::parser::parse_profile(&profile)? {
                Some(name) => Some((id, name)),
                None => {
                    db::log_message(
                        &mut conn,
                        &format!("Unnamed account {} reported as invalid.", id),
                    );
                    None
                }
            })
        })
        .filter_map(Result::transpose)
        .collect::<Result<Vec<(i32, String)>>>()?;
    info!("Account Names for unknown ids: {:?}", names);

    db::crawler::insert_missing_names(&mut conn, &names, &time)?;

    names.into_iter().for_each(|(id, name)| {
        db::log_message(
            &mut conn,
            &format!("Fetched account name {} for {}", name, id),
        )
    });

    Ok(())
}

/// Performs a complete crawl
fn run_update(pool: &Pool, config: &Config, retry_time: NaiveTime) -> Option<NaiveDateTime> {
    trace!("performing crawler");

    let mut member_success = false;
    let mut clan_success = false;

    let mut time = Local::now().naive_local();

    for x in 1..((config.main.retries + 1) as u32) {
        if !member_success {
            match run_update_member(pool, config, &time) {
                Ok(_) => {
                    debug!("Member crawling successful.");
                    member_success = true;
                }
                Err(e) => error!("Error at member update: {}: {}", x, e),
            }
        }

        if !clan_success {
            match run_update_clan(pool, config, &time) {
                Ok(_) => {
                    debug!("Clan crawling successful.");
                    clan_success = true;
                }
                Err(e) => error!("Error at clan update: {}: {}", x, e),
            }
        }

        if member_success && clan_success {
            info!("Crawling successful");
            return Some(time);
        } else if x == config.main.retries {
            warn!("No dataset for this schedule, all retries failed!");
            match write_missing(&time, pool, !member_success) {
                Ok(_) => {}
                Err(e) => error!("Unable to write missing date! {}", e),
            }

            let message = format!(
                    "Error at clantool update execution!\nRetried {} times, waiting {} seconds max.\nMissing Member data: {}. Missing clan data: {}"
                    ,x,retry_time.num_seconds_from_midnight()*x,!member_success,!clan_success);

            if config.main.send_error_mail {
                send_mail(&config, "Clantool error", &message);
            }
        } else {
            let wait_time = retry_time.num_seconds_from_midnight() * x;
            std::thread::sleep(std::time::Duration::from_secs(wait_time.into()));

            // refresh time, otherwise leave it, so it's synchronized
            if !member_success && !clan_success {
                time = Local::now().naive_local();
            }
        }
    }
    None
}

/// Send email, catch & log errors
fn send_mail(config: &Config, subject: &str, message: &str) {
    let mut email = EmailBuilder::new();
    for addr in config.main.mail.iter() {
        email = email.to(addr.as_str());
    }
    let email = email
        .from(config.main.mail_from.as_str())
        .subject(subject)
        .text(message)
        .build()
        .unwrap();

    // Open a local connection on port 25
    let mut mailer = SmtpClient::new_unencrypted_localhost().unwrap().transport();
    // Send the email
    if let Err(e) = mailer.send(email.into()) {
        error!("Error at mail sending: {}", e);
    }
}

/// wrapper to write missing date
/// allowing for error return
fn write_missing(timestamp: &NaiveDateTime, pool: &Pool, missing_member: bool) -> Result<()> {
    db::crawler::insert_missing_entry(timestamp, &mut pool.get_conn()?, missing_member)?;
    Ok(())
}

/// get member url for ajax request
fn get_member_url(site: u8, config: &Config) -> String {
    let _site = format!("{}", site);
    let end_row = site * config.main.clan_ajax_exptected_per_site;
    let start_row = end_row - (config.main.clan_ajax_exptected_per_site - 1);
    let _start_row = format!("{}", start_row);
    let _end_row = format!("{}", end_row);
    let mut output = config
        .main
        .clan_ajax_url
        .replace(&config.main.clan_ajax_site_key, &_site);
    output = output.replace(&config.main.clan_ajax_start_row_key, &_start_row);
    output = output.replace(&config.main.clan_ajax_end_row_key, &_end_row);
    debug!("Start: {} end: {} site: {}", start_row, end_row, site);
    output
}

/// run member data crawl & update
fn run_update_member(pool: &Pool, config: &Config, time: &NaiveDateTime) -> Result<()> {
    trace!("run_update_member");
    let mut site = 0;
    let mut members = Vec::new();
    let mut to_receive = 100;
    while members.len() < to_receive {
        site += 1;
        if site > config.main.clan_ajax_max_sites {
            error!(
                "Reaching member site {}, config max is {} aborting.",
                site, config.main.clan_ajax_max_sites
            );
            return Err(Error::Other("Site over limit."));
        }
        let raw_members_json = crawler::http::get(&get_member_url(site, config), HeaderType::Ajax)?;
        let (mut members_temp, t_total) = match crawler::parser::parse_all_member(&raw_members_json)
        {
            Err(e) => {
                info!("Response: {}", raw_members_json);
                return Err(e);
            }
            Ok(v) => v,
        };
        to_receive = t_total as usize;
        members.append(&mut members_temp);
        trace!("fetched site {}", site);
    }
    debug!("Fetched {} member entries", members.len());
    db::crawler::insert_members(&mut pool.get_conn()?, &members, time)?;
    Ok(())
}

/// run clan crawl & update
fn run_update_clan(pool: &Pool, config: &Config, time: &NaiveDateTime) -> Result<()> {
    trace!("run_update_clan");
    let raw_http_clan = crawler::http::get(&config.main.clan_url, HeaderType::Html)?;
    let clan = crawler::parser::parse_clan(&raw_http_clan)?;
    db::crawler::insert_clan_update(&mut pool.get_conn()?, &clan, &time)?;
    Ok(())
}

/// Init log system
/// Creating a default log file if not existing
fn init_log() -> Result<()> {
    let mut log_path = current_dir()?;
    log_path.push(LOG_PATH);
    let mut log_dir = log_path.clone();
    println!("LogPath: {:?}", &log_path);
    log_dir.pop();
    DirBuilder::new().recursive(true).create(log_dir)?;

    if metadata(&log_path).is_err() {
        let config = include_str!("../log.yml");
        let mut file = File::create(&log_path)?;
        file.write_all(config.as_bytes())?;
    }
    log4rs::init_file(log_path, Default::default())?;
    Ok(())
}

#[cfg(test)]
mod tests {

    use chrono::naive::NaiveTime;
    use chrono::Local;

    #[test]
    fn test_chrono() {
        let date = Local::now().date_naive();
        let parsed_time = NaiveTime::parse_from_str("12:00", "%H:%M").unwrap();
        let parsed_time_2 = NaiveTime::parse_from_str("12:01", "%H:%M").unwrap();
        let datetime_1 = date.and_time(parsed_time);
        let datetime_2 = date.and_time(parsed_time_2);
        //assert_eq!(datetime_1.cmp(datetime_2),Ordering::Less);
        assert_eq!(parsed_time_2 > parsed_time, true);
        assert_eq!(datetime_2 > datetime_1, true);
    }
}
