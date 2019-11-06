use crate::model::*;
use crate::settings::Settings;
use mysql::*;
use snafu::Snafu;
use snafu::{Backtrace, ResultExt};
use std::collections::HashMap;

type Result<T> = ::std::result::Result<T, DBError>;

/// Required manual formater, otherwise the time is omitted
const DATETIME_FORMAT: &str = "%Y-%m-%d %H:%M:%S";

#[derive(Debug, Snafu)]
pub enum DBError {
    #[snafu(display("DB error: {}", source))]
    Mysql { source: mysql::Error },
    #[snafu(display("No max date found in members!"))]
    MissingMaxDate,
    FromRowError {
        source: mysql::FromRowError,
        backtrace: Backtrace,
    },
}

impl std::convert::From<mysql::Error> for DBError {
    fn from(e: mysql::Error) -> Self {
        DBError::Mysql { source: e }
    }
}

pub fn init(config: &Settings) -> Result<Pool> {
    let mut opts = OptsBuilder::new();
    opts.ip_or_hostname(Some(config.db.ip.clone()));
    opts.db_name(Some(config.db.database.clone()));
    opts.tcp_port(config.db.port);
    opts.user(Some(config.db.user.clone()));
    opts.pass(Some(config.db.password.clone()));
    opts.prefer_socket(false);
    let opts: Opts = opts.into();
    Ok(Pool::new(opts)?)
}

pub fn update_data(pool: Pool, data: Vec<RankedEntry>) -> Result<()> {
    let mut transaction =
        pool.start_transaction(true, Some(IsolationLevel::RepeatableRead), Some(false))?;

    let mut rank_map = HashMap::new();
    let mut mode_map = HashMap::new();
    {
        transaction.prep_exec("DELETE FROM ranks", ())?;
        let mut stmt = transaction.prepare(
            "INSERT INTO ranks (`usn`,`season`,`mode`,`rank`,`subrank`) VALUES (?,?,?,?,?)",
        )?;

        for elem in data.into_iter() {
            stmt.execute((elem.usn, elem.season, elem.mode, elem.rank, elem.subrank))?;

            mode_map.insert(elem.mode, elem.mode_name);
            rank_map.insert(elem.rank, elem.rank_name);
        }
    }

    {
        transaction.prep_exec("DELETE FROM mode_names", ())?;
        let mut stmt =
            transaction.prepare("INSERT INTO mode_names (`mode`,`name`) VALUES (?,?)")?;
        for mode in mode_map.into_iter() {
            stmt.execute(mode)?;
        }
    }

    {
        transaction.prep_exec("DELETE FROM rank_names", ())?;
        let mut stmt =
            transaction.prepare("INSERT INTO rank_names (`rank`,`name`) VALUES (?,?)")?;
        for rank in rank_map.into_iter() {
            stmt.execute(rank)?;
        }
    }

    transaction.commit()?;
    Ok(())
}

pub fn get_current_member_ids(pool: &Pool) -> Result<Vec<USN>> {
    let date = get_member_max_date(&pool)?;
    trace!("Max date: {}", date);
    let res = pool.prep_exec(
        "SELECT id FROM member WHERE date = ?",
        (date.format(DATETIME_FORMAT).to_string(),),
    )?;

    let data: Vec<USN> = res
        .map(|row| {
            let id: USN = from_row_opt(row?).context(FromRowError)?;
            Ok(id)
        })
        .collect::<Result<_>>()?;

    Ok(data)
}

pub fn get_member_max_date(pool: &Pool) -> Result<Date> {
    let mut res = pool.prep_exec("SELECT MAX(date) FROM member", ())?;
    if let Some(r) = res.next() {
        let r = r?;
        let date: Date = from_row(r);
        Ok(date)
    } else {
        Err(DBError::MissingMaxDate)
    }
}

pub fn get_ranked_data(pool: &Pool) -> Result<Vec<APIRankedEntry>> {
    let res = pool.prep_exec("SELECT * from ranked", ())?;
    let data: Vec<APIRankedEntry> = res
        .map(|row| {
            let (player_name, usn, season, mode, rank, subrank, mode_name, rank_name): (
                String,
                USN,
                i32,
                i32,
                i32,
                i32,
                String,
                String,
            ) = from_row_opt(row?).context(FromRowError)?;
            Ok(APIRankedEntry {
                player_name,
                usn,
                season,
                mode,
                rank,
                subrank,
                mode_name,
                rank_name,
            })
        })
        .collect::<Result<_>>()?;
    Ok(data)
}
