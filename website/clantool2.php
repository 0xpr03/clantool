<?php
/*
 * !
 * Copyright 2018-2020 Aron Heinecke
 * aron.heinecke@t-online.de
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

error_reporting(E_ALL);
define('DATE_FROM', 'clantool_dateFrom');
define('DATE_TO', 'clantool_dateTo');
define('DATE_FROM_WEEKLY','clantool_dateFrom_W');
define('DATE_TO_WEEKLY','clantool_dateTo_W');
define('DATE_FROM_TS','clantool_dateFromTS');
define('DATE_TO_TS','clantool_dateToTS');
define('DATE_FROM_LOG','clantool_dateFromLog');
define('DATE_TO_LOG','clantool_dateToLog');
define('DATE_FROM_OVERVIEW','clantool_dateFromOverview');
define('DATE_TO_OVERVIEW','clantool_dateToOverview');
// difference: show data base
define('SHOW_BASE', 'clantool_showBase');
// difference week: show non current member rows
define('SHOW_NONMEMBERS','clantool_showNonMember');

define('C_TOTAL_ROWS', 'clantool_rows');
define('C_TOTAL_NAMES', 'clantool_names');
define('C_TOTAL_IDS', 'clantool_ids');
define('C_DIFFERENCE', 'clantool_c_overview');
define('C_DATE1', 'clantool_c_date1');
define('C_DATE2', 'clantool_c_date2');

// Maximum CP per day possible
define('MAX_CP_DAY', 10);
// EXP per CP
define('EXP_TO_CP', 500);

// ts diff minimum for display (privacy)
define('TS_DIFF_MIN',7);
// ignore ts client_id on statistics in General view [bot]
define('TS_IGNORE_ID', 18106);
// for checking up/down
define('TS_REFERENCE_ACCOUNT',11825577);

define('SITE', 'clantool2'); // site value
define('VIEW','view'); // view key for requests
define('DEFAULT_VIEW','changes');
define('SITE_MEMBER','memberDetail');
define('DATE_MIN','2016-11-31'); // minimum date for EXP/CP
define('HOUR_MIN',10); // hour of crawl per day, selecting previous day if time < HOUR_MIN
define('URL', 'index.php'); // base file
define('NAME_MISSING_DEFAULT','<unnamed>'); // value for missing name placeholder
define('MEMBER_DETAIL_URL','?site='.SITE.'&view=memberDetail&id=');
define('MEMBER_DIFF_URL','?site='.SITE.'&view=memberDiff&id=');
define('MEMBER_TS_URL','?site='.SITE.'&view=ts3&id=');
define('DIFFERENCE_URL','?site='.SITE.'&view=difference');

define('MAX_DIFF_COMMENT_CHAR',70); // max characters for weekly comment field

define('Z8PROFILE','http://crossfire.z8games.com/profile/');

define('PERM_CLANTOOL_TEST','clantoolTest');

// db keys, read by backend
define('KEY_AUTO_LEAVE', 'auto_leave_enable');
define('KEY_LEAVE_CAUSE','auto_leave_message');
define('KEY_TS3_CHECK','ts3_check_identities_enable');
define('KEY_TS3_MEMBER_GROUPS','ts3_check_member_groups');

define('KEY_TS3_GUEST_NOTIFY_ENABLE','ts3_guest_notify_enable');
define('KEY_TS3_GUEST_WATCHER_GROUP','ts3_guest_watcher_group');
define('KEY_TS3_GUEST_GROUP','ts3_guest_group');
define('KEY_TS3_GUEST_POKE_MSG','ts3_guest_poke_msg');
define('KEY_TS3_GUEST_CHANNEL','ts3_guest_channel');

function getContent() {
    require 'includes/clantool.view.php';
    getCTTemplate();
}

function increaseSite($site) {
    \main\getDB()->increaseSiteStats('clantool2_' . $site, $_SESSION[\main\C_USER][\main\C_U_UID]);
}

/**
 * Wrapper for permission checking.
 * @param perm String Permission to check for
 * @return true if user has permission
 */
function hasPermission($perm) {
    return \hasPerm($perm);
}

