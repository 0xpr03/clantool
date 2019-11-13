/*
Copyright 2017-2019 Aron Heinecke

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
use chrono::naive::NaiveDate;
use chrono::naive::NaiveDateTime;
use mysql::{from_row_opt, Opts, OptsBuilder, Pool, PooledConn, Row, Transaction};
use regex;

use crate::error::Error;
use crate::import;
use crate::Clan;
use crate::LeftMember;
use crate::Member;
use crate::Result;

const POOL_MIN_CONN: usize = 1; // minimum amount of running connection per pool
const POOL_MAX_CONN: usize = 100; // maximum amount of running connections per pool
const TABLE_MISSING_DATES: &str = "t_missingdates"; // temporary table used to store missing dates
const DATE_FORMAT: &str = "%Y-%m-%d";
const DATETIME_FORMAT: &str = "%Y-%m-%d %H:%M:%S";

/// Create a new db pool with fixed min-max connections
pub fn new(
    address: String,
    port: u16,
    user: String,
    password: Option<String>,
    db: String,
) -> Result<Pool> {
    let mut builder = OptsBuilder::new();
    builder
        .ip_or_hostname(Some(address))
        .db_name(Some(db))
        .tcp_port(port)
        .prefer_socket(false)
        .user(Some(user))
        .pass(password);
    let opts: Opts = builder.into();
    let pool = Pool::new_manual(POOL_MIN_CONN, POOL_MAX_CONN, opts)?;
    Ok(pool)
}

/// Insert log message for current timestamp.
/// Does not omit errors, logging them as error.
/// error_msg will be logged with the error itself: {error_msg} {error}
pub fn log_message(conn: &mut PooledConn, message: &str, error_msg: &str) {
    info!("db-log: {}", message);
    match log_message_opt(conn, message) {
        Ok(_) => (),
        Err(e) => error!("{} {}", error_msg, e),
    }
}

/// Insert log message for current timestamp
pub fn log_message_opt(conn: &mut PooledConn, message: &str) -> Result<()> {
    let mut stmt = conn.prepare("INSERT INTO `log` (`date`,`msg`) VALUES (NOW(),?)")?;
    stmt.execute((message,))?;
    Ok(())
}

/// Updatae unknwon_ts_ids table based on member clients  
/// Handles known member client_id filtering
pub fn update_unknown_ts_ids(conn: &mut PooledConn, member_clients: &[usize]) -> Result<()> {
    create_temp_ts3_table(conn, "t_member_clients")?;
    {
        // insert every member client id into temp table
        let mut stmt = conn.prepare("INSERT INTO `t_member_clients` (`client_id`) VALUES (?)")?;
        for client in member_clients {
            stmt.execute((&client,))?;
        }
    }

    {
        // filter everything out that has a member assigned
        conn.prep_exec("DELETE FROM t1 USING `t_member_clients` t1 INNER JOIN `ts_relation` t2 ON ( t1.client_id = t2.client_id )", ())?;
    }

    {
        // truncate unknown ts ids table
        conn.prep_exec("TRUNCATE `unknown_ts_ids`", ())?;
    }

    {
        // replace with correct values
        conn.prep_exec(
            "INSERT INTO `unknown_ts_ids` SELECT * FROM `t_member_clients`",
            (),
        )?;
    }

    Ok(())
}

/// Creates a temporary, single client_id column table with the specified name
fn create_temp_ts3_table(conn: &mut PooledConn, tbl_name: &'static str) -> Result<()> {
    let mut stmt = conn.prepare(format!(
        "CREATE TEMPORARY TABLE `{}` (
                        `client_id` int(11) NOT NULL PRIMARY KEY
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                        ",
        tbl_name
    ))?;
    stmt.execute(())?;
    Ok(())
}

/// Insert a Vec of members under the given Timestamp
/// This does affect table member and member_names
pub fn insert_members(
    conn: &mut PooledConn,
    members: &[Member],
    timestamp: &NaiveDateTime,
) -> Result<()> {
    {
        let mut stmt =
            conn.prepare("INSERT INTO `member` (`id`,`date`,`exp`,`cp`) VALUES (?,?,?,?)")?;
        for member in members {
            stmt.execute((&member.id, timestamp, &member.exp, &member.contribution))?;
        }
    }
    {
        let mut stmt = conn.prepare(
            "INSERT IGNORE INTO `member_names` (`id`,`name`,`date`,`updated`) VALUES (?,?,?,?)",
        )?;
        for member in members {
            stmt.execute((&member.id, &member.name, timestamp, timestamp))?;
        }
    }
    {
        let mut stmt =
            conn.prepare("UPDATE `member_names` SET `updated` = ? WHERE `id` = ? AND `name` = ?")?;
        for member in members {
            stmt.execute((timestamp, &member.id, &member.name))?;
        }
    }
    Ok(())
}

/// Insert clan struct into clan-table
pub fn insert_clan_update(
    conn: &mut PooledConn,
    clan: &Clan,
    timestamp: &NaiveDateTime,
) -> Result<()> {
    let mut stmt = conn.prepare(
        "INSERT INTO `clan` (`date`,`wins`,`losses`,`draws`,`members`) VALUES (?,?,?,?,?)",
    )?;
    stmt.execute((timestamp, clan.wins, clan.losses, clan.draws, clan.members))?;
    Ok(())
}

/// Insert datetime into missing entry table
/// missing_member set to true if also CP & EXP data of members is missing
/// which are to be distinct from missing clan data
pub fn insert_missing_entry(
    datetime: &NaiveDateTime,
    conn: &mut PooledConn,
    missing_member: bool,
) -> Result<()> {
    let mut stmt = conn.prepare("INSERT INTO `missing_entries` (`date`,`member`) VALUES (?,?)")?;
    stmt.execute((datetime, missing_member))?;
    Ok(())
}

/// Retrieves missing dates in the db
/// for which no entri(es exist
pub fn get_missing_dates(conn: &mut PooledConn) -> Result<Vec<NaiveDate>> {
    // create date lookup table
    create_temp_date_table(conn, "t_dates")?;
    create_temp_date_table(conn, TABLE_MISSING_DATES)?;

    let (min, max) = get_min_max_date(conn)?;
    info!("max: {} min: {}", max, min);

    if max == min {
        return Err(Error::Other("Not enough entries in DB, aborting."));
    }

    let days = max.signed_duration_since(min).num_days();
    let step = days / 10 + 1;

    debug!("days: {}", days);

    // {} required, stmt lives too long
    {
        // create date lookup table
        let mut stmt = conn.prepare("INSERT INTO `t_dates` (`date`) VALUES (?)")?;
        let mut current = min.succ();
        let mut i = 0;
        while current != max {
            stmt.execute((current,))?;
            current = current.succ();
            i += 1;
            if i % step == 0 {
                info!("{}%", i * 100 / days);
            }
        }
        debug!("lookup table size: {}", i);
    }
    {
        // get missing dates not already stored
        // t_dates left join (clan JOIN member) left join missing_entries
        // where right = null
        // using datetime as date, won't match otherwise
        let mut stmt = conn.prepare(format!(
            "
        INSERT INTO `{}` (`date`)
        SELECT t0.`date` FROM `t_dates` t0 
        LEFT JOIN (
            SELECT t2.date FROM `clan` t2 
            JOIN `member` t3 ON DATE(t2.date) = DATE(t3.date)
        ) as t1
        ON t0.date = DATE(t1.date)
        LEFT JOIN `missing_entries` t4
            ON t0.date = DATE(t4.date) AND t4.member = true
        WHERE t1.date IS NULL
        AND t4.date IS NULL",
            TABLE_MISSING_DATES
        ))?;

        stmt.execute(())?;
    }

    let mut dates: Vec<NaiveDate> = Vec::new();
    {
        // now retrieve missing dates for user information
        let mut stmt = conn.prepare(format!(
            "SELECT date FROM `{}` order by date ASC",
            TABLE_MISSING_DATES
        ))?;

        for row in stmt.execute(())? {
            dates.push(row?.take_opt("date").ok_or(Error::NoValue("date"))??);
        }
    }
    Ok(dates)
}

/// Inserts TABLE_MISSING_DATES into `missing_entries`
pub fn insert_missing_dates(conn: &mut PooledConn) -> Result<()> {
    let mut stmt = conn.prepare(format!(
        "
        INSERT INTO `missing_entries` (`date`)
        SELECT `date` FROM `{}`",
        TABLE_MISSING_DATES
    ))?;

    stmt.execute(())?;
    Ok(())
}

/// Creates a temporary, single date column table with the specified name
fn create_temp_date_table(conn: &mut PooledConn, tbl_name: &'static str) -> Result<()> {
    let mut stmt = conn.prepare(format!(
        "CREATE TEMPORARY TABLE `{}` (
                        `date` datetime NOT NULL PRIMARY KEY
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                        ",
        tbl_name
    ))?;
    stmt.execute(())?;
    Ok(())
}

/// Retrieves the oldest & newest date `clan` & `member` table combined
/// Returns (min,max) dates as String
fn get_min_max_date(conn: &mut PooledConn) -> Result<(NaiveDate, NaiveDate)> {
    // full outer join to get all
    let mut stmt = conn.prepare(
        "SELECT MIN(`date`) as min, MAX(`date`) as max FROM (
        SELECT t11.date FROM clan t11
        LEFT JOIN member t12 ON t11.date = t12.date
        UNION
        SELECT t22.date FROM clan t21
        RIGHT JOIN member t22 ON t21.date = t22.date
    ) as T",
    )?;
    let mut result = stmt.execute(())?;
    let row: Row = result.next().ok_or(Error::NoValue("empty result"))??;
    let values = from_row_opt(row)?;
    Ok(values)
}

/// Check whether date has data and is thus valid in member table
/// Returns datetime if correct
pub fn check_date_for_data(
    conn: &mut PooledConn,
    date: NaiveDate,
) -> Result<Option<NaiveDateTime>> {
    let mut stmt = conn.prepare(
        "SELECT `date` FROM member m
    WHERE m.date LIKE ? LIMIT 1",
    )?;
    let mut result = stmt.execute((format!("{}%", date.format(DATE_FORMAT)),))?;
    match result.next() {
        None => Ok(None),
        Some(v) => {
            let row = v?;
            let value = from_row_opt(row)?;
            Ok(Some(value))
        }
    }
}

/// Get next older date from specified datetime which is not marked as as missing entry
/// and not older than the specified minimum
/// returns Result<None> if no older date within range was found
pub fn get_next_older_date(
    conn: &mut PooledConn,
    date: &NaiveDateTime,
    min: NaiveDate,
) -> Result<Option<NaiveDateTime>> {
    debug!("date: {} min: {}", date, min);
    let mut stmt = conn.prepare(
        "SELECT MAX(m.`date`) as `date` FROM member m
    LEFT OUTER JOIN missing_entries mi
        ON ( DATE(m.date) = DATE(mi.date) AND mi.member = true)
    WHERE m.date < ? AND m.date >= ? AND mi.date IS NULL",
    )?;
    let mut result = stmt.execute((
        date.format(DATETIME_FORMAT).to_string(),
        format!("{}%", min.format(DATE_FORMAT)),
    ))?;
    match result.next() {
        None => Ok(None),
        Some(v) => {
            let row = v?;
            let value = from_row_opt(row)?;
            Ok(value)
        }
    }
}

/// read entry in settings table as string
pub fn read_string_setting(conn: &mut PooledConn, key: &str) -> Result<Option<String>> {
    read_setting::<String>(conn, key)
}

/// Read setting for generic value
pub fn read_setting<T>(conn: &mut PooledConn, key: &str) -> Result<Option<T>>
where
    T: mysql::prelude::FromValue,
{
    let mut stmt = conn.prepare("SELECT `value` FROM settings WHERE `key` = ?")?;
    let mut result = stmt.execute((key,))?;
    match result.next() {
        None => Ok(None),
        Some(v) => {
            let row = v?;
            let value: Option<T> = from_row_opt(row)?;
            Ok(value)
        }
    }
}

/// read entry in settings table as bool
pub fn read_bool_setting(conn: &mut PooledConn, key: &str) -> Result<Option<bool>> {
    read_setting::<String>(conn, key).map(|v| {
        v.map(|s| match s.as_str() {
            "1" | "true" => true,
            _ => false,
        })
    })
}

/// Get left members from difference betweeen date1 & date2
/// Expected date1 < date2
pub fn get_member_left(
    conn: &mut PooledConn,
    date1: &NaiveDateTime,
    date2: &NaiveDateTime,
) -> Result<Vec<LeftMember>> {
    if date1 >= date2 {
        return Err(Error::Other("invalid input, date1 < date2 expected!"));
    }
    let mut stmt = conn.prepare(
        "SELECT name,a.id,ms.nr
    FROM (
        SELECT m1.id
        FROM member m1
            WHERE m1.date = :datetime1
        UNION DISTINCT
        SELECT ms.id
        FROM membership ms
            WHERE ms.`from` = :date1 AND ms.`to` IS NULL
    ) a
    LEFT JOIN `member_names` names ON a.id = names.id AND
            `names`.updated = (SELECT MAX(n2.updated) 
                FROM `member_names` n2 
                WHERE n2.id = a.id
            )
    LEFT JOIN `membership` ms ON a.id = ms.id AND ms.to IS NULL
    WHERE 
    a.id NOT IN ( 
        SELECT m2.id FROM member m2 
        WHERE m2.id = a.id AND m2.date = :datetime2
    )",
    )?;

    let result = stmt.execute(params! {
        // do not use datetime directly, as milliseconds will be given
        // as there is no match for millseconds precise datetimes
        "datetime1" => date1.format(DATETIME_FORMAT).to_string(),
        "datetime2" => date2.format(DATETIME_FORMAT).to_string(),
        "date1" => date1.date(),
    })?;

    result
        .map(|res| {
            let (name, id, nr) = from_row_opt(res?)?;
            Ok(LeftMember {
                id,
                name,
                membership_nr: nr,
            })
        })
        .collect()
}

/// Insert member leave
/// terminates existing member-trials
/// insert leave cause as no kick with provided cause
/// requires existing membership entry
/// returns affected affected trial entries that were ended
pub fn insert_member_leave(
    conn: &mut PooledConn,
    id: i32,
    ms_nr: i32,
    date_leave: NaiveDate,
    cause: &str,
) -> Result<(u64)> {
    let trial_affected;
    {
        let mut stmt = conn.prepare("UPDATE `membership` SET `to` = ? WHERE `nr` = ?")?;
        stmt.execute((date_leave, ms_nr))?.affected_rows();
    }
    {
        let mut stmt = conn.prepare("UPDATE `member_trial` SET `to` = ? WHERE `id` = ?")?;
        trial_affected = stmt.execute((date_leave, id))?.affected_rows();
    }
    {
        let mut stmt = conn.prepare("INSERT INTO `membership_cause` (`nr`,`kicked`,`cause`) VALUES(?,?,?) ON DUPLICATE KEY UPDATE `kicked` = VALUES(`kicked`), `cause` = VALUES(`cause`)")?;
        stmt.execute((ms_nr, false, cause))?;
    }
    Ok(trial_affected)
}

/// Import account data inserter
pub struct ImportAccountInserter<'a> {
    transaction: Transaction<'a>,
    comment_addition: &'a str,
}

impl<'a> ImportAccountInserter<'a> {
    /// New Import Account Inserter
    /// comment_addition: appended to comment on insertion (`imported account`)
    /// date_name_insert: date to use for name insertion & update field
    pub fn new(pool: &'a Pool, comment_addition: &'a str) -> Result<ImportAccountInserter<'a>> {
        Ok(ImportAccountInserter {
            transaction: pool.start_transaction(false, None, None)?,
            comment_addition,
        })
    }

    /// Commit account import
    pub fn commit(self) -> Result<()> {
        self.transaction.commit()?;
        Ok(())
    }

    /// Format comment with addition
    pub fn get_formated_comment(&self, account: &import::ImportMembership) -> String {
        format!("{}\n{}", &self.comment_addition, account.comment)
    }

    /// Insert account data
    /// return total amount memberships,inserted for account
    pub fn insert_account(&mut self, acc: &import::ImportMembership) -> Result<(usize, usize)> {
        self.transaction.prep_exec(
            "INSERT IGNORE INTO `member_names` (`id`,`name`,`date`,`updated`) VALUES (?,?,?,?)",
            (acc.id, &acc.name, acc.date_name, acc.date_name),
        )?;
        let comment = self.get_formated_comment(acc);
        self.transaction.prep_exec(
            "INSERT IGNORE INTO `member_addition` (`id`,`name`,`vip`,`comment`) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE comment=CONCAT(comment,\"\n\",VALUES(`comment`))",
            (acc.id, &acc.vname, acc.vip, comment),
        )?;
        let membership_total = acc.memberships.len();
        let mut membership_inserted = 0;

        for membership in &acc.memberships {
            let nr;
            {
                let result = self.transaction.prep_exec(
                    "INSERT IGNORE INTO `membership` (`id`,`from`,`to`) VALUES (?,?,?)",
                    (acc.id, membership.from, membership.to),
                )?;
                nr = result.last_insert_id() as i32;
            }

            if nr != 0 {
                self.transaction.prep_exec(
                    "INSERT INTO `membership_cause` (`nr`,`kicked`,`cause`) VALUES (?,?,?)",
                    (nr, false, import::IMPORT_MEMBERSHIP_CAUSE),
                )?;
                trace!("membership id:{} nr:{} {:?}", acc.id, nr, membership);
                membership_inserted += 1;
            } else {
                warn!("Duplicate membership for id:{} {:?}", acc.id, membership);
            }
        }
        Ok((membership_total, membership_inserted))
    }
}

/// Initialize tables on first try, error if existing
/// Undos everything on failure
pub fn init_tables(pool: &Pool) -> Result<()> {
    let tables = get_db_create_sql();

    let mut transaction = pool.start_transaction(false, None, None)?;

    for table in tables {
        transaction.prep_exec(table, ())?;
    }

    transaction.commit()?;
    Ok(())
}

/// Get database setup sql
/// Returns a vector of table setup sql
fn get_db_create_sql() -> Vec<String> {
    let raw_sql = include_str!("../setup.sql");

    let reg = regex::Regex::new(r"(/\*(.|\s)*?\*/)").unwrap(); // https://regex101.com/r/bG6aF2/6, replace `\/` with `/`
    let raw_sql = reg.replace_all(raw_sql, "");

    let raw_sql = raw_sql.replace("\n", " ");
    let raw_sql = raw_sql.replace("\r", "");

    debug!("\n\nSQL: {}\n\n", raw_sql);

    let split_sql: Vec<String> = raw_sql
        .split(';')
        .filter_map(|x| {
            // split at `;`, filter_map on iterator
            let x = x.trim();
            if !x.is_empty() {
                // check if it's an empty group (last mostly)
                Some(x.to_owned()) // &str to String
            } else {
                None
            }
        })
        .collect(); // collect back to vec

    debug!("\n\nGroups: {:?}\n\n", split_sql);

    split_sql
}

