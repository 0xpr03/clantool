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

//! TS data handling functions

use super::prelude::*;
use chrono::naive::NaiveDate;

/// Update unknown_ts_ids table based on member clients  
/// Handles known member client_id filtering  
/// Allows doubled group IDs in member_clients
pub fn update_unknown_ts_ids(conn: &mut PooledConn, member_clients: &[usize]) -> Result<()> {
    let t_table = "t_member_clients";
    create_temp_ts3_table(conn, t_table)?;
    // insert every member client id into temp table, ignore multi-group clients
    conn.exec_batch(
        format!("INSERT IGNORE INTO `{}` (`client_id`) VALUES (?)", t_table),
        member_clients.iter().map(|m| (m,)),
    )?;
    // filter everything out that has a member assigned
    conn.query_drop(format!("DELETE FROM t1 USING `{}` t1 INNER JOIN `ts_relation` t2 ON ( t1.client_id = t2.client_id )",t_table))?;
    // truncate unknown ts ids table
    conn.query_drop("TRUNCATE `unknown_ts_ids`")?;
    // replace with correct values
    conn.query_drop(format!(
        "INSERT INTO `unknown_ts_ids` SELECT * FROM `{}`",
        t_table
    ))?;

    // cleanup temporary table
    conn.query_drop(format!("DROP TEMPORARY TABLE `{}`", t_table))?;
    Ok(())
}

/// Creates a temporary, single client_id column table with the specified name
fn create_temp_ts3_table(conn: &mut PooledConn, tbl_name: &str) -> Result<()> {
    conn.query_drop(format!("DROP TEMPORARY TABLE IF EXISTS `{}`", tbl_name))?;
    conn.query_drop(format!(
        "CREATE TEMPORARY TABLE `{}` (
        `client_id` int(11) NOT NULL PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
        tbl_name
    ))?;
    Ok(())
}

/// Update/Insert ts3 channel names
pub fn upsert_channels(conn: &mut PooledConn, channels: &[Channel]) -> Result<()> {
    conn.exec_batch(
        "INSERT INTO `ts_channels` (`channel_id`,`name`) VALUES (?,?) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`)",
        channels.iter().map(|e|(e.id, &e.name)))?;
    Ok(())
}

/// Update ts client names
pub fn update_ts_names(conn: &mut PooledConn, names: &[(TsClDBID, &str)]) -> Result<()> {
    conn.exec_batch(
        "INSERT INTO `ts_names` (`client_id`,`name`) VALUES (?,?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)",
        names.iter())?;
    Ok(())
}

/// Update ts online times & names
pub fn update_ts_activity(
    conn: &mut PooledConn,
    date: NaiveDate,
    times: &[TsActivity],
) -> Result<()> {
    let mut transaction = conn.start_transaction(TxOpts::default())?;
    transaction.exec_batch(
        "INSERT INTO `ts_activity` (`date`,`client_id`,`channel_id`,`time`) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE `time` = `time`+VALUES(`time`)",
        times.iter().map(|e|(date, e.client, e.channel, e.time)))?;
    transaction.commit()?;
    Ok(())
}

#[cfg(test)]
mod test {
    use super::*;
    use ::std::time::{SystemTime, UNIX_EPOCH};
    use db::testing::*;

    #[test]
    fn test_create_temp_ts3_table() {
        let (mut conn, _guard) = setup_db();
        create_temp_ts3_table(&mut conn, "temp_table").unwrap();
    }

    #[test]
    fn test_create_temp_ts3_table_existing() {
        let (mut conn, mut guard) = setup_db();
        // test drop temporary before creating
        let table = "temp_table";
        conn.query_drop(format!(
            "CREATE TEMPORARY TABLE `{}`(`date` datetime NOT NULL PRIMARY KEY)",
            table
        ))
        .unwrap();
        create_temp_ts3_table(&mut conn, table).unwrap();

        // test non-drop of permanent table, use unique to avoid conflicts
        let table = SystemTime::now()
            .duration_since(UNIX_EPOCH)
            .unwrap()
            .as_millis()
            .to_string();
        conn.query_drop(format!(
            "CREATE TABLE `{}`(`date` datetime NOT NULL PRIMARY KEY)",
            table
        ))
        .unwrap();
        guard.add_table(table.clone());

        // assert that no permanent table gets deleted by create_temp_ts3_table
        create_temp_ts3_table(&mut conn, &table).unwrap();
        conn.query_drop(format!("SELECT 1 FROM `{}` LIMIT 1", table))
            .unwrap();
    }

    #[test]
    fn test_update_unknown_ts_ids() {
        let (mut conn, _guard) = setup_db();
        // insert some ids into member ts relation
        conn.exec_batch(
            "INSERT INTO `ts_relation` (`client_id`,`id`) VALUES (?,1)",
            (0..5).map(|i| (i,)),
        )
        .unwrap();

        // insert some "old" values into the table
        conn.exec_batch(
            "INSERT INTO `unknown_ts_ids` (`client_id`) VALUES (?)",
            (0..10).map(|i| (i,)),
        )
        .unwrap();

        // include double ids, allows two relevant groups for same client
        update_unknown_ts_ids(&mut conn, &vec![2, 3, 6, 4, 5, 6]).unwrap();

        let res: Vec<isize> = conn
            .query_map(
                "SELECT client_id FROM `unknown_ts_ids` ORDER BY client_id",
                |id| id,
            )
            .unwrap();

        assert_eq!(res, vec![5, 6]);
    }