function handleDateDifference() {
    if(isset($_REQUEST['dateFrom']) && isset($_REQUEST['dateTo'])){
        $_SESSION[DATE_FROM] = $_REQUEST['dateFrom'];
        $_SESSION[DATE_TO] = $_REQUEST['dateTo'];
    }
    if(!(isset($_SESSION[DATE_FROM]) || isset($_SESSION[DATE_TO]))){
        if(date('H') < HOUR_MIN){
            $_SESSION[DATE_FROM] = date('Y-m-d', strtotime('-8 days'));
            $_SESSION[DATE_TO] = date('Y-m-d', strtotime('-1 days'));
        }else{
            $_SESSION[DATE_FROM] = date('Y-m-d', strtotime('-7 days'));
            $_SESSION[DATE_TO] = date('Y-m-d', strtotime('now'));
        }
    }
}

/**
 * Handle Daterange session variables
 * @param $dateFrom dateFrom name in session
 * @param $date
 */
function handleDateSes($dateFromR,$dateToR,$dateFrom,$dateTo,$diff) {
    if(isset($_REQUEST[$dateFromR]) && isset($_REQUEST[$dateToR])){
        $_SESSION[$dateFrom] = $_REQUEST[$dateFromR];
        $_SESSION[$dateTo] = $_REQUEST[$dateToR];
    }
    if(!(isset($_SESSION[$dateFrom]) || isset($_SESSION[$dateTo]))){
        $_SESSION[$dateFrom] = date('Y-m-d', strtotime($diff));
        $_SESSION[$dateTo] = date('Y-m-d', strtotime('now'));
    }
}

function handleDateWeekly() {
    handleDateSes('dateFromW','dateToW',DATE_FROM_WEEKLY,DATE_TO_WEEKLY,'-6 weeks');
}

function handleDateTS() {
    handleDateSes('dateFromTS','dateToTS',DATE_FROM_TS,DATE_TO_TS,'-6 weeks');
}

function handleDateLog() {
    handleDateSes('dateFromLog','dateToLog',DATE_FROM_LOG,DATE_TO_LOG,'-2 weeks');
}

function handleDateOverview() {
    handleDateSes('dateFromOverview','dateToOverview',DATE_FROM_OVERVIEW,DATE_TO_OVERVIEW,'-6 weeks');
}

function handleCacheOld() {
    handleDateDifference();
    
    $date1 = $_SESSION[DATE_FROM];
    $date2 = $_SESSION[DATE_TO];
    
    if($date1 != $_SESSION[C_DATE1] || $date2 != $_SESSION[C_DATE2]) {
        $_SESSION[C_DIFFERENCE] = null;
    }
    $_SESSION[C_DATE1] = $date1;
    $_SESSION[C_DATE2] = $date2;
}

//@Override
function getAjax(){
    require 'includes/clantool.ajax.php';
    ajax();
}


function checkboxStatus($checked) {
    if ($checked) {
        echo 'checked="checked"';
    }
}

//@Override
function getTitle() {
    return 'Clantool 2';
}
//@Override
function getHead() {?>
<script defer src="js/moment.min.js" type="text/javascript"></script>
<script defer src="js/jquery.tablesorter.combined.min.js" type="text/javascript"></script>
<script defer src="js/Chart.min.js" type="text/javascript"></script>
<script defer src="js/plotly-basic.min.js"></script>
<link rel="stylesheet" href="css/clantool.css">
<script defer src="https://static.proctet.net/js/fontawesome-all.min.js" type="text/javascript"></script>
<script defer src="js/daterangepicker.js" type="text/javascript"></script>
<link defer rel="stylesheet" href="css/daterangepicker.css">
<script defer src="https://static.proctet.net/js/select2.min.js" type="text/javascript"></script>
<link defer rel="stylesheet" href="https://static.proctet.net/css/select2.min.css">
<link defer rel="stylesheet" href="css/bootstrap-datetimepicker.min.css">
<script defer src="js/bootstrap-datetimepicker.min.js" type="text/javascript"></script>
<script src="js/clantool.js" type="text/javascript"></script>
<?php }
