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

//! Member/Exp/CP crawler data functions
use super::prelude::*;
use chrono::naive::NaiveDate;
use chrono::naive::NaiveDateTime;
const TABLE_MISSING_DATES: &str = "t_missingdates"; // temporary table used to store missing dates

/// Insert a Vec of members under the given Timestamp
/// This does affect table member and member_names
pub fn insert_members(
    conn: &mut PooledConn,
    members: &[Member],
    timestamp: &NaiveDateTime,
) -> Result<()> {
    conn.exec_batch(
        "INSERT INTO `member` (`id`,`date`,`exp`,`cp`) VALUES (?,?,?,?)",
        members
            .iter()
            .map(|m| (m.id, timestamp, m.exp, m.contribution)),
    )?;
    conn.exec_batch(
        "INSERT IGNORE INTO `member_names` (`id`,`name`,`date`,`updated`) VALUES (?,?,?,?)",
        members
            .iter()
            .map(|m| (m.id, &m.name, timestamp, timestamp)),
    )?;
    conn.exec_batch(
        "UPDATE `member_names` SET `updated` = ? WHERE `id` = ? AND `name` = ?",
        members.iter().map(|m| (timestamp, m.id, &m.name)),
    )?;
    Ok(())
}

/// Insert clan struct into clan-table
pub fn insert_clan_update(
    conn: &mut PooledConn,
    clan: &Clan,
    timestamp: &NaiveDateTime,
) -> Result<()> {
    conn.exec_drop(
        "INSERT INTO `clan` (`date`,`wins`,`losses`,`draws`,`members`) VALUES (?,?,?,?,?)",
        (timestamp, clan.wins, clan.losses, clan.draws, clan.members),
    )?;
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
    conn.exec_drop(
        "INSERT INTO `missing_entries` (`date`,`member`) VALUES (?,?)",
        (datetime, missing_member),
    )?;
    Ok(())
}

/// Insert missing names of accounts previously unknown
pub fn insert_missing_names(
    conn: &mut PooledConn,
    names: &[(AccountID, String)],
    time: &NaiveDateTime,
) -> Result<()> {
    conn.exec_batch(
        "INSERT IGNORE INTO `member_names` (`id`,`name`,`date`,`updated`) VALUES (?,?,?,?)",
        names.iter().map(|(id, name)| (id, name, time, time)),
    )?;
    Ok(())
}

/// Get account IDs which are system relevant but have no account name entry at all
pub fn get_missing_name_ids(conn: &mut PooledConn) -> Result<Vec<AccountID>> {
    let accounts = conn.query_map(
        "SELECT DISTINCT(id) FROM `membership`
        WHERE id NOT IN (SELECT id FROM `member_names`)
    UNION
    SELECT id_sec as id FROM second_acc WHERE id NOT IN (SELECT id FROM `member_names`)",
        |id| id,
    )?;
    Ok(accounts)
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
        let stmt = conn.prep("INSERT INTO `t_dates` (`date`) VALUES (?)")?;
        let mut current = min.succ();
        let mut i = 0;
        while current != max {
            conn.exec_drop(&stmt, (current,))?;
            current = current.succ();
            i += 1;
            if i % step == 0 {
                info!("{}%", i * 100 / days);
            }
        }
        debug!("lookup table size: {}", i);
    }
    // get missing dates not already stored
    // t_dates left join (clan JOIN member) left join missing_entries
    // where right = null
    // using datetime as date, won't match otherwise
    conn.query_drop(format!(
        "INSERT INTO `{}` (`date`)
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

    // now retrieve missing dates for user information
    let dates: Vec<NaiveDate> = conn.query_map(
        format!(
            "SELECT date FROM `{}` order by date ASC",
            TABLE_MISSING_DATES
        ),
        |date| date,
    )?;
    Ok(dates)
}

/// Inserts TABLE_MISSING_DATES into `missing_entries`
pub fn insert_missing_dates(conn: &mut PooledConn) -> Result<()> {
    conn.query_drop(format!(
        "INSERT INTO `missing_entries` (`date`)
        SELECT `date` FROM `{}`",
        TABLE_MISSING_DATES
    ))?;
    Ok(())
}

/// Retrieves the oldest & newest date `clan` & `member` table combined
/// Returns (min,max) dates as String
fn get_min_max_date(conn: &mut PooledConn) -> Result<(NaiveDate, NaiveDate)> {
    // full outer join to get all
    let values = conn.query_first_opt(
        "SELECT MIN(`date`) as min, MAX(`date`) as max FROM (
        SELECT t11.date FROM clan t11
        LEFT JOIN member t12 ON t11.date = t12.date
        UNION
        SELECT t22.date FROM clan t21
        RIGHT JOIN member t22 ON t21.date = t22.date
    ) as T",
    )?;
    Ok(values.ok_or(Error::NoValue("empty result"))??)
}