#[cfg(test)]
mod test {
    use super::*;
    use chrono::naive::{NaiveDateTime, NaiveTime};
    use chrono::offset::Local;
    use chrono::Duration;
    use std::collections::HashMap;

    use mysql::{from_row, Pool, PooledConn};

    use crate::error::Error;
    use Clan;
    use Member;

    use regex;

    // change these settings to connect to another DB
    // Please note: the TEST_DB has to be empty (no tables)!
    const TEST_USER: &str = "root";
    const TEST_PASSWORD: &str = "root";
    const TEST_DB: &str = "test";
    const TEST_PORT: u16 = 3306;
    const TEST_IP: &str = "127.0.0.1";
    const REGEX_VIEW: &str = r"CREATE (OR REPLACE)? VIEW `([\-_a-zA-Z]+)` AS";

    /// Cleanup guard for tests removing it's database content afterwards
    struct CleanupGuard {
        pub pool: Pool,
        pub tables: Vec<String>,
        pub views: Vec<String>,
    }

    impl CleanupGuard {
        pub fn new(pool: Pool) -> CleanupGuard {
            CleanupGuard {
                pool,
                tables: Vec::new(),
                views: Vec::new(),
            }
        }
    }

    impl Drop for CleanupGuard {
        fn drop(&mut self) {
            for view in &self.views {
                self.pool
                    .prep_exec(format!("DROP VIEW IF EXISTS `{}`;", view), ())
                    .unwrap();
                println!("dropped view {}", view);
            }
            for table in &self.tables {
                self.pool
                    .prep_exec(format!("DROP TABLE IF EXISTS `{}`;", table), ())
                    .unwrap();
                println!("dropped table {}", table);
            }
        }
    }

