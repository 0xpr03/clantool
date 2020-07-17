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

use reqwest::blocking::{Client, ClientBuilder};
use reqwest::header::*;
use std::io::Read;

use crate::REFERER as REF;
use crate::USER_AGENT as UA;

use crate::error::Error;

lazy_static! {
    static ref CLIENT: Client = ClientBuilder::new().gzip(true).danger_accept_invalid_certs(true).build().unwrap() ;
}

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

    let mut res = CLIENT.get(url).headers(header(htype)).send()?;

    debug!("Response header: {:?}", res.headers());
    debug!("Response status: {:?}", res.status());
    debug!("Final URL: {:?}", res.headers().get(LOCATION));
    trace!("DEV header: {:?}", res.headers().get(CONTENT_ENCODING));
    let mut body = String::new();
    res.read_to_string(&mut body)?;
    Ok(body)
}

/// Construct a header
/// This function does not check for errors and is
/// verified by the tests
fn header(htype: HeaderType) -> HeaderMap {
    let mut headers = HeaderMap::new();

    headers.insert(ACCEPT_ENCODING, "gzip, deflate".parse().unwrap());

    //headers.insert(PRAGMA, "no-cache".parse().unwrap());
    headers.insert(ACCEPT_LANGUAGE, "en-US,en;q=0.5".parse().unwrap());
    headers.insert(USER_AGENT, UA.parse().unwrap());
    headers.insert(REFERER, REF.parse().unwrap());
    headers.insert(CACHE_CONTROL, "max-age=0".parse().unwrap());

    match htype {
        HeaderType::Html => {
            headers.insert(
                ACCEPT,
                "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
                    .parse()
                    .unwrap(),
            );
        }
        HeaderType::Ajax => {
            headers.insert(ACCEPT, "application/json, text/plain, */*".parse().unwrap());
        }
    }

    trace!("Generated headers: {:?}", headers);
    headers
}

#[cfg(test)]
mod test {
    use super::header;
    use super::*;

    /// Test header creation
    #[test]
    fn header_test() {
        let _ = header(HeaderType::Html);
        let _ = header(HeaderType::Ajax);
    }

    /// Test a html get request
    #[test]
    fn get_html_gzipped() {
        let b_html: String = get("https://httpbin.org/gzip", HeaderType::Html).unwrap();
        assert!(true, b_html.contains(r#""gzipped": true"#));
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
        let b_ajax = get("https://crossfire.z8games.com/rest/clanmembers.json?clanID=68910&endrow=10&page=1&perPage=10&rankType=user&startrow=1", HeaderType::Ajax).unwrap();
        assert!(b_ajax.contains("Dr.Alptraum"));
        println!("{}", b_ajax);
    }
}
