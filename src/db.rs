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
use mysql::{from_row_opt,Row,Opts, OptsBuilder,Pool,PooledConn};
use chrono::naive::NaiveDateTime;
use chrono::naive::NaiveDate;

use error::Error;

use Member;
use Clan;

const POOL_MIN_CONN: usize = 0; // minimum amount of running connection per pool
const POOL_MAX_CONN: usize = 2; // maximum amount of running connections per pool
const TABLE_MISSING_DATES: &'static str = "t_missingdates"; // temporary table used to store missing dates

/// Create a new db pool with fixed min-max connections
pub fn new(address: String, port: u16, user: String, password: String, db: String) -> Result<Pool,Error> {
    let mut builder = OptsBuilder::new();
    builder.ip_or_hostname(Some(address))
    .db_name(Some(db))
    .tcp_port(port)
    .prefer_socket(false)
    .user(Some(user))
    .pass(Some(password));
    let opts: Opts = builder.into();
    let pool = try!(Pool::new_manual(POOL_MIN_CONN,POOL_MAX_CONN,opts));
    Ok(pool)
}

/// Insert a Vec of members under the given Timestamp
/// This does affect table member and member_names
pub fn insert_members(conn: &mut PooledConn, members: &Vec<Member>, timestamp: &NaiveDateTime) -> Result<(),Error> {
    {
        let mut stmt = try!(conn.prepare("INSERT INTO `member` (`id`,`date`,`exp`,`cp`) VALUES (?,?,?,?)"));
        for member in members {
            try!(stmt.execute((&member.id,timestamp,&member.exp,&member.contribution)));
        }
    }
    {
        let mut stmt = try!(conn.prepare("INSERT IGNORE INTO `member_names` (`id`,`name`,`date`,`updated`) VALUES (?,?,?,?)"));
        for member in members {
            try!(stmt.execute((&member.id,&member.name,timestamp,timestamp)));
        }
    }
    {
        let mut stmt = try!(conn.prepare("UPDATE `member_names` SET `updated` = ? WHERE `id` = ? AND `name` = ?"));
        for member in members {
            try!(stmt.execute((timestamp,&member.id,&member.name)));
        }
    }
    Ok(())
}

/// Insert clan struct into clan-table
pub fn insert_clan_update(conn: &mut PooledConn,clan: &Clan, timestamp: &NaiveDateTime) -> Result<(),Error> {
    let mut stmt = try!(conn.prepare("INSERT INTO `clan` (`date`,`wins`,`losses`,`draws`,`members`) VALUES (?,?,?,?,?)"));
    try!(stmt.execute((timestamp,clan.wins,clan.losses,clan.draws,clan.members)));
    Ok(())
}

/// Insert datetime into missing entry table
pub fn insert_missing_entry(datetime: &NaiveDateTime,conn: &mut PooledConn) -> Result<(),Error> {
    let mut stmt = conn.prepare("INSERT INTO `missing_entries` (`date`) VALUES (?)")?;
    stmt.execute((datetime,))?;
    Ok(())
}

