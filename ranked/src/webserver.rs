use crate::db;
use crate::settings::Settings;
use crate::Cache;
use actix_web::{middleware, web, App, HttpResponse, HttpServer};
use serde::Deserialize;
use snafu::{Backtrace, ResultExt, Snafu};

type Result<T> = ::std::result::Result<T, WebError>;

#[derive(Debug, Snafu)]
pub enum WebError {
    #[snafu(display("DB error: {}: {}", source, backtrace))]
    DBError {
        backtrace: Backtrace,
        source: db::DBError,
    },
    StartError {
        source: ::std::io::Error,
    },
    BindError {
        source: ::std::io::Error,
    },
}

pub fn run(cache: Cache, settings: Settings) -> Result<()> {
    HttpServer::new(move || {
        App::new()
            .register_data(cache.clone())
            .wrap(middleware::Logger::default())
            .service(web::resource("/api/ranked/{season}/{mode}").route(web::get().to(api)))
    })
    .bind(format!(
        "{}:{}",
        settings.main.bind_ip, settings.main.bind_port
    ))
    .context(BindError)?
    .run()
    .context(StartError)?;

    Ok(())
}

#[derive(Debug, Deserialize)]
struct ApiRequest {
    pub season: i32,
    pub mode: i32,
}

fn api(item: web::Path<ApiRequest>, state: Cache) -> HttpResponse {
    let data = state.read().expect("Can't read cache!");
    let resp: Vec<_> = data
        .iter()
        .filter(|e| e.season == item.season && e.mode == item.mode)
        .collect();
    HttpResponse::Ok().json(resp)
}
