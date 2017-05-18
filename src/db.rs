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
use mysql::conn::pool::{PooledConn,Pool};
use mysql::conn::{Opts, OptsBuilder};
use chrono::naive::datetime::NaiveDateTime;

use error::Error;

use Member;
use Clan;

const POOL_MIN_CONN: usize = 0; // minimum amount of running connection per pool
const POOL_MAX_CONN: usize = 2; // maximum amount of running connections per pool

/// Create a new db pool with fixed min-max connections
pub fn new(address: String, port: u16, user: String, password: String, db: String) -> Result<Pool,Error> {
    let mut builder = OptsBuilder::new();
    builder.ip_or_hostname(Some(address))
    .db_name(Some(db))
    .tcp_port(port)
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
        let mut stmt = try!(conn.prepare("INSERT IGNORE INTO `member_names` (`id`,`name`,`date`) VALUES (?,?,?)"));
        for member in members {
            try!(stmt.execute((&member.id,&member.name,timestamp)));
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

#[cfg(test)]
mod test {
    use super::*;
    use chrono::naive::datetime::NaiveDateTime;
    use chrono::offset::local::Local;
    use chrono::duration::Duration;
    
    use mysql::conn::pool::{PooledConn,Pool};
    
    use error::Error;
    use Member;
    use Clan;
    
    use regex;
    
    const TEST_USER: &'static str = "root";
    const TEST_PASSWORD: &'static str = "";
    const TEST_DB: &'static str = "test";
    
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
        new("localhost".into(), 3306,TEST_USER.into(),TEST_PASSWORD.into(),TEST_DB.into()).unwrap()
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
        time = time.checked_add(Duration::seconds(1)).unwrap();
        let members_2: Vec<Member> = (0..5).map(|x| create_member(&format!("tester {}",x),x,500,1)).collect();
        insert_members(&mut conn,&members_2,&time).unwrap();
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