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

use std::fs::File;
use std::io::BufReader;

use std::path::{Path, PathBuf};

use crate::error::Error;

use csv;
use csv::ReaderBuilder;
use csv::StringRecord;
use csv::Trim;

use chrono::naive::{NaiveDate, NaiveDateTime, NaiveTime};
use chrono::offset::Local;
use chrono::Duration;

use mysql::Pool;

use crate::db;

lazy_static! {
    pub static ref DEFAULT_IMPORT_COMMENT: String =
        format!("imported on {}", format_datetime(&Local::now().naive_utc()));
}

/// Import commando handler
pub fn import_cmd(
    simulate: bool,
    membership: bool,
    comment: &str,
    date_format: &str,
    path: PathBuf,
    pool: &Pool,
) {
    info!("CSV Import of File {:?}", path);
    if simulate {
        info!("Simulation mode");
    }
    if membership {
        info!("Importing membership data");
        panic!("Unsupported");
    } else {
        info!("Importing account data");

        let default_time =
            NaiveDateTime::parse_from_str("1970-01-01 00:00:01", "%Y-%m-%d %H:%M:%S").unwrap();
        let importer = match ImportParser::new(&path, default_time, &date_format) {
            Ok(v) => v,
            Err(e) => {
                error!("Error at importer: {}", e);
                return;
            }
        };

        let mut inserter = match db::import::ImportAccountInserter::new(pool, comment) {
            Ok(v) => v,
            Err(e) => {
                error!("Error at preparing insertion: {}", e);
                return;
            }
        };

        let mut imported = 0;
        let mut ms_imported = 0;
        let mut ms_total = 0;
        for entry in importer {
            match entry {
                Ok(v) => {
                    if simulate {
                        trace!("Entry: {:?}", v);
                    }
                    match inserter.insert_account(&v) {
                        Err(e) => {
                            error!("Error on import: {}", e);
                            return;
                        }
                        Ok((total, imported)) => {
                            ms_imported += imported;
                            ms_total += total;
                        }
                    }
                }
                Err(e) => {
                    error!("Error at parsing entry: {}", e);
                    return;
                }
            }
            imported += 1;
        }
        if simulate {
            info!(
                "Found {} correct entries to import, {}/{} memberships can be used",
                imported, ms_imported, ms_total
            );
        } else {
            if let Err(e) = inserter.commit() {
                error!("Unable to commit import: {}", e);
                return;
            }
            info!(
                "Imported {} accounts & {}/{} memberships",
                imported, ms_imported, ms_total
            );
        }
    }
}

const DATETIME_FORMAT_COMMENT: &str = "%Y-%m-%d %H:%M:%S";
pub const DATE_DEFAULT_FORMAT: &str = "%-m/%-d/%y";
pub const IMPORT_MEMBERSHIP_CAUSE: &str = "imported membership";

macro_rules! opt {
    ($e:expr) => {
        $e.ok_or_else(|| Error::NoValue(""))?
    };
}
macro_rules! get_string {
    ($e:expr,$b:expr) => {
        opt!($e.get($b)).to_string()
    };
}

#[inline]
pub fn format_datetime(datetime: &NaiveDateTime) -> String {
    datetime.format(DATETIME_FORMAT_COMMENT).to_string()
}

/// Import parser, parsing an ImportMembership into a ImportMembership
pub struct ImportParser<'a> {
    reader: csv::StringRecordsIntoIter<BufReader<File>>,
    header: StringRecord,
    default_date_name: NaiveDateTime,
    date_parse_format: &'a str,
}

impl<'a> ImportParser<'a> {
    /// Account import
    /// Contains USN, Name & VName, vip state as well as comments
    /// default_date_name: Default date for name insertion
    pub fn new(
        path: &Path,
        default_date_name: NaiveDateTime,
        date_parse_format: &'a str,
    ) -> Result<ImportParser<'a>, Error> {
        let file = File::open(path)?;
        let buf_reader = BufReader::new(file);
        let mut reader = ReaderBuilder::new()
            .has_headers(true)
            .trim(Trim::All)
            .from_reader(buf_reader);
        let header = reader.headers()?.clone();
        Ok(ImportParser {
            reader: reader.into_records(),
            header,
            default_date_name,
            date_parse_format,
        })
    }
}

