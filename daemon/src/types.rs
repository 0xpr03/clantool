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

use std::hash::Hash;
use std::hash::Hasher;

/// Ts channel ID
pub type TsChannelID = i32;
/// Ts client DB-ID
pub type TsClDBID = i32;
/// Ts group ID
pub type TsGroupID = i32;
/// Ts client connection ID
pub type TsConID = i32;

/// Clan data structure
#[derive(Debug, PartialEq, PartialOrd)]
pub struct Clan {
    pub members: u8,
    pub wins: u16,
    pub losses: u16,
    pub draws: u16,
}

/// Member data structure
#[derive(Debug, PartialEq, PartialOrd, Clone)]
pub struct Member {
    pub name: String,
    pub id: i32,
    pub exp: i32,
    pub contribution: i32,
}

/// Ts client activity
#[derive(Debug, PartialEq)]
pub struct TsActivity {
    pub client: TsClDBID,
    pub time: i32,
    pub channel: TsChannelID,
}

/// TS client
#[derive(Debug)]
pub struct TsClient {
    pub name: String,
    /// Database ID
    pub cldbid: TsClDBID,
    /// Connection ID
    pub conid: TsConID,
    pub channel: TsChannelID,
    pub groups: Vec<i32>,
}

/// Custom hash impl to allow dedup of multiple connections
impl Hash for TsClient {
    fn hash<H: Hasher>(&self, state: &mut H) {
        self.cldbid.hash(state);
    }
}

/// See hash impl
impl PartialEq for TsClient {
    fn eq(&self, other: &Self) -> bool {
        self.cldbid == other.cldbid
    }
}
impl Eq for TsClient {}

#[derive(Debug)]
pub struct Channel {
    pub id: TsChannelID,
    pub name: String,
}

/// Left member data structure
#[derive(Debug, PartialEq, PartialOrd)]
pub struct LeftMember {
    pub id: i32,
    // account name, can be None if same day join&leave
    pub name: Option<String>,
    // membership nr which can be closed
    pub membership_nr: Option<i32>,
}

impl LeftMember {
    /// Return account-name of member or spacer if no name found
    pub fn get_name(&self) -> &str {
        match self.name {
            Some(ref v) => v,
            None => "<unnamed>",
        }
    }
}