    /// Setup db tables, does crash if tables are existing
    /// Created as temporary if specified, using the u32 as name suffix: myTable{},u32
    /// Returns all table names which got created
    fn setup_tables(pool: Pool) -> Result<CleanupGuard> {
        let tables = get_db_create_sql();
        let reg_tables = regex::Regex::new(r"CREATE TABLE `([\-_a-zA-Z]+)` \(").unwrap();
        let reg_views = regex::Regex::new(REGEX_VIEW).unwrap();
        let mut guard = CleanupGuard::new(pool);
        for a in tables {
            if let Some(cap) = reg_tables.captures(&a) {
                let table = cap[1].to_string();
                guard
                    .pool
                    .prep_exec(format!("DROP TABLE IF EXISTS `{}`;", &table), ())?;
                guard.tables.push(table);
                guard.pool.prep_exec(a, ())?;
            } else if let Some(cap) = reg_views.captures(&a) {
                let view = cap[2].to_string();
                guard
                    .pool
                    .prep_exec(format!("DROP VIEW IF EXISTS `{}`;", &view), ())?;
                guard.views.push(view);
                guard.pool.prep_exec(a, ())?;
            } else {
                return Err(Error::InvalidDBSetup(format!(
                    "Expected table/view, found {}",
                    a
                )));
            }
        }
        Ok(guard)
    }

