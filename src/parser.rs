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
use json::JsonValue;
/*
Copyright 2017,2018 Aron Heinecke

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

use json;

use regex::Regex;

use error::Error;
use Clan;
use Member;

// https://regex101.com/r/XsoG5T/4
const REGEX_WINS: &'static str = r#"<div class="match_details">(\d+)<br><span>Wins</span>"#;
const REGEX_LOSSES: &'static str = r#"<div class="match_details">(\d+)<br><span>Losses</span>"#;
const REGEX_DRAWS: &'static str = r#"<div class="match_details">(\d+)<br><span>Draws</span>"#;
const REGEX_MEMBERS: &'static str = r#"<div>(\d+).?clan.members"#;

const KEY_MEMBERSHIP: &'static str = "position_title";

/// Parse a raw member json request to a vec of Members
pub fn parse_all_member(input: &str) -> Result<(Vec<Member>, i32), Error> {
    //Result<Vec<Member>,Error> {
    let mut parsed = json::parse(input)?;
    let total = get_i32_value(&mut parsed, "Total_Count")?;
    let mut pmembers = parsed["members"].take();

    let members: Vec<Member> = pmembers
        .members_mut()
        .map(|x| parse_member(x)) // Result<Option<Member>,Error>
        .filter_map(|r| // change it, to be usable by filter_map
            match r {
                Ok(Some(x)) => Some(Ok(x)),
                Err(x) => Some(Err(x)),
                _ => None,
            })
        .collect::<Result<Vec<Member>, _>>()?;
    Ok((members, total))
}

/// Parse json object to member,
/// moving the value
fn parse_member(input: &mut JsonValue) -> Result<Option<Member>, Error> {
    if check_is_member(input, KEY_MEMBERSHIP) {
        Ok(Some(Member {
            name: get_string_value(input, "name")?,
            id: get_i32_value(input, "USN")?,
            exp: get_i32_value(input, "xp_point")?,
            contribution: get_i32_value(input, "contribution")?,
        }))
    } else {
        Ok(None)
    }
}

/// Parse a raw clan http request to a clan data structure
pub fn parse_clan(input: &str) -> Result<Clan, Error> {
    let regex_wins = Regex::new(REGEX_WINS)?;
    let regex_draws = Regex::new(REGEX_DRAWS)?;
    let regex_losses = Regex::new(REGEX_LOSSES)?;
    let regex_members = Regex::new(REGEX_MEMBERS)?;

    let wins: u16;
    if let Some(caps) = regex_wins.captures(input) {
        let capture = caps.get(1).unwrap();
        wins = capture.as_str().parse::<u16>()?
    } else {
        return Err(Error::Parser(String::from("unable to parse wins")));
    }

    let draws: u16;
    if let Some(caps) = regex_draws.captures(input) {
        let capture = caps.get(1).unwrap();
        draws = capture.as_str().parse::<u16>()?
    } else {
        return Err(Error::Parser(String::from("unable to parse draws")));
    }

    let losses: u16;
    if let Some(caps) = regex_losses.captures(input) {
        let capture = caps.get(1).unwrap();
        losses = capture.as_str().parse::<u16>()?
    } else {
        return Err(Error::Parser(String::from("unable to parse losses")));
    }

    let members: u8;
    if let Some(caps) = regex_members.captures(input) {
        let capture = caps.get(1).unwrap();
        members = capture.as_str().parse::<u8>()?
    } else {
        return Err(Error::Parser(String::from("unable to parse members")));
    }

    let clan = Clan {
        members: members,
        wins: wins,
        losses: losses,
        draws: draws,
    };
    Ok(clan)
}

/// Helper function to get a string value from a json object
/// Returns an error if the key is non existent or the value no string
fn get_string_value(input: &mut JsonValue, key: &str) -> Result<String, Error> {
    let mut val = get_value(input, key)?;
    val.take_string()
        .ok_or(Error::Parser(format!("Value for {} is no string", key)))
}

/// Helper function to get a i32 from a provided json object
/// Returns an error if the key is non existent or the value is no i32
fn get_i32_value(input: &mut JsonValue, key: &str) -> Result<i32, Error> {
    let val = get_value(input, key)?;
    val.as_i32()
        .ok_or(Error::Parser(format!("Value for {} is no i32", key)))
}

/// Helper function to get a u32 from a provided json object
/// Returns an error if the key is non existent or the value is no u32
#[allow(dead_code)]
fn get_u32_value(input: &mut JsonValue, key: &str) -> Result<u32, Error> {
    let val = get_value(input, key)?;
    val.as_u32()
        .ok_or(Error::Parser(format!("Value for {} is no u32", key)))
}

/// Helper function to get a json sub-object under the provided key
/// Returns an error if the key has no value
fn get_value(input: &mut JsonValue, key: &str) -> Result<JsonValue, Error> {
    let val = input[key].take();
    if val == JsonValue::Null {
        Err(Error::Parser(format!("No value for {}", key)))
    } else {
        Ok(val)
    }
}

fn check_is_member(input: &mut JsonValue, key: &str) -> bool {
    let val = input[key].take();
    val != JsonValue::Null
}

#[cfg(test)]
mod test {
    use super::check_is_member;
    use super::parse_member;
    use super::KEY_MEMBERSHIP;
    use super::*;
    use json;
    use Clan;
    use Member;

    /// Test full parsing of parse_all_member
    #[test]
    fn parse_all_member_test() {
        let input = include_str!("test_json_members.json");
        let mut var = Vec::new();
        var.push(Member {
            name: String::from("Dr.Alptraum"),
            id: 9926942,
            exp: 10826457,
            contribution: 6830,
        });
        let (r, total) = parse_all_member(input).unwrap();
        assert_eq!(var, r);
        assert_eq!(56, total);
    }

    /// Test parsing of single member function parse_member
    #[test]
    fn parse_member_test() {
        let input = include_str!("test_json_member_valid.json");
        let mut parsed = json::parse(input).unwrap();
        let mut pmember = parsed["members"][0].take();
        let output = parse_member(&mut pmember).unwrap();
        let mem_thomas = Member {
            name: String::from("Dr.Alptraum"),
            id: 9926942,
            exp: 10826457,
            contribution: 6830,
        };
        assert_eq!(output, Some(mem_thomas));
    }

    /// Test for non-member detection
    #[test]
    fn parse_member_invalid_test() {
        let input = include_str!("test_json_member_invalid.json");
        let mut parsed = json::parse(input).unwrap();
        let mut pmember = parsed["members"][0].take();
        let output = parse_member(&mut pmember).unwrap();
        assert_eq!(output, None);
    }

    #[test]
    fn check_member_test() {
        let input = include_str!("test_json_members.json");
        let mut parsed = json::parse(input).unwrap();
        let mut valid_member = parsed["members"][0].take();
        let mut invalid_member = parsed["members"][1].take();
        assert_eq!(true, check_is_member(&mut valid_member, KEY_MEMBERSHIP));
        assert_eq!(false, check_is_member(&mut invalid_member, KEY_MEMBERSHIP));
    }

    /// Test clan parsing parse_clan
    #[test]
    fn parse_clan_test() {
        let input = include_str!("test_http_clan.txt");
        let clan = Clan {
            members: 41,
            wins: 11495,
            losses: 6872,
            draws: 449,
        };
        let parsed_clan = parse_clan(input).unwrap();
        assert_eq!(parsed_clan, clan);
    }
}
