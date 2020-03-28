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
}
