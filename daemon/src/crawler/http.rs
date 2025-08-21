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
use std::io::Read;
use std::process::Command;
use std::time::Duration;

use crate::REFERER as REF;
use crate::USER_AGENT as UA;

use crate::error::Error;

/// Header type for get requests
pub enum HeaderType {
    /// Html browser request
    Html,
    /// Ajax js request
    Ajax,
}

/// Does a get request under the provided url
/// The header varies by the provided HeaderType
pub fn get(url: &str, htype: HeaderType) -> Result<String, Error> {
    trace!("Starting downloading {}", url);

    //let mut res = CLIENT.get(url).headers(header(htype)).send()?;
    let mut cmd = Command::new("curl");
    cmd.args([url,"--compressed","-m","60"])
    .args(["--fail","--silent","--show-error"])
    .args(["-H","User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0"])
    .args(["-H","Accept-Language: en-US,en;q=0.5"])
    .args(["-H","Accept-Encoding: gzip, deflate, br, zstd"])
    .args(["-H","Connection: keep-alive"]);
    match htype {
        HeaderType::Html => {
            cmd.args([
                "-H",
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            ])
            .args(["-H", "Sec-Fetch-Dest: document"])
            .args(["-H", "Sec-Fetch-Mode: navigate"])
            .args(["-H", "Sec-Fetch-Site: none"])
            .args(["-H", "Sec-Fetch-User: ?1"])
            .args(["-H", "Priority: u=0, i"]);
        }
        HeaderType::Ajax => {
            cmd.args(["-H", "Accept: application/json, text/plain, */*"])
                .args(["-H", "Referer: https://crossfire.z8games.com/clan/68910"])
                .args(["-H", "Sec-Fetch-Dest: empty"])
                .args(["-H", "Sec-Fetch-Mode: cors"])
                .args(["-H", "Sec-Fetch-Site: same-origin"])
                .args(["-H", "Priority: u=0"]);
        }
    }

    let output = cmd.output();

    let output = output?;
    let stdout = String::from_utf8_lossy(&output.stdout);
    let stderr = String::from_utf8_lossy(&output.stderr);

    debug!("Stdout: {:?}", stdout);
    debug!("Stderr: {:?}", stderr);

    if !output.stderr.is_empty() || !output.status.success() {
        return Err(Error::Curl(stderr.into()));
    }

    Ok(stdout.trim().into())
}

#[cfg(test)]
mod test {
    use log::LevelFilter;
    use log4rs::{
        append::console::{ConsoleAppender, Target},
        config::{Appender, Root},
        Config,
    };

    use super::*;

    /// Test a html get request
    #[test]
    fn get_html_gzipped() {
        let b_html: String = get("https://httpbin.org/gzip", HeaderType::Html).unwrap();
        assert!(b_html.contains(r#""gzipped": true"#));
    }

    /// Test a ajax json get request
    #[test]
    fn get_ajax() {
        let b_ajax = get("https://httpbin.org/user-agent", HeaderType::Ajax).unwrap();
        assert!(b_ajax.contains(UA));
    }

    /// Run z8 test
    #[test]
    #[ignore]
    fn get_ajax_z8() {
        let b_ajax = get("http://crossfire.z8games.com/clan/1", HeaderType::Ajax).unwrap();
        assert!(b_ajax.contains("Clan1"));
    }

    #[test]
    #[ignore]
    fn get_ajax_z8_member() {
        let stderr = ConsoleAppender::builder().target(Target::Stderr).build();
        let _ = log4rs::init_config(
            Config::builder()
                .appender(Appender::builder().build("stderr", Box::new(stderr)))
                .build(Root::builder().appender("stderr").build(LevelFilter::Trace))
                .unwrap(),
        );
        let b_ajax = get("https://crossfire.z8games.com/rest/clanmembers.json?clanID=68910&endrow=10&page=1&perPage=10&rankType=user&startrow=1", HeaderType::Ajax).unwrap();
        assert!(b_ajax.contains("Dr.Alptraum"));
        println!("{}", b_ajax);
    }

    #[test]
    #[ignore]
    fn get_ajax_too_big() {
        let b_ajax = get("https://crossfire.z8games.com/rest/clanmembers.json?clanID=68910&endrow=40&page=4&perPage=10&rankType=user&startrow=31", HeaderType::Ajax).unwrap();
        println!("{}", b_ajax);
    }
}