/// Parse import naive date & print errors
fn parse_date(input: &str, format: &str) -> Result<NaiveDate, Error> {
    match NaiveDate::parse_from_str(input, format) {
        Ok(v) => Ok(v),
        Err(e) => {
            error!("Unable to parse date in {} with format {}", input, format);
            Err(e.into())
        }
    }
}

/// parse a single record (line) of the csv file
/// We assume a layout of total,new,total,new... with total having the end-of-week date
/// For example
/// ```
/// 02-09-18,,09-09-18,,16-09-18,,23-09-18,,
/// ---,---,1,1,15,14,---,---
/// ```
/// As we have data which is under 09-09-18 and 16-09-18 we can assume
/// an optimistic membership from 03-09-18 - 16-09-18
fn parse_record(
    parser: &ImportParser,
    record: &StringRecord,
    header: &StringRecord,
) -> Result<ImportMembership, Error> {
    let mut memberships = Vec::new();

    let mut start: Option<NaiveDate> = None;
    let mut end: Option<NaiveDate> = None;
    for i in 5..record.len() {
        let head = opt!(header.get(i));
        if head != "" {
            match opt!(record.get(i)) {
                "---" | "" => {
                    if start.is_some() {
                        memberships.push(Membership {
                            from: opt!(start.take()),
                            to: Some(opt!(end.take())),
                        });
                    }
                }
                _ => {
                    let date = parse_date(head, parser.date_parse_format)?;
                    if start.is_none() {
                        // sub 6 days mo-su, we have the sunday and want the monday
                        start = Some(date.checked_sub_signed(Duration::days(6)).unwrap());
                        end = Some(date);
                    } else {
                        end = Some(date);
                    }
                }
            }
        }
    }
    // data going over the end
    if start.is_some() {
        memberships.push(Membership {
            from: opt!(start.take()),
            to: Some(opt!(end.take())),
        });
    }

    let date_name = if !memberships.is_empty() {
        NaiveDateTime::new(memberships[0].from, NaiveTime::from_hms(0, 0, 1))
    } else {
        parser.default_date_name
    };

    Ok(ImportMembership {
        id: opt!(record.get(0)).parse()?,
        name: get_string!(record, 1),
        vname: get_string!(record, 2),
        vip: bool_from_string(opt!(record.get(3)))?,
        comment: get_string!(record, 4),
        date_name,
        memberships,
    })
}

impl<'a> Iterator for ImportParser<'a> {
    type Item = Result<ImportMembership, Error>;

    fn next(&mut self) -> Option<Result<ImportMembership, Error>> {
        match self.reader.next() {
            Some(Ok(record)) => {
                let result = parse_record(self, &record, &self.header);
                match result {
                    Ok(_) => (),
                    Err(ref e) => {
                        info!("{} on row {:?}", e, record);
                    }
                }
                Some(result)
            }
            Some(Err(e)) => Some(Err(e.into())),
            None => None,
        }
    }
}

/// Import Membership
#[derive(Debug, PartialEq)]
pub struct ImportMembership {
    pub id: i32,
    pub name: String,
    pub vname: String,
    pub vip: bool,
    pub comment: String,
    pub date_name: NaiveDateTime,
    pub memberships: Vec<Membership>,
}

/// Membership
#[derive(Debug, PartialEq)]
pub struct Membership {
    pub from: NaiveDate,
    pub to: Option<NaiveDate>,
}

/// Deserialize bool from String with custom value mapping
fn bool_from_string(input: &str) -> Result<bool, Error> {
    match input {
        "VIP" | "true" => Ok(true),
        "nVIP" | "false" => Ok(false),
        other => Err(Error::Parser(format!(
            "Unexpected value: {} wanted {}",
            other, "VIP or nVIP"
        ))),
    }
}

#[cfg(test)]
mod test {
    use super::*;
    use crate::get_path_for_existing_file;
    use chrono::naive::{NaiveDate, NaiveDateTime, NaiveTime};

    /// Generate default NaiveDateTime for tests where no specific datetime is required
    fn default_date() -> NaiveDateTime {
        let d = NaiveDate::from_ymd(2015, 6, 3);
        let t = NaiveTime::from_hms(12, 34, 56);

        NaiveDateTime::new(d, t)
    }

    #[test]
    fn date_parse() {
        match parse_date("03/08/16", DATE_DEFAULT_FORMAT) {
            Ok(v) => {
                assert_eq!("2016-03-08", format!("{}", v));
            }
            Err(e) => panic!("invalid result {}", e),
        }
    }

