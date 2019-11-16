# Clantool [![Build Status](https://travis-ci.com/0xpr03/clantool.svg?branch=master)](https://travis-ci.com/0xpr03/clantool)

Full featured management tool for CF NA groups.

- Join,Leave,Account switch tracking
- suspension reports
- automated data crawling from NA website
- automatic rename detection
- second account linkage
- detailed activity reports between dates or weekly, individual and in summary
- sortable after away,freshman,cp,exp,names
- per-individual comments for notes in the weekly view, for decision making
- reports over recent membership changes
- auto leave detection
- import of older sheets
- detection of unassigned, relevant ts3 identities

[Screenshots](/doc)
[Scheme](scheme_final.png)

## Structure

The project consists of three parts, all connected with one Database.
The worker backend handles data gathering & triggers leave detections. It also comes with some maintenance tools build in. Furthermore it scans daily for ts3 member-identities which are not assigned to a player, to avoid incorrect reports.  
On the other hand is the frontend that displays the data, renders the reports as well as forms to enter new Members & Leaves.  
The third application is [ts3-manager](https://github.com/0xpr03/ts3-manager), used for ts3 data gathering.  

[website/](/website) contains the website frontend
[daemon/](/daemon) is the sourcecode for the rust backend

[ranked/](/ranked) is an additional module that allows ranked data gathering for player-compatibility views

## Development & Testing
For running DB tests a mariadb server instance wth an empty Database is required.  
The default values are `root`:`root` for login and Database `test`.

You can override the login with the following ENV variables:  
`TEST_DB_USER`  
`TEST_DB_PW`  
Specifying only the user is interpreted as paswordless login.

Tests have to run as `cargo test -- --test-threads=1` as the DB doesn't allow for parallel tests.

## Copyright
Aron Heinecke 2017-2019 under the  
Apache License, Version 2.0