/// Check whether date has data and is thus valid in member table
/// Returns datetime if correct
pub fn check_date_for_data(
    conn: &mut PooledConn,
    date: NaiveDate,
) -> Result<Option<NaiveDateTime>> {
    let res = conn.exec_first_opt(
        "SELECT `date` FROM member m
        WHERE m.date LIKE ? LIMIT 1",
        (format!("{}%", date.format(DATE_FORMAT)),),
    )?;
    match res {
        Some(Err(e)) => Err(e.into()),
        None => Ok(None),
        Some(Ok(v)) => Ok(Some(v)),
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
    // TODO: we can get a null-response, probably doing a bad join
    // so val is from type Mabye-Row(Maybe-Null-Value<NaiveDateTime>)
    let val: Option<Option<NaiveDateTime>> = conn.exec_first(
        "SELECT MAX(m.`date`) as `date` FROM member m
        LEFT OUTER JOIN missing_entries mi
            ON ( DATE(m.date) = DATE(mi.date) AND mi.member = true)
        WHERE m.date < ? AND m.date >= ? AND mi.date IS NULL",
        (
            date.format(DATETIME_FORMAT).to_string(),
            format!("{}%", min.format(DATE_FORMAT)),
        ),
    )?;
    Ok(val.flatten())
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
    let res = conn.exec_map(
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
        params! {
            // do not use datetime directly, as milliseconds will be given
            // as there is no match for millseconds precise datetimes
            "datetime1" => date1.format(DATETIME_FORMAT).to_string(),
            "datetime2" => date2.format(DATETIME_FORMAT).to_string(),
            "date1" => date1.date(),
        },
        |(name, id, membership_nr)| LeftMember {
            id,
            name,
            membership_nr,
        },
    )?;
    Ok(res)
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
) -> Result<u64> {
    let trial_affected;
    conn.exec_drop(
        "UPDATE `membership` SET `to` = ? WHERE `nr` = ?",
        (date_leave, ms_nr),
    )?;
    {
        let mut result = conn.exec_iter(
            "UPDATE `member_trial` SET `to` = ? WHERE `id` = ?",
            (date_leave, id),
        )?;
        trial_affected = result
            .next_set()
            .ok_or(Error::NoValue("No result"))??
            .affected_rows();
    }
    conn.exec_drop("INSERT INTO `membership_cause` (`nr`,`kicked`,`cause`) VALUES(?,?,?) ON DUPLICATE KEY UPDATE `kicked` = VALUES(`kicked`), `cause` = VALUES(`cause`)",(ms_nr, false, cause))?;
    Ok(trial_affected)
}

/// Creates a temporary, single date column table with the specified name
fn create_temp_date_table(conn: &mut PooledConn, tbl_name: &'static str) -> Result<()> {
    conn.query_drop(format!(
        "CREATE TEMPORARY TABLE `{}` (
        `date` datetime NOT NULL PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ",
        tbl_name
    ))?;
    Ok(())
}

#[cfg(test)]
mod test {
    use super::*;
    use db::testing::*;
    use std::collections::HashMap;

    /// Test temporary date lookup table creation
    #[test]
    fn create_temp_date_table_test() {
        let (mut conn, _guard) = setup_db();
        create_temp_date_table(&mut conn, TABLE_MISSING_DATES).unwrap();
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
            .map(|x| create_member(&format!("tester {}", x), x, (500 * x).into(), 1 * x))
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

    #[test]
    fn get_missing_name_ids_test() {
        let time: NaiveDateTime = Local::now().naive_local();
        let (mut conn, mut _guard) = setup_db();
        insert_random_membership(&mut conn, 1, 2);
        insert_random_membership(&mut conn, 2, 1);
        insert_random_membership(&mut conn, 3, 1);
        insert_random_membership(&mut conn, 4, 1);
        let mem = vec![
            create_member(&format!("tester {}", 2), 2, 500, 1),
            create_member(&format!("tester {}", 4), 4, 500, 1),
        ];
        insert_members(&mut conn, &mem, &time).unwrap();

        let v = get_missing_name_ids(&mut conn).unwrap();
        assert_eq!(v, vec![1, 3]);
    }

    #[test]
    fn insert_missing_names_test() {
        let time: NaiveDateTime = Local::now().naive_local();
        let (mut conn, _guard) = setup_db();
        let mut names = vec![
            (1, "asd".to_string()),
            (4, "def".to_string()),
            (5, "asdasd".to_string()),
        ];
        insert_missing_names(&mut conn, &names, &time).unwrap();

        let names_r = conn
            .query_map(
                "SELECT `id`,`name` FROM `member_names` ORDER BY id",
                |(id, name): (i32, String)| (id, name),
            )
            .unwrap();

        assert_eq!(names, names_r);
    }
}
