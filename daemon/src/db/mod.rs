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

//! Database functions
use prelude::*;
use regex;

mod prelude {
    pub use crate::*;
    pub use mysql::prelude::*;
    pub use mysql::{Opts, OptsBuilder, Pool, PooledConn, TxOpts};
    pub const DATE_FORMAT: &str = "%Y-%m-%d";
    pub const DATETIME_FORMAT: &str = "%Y-%m-%d %H:%M:%S";
}

const POOL_MIN_CONN: usize = 1; // minimum amount of running connection per pool
const POOL_MAX_CONN: usize = 100; // maximum amount of running connections per pool
pub mod crawler;
pub mod import;
pub mod ts;

#[cfg(test)]
mod testing;

/// Create a new db pool with fixed min-max connections
pub fn new(
    address: String,
    port: u16,
    user: String,
    password: Option<String>,
    db: String,
) -> Result<Pool> {
    let opts: Opts = OptsBuilder::new()
        .ip_or_hostname(Some(address))
        .db_name(Some(db))
        .tcp_port(port)
        .prefer_socket(false)
        .user(Some(user))
        .pass(password)
        .into();
    let pool = Pool::new_manual(POOL_MIN_CONN, POOL_MAX_CONN, opts)?;
    Ok(pool)
}

/// Insert log message for current timestamp.
/// Does not omit errors, logging them via error!().
pub fn log_message(conn: &mut PooledConn, message: &str) {
    info!("db-log: {}", message);
    match log_message_opt(conn, message) {
        Ok(_) => (),
        Err(e) => error!(
            "Unable to insert log message to db: \"{}\" Error: {}",
            message, e
        ),
    }
}

/// Insert log message for current timestamp
pub fn log_message_opt(conn: &mut PooledConn, message: &str) -> Result<()> {
    conn.exec_drop(
        "INSERT INTO `log` (`date`,`msg`) VALUES (NOW(),?)",
        (message,),
    )?;
    Ok(())
}

/// Read a settings entry consisting of multiple values, comma separated
///
/// Note that a from() conversion for crate::Error has to exist for the from_str implemention error type
pub fn read_list_setting<T: ::std::str::FromStr>(
    conn: &mut PooledConn,
    key: &str,
) -> Result<Option<Vec<T>>>
where
    crate::error::Error: std::convert::From<<T as std::str::FromStr>::Err>,
{
    let raw = read_string_setting(conn, key)?;
    if let Some(v) = raw {
        let values: Vec<T> = v
            .split(',')
            .map(|v| T::from_str(v.trim()))
            .collect::<::std::result::Result<Vec<_>, _>>()?;
        return Ok(Some(values));
    }
    Ok(None)
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
    let v = conn.exec_first_opt("SELECT `value` FROM settings WHERE `key` = ?", (key,))?;
    v.transpose().map_err(From::from)
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

/// Initialize tables on first try, error if existing
/// Undos everything on failure
pub fn init_tables(pool: &Pool) -> Result<()> {
    let tables = get_db_create_sql();

    let mut transaction = pool.start_transaction(TxOpts::default())?;

    for table in tables {
        transaction.query_drop(table)?;
    }

    transaction.commit()?;
    Ok(())
}

/// Get database setup sql
/// Returns a vector of table setup sql
fn get_db_create_sql() -> Vec<String> {
    let raw_sql = include_str!("../../setup.sql");

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
    use chrono::naive::NaiveDateTime;
    use chrono::offset::Local;

    use super::testing::*;

    /// Check log insertion
    #[test]
    fn check_log_insert() {
        let (mut conn, _guard) = setup_db();
        let msg_insert = "asdf";
        log_message_opt(&mut conn, msg_insert).unwrap();
        log_message_opt(&mut conn, msg_insert).unwrap();
        log_message_opt(&mut conn, msg_insert).unwrap();

        let date_check: NaiveDate = Local::now().naive_local().date();

        let res = conn.query_iter("SELECT date,msg FROM log").unwrap();

        for row in res {
            let (date, msg): (NaiveDateTime, String) = from_row(row.unwrap());
            assert_eq!(date_check, date.date());
            assert_eq!(msg_insert, msg);
        }
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
}