    /// Helper method to connect to the db
    fn connect_db() -> Pool {
        let password;
        let user = match option_env!("TEST_DB_USER") {
            Some(u) => {
                password = option_env!("TEST_DB_PW");
                u
            }
            None => {
                password = Some(TEST_PASSWORD);
                TEST_USER
            }
        };

        let mut builder = OptsBuilder::new();
        builder
            .ip_or_hostname(Some("foo"))
            .db_name(Some(TEST_DB))
            .user(Some(user))
            .pass(password)
            .tcp_port(TEST_PORT)
            .ip_or_hostname(Some(TEST_IP))
            .prefer_socket(false);
        let opts: Opts = builder.into();
        Pool::new_manual(POOL_MIN_CONN, POOL_MAX_CONN, opts).unwrap() // min 6, check_import_account_insert will deadlock otherwise
    }

    /// Helper method to setup a db connection
    /// the returned connection has a complete temporary table setup
    /// new connection retrieved from the pool can't interact with these
    fn setup_db() -> (PooledConn, CleanupGuard) {
        let pool = connect_db();
        let conn = pool.get_conn().unwrap();
        let guard = setup_tables(pool).unwrap();
        (conn, guard)
    }

    /// Create member struct
    fn create_member(name: &str, id: i32, exp: i32, contribution: i32) -> Member {
        Member {
            name: String::from(name),
            id,
            exp,
            contribution,
        }
    }

    /// Insert full membership with cause
    fn insert_full_membership(
        mut conn: &mut PooledConn,
        id: &i32,
        join: &NaiveDate,
        leave: &NaiveDate,
        cause: &str,
        kicked: bool,
    ) -> i32 {
        let nr = insert_membership(&mut conn, &id, join, Some(leave));
        insert_membership_cause(&mut conn, &nr, cause, kicked);
        nr
    }

    /// Insert membership, return nr on success
    fn insert_membership(
        conn: &mut PooledConn,
        id: &i32,
        join: &NaiveDate,
        leave: Option<&NaiveDate>,
    ) -> i32 {
        let mut stmt = conn
            .prepare("INSERT INTO `membership` (`id`,`from`,`to`) VALUES (?,?,?)")
            .unwrap();
        let res = stmt.execute((id, join, leave)).unwrap();
        res.last_insert_id() as i32
    }

    /// Insert open trial for id
    fn insert_trial(conn: &mut PooledConn, id: &i32, start: &NaiveDate) {
        let mut stmt = conn
            .prepare("INSERT INTO `member_trial` (`id`,`from`,`to`) VALUES (?,?,NULL)")
            .unwrap();
        stmt.execute((id, start)).unwrap();
    }