/// Retrieves missing dates in the db
/// for which no entri(es exist
pub fn get_missing_dates(conn: &mut PooledConn) -> Result<Vec<NaiveDate>,Error> {
    // create date lookup table
    create_temp_date_table(conn,"t_dates")?;
    create_temp_date_table(conn,TABLE_MISSING_DATES)?;
    
    let (min,max) = get_min_max_date(conn)?;
    debug!("max:{} min:{}",max,min);
    // {} required, stmt lives too long
    {// create date lookup table
        let mut stmt = conn.prepare("INSERT INTO `t_dates` (`date`) VALUES (?)")?;
        let mut current = min.succ();
        let mut i = 0;
        while current != max {
            stmt.execute((current,))?;
            current = current.succ();
            i += 1;
        }
        debug!("lookup table size: {}",i);
    }
    {// get missing dates not already stored
        // t_dates left join (clan JOIN member) left join missing_entries
        // where right = null
        // using datetime as date, won't match otherwise
        let mut stmt = conn.prepare(format!("
        INSERT INTO `{}` (`date`)
        SELECT t0.`date` FROM `t_dates` t0 
        LEFT JOIN (
            SELECT t2.date FROM `clan` t2 
            JOIN `member` t3 ON DATE(t2.date) = DATE(t3.date)
        ) as t1
        ON t0.date = DATE(t1.date)
        LEFT JOIN `missing_entries` t4
            ON t0.date = DATE(t4.date)
        WHERE t1.date IS NULL
        AND t4.date IS NULL",TABLE_MISSING_DATES))?;
        
        stmt.execute(())?;
    }
    
    let mut dates: Vec<NaiveDate> = Vec::new();
    {// now retrieve missing dates for user information
        let mut stmt = conn.prepare(format!(
            "SELECT date FROM `{}`",TABLE_MISSING_DATES))?;
        
        for row in stmt.execute(())? {
            dates.push(row?.take_opt("date").ok_or(Error::NoValue("date"))??);
        }
    }
    Ok(dates)
}

/// Inserts TABLE_MISSING_DATES into `missing_entries`
pub fn insert_missing_dates(conn: &mut PooledConn) -> Result<(),Error> {
    let mut stmt = conn.prepare(format!("
        INSERT INTO `missing_entries` (`date`)
        SELECT `date` FROM `{}`",TABLE_MISSING_DATES))?;
    
    stmt.execute(())?;
    Ok(())
}

/// Creates a temporary, single date column table with the specified name
fn create_temp_date_table(conn: &mut PooledConn, tbl_name: &'static str) -> Result<(),Error> {
    let mut stmt = conn.prepare(format!("CREATE TEMPORARY TABLE `{}` (
                        `date` datetime NOT NULL PRIMARY KEY
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                        ",tbl_name))?;
    stmt.execute(())?;
    Ok(())
}

/// Retrieves the oldest date in of `clan` & `member` combined
/// Returns (min,max) dates as String
fn get_min_max_date(conn: &mut PooledConn) -> Result<(NaiveDate,NaiveDate),Error> {
    // full outer join to get all
    let mut stmt = conn.prepare("SELECT MIN(`date`) as min, MAX(`date`) as max FROM (
        SELECT t11.date FROM clan t11
        LEFT JOIN member t12 ON t11.date = t12.date
        UNION
        SELECT t22.date FROM clan t21
        RIGHT JOIN member t22 ON t21.date = t22.date
    ) as T")?;
    let mut result = stmt.execute(())?;
    let row: Row = result.next().ok_or(Error::NoValue("empty result"))??;
    let values = from_row_opt(row)?;
    Ok(values)
}

#[cfg(test)]
mod test {
    use super::*;
    use chrono::naive::NaiveDateTime;
    use chrono::offset::Local;
    use chrono::Duration;
    
    use mysql::{PooledConn,Pool};
    
    use error::Error;
    use Member;
    use Clan;
    
    use regex;
    
    const TEST_USER: &'static str = "root";
    const TEST_PASSWORD: &'static str = "root";
    const TEST_DB: &'static str = "test";
    const TEST_PORT: u16 = 3306;
    
    /// Get database setup sql
    /// Returns a vector of table setup sql
    fn get_db_create_sql<'a>() -> Vec<String> {
        let raw_sql = include_str!("../setup.sql");
        
        let reg = regex::Regex::new(r"(/\*(.|\s)*?\*/)").unwrap(); // https://regex101.com/r/bG6aF2/6, replace `\/` with `/`
        let raw_sql = reg.replace_all(raw_sql, "");
        
        let raw_sql = raw_sql.replace("\n","");
        let raw_sql = raw_sql.replace("\r","");
        
        debug!("\n\nSQL: {}\n\n",raw_sql);
        
        let split_sql:Vec<String> = raw_sql.split(";").filter_map(|x| // split at `;`, filter_map on iterator
            if x != "" { // check if it's an empty group (last mostly)
                Some(x.to_owned()) // &str to String
            } else {
                None
            }
            ).collect(); // collect back to vec
        
        debug!("\n\nGroups: {:?}\n\n",split_sql);
        
        split_sql
    }
    
    /// Setup db tables, does crash if tabes are existing
    /// Created as temporary if specified (valid for the current connection)
    /// If a temporary creation is required no permanent table of the same name
    /// can exist
    fn setup_tables(conn: &mut PooledConn, temp: bool) -> Result<(),Error> {
        let tables = get_db_create_sql();
        for a in tables {
            conn.query(
                if temp {
                    a.replace("CREATE TABLE","CREATE TEMPORARY TABLE")
                } else {
                    a
                }
            ).unwrap();
        }
        Ok(())
    }
    
    /// Helper method to connect to the db
    fn connect_db() -> Pool {
        new("localhost".into(), TEST_PORT,TEST_USER.into(),TEST_PASSWORD.into(),TEST_DB.into()).unwrap()
    }
    
    /// Helper method to setup a db connection
    /// having only temporary tables on the returned connection
    fn setup_db() -> (Pool,PooledConn) {
        let pool = connect_db();
        let mut conn = pool.get_conn().unwrap();
        setup_tables(&mut conn,true).unwrap();
        (pool,conn)
    }
    
    /// Create member struct
    fn create_member(name: &str, id: u32,exp: u32, contribution: u32) -> Member {
        Member {
            name: String::from(name),
            id: id,
            exp: exp,
            contribution: contribution
        }
    }
    
    /// Test connection
    #[test]
    fn connection_test() {
        connect_db();
    }
    
    /// Test table setup
    #[test]
    fn setup_tables_test() {
        let (_,_) = setup_db();
    }
    
    /// Setup insert members twice with the same date
    /// This test should fail
    #[test]
    #[should_panic]
    fn insert_members_duplicate_test() {
        let time: NaiveDateTime = Local::now().naive_local();
        let (_,mut conn) = setup_db();
        let members: Vec<Member> = (0..5).map(|x| create_member(&format!("tester {}",x),x,500,1)).collect();
        insert_members(&mut conn,&members,&time).unwrap();
        let members_2: Vec<Member> = (0..5).map(|x| create_member(&format!("tester {}",x),x,500*x,1*x)).collect();
        insert_members(&mut conn,&members_2,&time).unwrap();
    }
    
    /// Test member insertion
    #[test]
    fn insert_members_test() {
        let mut time: NaiveDateTime = Local::now().naive_local();
        let (_,mut conn) = setup_db();
        let members: Vec<Member> = (0..5).map(|x| create_member(&format!("tester {}",x),x,500,1)).collect();
        insert_members(&mut conn,&members,&time).unwrap();
        time = time.checked_add_signed(Duration::seconds(1)).unwrap();
        let members_2: Vec<Member> = (0..5).map(|x| create_member(&format!("tester {}",x),x,500,1)).collect();
        insert_members(&mut conn,&members_2,&time).unwrap();
    }
    
    /// Test temporary date lookup table creation
    #[test]
    fn create_temp_date_table_test() {
        let (_,mut conn) = setup_db();
        create_temp_date_table(&mut conn, TABLE_MISSING_DATES).unwrap();
    }
    
    /// Test missing entry insertion
    #[test]
    fn insert_missing_entry_test() {
        let time: NaiveDateTime = Local::now().naive_local();
        let (_,mut conn) = setup_db();
        insert_missing_entry(&time,&mut conn).unwrap();
    }
    
    /// Test clan insertion
    #[test]
    fn insert_clan_test() {
        let time: NaiveDateTime = Local::now().naive_local();
        let (_,mut conn) = setup_db();
        let clan = Clan {
            members: 4,
            wins: 1,
            losses: 2,
            draws: 3
        };
        insert_clan_update(&mut conn,&clan, &time).unwrap();
    }
}