    fn get_channels_ordered(conn: &mut PooledConn) -> Result<Vec<(TsChannelID, String)>> {
        let res: Vec<(TsChannelID, String)> = conn.query_map(
            "SELECT channel_id,name FROM `ts_channels` ORDER BY channel_id",
            |row| row,
        )?;
        Ok(res)
    }

    #[test]
    fn test_upsert_channels() {
        let (mut conn, _guard) = setup_db();

        let channels = vec![
            Channel {
                id: 2,
                name: "äüö2".to_string(),
            },
            Channel {
                id: 1,
                name: "äöü".to_string(),
            },
        ];

        upsert_channels(&mut conn, &channels).unwrap();

        let data = get_channels_ordered(&mut conn).unwrap();
        assert_eq!(data, vec![(1, "äöü".to_string()), (2, "äüö2".to_string())]);

        // we inserted, let's update one, insert another
        let channels = vec![
            Channel {
                id: 1,
                name: "123".to_string(),
            },
            Channel {
                id: 3,
                name: "345".to_string(),
            },
        ];
        upsert_channels(&mut conn, &channels).unwrap();
        let data = get_channels_ordered(&mut conn).unwrap();
        assert_eq!(
            data,
            vec![
                (1, "123".to_string()),
                (2, "äüö2".to_string()),
                (3, "345".to_string())
            ]
        );
    }

    fn get_ts_activity_ordered(conn: &mut PooledConn) -> Result<Vec<(NaiveDate, TsActivity)>> {
        let data = conn.query_map(
            "SELECT client_id,channel_id,time,date FROM `ts_activity` ORDER BY client_id,channel_id",
            |(client, channel, time, date)|(date,TsActivity {
                client,
                channel,
                time,
            }),
        )?;
        Ok(data)
    }

    #[test]
    fn test_update_ts_activity() {
        let (mut conn, _guard) = setup_db();

        let data = vec![
            TsActivity {
                client: 1,
                time: 1,
                channel: 1,
            },
            TsActivity {
                client: 1,
                time: 2,
                channel: 2,
            },
            TsActivity {
                client: 2,
                time: 3,
                channel: 1,
            },
        ];
        let date = NaiveDate::from_ymd_opt(2020, 03, 29).unwrap();
        update_ts_activity(&mut conn, date, &data).unwrap();

        let res = get_ts_activity_ordered(&mut conn).unwrap();
        for i in 0..res.len() {
            let (r_date, act) = &res[i];
            assert_eq!(*r_date, date);
            assert_eq!(act, &data[i]);
        }

        // now update it
        let data = vec![
            TsActivity {
                client: 1,
                time: 10,
                channel: 1,
            },
            TsActivity {
                client: 2,
                time: 10,
                channel: 1,
            },
        ];
        update_ts_activity(&mut conn, date, &data).unwrap();
        let res = get_ts_activity_ordered(&mut conn).unwrap();
        let expected: Vec<_> = vec![
            TsActivity {
                client: 1,
                time: 11,
                channel: 1,
            },
            TsActivity {
                client: 1,
                time: 2,
                channel: 2,
            },
            TsActivity {
                client: 2,
                time: 13,
                channel: 1,
            },
        ]
        .drain(..)
        .map(|v| (date.clone(), v))
        .collect();
        assert_eq!(expected, res);
    }

    /// Helper function, returns client_id,name ordered by client_id
    fn get_ts_names_ordered(conn: &mut PooledConn) -> Result<Vec<(TsClDBID, String)>> {
        let data = conn.query_map(
            "SELECT client_id,name FROM `ts_names` ORDER BY client_id",
            |r| r,
        )?;
        Ok(data)
    }

    #[test]
    fn test_update_ts_names() {
        let (mut conn, _guard) = setup_db();
        let name_a = "abc".to_string();
        let name_b = "クマ".to_string();
        let data = [(1, name_a.as_str()), (2, name_b.as_str())];
        update_ts_names(&mut conn, &data).unwrap();

        let res = get_ts_names_ordered(&mut conn).unwrap();
        assert_eq!(res, vec![(1, name_a), (2, name_b.clone())]);
        // update names of clients 1 & 3
        let name_a = "def".to_string();
        let name_c = "123".to_string();
        let data = [(1, name_a.as_str()), (3, name_c.as_str())];
        update_ts_names(&mut conn, &data).unwrap();
        let res = get_ts_names_ordered(&mut conn).unwrap();
        let expected = vec![
            (1, name_a.to_string()),
            (2, name_b.to_string()),
            (3, name_c.to_string()),
        ];
        assert_eq!(res, expected);
    }
}