    /// Insert membership cause
    fn insert_membership_cause(conn: &mut PooledConn, nr: &i32, cause: &str, kicked: bool) {
        let mut stmt = conn
            .prepare("INSERT INTO `membership_cause` (`nr`,`kicked`,`cause`) VALUES (?,?,?)")
            .unwrap();
        let _ = stmt.execute((nr, kicked, cause)).unwrap();
    }

    /// insert key-value entry into settings table
    fn insert_settings(conn: &mut PooledConn, key: &str, value: &str) {
        let mut stmt = conn
            .prepare("INSERT INTO `settings` (`key`,`value`) VALUES(?,?)")
            .unwrap();
        let _ = stmt.execute((key, value)).unwrap();
    }

    /// Get first member_names entry for specified id, return (id,name,date,updated)
    fn get_member_name(
        conn: &mut PooledConn,
        id: &i32,
    ) -> (i32, String, NaiveDateTime, NaiveDateTime) {
        let mut stmt = conn
            .prepare(
                "SELECT `id`,`name`,`date`,`updated` FROM `member_names` WHERE `id` = ? LIMIT 1",
            )
            .unwrap();
        let mut result = stmt.execute((id,)).unwrap();
        let result = result.next().unwrap().unwrap();
        from_row(result)
    }

    /// Get member_addition for specified id return (id,name,vip,comment)
    fn get_member_addition(conn: &mut PooledConn, id: &i32) -> (i32, String, bool, String) {
        let mut stmt = conn.prepare("SELECT `id`,`name`,CAST(`vip` AS INT) as `vip`,`comment` FROM `member_addition` WHERE `id` = ?").unwrap();
        let mut result = stmt.execute((id,)).unwrap();
        let result = result.next().unwrap().unwrap();
        from_row(result)
    }

    /// Test import for account with existing comments
    #[test]
    fn check_import_account_insert_comment_existing() {
        let (_, guard) = setup_db();

        let date1 = NaiveDateTime::parse_from_str("2014-01-01 09:12:43", DATETIME_FORMAT).unwrap();
        let account = import::ImportMembership {
            name: String::from("Alptraum"),
            id: 9926942,
            vip: true,
            vname: String::from("Thomas"),
            comment: String::from("Ein Kommentar"),
            date_name: date1.clone(),
            memberships: Vec::new(),
        };

        let orig_name = "Current Name".to_string();
        let orig_comment = "original comment";
        let orig_vip = false;
        // insert comment into member_addition
        guard
            .pool
            .prep_exec(
                "INSERT INTO `member_addition` (`id`,`name`,`vip`,`comment`) VALUES (?,?,?,?)",
                (&account.id, &orig_name, &orig_vip, &orig_comment),
            )
            .unwrap();

        let comment = "stuff";

        let mut importer = ImportAccountInserter::new(&guard.pool, &comment).unwrap();
        assert_eq!((0, 0), importer.insert_account(&account).unwrap());

        let exp_comment = format!(
            "{}\n{}",
            orig_comment,
            importer.get_formated_comment(&account)
        );
        importer.commit().unwrap();

        // first insert, empty db, should succeed
        let mut conn = guard.pool.get_conn().unwrap();
        let (id, vname, vip, comment) = get_member_addition(&mut conn, &account.id);
        assert_eq!(account.id, id);
        assert_eq!(orig_name, vname);
        assert_eq!(orig_vip, vip);
        assert_eq!(exp_comment, comment); // expect original + imported comment
    }

    /// Test import account insertion
    #[test]
    fn check_import_account_insert() {
        let (_, guard) = setup_db();

        let comment = "stuff";
        let date1 = NaiveDateTime::parse_from_str("2014-01-01 09:12:43", DATETIME_FORMAT).unwrap();
        let account = import::ImportMembership {
            name: String::from("Alptraum"),
            id: 9926942,
            vip: true,
            vname: String::from("Thomas"),
            comment: String::from("Ein Kommentar"),
            date_name: date1.clone(),
            memberships: Vec::new(),
        };

        let mut importer = ImportAccountInserter::new(&guard.pool, &comment).unwrap();
        importer.insert_account(&account).unwrap();

        let exp_comment = importer.get_formated_comment(&account);
        importer.commit().unwrap();

        // first insert, empty db, should succeed
        let mut conn = guard.pool.get_conn().unwrap();
        let (id, vname, vip, comment) = get_member_addition(&mut conn, &account.id);
        assert_eq!(account.id, id);
        assert_eq!(account.vname, vname);
        assert_eq!(account.vip, vip);
        assert_eq!(exp_comment, comment);
        let (id, name, date, updated) = get_member_name(&mut conn, &account.id);
        assert_eq!(account.id, id);
        assert_eq!(account.name, name);
        assert_eq!(date1, date);
        assert_eq!(date1, updated);

        let mut importer = ImportAccountInserter::new(&guard.pool, &comment).unwrap();
        // existing entries now, should insert IGNORE
        importer.insert_account(&account).unwrap();
    }

    /// Test ImportAccountInserter creation
    #[test]
    fn check_import_account_init() {
        let (_, guard) = setup_db();
        ImportAccountInserter::new(&guard.pool, "").unwrap();
    }

    /// Test leave detection for member based on membership-entry
    /// (1-day membership)
    #[test]
    fn check_get_member_left_single_join() {
        let (mut conn, _guard) = setup_db();

        let date1 = NaiveDateTime::parse_from_str("2014-01-01 09:12:43", DATETIME_FORMAT).unwrap();
        let date2 = NaiveDateTime::parse_from_str("2014-01-02 12:34:45", DATETIME_FORMAT).unwrap();

        let id = 1234;
        let name = String::from("tester1234");
        let name_noise = "asc";

        let mut vec_t = Vec::with_capacity(1);
        vec_t.push(create_member(&name, id + 1, 2, 3));
        // insert open membership
        let ms_nr = insert_membership(&mut conn, &id, &date1.date(), None);

        // create member which joined on date2 (verify date1&2 are not interchanged
        // should not be report as left
        vec_t.push(create_member(name_noise, id + 2, 4, 6));
        insert_members(&mut conn, &vec_t, &date2).unwrap();

        let expected = LeftMember {
            id,
            name: None,
            membership_nr: Some(ms_nr),
        };

        let left = get_member_left(&mut conn, &date1, &date2).unwrap();
        assert_eq!(1, left.len());
        assert_eq!(expected, left[0]);
    }

