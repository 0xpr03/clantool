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

use super::*;
use chrono::naive::NaiveDate;

/// Update unknown_ts_ids table based on member clients  
/// Handles known member client_id filtering  
/// Allows doubled group IDs in member_clients
pub fn update_unknown_ts_ids(conn: &mut PooledConn, member_clients: &[usize]) -> Result<()> {
    create_temp_ts3_table(conn, "t_member_clients")?;
    {
        // insert every member client id into temp table, ignore multi-group clients
        let mut stmt =
            conn.prepare("INSERT IGNORE INTO `t_member_clients` (`client_id`) VALUES (?)")?;
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

/// Update/Insert ts3 channel names
pub fn upsert_channels(conn: &mut PooledConn, channels: &[Channel]) -> Result<()> {
    let mut stmt = conn.prepare(
        "INSERT INTO `ts_channels` (`channel_id`,`name`) VALUES (?,?) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`)",
    )?;
    for e in channels {
        stmt.execute((e.id, &e.name))?;
    }
    Ok(())
}

pub fn update_ts_names(conn: &mut PooledConn, names: &[TsClient]) -> Result<()> {
    let mut stm_names = conn.prepare(
        "INSERT INTO `ts_names` (`client_id`,`name`) VALUES (?,?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)"
    )?;
    for e in names {
        stm_names.execute((e.clid, &e.name))?;
    }
    Ok(())
}

/// Update ts online times & names
pub fn update_ts_activity(
    conn: &mut PooledConn,
    date: &NaiveDate,
    times: &[TsActivity],
) -> Result<()> {
    let mut transaction = conn.start_transaction(false, None, None)?;
    {
        let mut stm_time = transaction.prepare(
        "INSERT INTO `ts_activity` (`date`,`client_id`,`channel_id`,`time`) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE `time` = `time`+VALUES(`time`)",
        )?;

        for e in times {
            stm_time.execute((date, e.client, e.channel, e.time))?;
        }
    }
    transaction.commit()?;
    Ok(())
}

#[cfg(test)]
mod test {
    use super::*;
    use db::testing::*;
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

        // include double ids, allows two relevant groups for same client
        update_unknown_ts_ids(&mut conn, &vec![2, 3, 6, 4, 5, 6]).unwrap();

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

    fn get_channels_ordered(conn: &mut PooledConn) -> Result<Vec<(ChannelID, String)>> {
        let res = conn.prep_exec(
            "SELECT channel_id,name FROM `ts_channels` ORDER BY channel_id",
            (),
        )?;
        let data: Vec<_> = res
            .map(|row| {
                let row: (ChannelID, String) = from_row(row.unwrap());
                row
            })
            .collect();
        Ok(data)
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

    fn get_ts_activity_ordered(conn: &mut PooledConn) -> Result<Vec<(NaiveDate,TsActivity)>> {
        let res = conn.prep_exec(
            "SELECT client_id,channel_id,time,date FROM `ts_activity` ORDER BY client_id,channel_id",
            (),
        )?;
        let data: Vec<_> = res
            .map(|row| {
                let (client, channel, time,date): (TsClDBID, ChannelID, i32, NaiveDate) = from_row(row.unwrap());
                (date,TsActivity {
                    client,
                    channel,
                    time,
                })
            })
            .collect();
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
        let date = NaiveDate::from_ymd(2020, 03, 29);
        update_ts_activity(&mut conn, &date, &data).unwrap();

        let res = get_ts_activity_ordered(&mut conn).unwrap();
        for i in 0..res.len() {
            let (r_date,act) = &res[i];
            assert_eq!(*r_date,date);
            assert_eq!(act,&data[i]);
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
        update_ts_activity(&mut conn, &date, &data).unwrap();
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
        ].drain(..).map(|v|(date.clone(),v)).collect();
        assert_eq!(expected,res);
    }
}
