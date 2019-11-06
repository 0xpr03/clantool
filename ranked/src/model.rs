use chrono::NaiveDateTime;
use serde::Deserialize;
use serde::Serialize;

pub type USN = i32;
pub type Date = NaiveDateTime;

#[derive(Deserialize)]
pub struct PlayerResponse {
    #[serde(rename(deserialize = "dsRankedStatsResult"))]
    pub ranked: Vec<RankedEntryRaw>,
}

/// Raw entry from z8, can contain gibberish null values
/// Happens on new seasons etc
#[derive(Debug, Deserialize)]
pub struct RankedEntryRaw {
    #[serde(rename(deserialize = "USN"))]
    pub usn: USN,
    #[serde(rename(deserialize = "MODE_NO_DESC"))]
    pub mode_name: Option<String>,
    #[serde(rename(deserialize = "MODE_NO"))]
    pub mode: Option<i32>,
    #[serde(rename(deserialize = "TIER_GROUP_NAME"))]
    pub rank_name: Option<String>,
    #[serde(rename(deserialize = "TIER_GROUP_ID"))]
    pub rank: Option<i32>,
    #[serde(rename(deserialize = "TIER_STAGE_ID"))]
    pub subrank: Option<i32>,
    #[serde(rename(deserialize = "PARTKEY_SEASON_NO"))]
    pub season: i32,
}

/// Cleaned, valid entry
#[derive(Debug, Serialize)]
pub struct RankedEntry {
    pub usn: USN,
    pub mode_name: String,
    pub mode: i32,
    pub rank_name: String,
    pub rank: i32,
    pub subrank: i32,
    pub season: i32,
}

/// Json API entry
#[derive(Debug, Serialize)]
pub struct APIRankedEntry {
    pub usn: USN,
    pub player_name: String,
    pub mode_name: String,
    pub mode: i32,
    pub rank_name: String,
    pub rank: i32,
    pub subrank: i32,
    pub season: i32,
}

/*
{
    "USN": 7222040,
    "RANKING": 3235,
    "MODE_NO": 2,
    "LOSE": 19,
    "ROWPOS": 2,
    "MODE_NO_DESC": "SND",
    "TIER_GROUP_NAME": "Master",
    "HEADSHOT": 200,
    "WIN": 57,
    "TIER_GROUP_ID": 6,
    "MODE_NO_DISPLAY_NAME": "SND",
    "MEDAL": 31,
    "DEATH": 101,
    "MATCH_COUNT": 76,
    "TIER_STAGE_ID": 1,
    "KILL": 298,
    "RANK_SCORE": 3288,
    "PARTKEY_SEASON_NO": 9
},
*/