    /// Test leave detection for member based on member-data
    #[test]
    fn check_get_member_left_single_data() {
        let (mut conn, _guard) = setup_db();

        let date1 = NaiveDateTime::parse_from_str("2014-01-01 09:12:43", DATETIME_FORMAT).unwrap();
        let date2 = NaiveDateTime::parse_from_str("2014-01-02 12:34:45", DATETIME_FORMAT).unwrap();

        let id = 1234;
        let name = String::from("tester1234");
        let name_noise = "asc";

        let mut vec_t = Vec::with_capacity(1);
        vec_t.push(create_member(&name, id.clone(), 2, 3));
        insert_members(&mut conn, &vec_t, &date1).unwrap();
        // insert open membership
        let ms_nr = insert_membership(&mut conn, &id, &date1.date(), None);

        // create member which joined on date2 (verify date1&2 are not interchanged)
        // should not be report as left
        vec_t.clear();
        vec_t.push(create_member(name_noise, id + 1, 4, 6));
        insert_members(&mut conn, &vec_t, &date2).unwrap();

        let expected = LeftMember {
            id,
            name: Some(name),
            membership_nr: Some(ms_nr),
        };

        let left = get_member_left(&mut conn, &date1, &date2).unwrap();
        assert_eq!(1, left.len());
        assert_eq!(expected, left[0]);
    }

    #[test]
    fn check_get_member_left_full() {
        let (mut conn, _guard) = setup_db();

        let time = NaiveTime::parse_from_str("00:00:01", "%H:%M:%S").unwrap();
        let date_test_1 = NaiveDate::parse_from_str("2014-03-05", DATE_FORMAT).unwrap();
        let date_test_2 = NaiveDate::parse_from_str("2014-03-06", DATE_FORMAT).unwrap();
        let datetime_test_1 = date_test_1.and_time(time);
        let datetime_test_2 = date_test_2.and_time(time);
        let date_noise_start = NaiveDate::parse_from_str("2014-03-01", DATE_FORMAT).unwrap();
        let date_noise_end = NaiveDate::parse_from_str("2014-03-10", DATE_FORMAT).unwrap();

        let mut offset = 10; // id counter

        let member_noise: Vec<Member> = (0..offset)
            .map(|x| create_member(&format!("tester {}", x), x, 500, 1))
            .collect();

        {
            // insert noise data of members
            let mut current = date_noise_start.clone();
            while current <= date_noise_end {
                insert_members(&mut conn, &member_noise, &current.and_time(time)).unwrap();
                current = current.succ();
            }
            for ref mem in member_noise {
                // open memberships
                insert_membership(&mut conn, &mem.id, &date_noise_start, None);
            }
        }

        let mut expected: HashMap<i32, LeftMember> = HashMap::new();

        {
            // member which has left based on data
            let name = format!("tester {}", offset);
            let data_member = create_member(&name, offset.clone(), 2, 3);

            let mut vec_t = Vec::new();
            vec_t.push(data_member);
            let mut current = date_noise_start.clone();
            while current < date_test_2 {
                insert_members(&mut conn, &vec_t, &current.and_time(time)).unwrap();
                current = current.succ();
            }

            // current ms
            let nr = insert_membership(&mut conn, &offset, &date_test_1, None);

            let left = LeftMember {
                id: offset.clone(),
                name: Some(name),
                membership_nr: Some(nr),
            };

            // insert some earlier memberships
            insert_full_membership(
                &mut conn,
                &left.id,
                &date_noise_start,
                &date_test_1.pred(),
                "asdf",
                true,
            );

            expected.insert(left.id.clone(), left);
        }
        offset += 1;
        {
            // member which has left based on join-data
            // no data in member table
            let nr = insert_membership(&mut conn, &offset, &date_test_1, None);

            let left = LeftMember {
                id: offset.clone(),
                name: None,
                membership_nr: Some(nr),
            };

            // insert some earlier memberships
            insert_full_membership(
                &mut conn,
                &left.id,
                &date_noise_start,
                &date_test_1.pred(),
                "asdf",
                true,
            );

            expected.insert(left.id.clone(), left);
        }
        //offset += 1;

        // test function
        let found = get_member_left(&mut conn, &datetime_test_1, &datetime_test_2).unwrap();

        assert_eq!(expected.len(), found.len());

        for m in found {
            assert_eq!(expected.get(&m.id), Some(&m));
        }
    }

    /// Check log insertion
    #[test]
    fn check_log_insert() {
        let (mut conn, _guard) = setup_db();
        let msg_insert = "asdf";
        log_message_opt(&mut conn, msg_insert).unwrap();
        log_message_opt(&mut conn, msg_insert).unwrap();
        log_message_opt(&mut conn, msg_insert).unwrap();

        let date_check: NaiveDate = Local::now().naive_local().date();

        let mut stmt = conn.prepare("SELECT date,msg FROM log").unwrap();

        let result = stmt.execute(()).unwrap();
        for row in result {
            let (date, msg): (NaiveDateTime, String) = from_row(row.unwrap());
            assert_eq!(date_check, date.date());
            assert_eq!(msg_insert, msg);
        }
    }

    /// Check date valid with data function
    #[test]
    fn check_date_for_data_test() {
        let date_valid: NaiveDate = NaiveDate::parse_from_str("2015-01-01", DATE_FORMAT).unwrap();
        let date_invalid: NaiveDate = NaiveDate::parse_from_str("2015-01-02", DATE_FORMAT).unwrap();
        let (mut conn, _guard) = setup_db();
        let datetime = date_valid.and_hms(10, 0, 0);
        {
            // setup valid date data
            let members: Vec<Member> = (0..5)
                .map(|x| create_member(&format!("tester {}", x), x, 500, 1))
                .collect();
            insert_members(&mut conn, &members, &datetime).unwrap();
        }
        assert_eq!(
            Some(datetime),
            check_date_for_data(&mut conn, date_valid).unwrap()
        );
        assert_eq!(None, check_date_for_data(&mut conn, date_invalid).unwrap());
    }