    /// Test account parsing with long header and membership going beyong the provided data
    #[test]
    fn parse_account_header_correct_long_unending() {
        let path =
            get_path_for_existing_file("tests/test_csv_header_valid_long_unending.csv").unwrap();
        let mut var = Vec::new();
        let date_format = "%d-%m-%y";
        let verify_date = parse_date("20-08-18", date_format).unwrap();
        var.push(ImportMembership {
            name: String::from("Alptraum"),
            id: 9926942,
            vip: true,
            vname: String::from("Thomas"),
            comment: String::from("Ein Kommentar"),
            date_name: verify_date.and_time(NaiveTime::from_hms(0, 0, 1)).clone(),
            memberships: vec![
                Membership {
                    from: verify_date,
                    to: Some(parse_date("09-09-18", date_format).unwrap()),
                },
                Membership {
                    // second date, doesn't close in parse_record loop, end of data
                    from: parse_date("17-09-18", date_format).unwrap(),
                    to: Some(parse_date("07-10-18", date_format).unwrap()),
                },
            ],
        });
        let importer = ImportParser::new(&path, default_date(), date_format).unwrap();
        let result: Vec<ImportMembership> = importer.map(|v| v.unwrap()).collect();
        assert_eq!(var, result);
        assert_eq!(1, result.len());
    }

    /// Test account pasing with long, valid
    #[test]
    fn parse_account_header_correct_long() {
        let path = get_path_for_existing_file("tests/test_csv_header_valid_long.csv").unwrap();
        let mut var = Vec::new();
        let verify_date = parse_date("03/02/16", DATE_DEFAULT_FORMAT).unwrap();
        var.push(ImportMembership {
            name: String::from("Alptraum"),
            id: 9926942,
            vip: true,
            vname: String::from("Thomas"),
            comment: String::from("Ein Kommentar"),
            date_name: verify_date.and_time(NaiveTime::from_hms(0, 0, 1)).clone(),
            memberships: vec![Membership {
                from: verify_date,
                to: Some(parse_date("03/15/16", DATE_DEFAULT_FORMAT).unwrap()),
            }],
        });
        let importer = ImportParser::new(&path, default_date(), "%d-%m-%y").unwrap();
        let result: Vec<ImportMembership> = importer.map(|v| v.unwrap()).collect();
        assert_eq!(var, result);
        assert_eq!(1, result.len());
    }

    /// Test account pasing with header, valid
    #[test]
    fn parse_account_header_correct() {
        let path = get_path_for_existing_file("tests/test_csv_header_valid.csv").unwrap();
        let mut var = Vec::new();
        var.push(ImportMembership {
            name: String::from("Alptraum"),
            id: 9926942,
            vip: true,
            vname: String::from("Thomas"),
            comment: String::from("Ein Kommentar"),
            date_name: default_date(),
            memberships: Vec::new(),
        });
        let importer = ImportParser::new(&path, default_date(), DATE_DEFAULT_FORMAT).unwrap();
        let result: Vec<ImportMembership> = importer.map(|v| v.unwrap()).collect();
        assert_eq!(var, result);
        assert_eq!(1, result.len());
    }

    /// Test account pasing without header, valid
    #[test]
    fn parse_account_correct() {
        let path = get_path_for_existing_file("tests/test_csv_valid.csv").unwrap();
        let mut var = Vec::new();
        var.push(ImportMembership {
            name: String::from("Alptraum"),
            id: 9926942,
            vip: true,
            vname: String::from("Thomas"),
            comment: String::from("Ein Kommentar"),
            date_name: default_date(),
            memberships: Vec::new(),
        });
        let importer = ImportParser::new(&path, default_date(), DATE_DEFAULT_FORMAT).unwrap();
        let result: Vec<ImportMembership> = importer.map(|v| v.unwrap()).collect();
        assert_eq!(var, result);
        assert_eq!(1, result.len());
    }

    /// Test account pasing without header, invalid vip field
    #[test]
    fn parse_account_incorrect_vip() {
        let path = get_path_for_existing_file("tests/test_csv_invalid_vip.csv").unwrap();
        let mut importer = ImportParser::new(&path, default_date(), DATE_DEFAULT_FORMAT).unwrap();
        assert!(importer.next().unwrap().is_err());
    }
}
