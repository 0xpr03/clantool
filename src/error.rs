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
use std::io;
use reqwest;
use json;
use mysql;
use log4rs;
use regex;
use std;

quick_error! {
    #[derive(Debug)]
    pub enum Error {
        Io(err: io::Error) {
            from()
            description("io error")
            display("I/O error: {}", err)
            cause(err)
        }
        Http(err: reqwest::Error) {
            from()
            description("http error")
            display("http error: {}",err)
            cause(err)
        }
        Json(err: json::Error) {
            from()
            description("json error")
            display("json error: {}",err)
            cause(err)
        }
        Parser(descr: String) {
            description(descr)
            display("Error: {}",descr)
        }
        Regex(err: regex::Error) {
            from()
            description("regex error")
            display("regex error: {}",err)
            cause(err)
        }
        ParseError(err: std::num::ParseIntError) {
            from()
            description("parse error")
            display("parse error: {}",err)
            cause(err)
        }
        Mariadb(err: mysql::Error) {
            from()
            description("mariadb error")
            display("mariadb error: {}",err)
            cause(err)
        }
        Log(err: log4rs::Error) {
            from()
            description("log4rs error")
            display("log4rs error: {}",err)
            cause(err)
        }
        Other(descr: &'static str) {
            description(descr)
            display("Error {}", descr)
        }
    }
}