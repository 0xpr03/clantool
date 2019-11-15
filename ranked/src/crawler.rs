use crate::db;
use crate::model::*;
use mysql::Pool;
use reqwest::{header, Client, ClientBuilder};
use snafu::{Backtrace, ResultExt, Snafu};

const USERAGENT: &str =
    "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:70.0) Gecko/20100101 Firefox/70.0";

lazy_static! {
    static ref CLIENT: Client = {
        let mut headers = header::HeaderMap::new();
        headers.insert(
            header::USER_AGENT,
            header::HeaderValue::from_static(&USERAGENT),
        );
        headers.insert(
            header::ACCEPT,
            header::HeaderValue::from_static("application/json, text/plain, */*"),
        );
        headers.insert(
            header::ACCEPT_LANGUAGE,
            header::HeaderValue::from_static("en-US,en;q=0.5"),
        );
        headers.insert(
            header::ORIGIN,
            header::HeaderValue::from_static("http://crossfire.z8games.com"),
        );
        headers.insert(
            header::ACCEPT_ENCODING,
            header::HeaderValue::from_static("gzip, deflate, br"),
        );
        ClientBuilder::new()
            .default_headers(headers)
            .build()
            .unwrap()
    };
}

pub type Result<T> = ::std::result::Result<T, CrawlerError>;

#[derive(Debug, Snafu)]
pub enum CrawlerError {
    #[snafu(display("DB error: {}\n{}", source, backtrace))]
    DBError {
        backtrace: Backtrace,
        source: db::DBError,
    },
    #[snafu(display("Reqwest error: {}", source))]
    WebError { source: reqwest::Error },
    #[snafu(display("Parse error: {}: {}", source, text))]
    ParseError {
        text: String,
        source: reqwest::Error,
    },
}

pub fn schedule_crawler(pool: Pool) -> Result<()> {
    trace!("Scheduling crawler");
    let ids = db::get_current_member_ids(&pool).context(DBError)?;
    debug!("Found {} USNs", ids.len());

    // good guess
    let mut data = Vec::with_capacity(ids.len() * 4);
    // no flat_map iterator possible with results
    for id in ids {
        data.append(&mut crawl_id_rank(id)?);
    }
    debug!("Ranked data size {}", data.len());

    db::update_data(pool, data).context(DBError)?;
    Ok(())
}

fn crawl_id_rank(id: USN) -> Result<Vec<RankedEntry>> {
    let mut response = CLIENT
        .get(&format!(
            "http://crossfire.z8games.com/rest/userprofile.json?usn={}",
            id
        ))
        .header(
            header::REFERER,
            format!("http://crossfire.z8games.com/profile/{}", id),
        )
        .send()
        .context(WebError)?;
    // let text = response.text().context(WebError)?;
    // println!("{}",text);
    // let data: PlayerResponse = from_str(&text).unwrap();
    let data: PlayerResponse = response.json().context(ParseError {
        text: response.text().context(WebError)?,
    })?;
    let data = data
        .ranked
        .into_iter()
        .filter_map(|e| {
            if let (Some(mode_name), Some(mode), Some(rank_name), Some(rank), Some(subrank)) =
                (e.mode_name, e.mode, e.rank_name, e.rank, e.subrank)
            {
                Some(RankedEntry {
                    usn: e.usn,
                    mode_name,
                    mode,
                    rank_name,
                    rank,
                    subrank,
                    season: e.season,
                })
            } else {
                None
            }
        })
        .collect();

    Ok(data)
}

#[cfg(test)]
mod test {
    use super::*;

    #[test]
    fn test_crawl() {
        let data = crawl_id_rank(7222040).unwrap();
        dbg!(data);
    }
}
