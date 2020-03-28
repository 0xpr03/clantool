use std::hash::Hash;
use std::hash::Hasher;

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

/// TS client
#[derive(Debug)]
pub struct TsClient {
    pub name: String,
    pub db_id: i32,
    pub channel: i32,
    pub groups: Vec<i32>,
}

/// Custom hash impl to allow dedup of multiple connections
impl Hash for TsClient {
    fn hash<H: Hasher>(&self, state: &mut H) {
        self.db_id.hash(state);
    }
}

/// See hash impl
impl PartialEq for TsClient {
    fn eq(&self, other: &Self) -> bool {
        self.db_id == other.db_id
    }
}
impl Eq for TsClient {}

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
