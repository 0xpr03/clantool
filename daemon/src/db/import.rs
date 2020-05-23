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

//! DB function for CSV import
use super::prelude::*;
use crate::import::*;
use mysql::Transaction;

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
            transaction: pool.start_transaction(TxOpts::default())?,
            comment_addition,
        })
    }

    /// Commit account import
    pub fn commit(self) -> Result<()> {
        self.transaction.commit()?;
        Ok(())
    }

    /// Format comment with addition
    pub fn get_formated_comment(&self, account: &ImportMembership) -> String {
        format!("{}\n{}", &self.comment_addition, account.comment)
    }

    /// Insert account data
    /// return total amount memberships,inserted for account
    pub fn insert_account(&mut self, acc: &ImportMembership) -> Result<(usize, usize)> {
        self.transaction.exec_drop(
            "INSERT IGNORE INTO `member_names` (`id`,`name`,`date`,`updated`) VALUES (?,?,?,?)",
            (acc.id, &acc.name, acc.date_name, acc.date_name),
        )?;
        let comment = self.get_formated_comment(acc);
        self.transaction.exec_drop(
            "INSERT IGNORE INTO `member_addition` (`id`,`name`,`vip`,`comment`) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE comment=CONCAT(comment,\"\n\",VALUES(`comment`))",
            (acc.id, &acc.vname, acc.vip, comment),
        )?;
        let membership_total = acc.memberships.len();
        let mut membership_inserted = 0;

        for membership in &acc.memberships {
            self.transaction.exec_drop(
                "INSERT IGNORE INTO `membership` (`id`,`from`,`to`) VALUES (?,?,?)",
                (acc.id, membership.from, membership.to),
            )?;
            let nr = self
                .transaction
                .last_insert_id()
                .ok_or(Error::NoValue("No last insert ID!"))? as i32;

            if nr != 0 {
                self.transaction.exec_drop(
                    "INSERT INTO `membership_cause` (`nr`,`kicked`,`cause`) VALUES (?,?,?)",
                    (nr, false, IMPORT_MEMBERSHIP_CAUSE),
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

#[cfg(test)]
mod test {
    use super::*;
    use chrono::naive::NaiveDateTime;
    use db::testing::*;
    use db::DATETIME_FORMAT;

    /// Get member_addition for specified id return (id,name,vip,comment)
    fn get_member_addition(conn: &mut PooledConn, id: &i32) -> (i32, String, bool, String) {
        let res = conn.exec_first("SELECT `id`,`name`,CAST(`vip` AS INT) as `vip`,`comment` FROM `member_addition` WHERE `id` = ?",(id,)).unwrap();
        res.unwrap()
    }

    /// Get first member_names entry for specified id, return (id,name,date,updated)
    fn get_member_name(
        conn: &mut PooledConn,
        id: &i32,
    ) -> (i32, String, NaiveDateTime, NaiveDateTime) {
        let res = conn
            .exec_first(
                "SELECT `id`,`name`,`date`,`updated` FROM `member_names` WHERE `id` = ? LIMIT 1",
                (id,),
            )
            .unwrap();
        res.unwrap()
    }

    /// Test import for account with existing comments
    #[test]
    fn check_import_account_insert_comment_existing() {
        let (_, guard) = setup_db();

        let date1 = NaiveDateTime::parse_from_str("2014-01-01 09:12:43", DATETIME_FORMAT).unwrap();
        let account = ImportMembership {
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
            .get_conn()
            .unwrap()
            .exec_drop(
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
        let account = ImportMembership {
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
}