    #[test]
    fn read_string_setting_test() {
        let (mut conn, _guard) = setup_db();
        let key = "a";
        let value = String::from("b");
        insert_settings(&mut conn, key, &value);
        insert_settings(&mut conn, "aa", "bb");
        insert_settings(&mut conn, "", "c");
        assert_eq!(Some(value), read_string_setting(&mut conn, key).unwrap());
        assert_eq!(None, read_string_setting(&mut conn, "asdf").unwrap());
    }

    #[test]
    fn read_bool_setting_test() {
        let (mut conn, _guard) = setup_db();
        let key = "a";
        let key_2 = "ab";
        let key_3 = "abc";
        insert_settings(&mut conn, key, "1");
        insert_settings(&mut conn, key_2, "true");
        insert_settings(&mut conn, key_3, "c");
        assert_eq!(Some(true), read_bool_setting(&mut conn, key).unwrap());
        assert_eq!(Some(true), read_bool_setting(&mut conn, key_2).unwrap());
        assert_eq!(Some(false), read_bool_setting(&mut conn, key_3).unwrap());
        assert_eq!(None, read_bool_setting(&mut conn, "asdf").unwrap());
    }

    #[test]
    fn insert_member_leave_test() {
        let (mut conn, _guard) = setup_db();
        let msg = "my kick message";
        let id = 123;
        let join = Local::today().naive_local();
        let nr = insert_membership(&mut conn, &id, &join, None);
        let leave = Local::today().naive_local().succ();
        let trial = insert_member_leave(&mut conn, id, nr, leave, msg).unwrap();
        assert_eq!(trial, 0);
    }

    #[test]
    fn insert_member_leave_test_multiple() {
        let (mut conn, _guard) = setup_db();
        let msg = "my kick message";
        let id = 123;
        let join = Local::today().naive_local();
        insert_trial(&mut conn, &id, &join);
        insert_trial(&mut conn, &id, &join.succ());
        let nr = insert_membership(&mut conn, &id, &join, None);
        let leave = Local::today().naive_local().succ();
        let trial = insert_member_leave(&mut conn, id, nr, leave, msg).unwrap();
        assert_eq!(trial, 2);
    }

    #[test]
    fn insert_member_leave_test_override() {
        let (mut conn, _guard) = setup_db();
        let msg = "my kick message";
        let id = 123;
        let join = Local::today().naive_local();
        let nr = insert_full_membership(&mut conn, &id, &join, &join, "asd", true);
        insert_trial(&mut conn, &id, &join);
        let leave = Local::today().naive_local().succ();
        let trial = insert_member_leave(&mut conn, id, nr, leave, msg).unwrap();
        assert_eq!(trial, 1);
    }

    #[test]
    fn get_next_older_date_test() {
        let date_start: NaiveDate = NaiveDate::parse_from_str("2015-01-01", DATE_FORMAT).unwrap();

        let (mut conn, _guard) = setup_db();

        let members: Vec<Member> = (0..5)
            .map(|x| create_member(&format!("tester {}", x), x, 500, 1))
            .collect();
        let mut date_curr = date_start.clone();
        for i in 0..10 {
            date_curr = date_curr.succ();
            let datetime = date_curr.and_hms(10, i, 0);
            insert_members(&mut conn, &members, &datetime).unwrap();
        }

        let correct = date_curr; // 2015-01-11 //10:09:00

        // add data with missing flags
        for i in 0..2 {
            date_curr = date_curr.succ();
            let datetime = date_curr.and_hms(10, i, 0);
            insert_missing_entry(&datetime, &mut conn, true).unwrap();
            insert_members(&mut conn, &members, &datetime).unwrap();
        } //2015-01-13

        // go 1 days ahead (2 in total), no dataset there
        let start_test = date_curr.checked_add_signed(Duration::days(1)).unwrap();
        // 2015-01-14

        // create a gap
        date_curr = date_curr.checked_add_signed(Duration::days(30)).unwrap();

        for i in 1..7 {
            let datetime = date_curr.and_hms(10, i, 0);
            insert_members(&mut conn, &members, &datetime).unwrap();
            date_curr = date_curr.succ();
        }

        assert_eq!(
            NaiveDate::parse_from_str("2015-01-11", DATE_FORMAT).unwrap(),
            correct
        );
        assert_eq!(
            NaiveDate::parse_from_str("2015-01-14", DATE_FORMAT).unwrap(),
            start_test
        );

        assert_eq!(
            Some(correct.and_hms(10, 9, 0)),
            get_next_older_date(&mut conn, &start_test.and_hms(10, 0, 0), date_start).unwrap()
        );
        assert_eq!(
            Some(correct.and_hms(10, 9, 0)),
            get_next_older_date(&mut conn, &correct.succ().and_hms(10, 0, 0), correct).unwrap()
        );
        assert_eq!(
            Some(correct.and_hms(10, 9, 0)),
            get_next_older_date(&mut conn, &correct.succ().and_hms(10, 0, 0), date_start).unwrap()
        );
        assert_eq!(
            Some(date_curr.pred().pred().and_hms(10, 5, 0)),
            get_next_older_date(&mut conn, &date_curr.pred().and_hms(10, 0, 0), date_start)
                .unwrap()
        );
        assert_eq!(
            None,
            get_next_older_date(&mut conn, &correct.succ().and_hms(10, 0, 0), correct.succ())
                .unwrap()
        );
        assert_eq!(
            None,
            get_next_older_date(&mut conn, &correct.succ().and_hms(10, 0, 0), start_test).unwrap()
        );
    }

    /// Test connection
    #[test]
    fn connection_test() {
        connect_db();
    }

    /// Test table setup
    #[test]
    fn setup_tables_test() {
        let (_conn, _guard) = setup_db();
    }

    /// Setup insert members twice with the same datetime
    /// This test should fail
    #[test]
    #[should_panic]
    fn insert_members_duplicate_test() {
        let time: NaiveDateTime = Local::now().naive_local();
        let (mut conn, _guard) = setup_db();
        let members: Vec<Member> = (0..5)
            .map(|x| create_member(&format!("tester {}", x), x, 500, 1))
            .collect();
        insert_members(&mut conn, &members, &time).unwrap();
        let members_2: Vec<Member> = (0..5)
            .map(|x| create_member(&format!("tester {}", x), x, 500 * x, 1 * x))
            .collect();
        insert_members(&mut conn, &members_2, &time).unwrap();
    }

