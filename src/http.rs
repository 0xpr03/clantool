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
use reqwest::Client;
use std::io::Read;

use flate2::read::GzDecoder;

use reqwest::header::{Headers,Pragma,Referer,Location,AcceptEncoding,Connection,ConnectionOption,AcceptCharset,Accept,Encoding,UserAgent,ContentEncoding,qitem,QualityItem,Charset,q};

use USER_AGENT;
use REFERER;

use error::Error;

pub enum HeaderType {
    Html, // html browser request
    Ajax // ajax js request
}

/// Does a get request under the provided url
/// The header varies by the provided HeaderType
pub fn get(url: &str, htype: HeaderType) -> Result<String,Error>{
    trace!("Starting downloading {}",url);
    let client = try!(Client::new());
    let mut builder = client.get(url)?;
    let mut res = builder.headers(header(htype)).send()?;
    debug!("Response header: {:?}",res.headers());
    debug!("Response status: {:?}",res.status());
    debug!("Final URL: {:?}",res.headers().get::<Location>());
    trace!("DEV header: {:?}",res.headers().get::<ContentEncoding>());
    let mut body = String::new();
    let gzipped = if res.headers().has::<ContentEncoding>() {
        res.headers().get::<ContentEncoding>().unwrap().contains(&Encoding::Gzip)
    }else{
        false
    };
    debug!("Gzip compressed: {}",gzipped);
    
    if gzipped {
        let mut decoder = try!(GzDecoder::new(res));
        try!(decoder.read_to_string(&mut body));
    }else{
        try!(res.read_to_string(&mut body));
    }
    Ok(body)
}

/// Construct a header
/// This function does not check for errors and is
/// verified by the tests
fn header(htype: HeaderType) -> Headers {
    let mut headers = Headers::new();
    
    headers.set(
        AcceptEncoding(vec![
            qitem(Encoding::Chunked),
            qitem(Encoding::Gzip),
        ])
    );
    
    headers.set(Referer::new(REFERER));
    
    headers.set(Pragma::NoCache);

    match htype {
        HeaderType::Html => {
            headers.set(
                AcceptCharset(vec![
                    QualityItem::new(Charset::Us_Ascii, q(0.100)),
                    QualityItem::new(Charset::Ext("utf-8".to_owned()), q(0.900)),
                ])
            );
            
            headers.set(
                Accept(vec![
                    qitem("text/html;q=0.9".parse().unwrap()),
                    qitem("application/xhtml+xml".parse().unwrap()),
                    qitem("application/xml;q=0.8".parse().unwrap()),
                ])
            );
        },
        HeaderType::Ajax => {
            headers.set(
                Accept(vec![
                    qitem("application/json".parse().unwrap()),
                    qitem("text/plain".parse().unwrap()),
                ])
            );
        }
    }
    headers.set(
        Connection(
            vec![(ConnectionOption::Close)]
        )
    );
    headers.set(UserAgent::new(USER_AGENT.to_owned()));
    
    trace!("Generated headers: {}",headers);
    headers
}

#[cfg(test)]
mod test {
    use super::*;
    use super::header;
    
    use USER_AGENT;
    
    /// Test header creation
    #[test]
    fn header_test() {
        let _ = header(HeaderType::Html);
        let _ = header(HeaderType::Ajax);
    }
    
    /// Test a html get request
    #[test]
    fn get_html_gzipped() {
        let b_html: String = get("https://httpbin.org/gzip",HeaderType::Html).unwrap();
        assert!(true, b_html.contains(r#""gzipped": true"#));
    }
    
    /// Test a ajax json get request
    #[test]
    fn get_ajax() {
        let b_ajax = get("https://httpbin.org/user-agent",HeaderType::Ajax).unwrap();
        assert_eq!(b_ajax,format!("{{\n  \"user-agent\": \"{}\"\n}}\n", USER_AGENT)); // {{ = {, } = }}
    }
}
