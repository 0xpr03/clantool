#[macro_use]
extern crate log;
#[macro_use]
extern crate lazy_static;
use actix_web::web::Data;
use chrono::offset::Local;
use snafu::{Backtrace, ResultExt, Snafu};
use std::sync::RwLock;
use structopt::StructOpt;
use mysql::Pool;
use timer;

mod crawler;
mod db;
mod model;
mod settings;
mod webserver;

const INTERVALL_H: i64 = 24; // execute intervall
const RUST_LOG: &'static str = "RUST_LOG";

#[derive(Debug, StructOpt)]
#[structopt(name = "Clantool-Ranked", about = "Clantool ranked stats daemon.")]
struct Opt {
    /// Activate debug mode
    #[structopt(short, long)]
    debug: bool,
    /// Force crawling
    #[structopt(short, long)]
    crawl: bool,
}

type Cache = Data<RwLock<Vec<model::APIRankedEntry>>>;

type Result<T> = ::std::result::Result<T, Error>;

#[derive(Debug, Snafu)]
pub enum Error {
    #[snafu(display("Could not load config: {}\n{}", source, backtrace))]
    LoadConfig {
        source: config::ConfigError,
        backtrace: Backtrace,
    },
    #[snafu(display("DB error: {}: {}", context, source))]
    DBError {
        context: &'static str,
        source: db::DBError,
    },
    #[snafu(display("Webserver error: {}", source))]
    WebError { source: webserver::WebError },
}

fn main() -> Result<()> {
    init_log();
    if let Err(e) = _main() {
        eprintln!("{}", e);
    }
    Ok(())
}

fn init_log() {
    if std::env::var(RUST_LOG).is_err() {
        std::env::set_var(
            RUST_LOG,
            "ranked_daemon=trace,reqwest=info,actix_web=info,actix_server=info",
        );
    }
    env_logger::init();
}

fn _main() -> Result<()> {
    let config = settings::read()?;
    trace!("{:?}", config);
    let pool = db::init(&config).context(DBError {
        context: "Database initialization",
    })?;

    let cache: Cache = Data::new(RwLock::new(Vec::new()));

    let opt = Opt::from_args();
    if opt.crawl {
        info!("Starting manual crawl");
        match crawler::schedule_crawler(pool.clone()) {
            Err(e) => error!("Error in crawler thread {}", e),
            Ok(_) => (),
        }
    }

    // populate cache
    update_cache(&cache, &pool);

    info!("Entering daemon mode");

    let date_time = Local::now(); // get current datetime
    let today = Local::today();

    let schedule_time = if config.main.hour < date_time.time() {
        debug!("Target time is tinier then current time");
        // create from chrono::Duration, convert to std::time::Duration to add
        let c_duration = chrono::Duration::hours(INTERVALL_H);
        let tomorrow = today.checked_add_signed(c_duration).unwrap();
        tomorrow.and_time(config.main.hour).unwrap()
    } else {
        today.and_time(config.main.hour).unwrap()
    };

    let timer = timer::Timer::new();

    let cache_c = cache.clone();
    let _a = timer.schedule(
        schedule_time,
        Some(chrono::Duration::hours(INTERVALL_H)),
        move || match crawler::schedule_crawler(pool.clone()) {
            Err(e) => error!("Error in crawler thread {}", e),
            Ok(_) => update_cache(&cache_c, &pool),
        },
    );

    webserver::run(cache, config).context(WebError)?;

    info!("Exiting daemon");

    Ok(())
}

fn update_cache(cache: &Cache, pool: &Pool) {
    let mut cache = cache.write().expect("Can't write cache!");
    let mut data = match db::get_ranked_data(pool) {
        Ok(v) => v,
        Err(e) => {
            error!("Can't retrieve new ranked data!\n{}", e);
            return;
        }
    };
    cache.clear();
    cache.append(&mut data);
    drop(cache);
}