    /// Test member insertion
    #[test]
    fn insert_members_test() {
        let mut time: NaiveDateTime = Local::now().naive_local();
        let (mut conn, _guard) = setup_db();
        let members: Vec<Member> = (0..5)
            .map(|x| create_member(&format!("tester {}", x), x, 500, 1))
            .collect();
        insert_members(&mut conn, &members, &time).unwrap();
        time = time.checked_add_signed(Duration::seconds(1)).unwrap();
        let members_2: Vec<Member> = (0..5)
            .map(|x| create_member(&format!("tester {}", x), x, 500, 1))
            .collect();
        insert_members(&mut conn, &members_2, &time).unwrap();
    }

    /// Test temporary date lookup table creation
    #[test]
    fn create_temp_date_table_test() {
        let (mut conn, _guard) = setup_db();
        create_temp_date_table(&mut conn, TABLE_MISSING_DATES).unwrap();
    }

    /// Test missing entry insertion
    #[test]
    fn insert_missing_entry_test() {
        let time: NaiveDateTime = Local::now().naive_local();
        let (mut conn, _guard) = setup_db();
        insert_missing_entry(&time, &mut conn, true).unwrap();
        let time = time.checked_add_signed(Duration::seconds(5)).unwrap();
        insert_missing_entry(&time, &mut conn, false).unwrap();
    }

    #[test]
    fn get_min_max_date_test() {
        let (mut conn, _guard) = setup_db();
        // insert data for three dates
        let data: Vec<Clan> = (0..3)
            .map(|x| Clan {
                members: x,
                wins: x as u16,
                losses: x as u16,
                draws: x as u16,
            })
            .collect();
        let parse_fmt = "%Y-%m-%d %H:%M:%S";
        let min = NaiveDateTime::parse_from_str("2015-09-05 23:56:04", parse_fmt).unwrap();
        let max = NaiveDateTime::parse_from_str("2017-07-02 08:03:17", parse_fmt).unwrap();
        let third = NaiveDateTime::parse_from_str("2016-05-01 21:05:08", parse_fmt).unwrap();

        insert_clan_update(&mut conn, &data[0], &min).unwrap();
        insert_clan_update(&mut conn, &data[1], &max).unwrap();
        insert_clan_update(&mut conn, &data[2], &third).unwrap();

        let (min_r, max_r) = get_min_max_date(&mut conn).unwrap();
        assert_eq!(min.date(), min_r);
        assert_eq!(max.date(), max_r);
    }

    #[test]
    fn get_missing_dates_test() {
        let (mut conn, _guard) = setup_db();

        let data: Vec<Clan> = (0..2)
            .map(|x| Clan {
                members: x,
                wins: x as u16,
                losses: x as u16,
                draws: x as u16,
            })
            .collect();

        let mut dates: Vec<NaiveDate> = Vec::new();

        let time = NaiveTime::from_hms_milli(12, 34, 56, 789);
        let parse_fmt = "%Y-%m-%d";

        let start = NaiveDate::parse_from_str("2015-09-05", parse_fmt).unwrap();

        dates.push(start.succ());
        for _ in 0..3 {
            let date = dates.last().unwrap().succ();
            dates.push(date);
        }

        insert_clan_update(&mut conn, &data[0], &start.and_time(time)).unwrap();
        insert_clan_update(
            &mut conn,
            &data[1],
            &dates.last().unwrap().succ().and_time(time),
        )
        .unwrap();

        let found = get_missing_dates(&mut conn).unwrap();

        assert_eq!(dates.len(), found.len());
        for x in 0..dates.len() {
            assert_eq!(dates[x], found[x]);
        }
    }

    /// Test clan insertion
    #[test]
    fn insert_clan_test() {
        let time: NaiveDateTime = Local::now().naive_local();
        let (mut conn, _guard) = setup_db();
        let clan = Clan {
            members: 4,
            wins: 1,
            losses: 2,
            draws: 3,
        };
        insert_clan_update(&mut conn, &clan, &time).unwrap();
    }

    /// Verify REGEX_VIEW and also behavior of regex captures
    #[test]
    fn test_view_regex() {
        let reg = regex::Regex::new(REGEX_VIEW).unwrap();

        {
            let test_1 = "CREATE OR REPLACE VIEW `ranked` AS";
            let cap_1 = reg.captures(test_1).unwrap();
            assert_eq!("ranked", &cap_1[2]);
        }
        {
            let test_2 = "CREATE OR REPLACE VIEW `ranked` AS";
            let cap_2 = reg.captures(test_2).unwrap();
            assert_eq!("ranked", &cap_2[2]);
        }
    }

    #[test]
    fn test_create_temp_ts3_table() {
        let (mut conn, _guard) = setup_db();
        create_temp_ts3_table(&mut conn, "temp_table").unwrap();
    }

    #[test]
    fn test_update_unknown_ts_ids() {
        let (mut conn, _guard) = setup_db();
        {
            // insert some ids into member ts relation
            let mut stmt = conn
                .prepare("INSERT INTO `ts_relation` (`client_id`,`id`) VALUES (?,1)")
                .unwrap();
            for i in 0..5 {
                stmt.execute((i,)).unwrap();
            }
        }

        {
            // insert some "old" values into the table
            let mut stmt = conn
                .prepare("INSERT INTO `unknown_ts_ids` (`client_id`) VALUES (?)")
                .unwrap();
            for i in 0..10 {
                stmt.execute((i,)).unwrap();
            }
        }

        update_unknown_ts_ids(&mut conn, &vec![2, 3, 4, 5, 6]).unwrap();

        let res = conn
            .prep_exec(
                "SELECT client_id FROM `unknown_ts_ids` ORDER BY client_id",
                (),
            )
            .unwrap();
        let data: Vec<isize> = res
            .map(|row| {
                let id = from_row(row.unwrap());
                id
            })
            .collect();

        assert_eq!(data, vec![5, 6]);
    }
}
