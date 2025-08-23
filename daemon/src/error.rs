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

use chrono;
use csv;
use json;
use mysql;
use regex;
use std;
use std::io;
use toml;
use ts3_query;

quick_error! {
    #[derive(Debug)]
    pub enum Error {
        Toml(err: toml::de::Error) {
            from()
            display("toml error: {}",err)
            source(err)
        }
        Io(err: io::Error) {
            from()
            display("I/O error: {}", err)
            source(err)
        }
        Curl(descr: String) {
            display("CURL Error: {}",descr)
        }
        Json(err: json::Error) {
            from()
            display("json error: {}",err)
            source(err)
        }
        Parser(descr: String) {
            display("Error: {}",descr)
        }
        Regex(err: regex::Error) {
            from()
            display("regex error: {}",err)
            source(err)
        }
        IntParseError(err: std::num::ParseIntError) {
            from()
            display("parse error: {}",err)
            source(err)
        }
        DateParseError(err: chrono::ParseError) {
            from()
            display("parse error: {}",err)
            source(err)
        }
        Mariadb(err: mysql::Error) {
            from()
            display("mariadb error: {}",err)
            source(err)
        }
        RowParse(err: mysql::FromRowError) {
            from()
            display("RowParse: {}",err)
            source(err)
        }
        ValueParse(err: mysql::FromValueError) {
            from()
            display("ValueParse: {}",err)
            source(err)
        }
        NoValue(descr: &'static str) {
            display("no value {}",descr)
        }
        CSV(err: csv::Error) {
            from()
            display("csv error: {}",err)
            source(err)
        }
        InvalidDBSetup(descr: String) {
            display("Error {}", descr)
        }
        Other(descr: &'static str) {
            display("Error {}", descr)
        }
        TSError(err: ts3_query::Ts3Error) {
            from()
            display("ts3 error: {}",err)
            source(err)
        }
        MissingKey(key: &'static str) {
            display("Key {} not found in db!",key)
        }
        TsMissingValue(value: &'static str) {
            display("Missing value for {} in ts3-server response",value)
        }
        /// Expected at least ourself, found 0 clients online
        NoTsClients {}
    }
}
