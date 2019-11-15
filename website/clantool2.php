<?php
/*
 * !
 * Copyright 2018-2019 Aron Heinecke
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
define('KEY_TS3_REMOVE','ts3_removal_enable');
define('KEY_TS3_WHITELIST','ts3_whitelist');

function getContent() {
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

function getCTTemplate() {
    if (!isset($_SESSION[SHOW_BASE])) {
        $_SESSION[SHOW_BASE] = false;
    }
    if (!isset($_SESSION[SHOW_NONMEMBERS])) {
        $_SESSION[SHOW_NONMEMBERS] = false;
    }
    
    if(!isset($_SESSION[C_DATE1]) || !isset($_SESSION[C_DATE2])) {
        $_SESSION[C_DATE1] = 0;
        $_SESSION[C_DATE2] = 0;
        $_SESSION[C_DIFFERENCE] = 'a';
    }
    
    if(!isset($_GET[VIEW])){
        $_GET[VIEW] = DEFAULT_VIEW;
    }
    ?>
    <div class="column col-sm-3 col-xs-1 sidebar-offcanvas" id="sidebar">
        <ul class="nav" id="menu">
            <?=generateViewLink('general','fas fa-chart-bar fa-lg','General')?>
            <?=generateViewLink('difference','fas fa-table fa-lg','Difference')?>
            <?=generateViewLink('differenceWeekly','fas fa-th-list fa-lg','Difference Weekly')?>
            <?=generateViewLink('memberDiff','fas fa-chart-area fa-lg', 'Member Difference')?>
            <?=generateViewLink('memberJoin','fas fa-user-plus fa-lg','Member Join')?>
            <?=generateViewLink('accChange','fas fa-user-friends fa-lg','Account Change')?>
            <?=generateViewLink('memberLeave','fas fa-user-times fa-lg','Member Leave')?>
            <?=generateViewLink('away','fas fa-clock fa-lg','Member Away')?>
            <?=generateViewLink(SITE_MEMBER,'fas fa-address-book fa-lg','Member')?>
            <?=generateViewLink('changes','fas fa-users fa-lg','Joins &amp; Leaves')?>
            <?=generateViewLink('ts3','fas fa-chart-bar fa-lg','TS Activity')?>
            <?=generateViewLink('tsTop','fas fa-list-ol fa-lg','TS3 Toplist')?>
            <?=generateViewLink('database','fas fa-server fa-lg','Status')?>
            <?=generateViewLink('log','far fa-list-alt fa-lg','System Log')?>
            <?=generateViewLink('settings','fas fa-sliders-h fa-lg','System Settings')?>
            <?php
            if(hasPermission(PERM_CLANTOOL_TEST)) { // alpha/beta views
                //echo generateViewLink('general','fas fa-chart-bar fa-lg','General');
            }?>
        </ul>
    </div>
    <div class="column col-sm-9 col-xs-11 container-fluid" id="main">
    <?php        
        $view = $_GET[VIEW];
        $ok_view = true;
        switch($_GET[VIEW]){
            case 'difference':
                getDifferenceView();
                break;
            case 'differenceWeekly':
                getDifferenceWeeklyView();
                break;
            case 'memberJoin':
                getMemberJoinView();
                break;
            case 'accChange':
                getAccountChangeView();
                break;
            case 'memberLeave':
                getMemberLeaveView();
                break;
            case 'database':
                getDatabaseView();
                break;
            case SITE_MEMBER:
                getMemberDetailView();
                break;
            case 'memberDiff':
                getMemberDifferenceView();
                break;
            case 'away':
                getAwayView();
                break;
            case 'tsTop':
                getTSTopView();
                break;
            case 'general':
                getGeneralView();
                break;
            case 'ts3':
                getTSView();
                break;
            case DEFAULT_VIEW:
                getMSChangesView();
                break;
            case 'log':
                getLogView();
                break;
            case 'settings':
                getSettingsView();
                break;
            default:
                $ok_view = false;
                echo '<h3>404 Not found !</h3>';
                http_response_code(404);
                break;
        }
        if($ok_view){
            increaseSite($view);
        }
    ?>
        <script type="text/javascript">
        const VAR_SITE = "<?=SITE?>"; // this sites for site= parameter on ajax
        const EXP_REQUIRED_CP = <?=EXP_TO_CP?>; // EXP per CP
        const CP_MAX = <?=MAX_CP_DAY?>; // max CP per day
        const EXP_MAX_CP = EXP_REQUIRED_CP * CP_MAX; // max exp for max CP per day
        const SITE_MEMBER = "<?=SITE_MEMBER?>";
        const URL = "<?=URL?>"; // sites URL to do ajax on (relative)
        const DATE_FORMAT = "YYYY-MM-DD";
        const P_ACC_ID = 'id';
        const TS_DIFF_MIN = <?=TS_DIFF_MIN?>;
        var charts = []; // keep track of charts for cleanup
        const MONTH_NAMES = [
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
        ];
        const DAYS_OF_WEEK = [
            "Su",
            "Mo",
            "Tu",
            "We",
            "Th",
            "Fr",
            "Sa"
        ];
        
        // add map if not existing
        if (!Array.prototype.map)
        {
            Array.prototype.map = function(fun /*, thisp*/)
            {
                var len = this.length;
                
                if (typeof fun != "function")
                throw new TypeError();
                
                var res = new Array(len);
                var thisp = arguments[1];
                
                for (var i = 0; i < len; i++)
                {
                    if (i in this)
                    res[i] = fun.call(thisp, this[i], i, this);
                }
                return res;
            };
        }
        
        function cleanupCharts(){
            var length = charts.length;
            for (var i = 0; i < length; i++ ){
                charts[i].destroy();
            }
            charts = [];
        }
        
        function escapeHtml(unsafe) {
            if(unsafe == null)
                return "";
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        function ts3Select(element){
            element.select2({
                ajax: {
                    url: URL,
                    type: 'get',
                    dataType: 'json',
                    delay: 500, // ms
                    data: function(params) {
                        var query = {
                            'site' : VAR_SITE,
                            'ajaxCont' : 'data',
                            'type' : 'ts3-search-select2',
                            'key' : params.term
                        }
                        
                        return query;
                    }
                },
                placeholder: "TS3 Account Namenssuche",
                allowClear: true
            });
        }
        
        function formatMemberDetailLink(id,text){
            return '<a href="'+escapeHtml('<?=MEMBER_DETAIL_URL?>'+id)+'">'+text+'</a>';
        }
        
        function setURLParameter(param){
            var url = window.location.href;
            var obj = "";
            for (var key in param) {
                url = updateURLParameter(url,key,param[key]);
                obj += param[key];
            }
            window.history.replaceState(obj,"",url);
        }
        
        function setURLParameterHistory(obj,param,val){
            window.history.pushState(obj,"",
            updateURLParameter(window.location.href,param,val));
        }
        
        function updateURLParameter(url, param, paramVal){
            var newAdditionalURL = "";
            var tempArray = url.split("?");
            var baseURL = tempArray[0];
            var additionalURL = tempArray[1];
            var temp = "";
            if (additionalURL) {
                tempArray = additionalURL.split("&");
                for (var i=0; i<tempArray.length; i++){
                    if(tempArray[i].split('=')[0] != param){
                        newAdditionalURL += temp + tempArray[i];
                        temp = "&";
                    }
                }
            }

            var rows_txt = temp + "" + param + "=" + paramVal;
            return baseURL + "?" + newAdditionalURL + rows_txt;
        }
        
        function getUrlParameter(sParam) {
            var sPageURL = decodeURIComponent(window.location.search.substring(1)),
                sURLVariables = sPageURL.split('&'),
                sParameterName,
                i;

            for (i = 0; i < sURLVariables.length; i++) {
                sParameterName = sURLVariables[i].split('=');

                if (sParameterName[0] === sParam) {
                    return sParameterName[1] === undefined ? true : sParameterName[1];
                }
            }
        };
        
        function accountSelect(element){
            element.select2({
                ajax: {
                    url: URL,
                    type: 'get',
                    dataType: 'json',
                    delay: 600, // ms
                    data: function(params) {
                        var query = {
                            'site' : VAR_SITE,
                            'ajaxCont' : 'data',
                            'type' : 'member-search-select2',
                            'key' : params.term
                        }
                        
                        return query;
                    }
                },
                placeholder: "Account ID/Namensuche",
                allowClear: true
            });
        }
        
        const fixedTableClass = '.table';
        
        function updateTableTool() {
            $(fixedTableClass).trigger('update');
        }
        
        function initTableTool() {
            console.log("default sorter");
            var myTextExtraction = function(node) 
            {
                return node.innerHTML;
            };
            initTableTool(myTextExtraction);
        }
        
        function initTableTool(extractor) {
            console.log("extractor sorter..");
            $(fixedTableClass).tablesorter({
                textExtraction: extractor,
                widgets: ["stickyHeaders"],
                widgetOptions : {
                    stickyHeaders : "fixed-header" // background fix class
                }
            });
        }
        
        // jquery function to allow editing of td's with payload function
        // function called upon change, receiving td & new value
        $.fn.makeEditable = function(payload,maxlength) {
            $(this).on('click',function(){
                if($(this).find('input').is(':focus')) return this;
                var cell = $(this);
                var content = $(this).html();
                var lAddition = maxlength == null ? '' : 'maxlength="'+maxlength+'"';
                $(this).html('<input type="text" class="form-control" value="' + $(this).html() + '"'+lAddition+' />')
                .find('input')
                .trigger('focus')
                .on({
                    'blur': function(){
                        $(this).trigger('closeEditable');
                    },
                    'keyup':function(e){
                        if(e.which == '13'){ // enter
                            $(this).trigger('saveEditable');
                        } else if(e.which == '27'){ // escape
                            $(this).trigger('closeEditable');
                        }
                    },
                    'closeEditable':function(){
                        cell.html(content);
                    },
                    'saveEditable':function(){
                        content = $(this).val();
                        $(this).trigger('closeEditable');
                        payload(cell,content);
                    }
                });
            });
            return this;
        }
        
        /**
         * Format ajax fail(data) objects
         */
        function formatErrorData(data) {
            return '<h3><span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>'
                        +'<span class="sr-only">Error:</span>Error:</h3><br><b>Status:</b> '+data.status+' '+data.statusText+'<br>'+data.responseText;
        }
        </script>
        Copyright Aron Heinecke 2017-2019 <a href="https://github.com/0xpr03/clantool">Sourcecode</a>
    </div>
    
<?php }

/**
 * Generate view link menu entry
 * @param subSite view site tag used internally
 * @param icon fontawesome icon to use
 * @param name Name to Display for link
 * @return HTML for given view menu entry
 */
function generateViewLink($subSite, $icon, $name) {
    return '<li ' . ($_GET[VIEW] == $subSite ? 'class="active" >' : '>' )
    . '<a href="index.php?site='. SITE .'&amp;'.VIEW.'=' . $subSite .'"><i class="' . $icon . '"></i> <span class="collapse in hidden-xs">' . $name . "</span></a></li>\n";
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
    switch($_REQUEST['ajaxCont']){
        case 'data':
            require 'includes/clantool.db.inc.php';
            $clanDB = new clanDB();
            
            header('Content-type:application/json;charset=utf-8');
            
            switch($_REQUEST['type']) {
            case 'log':
                handleDateLog();
                echo json_encode(
                    $clanDB->loadLog($_SESSION[DATE_FROM_LOG],$_SESSION[DATE_TO_LOG])
                );
                break;
            case 'settings-load':
                echo json_encode(
                    array(
                        'leave-cause' => $clanDB->getSetting(KEY_LEAVE_CAUSE),
                        'ts3-removal' => $clanDB->getSetting(KEY_TS3_REMOVE),
                        'ts3-whitelist' => $clanDB->getSetting(KEY_TS3_WHITELIST),
                        'leave-detection' => $clanDB->getSetting(KEY_AUTO_LEAVE),
                    )
                );
                break;
            case 'settings-set':
                // manually to disallow sender dictating key names
                $clanDB->setSetting(KEY_LEAVE_CAUSE,$_POST['leave-cause']);
                $clanDB->setSetting(KEY_TS3_REMOVE,isset($_POST['ts3-removal']));
                $clanDB->setSetting(KEY_TS3_WHITELIST,$_POST['ts3-whitelist']);
                $clanDB->setSetting(KEY_AUTO_LEAVE,isset($_POST['leave-detection']));
                
                echo json_encode(true);
                break;
            case 'difference-weekly':
                handleDateWeekly();
                if(isset($_REQUEST['showNonMember'])){
                    $_SESSION[SHOW_NONMEMBERS] = $_REQUEST['showNonMember'];
                }
                /*
                 * because of difference & 1-day shift of data tue-thursday = mon - fr ($start-1 - $end+1)
                 * => 1 week sun-sat of data = previous sat - sun
                 */
                $end = new DateTime($_REQUEST['dateToW']);
                $begin = new DateTime($_REQUEST['dateFromW']);
                $begin->modify('sunday'); // start day of week
                $end = $end->modify('+1 day'); // record from today is data from yesterday
                $FORMAT = "Y-m-d";

                $interval = new DateInterval('P1W');
                $daterange = new DatePeriod($begin, $interval ,$end);
                
                $result = array();
                $data = array();
                foreach($daterange as $date) {
                    //// echo $date->format($FORMAT);
                    // $result[id][date] = ..
                    $chunkstart = $date->format($FORMAT);
                    $chunkend = $date->modify('+7 day')->format($FORMAT); // $chunkend+1
                    $chunk = $clanDB->getDifferenceSum($chunkstart,
                    $chunkend);
                    
                    //var_dump($chunk);
                    if($chunk != null) {
                        $result['date'][] = array(
                            'start' => $chunkstart,
                            'end' => $chunkend);
                        foreach($chunk as $row) {
                            $data[$row['id']][$chunkend] = $row;
                        }
                    }
                }
                $result['data'] = $data;
                
                echo json_encode($result);
                break;
            case 'difference-json':
                
                handleCacheOld();
                if(isset($_REQUEST['showBase'])){
                    $_SESSION[SHOW_BASE] = $_REQUEST['showBase'];
                }
                
                $NO_CACHE = true;
                
                if($NO_CACHE || $_SESSION[C_DIFFERENCE] === null){
                    $_SESSION[C_DIFFERENCE] = json_encode($clanDB->getDifference($_SESSION[DATE_FROM],$_SESSION[DATE_TO]));
                }
                echo $_SESSION[C_DIFFERENCE];
                break;
            case 'update-diff-comment':
                $clanDB->updateDiffComment($_POST['id'],$_POST['comment']);
                echo json_encode(true);
                break;
            case 'member-difference':
                handleDateDifference();
                
                echo json_encode($clanDB->getMemberChange($_SESSION[DATE_FROM],$_SESSION[DATE_TO],$_REQUEST['id']));
                
                break;
            case 'ts3-stats':
                handleDateOverview();
                
                echo json_encode($clanDB->getTS3Stats($_SESSION[DATE_FROM_OVERVIEW],$_SESSION[DATE_TO_OVERVIEW]));
                break;
            case 'overview-graph':
                handleDateOverview();
                
                echo json_encode($clanDB->getOverview($_SESSION[DATE_FROM_OVERVIEW],$_SESSION[DATE_TO_OVERVIEW]));
                
                break;
            case 'member-search-select2':
                if(isset($_REQUEST['key']) && $_REQUEST['key'] != null) {
                    if(ctype_digit($_REQUEST['key'])) {
                        $result = array(
                        'results' => $clanDB->getMemberByExactID($_REQUEST['key']),
                        'pagination' => array('more' => false),
                        );
                    } else {
                        $result = array(
                        'results' => $clanDB->getMemberByName($_REQUEST['key']),
                        'pagination' => array('more' => false),
                        );
                    }
                } else {
                    $result = array(
                        'results' => array(),
                        'pagination' => array('more' => false),
                    );
                }
                echo json_encode($result);
                break;
            case 'account-select': // check for valid ID & send select2 entry
                $accs = $clanDB->getMemberByExactID($_REQUEST['id']);
                $res = array(
                'acc' => null,
                'valid' => !empty($accs)
                );
                if($res['valid']){
                    $res['acc'] = $accs[0];
                }
                echo json_encode($res);
                break;
            case 'member-data':
                $id = $_REQUEST['id'];
                $addition = $clanDB->getMemberAddition($id);
                $res = array(
                    'id' => $id,
                    'membership' => $clanDB->getMembershipData($id),
                    'ts3' => $clanDB->getMemberTSRelations($id),
                    'trials' => $clanDB->getMemberTrials($id),
                    'trial' => $clanDB->getMemberTrialOpen($id),
                    'name' => $addition['name'],
                    'comment' => $addition['comment'],
                    'vip' => $addition['vip'],
                    'secAccs' => $clanDB->getSecondAccounts($id),
                    'afk' => $clanDB->getAFKs($id),
                    'caution' => $clanDB->getCautions($id),
                    'names' => $clanDB->getAccountNames($id)
                );
                echo json_encode($res);
                break;
            case 'membership-edit':
                $nr = $_POST['nr'];
            case 'membership-add': // NO break, for memberDetail editing, not join form!
                $id = $_POST['id'];
                $from = $_POST['from'];
                
                $kick = isset($_POST['kicked']);
                
                if(isset($_POST['to'])){
                    $cause = $_POST['cause'];
                    $to = $_POST['to'];
                }else{
                    $cause = null;
                    $to = null;
                }
                
                
                $clanDB->startTransaction();
                if($_POST['type'] == "membership-add"){
                    $nr = $clanDB->insertJoin($id,$from);
                    if($to != null){
                        $clanDB->insertLeave($nr,$to,$kick,$cause);
                    } else {
                        $clanDB->deleteLeave($nr);
                    }
                }else{
                    $clanDB->updateMembership($nr,$from,$to);
                    $clanDB->setMembershipCause($nr,$kick,$cause);
                }
                $clanDB->endTransaction();
                
                $res = array(
                    'nr' => $nr,
                    'from' => $from,
                    'to' => $to,
                    'cause' => $cause,
                    'kicked' => $kick,
                );
                echo json_encode($res);
                break;
            case 'membership-delete':
                $nr = $_POST['nr'];
                $clanDB->deleteMembershipEntry($nr);
                echo json_encode(array('nr' => $nr));
                break;
            case 'add-second-account':
                $clanDB->setSecondAccount($_POST['id'],$_POST['secID']);
                $res = $clanDB->getMemberByExactID($_POST['secID']);
                if($res != null)
                    $name = $res[0]['text'];
                else 
                    $name = "";
                echo json_encode(array('secID' => $_POST['secID'],'name' => $name));
                break;
            case 'remove-second-account':
                $clanDB->removeSecondAccount($_POST['id'],$_POST['secID']);
                echo json_encode(array('secID' => $_POST['secID']));
                break;
            case 'add-caution':
                $cause = $_POST['cause']; // NO break!
            case 'delete-caution':
                $id = $_POST['id'];
                $from = $_POST['from'];
                $to = $_POST['to'];
                $res = array(
                    'id' => $id,
                    'from' => $from,
                    'to' => $to
                ); 
                
                if($_POST['type'] == "delete-caution"){
                    $clanDB->deleteCaution($id,$from);
                } else {
                    $clanDB->insertCaution($id,$from,$to,$cause);
                    $res['cause'] = $cause;
                }
                
                echo json_encode($res);
                break;
            case 'add-afk':
                $cause = $_POST['cause']; // NO break!
            case 'delete-afk':
                $id = $_POST['id'];
                $from = $_POST['from'];
                $to = $_POST['to'];
                $res = array(
                    'id' => $id,
                    'from' => $from,
                    'to' => $to
                ); 
                
                if($_POST['type'] == "delete-afk"){
                    $clanDB->deleteAFK($id,$from,$to);
                } else {
                    $clanDB->insertAFK($id,$from,$to,$cause);
                    $res['cause'] = $cause;
                }
                
                echo json_encode($res);
                break;
            case 'edit-afk':
                $id = $_POST['id'];
                $fromNew = $_POST['fromNew'];
                $toNew = $_POST['toNew'];
                $from = $_POST['from'];
                $to = $_POST['to'];
                $cause = $_POST['cause'];
                $clanDB->editAFK($id,$from,$to,$fromNew,$toNew,$cause);
                echo json_encode(array('from' => $fromNew, 'to' => $toNew, 'cause' => $cause));
                break;
            case 'add-ts3-relation':
                $clanDB->insertTSRelation($_POST['id'],$_POST['tsID']);
                echo json_encode(array('tsID' => $_POST['tsID'],'name' => $_POST['name']));
                break;
            case 'remove-ts3-relation':
                $clanDB->removeTSRelation($_POST['id'],$_POST['tsID']);
                echo json_encode(array('tsID' => $_POST['tsID']));
                break;
            case 'database-json':
                $res = array(
                    'rows' => $clanDB->getMemberTableCount(),
                    'names' => $clanDB->getDBNameCount(),
                    'ids' => $clanDB->getDBIDCount(),
                    'realnames' => $clanDB->getRealNameCount(),
                    'afks' => $clanDB->getAFKCount(),
                    'cautions' => $clanDB->getCautionCount(),
                    'joins' => $clanDB->getJoinCount(),
                    'leaves' => $clanDB->getLeaveCount(),
                    'secondAccs' => $clanDB->getSecondAccCount(),
                    'unlinkedTS' => $clanDB->getUnlinkedTSIdCount(),
                    'tsIDs' => $clanDB->getTSIDCount(),
                    'causes' => $clanDB->getMemberCauseEntries(),
                    'tsdata' => $clanDB->getTSDataCount(),
                    'missing' => $clanDB->getMissingEntriesCount(),
                    'log' => $clanDB->getLogEntryCount(),
                );
                echo json_encode($res);
                break;
            case 'ts3-search-select2':
                if(isset($_REQUEST['key']) && $_REQUEST['key'] != null) {
                    $result = array(
                        'results' => $clanDB->searchTs3ID($_REQUEST['key']),
                        'pagination' => array('more' => false),
                    );
                } else {
                    $result = array(
                        'results' => array(),
                        'pagination' => array('more' => false),
                    );
                }
                echo json_encode($result);
                break;
            case 'member-ts':
                handleDateTS();
                $start = $_REQUEST['dateFromTS'];
                $end = $_REQUEST['dateToTS'];
                $id = $_REQUEST['id'];
                
                $start_d = new DateTime($start);
                $end_d = new DateTime($end);
                
                $diff = $start_d->diff($end_d);
                $diff = $diff->days;
                $diff += 1; // +1 because current day inclusive
                
                $diff_ok = $diff >= TS_DIFF_MIN;
                $date = null;
                
                if ($diff_ok) {
                    $interval = new DateInterval('P1W');
                    $start_d->modify('sunday'); // start of week
                    $daterange = new DatePeriod($start_d, $interval ,$end_d);
                    
                    $FORMAT = 'Y-m-d';
                    $data = array(
                        'days' => array(),
                        'average' => array(),
                        'date' => array(),
                    );
                    
                    foreach($daterange as $date) {
                        $chunkstart = $date->format($FORMAT);
                        $chunkend = $date->modify('+'.(TS_DIFF_MIN-1).' day')->format($FORMAT); // go till (inclusive) sunday
                        
                        $chunk = $clanDB->getMemberTSSummary($chunkstart,
                        $chunkend,$id);
                        
                        $data['date'][] = array(
                            'start' => $chunkstart,
                            'end' => $chunkend,
                        );
                        $data['days'][] = $chunk['days'] == null ? 0 : $chunk['days'];
                        $data['average'][] = $chunk['avg_raw'] == null ? 0 : $chunk['avg_raw'];
                    }
                }
                echo json_encode($data);
                break;
            case 'member-trial-delete':
                $id = $_POST['id'];
                $from = $_POST['from'];
                $clanDB->deleteMemberTrial($id,$from);
                echo json_encode(array(
                    'id' => $id,
                    'from' => $from
                ));
                break;
            case 'member-trial-set':
                $id = $_POST['id'];
                $from = $_POST['from'];
                $to = null;
                if(isset($_POST['to'])){
                    $to = $_POST['to'];
                }
                $clanDB->setMemberTrial($id,$from,$to);
                echo json_encode(array(
                    'id' => $id,
                    'from' => $from,
                    'to' => $to
                ));
                break;
            case 'member-addition-set';
                $clanDB->setMemberAddition($_POST['id'],$_POST['name'],$_POST['comment'],isset($_POST['vip']));
                echo json_encode(true);
                break;
            case 'memberLeave':
                $id = $_POST['id'];
                $kicked = isset($_POST['kicked']);
                $cause = $_POST['cause'];
                $date = $_POST['date'];
                $res = $clanDB->getMemberByExactID($id);
                $result = array(
                    'id' => $id,
                    'to' => $date,
                    'cause' => $cause,
                    'kicked' => $kicked
                );
                if($res != null)
                    $name = $res[0]['text'];
                else 
                    $name = "";
                $result['name'] = $name;
                
                $ms = $clanDB->getOpenMembership($id);
                $size = count($ms);
                $result['size'] = $size;
                $result['ok'] = $size == 1;
                if($size == 1){
                    $nr = $ms[0]['nr'];
                    $result['from'] = $ms[0]['from'];
                    $result['trials'] = $clanDB->endMemberTrials($id,$date);
                    $clanDB->insertLeave($nr,$date,$kicked,$cause);
                }
                echo json_encode($result);
                break;
            case 'account-change':
                $oldID = $_POST['oldID'];
                $newID = $_POST['newID'];
                //$copy = $_POST['copy'];
                
                $ms = $clanDB->getOpenMembership($oldID);
                $size = count($ms);
                $msNew = count($clanDB->getOpenMembership($newID));
                // old acc has an active membership, new account not
                if ($size > 0 && $msNew == 0){
                    $clanDB->startTransaction();
                
                    // add entry in comment section of both accounts
                    // and copy over name & vip from old to new ID
                    $additionOldAcc = $clanDB->getMemberAddition($oldID);
                    $additionNewAcc = $clanDB->getMemberAddition($newID);
                    
                    $newline = "\n";
                    $dateStr = date('Y-m-d', strtotime('now'));
                    
                    $comment_new = $dateStr.' Account Changed from ' . $oldID;
                    if ($additionNewAcc != null) { // new account could be non existing in DB
                        if ($additionNewAcc['comment'] != '') {
                            $comment_new .= $newline;
                            $comment_new .= $additionNewAcc['comment'];
                        }
                    }
                    $clanDB->setMemberAddition($newID,$additionOldAcc['name'],$comment_new,$additionOldAcc['vip']);
                    
                    $comment_old = $dateStr.' Account Changed to ' . $newID;
                    if ($additionOldAcc['comment'] != '') {
                        $comment_old .= $newline;
                        $comment_old .= $additionOldAcc['comment'];
                    }
                    $clanDB->setMemberAddition($oldID,$additionOldAcc['name'],$comment_old,$additionOldAcc['vip']);
                    
                    $today = date('Y-m-d', strtotime('now'));
                    
                    // copy over ts relations
                    $clanDB->copyTSRelation($oldID,$newID);
                    
                    // copy over trials
                    $trial = $clanDB->getMemberTrialOpen($oldID);
                    if ($trial != null) {
                        $clanDB->setMemberTrial($newID,$trial['from'],null);
                        // end existing trials
                        $clanDB->endMemberTrials($oldID,$today);
                    }
                    
                    // handle memberships
                    $clanDB->insertLeave($ms[0]['nr'],$today,false,'Account Change to ' . $newID);
                    $clanDB->insertJoin($newID,$today);
                    
                    // cross link in 2nd acc table
                    $clanDB->setSecondAccount($oldID,$newID);
                    $clanDB->setSecondAccount($newID,$oldID);
                    
                    $clanDB->endTransaction();
                }
                
                echo json_encode(
                    array(
                        'closableJoin' => $size > 0,
                        'clearNewAcc' => $msNew == 0,
                        'oldID' => $oldID,
                        'newID' => $newID,
                        //'copy' => $copy
                    )
                );
                break;
            case 'member-join':
                $id = $_POST['id'];
                $tsID = $_POST['ts3'];
                $name = $_POST['name'];
                $vip = isset($_POST['vip']);
                $date = date('Y-m-d', strtotime('now'));
                $clanDB->startTransaction();
                
                $comment = ""; // retrieve existing comment
                $additions = $clanDB->getMemberAddition($id);
                if ($additions !== null) {
                    $comment = $additions['comment'];
                }
                
                $nr = $clanDB->checkForMemberShipNr($id,$date);
                if($nr != null) {
                    $clanDB->setMembershipCause($nr,false,'');
                }
                $openMemberships = count($clanDB->getOpenMembership($id));
                $clanDB->insertJoin($id,$date);
                $clanDB->insertTSRelation($id,$tsID);
                $clanDB->setMemberAddition($id,$name,$comment,$vip);
                $clanDB->setMemberTrial($id,$date,null);
                
                $clanDB->endTransaction();
                
                $result = array(
                    'id' => $id,
                    'ts3' => $tsID,
                    'name' => $name,
                    'date' => $date,
                    'vip' => $vip,
                    'open' => $openMemberships,
                    'overrode' => $nr != null,
                );
                echo json_encode($result);
                break;
            case 'member-join-prefetch':
                $id = $_REQUEST['id'];
                
                $res = $clanDB->getMemberAddition($id);
                echo json_encode(
                    array(
                        'name' => $res['name'],
                        'vip' => $res['vip']
                    )
                );
                break;
            default:
                http_response_code(404);
                echo 'Data type not found! Unknown data request!';
                break;
            }
            break;
        default:
            http_response_code(404);
            echo 'Case not found!';
            break;
    }
}

function getTSTopView() {
    require 'includes/clantool.db.inc.php';
            $clanDB = new clanDB();
            $amount = 25;
            $from = date('Y-m-d', strtotime('-7 days'));
            $to = date('Y-m-d', strtotime('now'));
            $res = $clanDB->getTSTop($from,$to,$amount);
    ?>
    <h3>TS Aktivste Spieler</h3>
    Top <?=$amount?> vom <?=$from?> bis <?=$to?>
    <table class="table table-striped table-bordered table-hover">
        <thead>
            <tr>
                <th>Name</th>
                <th>Tage Aktiv</th>
                <th>Durchschnittliche Zeit</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if($res != null && count($res) > 0) {
                foreach($res as &$elem){ 
                    $name = '<unnamed>';
                    if($elem['vname'] != null) {
                        $name = $elem['vname'];
                    }
                    $name = htmlspecialchars($name);
                    ?>
                    <tr>
                        <td><a href="?site=<?=SITE?>&view=<?=SITE_MEMBER?>&id=<?=$elem['id']?>"><?=$name?></a></td>
                        <td><?=$elem['days']?></td>
                        <td><?=$elem['avg']?></td>
                        <td><?=$elem['sum']?></td>
                    </tr>
                <?php
                }
            }
            ?>
        </tbody>
    </table>
    <script type="text/javascript">
    $(document).ready(function() {
        initTableTool();
    });
    </script>
    <?php
}

function getMSChangesView() {
    $days = 14;
    require 'includes/clantool.db.inc.php';
    $clanDB = new clanDB();
    $resp = $clanDB->getSetting(KEY_AUTO_LEAVE);
    $res = $clanDB->getMembershipChanges(
        date('Y-m-d', strtotime('-'.$days.' days'))
    );
    ?>
    <h3>Membership Änderungen</h3>
    der letzten <?=$days?> Tage
    <?php
    if ($resp != true ) {
        echo '<div class="alert alert-info">Auto-<b>Leave detection </b>is currently <b>disabled!</b></div>';
    }
    ?>
    <h4>Joins</h4>
    <table id="joins" data-sortlist="[[2,0]]" class="table table-striped table-bordered table-hover table_nowrap">
        <thead>
            <tr>
                <th>Vorname</th>
                <th>Account</th>
                <th data-sortinitialorder="desc"><i class="fas fa-user-plus"></i> Join</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach($res as &$elem){
                if ( $elem['to'] === null ) {?>
                <tr>
                    <td><?=htmlspecialchars($elem['vname'])?></td>
                    <td><?=htmlspecialchars($elem['name'])?> <a href="?site=<?=SITE?>&view=<?=SITE_MEMBER?>&id=<?=$elem['id']?>">(<?=$elem['id']?>)</a></td>
                    <td>
                    
                    <?=$elem['from']?>
                    </td>
                </tr>
            <?php
              }
           }
            ?>
        </tbody>
    </table>
    <h4>Leaves</h4>
    <table id="leaves" data-sortlist="[[3,0],[2,0],[4,0]]" class="table table-striped table-bordered table-hover table_nowrap">
        <thead>
            <tr>
                <th class="sorter-text">Vorname</th>
                <th class="sorter-text">Account</th>
                <th><i class="fas fa-user-plus"></i> Join</th>
                <th><i class="fas fa-user-times"></i> Leave</th>
                <th><i class="fas fa-times-circle sorter-false"></i> Kicked</th>
                <th>Grund</th>
            </tr>
        </thead>
        <tbody>
            <?php
            
            $res = $clanDB->getMembershipChanges(
                date('Y-m-d', strtotime('-'.$days.' days'))
            );
            
            foreach($res as &$elem){
                if ( $elem['to'] !== null ) {?>
                <tr>
                    <td><?=htmlspecialchars($elem['vname'])?></td>
                    <td><?=htmlspecialchars($elem['name'])?> <a href="?site=<?=SITE?>&view=<?=SITE_MEMBER?>&id=<?=$elem['id']?>">(<?=$elem['id']?>)</a></td>
                    <td>
                    
                    <?=$elem['from']?>
                    </td>
                    <?php
                    if($elem['to'] != null) {
                        echo '<td>';
                        echo $elem['to'];
                        echo '<td>';
                        if($elem['kicked']) {
                            echo 'Kicked';
                        }
                        echo '</td><td>' . htmlspecialchars($elem['cause']) . '</td>';
                    }else{
                        echo '<td colspan=3></td>';
                    }
                    ?>
                </tr>
            <?php
              }
           }
            ?>
        </tbody>
    </table>
    <script type="text/javascript">
    $(document).ready(function() {
        var myTextExtraction = function(node) 
        {
            return node.innerText;
        }
        
        initTableTool(myTextExtraction);
    });
    </script>
    <?php
}

function getAwayView() {
    require 'includes/clantool.db.inc.php';
    $clanDB = new clanDB();
    ?>
    <h3>Aktuelle Abmeldungen</h3>
    <table class="table table-striped table-bordered table-hover">
        <thead>
            <tr>
                <th class="sorter-text">Vorname</th>
                <th class="sorter-text">Account</th>
                <th class="sorter-text">Von</th>
                <th class="sorter-text">Bis</th>
                <th class="sorter-text">Grund</th>
            </tr>
        </thead>
        <tbody>
            <?php            
            $res = $clanDB->getActiveFutureAFK(date('Y-m-d', strtotime('now')),true);
            
            foreach($res as &$elem){ ?>
                <tr>
                    <td><?=htmlspecialchars($elem['vname'])?></td>
                    <td><?=htmlspecialchars($elem['name'])?> <a href="?site=<?=SITE?>&view=<?=SITE_MEMBER?>&id=<?=$elem['id']?>">(<?=$elem['id']?>)</a></td>
                    <td><?=$elem['from']?></td>
                    <td><?=$elem['to']?></td>
                    <td><?=htmlspecialchars($elem['cause'])?></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>
    <h3>Kommende Abmeldungen</h3>
    <table class="table table-striped table-bordered table-hover">
        <thead>
            <tr>
                <th class="sorter-text">Vorname</th>
                <th class="sorter-text">Account</th>
                <th class="sorter-text">Von</th>
                <th class="sorter-text">Bis</th>
                <th class="sorter-text">Grund</th>
            </tr>
        </thead>
        <tbody>
            <?php            
            $res = $clanDB->getActiveFutureAFK(date('Y-m-d', strtotime('now')),false);
            
            foreach($res as &$elem){ ?>
                <tr>
                    <td><?=htmlspecialchars($elem['vname'])?></td>
                    <td><?=htmlspecialchars($elem['name'])?> <a href="?site=<?=SITE?>&view=<?=SITE_MEMBER?>&id=<?=$elem['id']?>">(<?=$elem['id']?>)</a></td>
                    <td><?=$elem['from']?></td>
                    <td><?=$elem['to']?></td>
                    <td><?=htmlspecialchars($elem['cause'])?></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>
    <script type="text/javascript">
    $(document).ready(function() {
        var myTextExtraction = function(node) 
        {
            return node.innerText;
        }
        
        initTableTool(myTextExtraction);
    });
    </script>
    <?php
}

function getMemberDetailView() { ?>
<div class="row container-fluid">
    <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
    <div class="col-sm-12 col-xs-11">
       <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
                <div style="position: fixed; left: 50%; top: 50%;">
                    <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                    <div id="loading-content">Loading...</div>
                </div>
        </div>
        <form class="form-horizontal" id="additionalForm" action="" method="post">
            <input type="hidden" name="site" value="<?=SITE?>">
            <input type="hidden" name="ajaxCont" value="data">
            <input type="hidden" name="type" value="member-addition-set">
            <div class="form-group">
                <label for="inputAccount" class="control-label col-xs-2">Account</label>
                <div class="col-xs-10">
                    <select class="form-control" name="id" required="" id="inputAccount">
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="inputID" class="control-label col-xs-2">z8 ID</label>
                <div class="col-xs-10">
                    <input type="number" required="" class="form-control" id="inputID" placeholder="ID" disabled>
                </div>
            </div>
            <div class="form-group">
                <label for="inputName" class="control-label col-xs-2">Vorname</label>
                <div class="col-xs-10">
                    <input type="text" name="name" autocomplete="off" required="" class="form-control" id="inputName" placeholder="Vorname">
                </div>
            </div>
            <div class="form-group">
                <label for="inputComment" autocomplete="off" class="control-label col-xs-2">Kommentar</label>
                <div class="col-xs-10">
                    <textarea rows="rows" name="comment" class="form-control" id="inputComment" placeholder="Kommentar"></textarea>
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <div class="checkbox">
                        <label><input type="checkbox" id="inputVIP" name="vip" checked="checked"> VIP Spieler</label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <button type="submit" class="btn btn-primary" id="submitMember"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
            <div class="form-group" id="missingDataDiv" style="display: none;">
                <div class="col-xs-offset-2 col-xs-10">
                    <div class="alert alert-warning">
                        <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                        <span class="sr-only">Error:</span>
                        Folgende Angaben fehlen: Vorname und VIP Status!
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <a type="button" class="btn btn-default" id="showDifference"><i class="fas fa-chart-area"></i> Member CP &amp; EXP</a>
                    <a type="button" class="btn btn-default" id="showTSChart"><i class="fas fa-chart-bar"></i> TS Chart</a>
                    <a type="button" target="_blank" class="btn btn-default" id="showZ8Account"><i class="fas fa-external-link-alt"></i> z8 Account</a>
                </div>
            </div>
        </form>
        <div id="trial" class="panel panel-default" style="display:none;">
            <div class="panel-heading">
                <form id="trialForm" action="" method="post">
                    <div id="trial-data" class="form-group"></div>
                    <div class="form-group">
                        <div class="">
                            <button type="submit" class="btn btn-warning" id="submitTrialEnd">Zum Member machen</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xs-11">
        <div class="panel panel-default">
            <div class="panel-heading"><i class="fas fa-user-plus"></i> Mitgliedsdaten</div>
            <ul class="list-group">
                <div id="membership">
                </div>
                <li class="list-group-item">
                    <div id="msDefault" class="form-horizontal">
                        <div class="form-horizontal form-group" >
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="submit" id="msNewBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Neuer Eintrag</button>
                            </div>
                        </div>
                    </div>
                    <form id="msForm" style="display:none;" class="form-horizontal" action="" method="post">
                        <div class="form-group">
                            <label for="msNewFrom" class="control-label col-xs-2">Join Datum</label>
                            <div class="col-xs-10">
                                <input id="msNewFrom" required="true" type="text" class="form-control" />
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <div class="checkbox">
                                    <label><input type="checkbox" name="trial" id="msNewMember" checked="checked"> Member</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="msNewTo" class="control-label col-xs-2">Leave Datum</label>
                            <div class="col-xs-10">
                                <input id="msNewTo" required="false" type="text" disabled="true" class="form-control" />
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <div class="checkbox">
                                    <label><input type="checkbox" name="kick" id="msNewKick" disabled="true" checked="checked"> Kick</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="msNewCause" class="control-label col-xs-2">Grund</label>
                            <div class="col-xs-10">
                                <input type="text" name="cause" class="form-control" id="msNewCause" autocomplete="off" disabled="true" placeholder="Grund">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="submit" id="msNewAdd" class="btn btn-primary"><i class="fas fa-plus"></i> Eintrag Hinzufügen</button>
                                <button type="submit" id="msNewSave" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                            </div>
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="button" id="msNewCancel" class="btn btn-danger"><i class="fas fa-times"> </i> Abbrechen</button>
                            </div>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-sm-6 col-xs-11">
        <div class="panel panel-default">
            <div class="panel-heading"><i class="fas fa-exclamation-triangle"></i> Verwarnungen</div>
            <ul class="list-group">
                <div id="caution">
                </div>
                <li class="list-group-item">
                    <div id="cautionAddDiv" class="form-horizontal">
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="submit" id="cautionAdd" class="btn btn-primary"><i class="fas fa-plus"></i> Neuer Eintrag</button>
                            </div>
                        </div>
                    </div>
                    <form id="cautionForm" style="display: none;" class="form-horizontal" action="" method="post">
                        <div class="form-group">
                            <label for="cautionFrom" class="control-label col-xs-2">Von</label>
                            <div class="col-xs-10">
                                <input id="cautionFrom" required="true" type="text" class="form-control" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cautionTo" class="control-label col-xs-2">Bis</label>
                            <div class="col-xs-10">
                                <input id="cautionTo" required="true" type="text" class="form-control" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="inputCautionCause" class="control-label col-xs-2">Grund</label>
                            <div class="col-xs-10">
                                <input type="text" name="name" autocomplete="off" required="true" class="form-control" id="inputCautionCause" placeholder="Grund">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="submit" id="cautionAddSubmit" class="btn btn-primary"><i class="fas fa-plus"></i> Eintrag Hinzufügen</button>
                            </div>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-sm-6 col-xs-11">
        <div class="panel panel-default">
            <div class="panel-heading"><i class="fas fa-clock"></i> AFK</div>
            <ul class="list-group">
                <div id="afk">
                </div>
                <li class="list-group-item">
                    <div id="afkAddDiv" class="form-horizontal">
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="submit" id="afkAdd" class="btn btn-primary"><i class="fas fa-plus"></i> Neuer Eintrag</button>
                            </div>
                        </div>
                    </div>
                    <form id="afkForm" style="display: none;" class="form-horizontal" action="" method="post">
                        <div class="form-group">
                            <label for="afkFrom" class="control-label col-xs-2">Von</label>
                            <div class="col-xs-10">
                                <input id="afkFrom" required="true" type="text" class="form-control" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="afkTo" class="control-label col-xs-2">Bis</label>
                            <div class="col-xs-10">
                                <input id="afkTo" required="true" type="text" class="form-control" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="inputAFKCause" class="control-label col-xs-2">Grund</label>
                            <div class="col-xs-10">
                                <input type="text" name="name" autocomplete="off" required="true" class="form-control" id="inputAFKCause" placeholder="Grund">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="submit" id="afkAddSubmit" class="btn btn-primary"><i class="fas fa-plus"></i> Eintrag Hinzufügen</button>
                                <button type="submit" stlye="display:none;" id="afkEditSubmit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                            </div>
                            <div style="display:none;" id="afkEditCancelDiv" class="col-xs-offset-2 col-xs-10">
                                <button type="button" id="afkEditCancel" class="btn btn-danger"><i class="fas fa-times"> </i> Abbrechen</button>
                            </div>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-sm-6 col-xs-11">
        <div class="panel panel-default">
            <div class="panel-heading"><i class="fas fa-id-badge"></i> Namechanges</div>
            <table class="table" id="names">
                <tbody>
                
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-sm-6 col-xs-11">
        <div class="panel panel-default">
            <div class="panel-heading"><i class="fas fa-user-friends"></i> Zweit Accounts</div>
            <ul class="list-group">
                <div id="Accs">
                </div>
                <li class="list-group-item">
                    <form id="accsForm" action="" method="post">
                        <div class="form-group">
                            <select class="form-control" id="accsSearch">
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="number" class="form-control" required="true" name="accID" id="AccsID" placeholder="ID / USN">
                        </div>
                        <div class="form-group">
                            <button type="submit" id="AccsSubmit" class="btn btn-primary"><i class="fas fa-plus"></i> Hinzufügen</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-sm-6 col-xs-11">
        <div class="panel panel-default">
            <div class="panel-heading"><i class="fas fa-plus" title="Pröbling"></i>  Pröblings Daten</div>
            <ul class="list-group">
                <div id="trials">
                </div>
                <li class="list-group-item">
                    <div id="trialAddDiv" class="form-horizontal">
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="submit" id="trialAdd" class="btn btn-primary"><i class="fas fa-plus"></i> Neuer Eintrag</button>
                            </div>
                        </div>
                    </div>
                    <form id="trialsListForm" style="display: none;" class="form-horizontal" action="" method="post">
                        <div class="form-group">
                            <label for="trialFrom" class="control-label col-xs-2">Von</label>
                            <div class="col-xs-10">
                                <input id="trialFrom" required="true" type="text" class="form-control" />
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <div class="checkbox">
                                    <label><input type="checkbox" id="trialMember" name="member" checked="checked"> Member</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="trialTo" class="control-label col-xs-2">Bis</label>
                            <div class="col-xs-10">
                                <input id="trialTo" required="true" type="text" class="form-control" />
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-xs-offset-2 col-xs-10">
                                <button type="submit" id="trialAddSubmit" class="btn btn-primary"><i class="fas fa-plus"></i> Eintrag Hinzufügen</button>
                            </div>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-sm-6 col-xs-11">
        <div class="panel panel-default">
            <div class="panel-heading">Teamspeak Identitäten</div>
            <ul class="list-group">
                <div id="ts3">
                </div>
                <li class="list-group-item">
                    <form id="ts3Form" action="" method="post">
                        <div class="form-group">
                            <select class="form-control" required="" id="ts3Input">
                            <option></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" id="ts3submit" class="btn btn-primary"><i class="fas fa-plus"></i> Hinzufügen</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-sm-6 col-xs-11">
        
    </div>
</div>
    <script type="text/javascript">
    const TS3_ENTRY = "ts3-";
    const AFK_ENTRY = "afk-";
    const CAUTION_ENTRY = "caution-";
    const MS_ENTRY = "ms-";
    const TRIAL_ENTRY = "trial-";
    const actionEdit = 'edit';
    const actionDelete = 'delete';
    
    function setLoadingMsg(msg){
        $('#loading-content').text(msg);
    }
    
    function loadData(id) {
        if(getUrlParameter('id') != id) {
            setURLParameterHistory({'id' : id},'id',id);
        } else {
            setURLParameter({'id' : id});
        }
        $('#loading').show();
        disable(true);
        $.ajax({
            url: URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'id' : id,
                'ajaxCont' : 'data',
                'type' : 'member-data',
            }
        }).done(function(data){
            setLoadingMsg('Rendering..');
            setTimeout(function() {
                $('#inputName').val(data.name);
                $('#inputComment').val(data.comment);
                if(data.vip == null){
                    $('#missingDataDiv').show();
                } else {
                    $('#inputVIP').prop('checked',data.vip);
                    $('#missingDataDiv').hide();
                }
                $('#showDifference').attr('href',"<?=MEMBER_DIFF_URL?>"+data.id);
                $('#showZ8Account').attr('href',"<?=Z8PROFILE?>"+data.id);
                $('#showTSChart').attr('href',"<?=MEMBER_TS_URL?>"+data.id);
                renderMembership(data.membership);
                renderTs3(data.ts3);
                renderTrial(data.trial);
                renderTrialsList(data.trials);
                renderSecondsAccounts(data.secAccs);
                renderAFK(data.afk);
                renderCaution(data.caution);
                renderNames(data.names);
                $('#error').hide();
                disable(false);
                resetMS();
                resetAfk();
                resetTrials();
                $('#loading').hide();
                setLoadingMsg('Loading...');
            },0);
        }).fail(function(data){
            $('#error').html(formatErrorData(data));
            $('#error').show();
            $('#loading').hide();
        });
    }
    
    function preselect(id,inputAcc) {
        $('#loading').show();
        disable(true);
        $.ajax({
            url: URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'id' : id,
                'ajaxCont' : 'data',
                'type' : 'account-select',
            }
        }).done(function(data){
            if(data.valid){
                var option = new Option(data.acc.text, data.acc.id, true, true);
                inputAcc.append(option).trigger('change');
                inputAcc.trigger({
                    type: 'select2:select',
                    params: {
                        data: data.acc
                    }
                });
            } else {
                $('#error').html(
                '<span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>'
                +'<span class="sr-only">Error:</span> '
                +'Invalid Account id!'
                );
                $('#error').show();
                $('#loading').hide();
            }
        }).fail(function(data){
            $('#error').html(formatErrorData(data));
            $('#error').show();
            $('#loading').hide();
        });
    }
    
    function renderTrialsList(data) {
        var elements = "";
        if(data != null) {
            for(var i = 0; i < data.length; i++) {
                var elem = data[i];
                elements += formatTrialEntry(elem.start,elem.end);
            }
        }
        $('#trials').html(elements);
    }
    
    function renderNames(data) {
        var elements = "";
        if(data != null) {
            for(var i = 0; i < data.length; i++) {
                var elem = data[i];
                elements += formatNameEntry(elem.name,elem.date);
            }
        }
        $('#names tbody').html(elements);
    }
    
    function renderCaution(data) {
        var elements = "";
        if(data != null){
            for(var i = 0; i < data.length; i++) {
                var elem = data[i];
                elements += formatCautionEntry(elem.from,elem.to,elem.cause);
            }
        }
        $('#caution').html(elements);
    }
    
    function renderAFK(data) {
        var elements = "";
        if(data != null){
            for(var i = 0; i < data.length; i++) {
                var elem = data[i];
                elements += formatAFKEntry(elem.from,elem.to,elem.cause);
            }
        }
        $('#afk').html(elements);
    }
    
    function renderSecondsAccounts(data) {
        var elements = "";
        if(data != null){
            for(var i = 0; i < data.length; i++) {
                var elem = data[i];
                elements += formatAccsEntry(elem.name,elem.id_sec);
            }
        }
        $('#Accs').html(elements);
    }
   
    function renderTrial(data) {
        if(data == null){
            $('#trial').hide();
        }else{
            $('#trial-data').text('Pröbling seit '+data.from);
            $('#trial-data').attr('data-from',data.from);
            $('#trial').show();
        }
    }
    
    function renderTs3(data){
        var elements = "";
        if(data != null){
            for(var i = 0; i < data.length; i++) {
                var elem = data[i];
                elements += formatTS3Entry(elem.name,elem.cID);
            }
        }
        $('#ts3').html(elements);
    }
    
    function formatTrialEntry(from,to){
        var str = '<li class="list-group-item" id="'+TRIAL_ENTRY+from+'">'
            + from
            + '<a href="#" class="critical" data-from="'+from+'" data-to="'+to+'" style="float: right;"> <i class="fas fa-trash"></i> Delete</a>'
        if(to == null){
            str += ' open';
        }else{
            str += ' - ' + to;
        }
        str += '</li>';
        return str;
    }
        
    function formatNameEntry(name,date) {
        return '<tr><td>'
            + escapeHtml(name) + '</td><td>'+ date
            + '</tr>';
    }
    
    function formatCautionEntry(from,to,cause) {
        return '<li class="list-group-item" id="'+CAUTION_ENTRY+from+'">'
            + '<a href="#" class="critical" data-from="'+from+'" data-to="'+to+'" style="float: right;"> <i class="fas fa-trash"></i> Delete</a>'
            + from + ' - ' + to + '<br>'
            + escapeHtml(cause)
            + '</li>';
    }
    
    function formatAFKEntry(from,to,cause,nr) {
        return '<li class="list-group-item" id="'+AFK_ENTRY+from+'-'+to+'">'
            + '<a href="#" style="float: right; margin-left: 2em;" data-action="'+actionEdit+'" data-from="'
            + from + '" data-to="' + to +'" data-cause="'
            + escapeHtml(cause) +'">'
            + '<i class="fas fa-pencil-alt"></i> Edit </a>'
            + '<a href="#" class="critical" data-action="'+actionDelete+'" data-from="'+from+'" data-to="'+to+'" style="float: right;"> <i class="fas fa-trash"></i> Delete</a>'
            + from + ' - ' + to + '<br>'
            + escapeHtml(cause)
            + '</li>';
    }
    
    function formatTS3Entry(name,id) {
        return '<li class="list-group-item" id="'+TS3_ENTRY+id+'">'
            + '<a href="#" class="critical" data-id="'+id+'" style="float: right;"> <i class="fas fa-trash"></i> Remove</a>'
            + escapeHtml(name)
            + '</li>';
    }
    
    function formatAccsEntry(name,id) {
        var vname = "&lt;unknown&gt;";
        if(name != null && name != "")
            vname = name;
        return '<li class="list-group-item" id="accs-'+id+'">'
            + '<a href="#" class="critical" data-id="'+id+'" style="float: right;"> <i class="fas fa-trash"></i> Remove</a>'
            + escapeHtml(vname) +' ('+formatMemberDetailLink(id,id)+')'
            + '</li>';
    }    
    
    function disable(disable) {
        var lst = ['#ts3Input','#ts3submit','#submitMember','#inputName',
        '#membershipSubmit','#inputVIP','#msNewBtn','#showDifference',
        '#AccsSubmit','#AccsID','#accsSearch','#afkAdd','#afkAddSubmit',
        '#trialAddSubmit','#trialAdd','#showZ8Account','#inputComment',
        '#showTSChart','#cautionAdd','#cautionAddSubmit'];
        for(var i = 0; i < lst.length; i++) {
            $(lst[i]).prop('disabled', disable);
        }
    }
    
    function renderMembership(data) {
        $('#membership').empty();
        for(var i = 0; i < data.length; i++){
            $('#membership').append(formatMembershipEntry(data[i],false));
        }
    }
    // generate membership entry, edit = true will suppress the main <li>
    function formatMembershipEntry(data,edit) {
        var span_start = '<span class="avoidwrap">'; // avoid bad linebreaks
        var span_end = '</span>';
        if(edit)
            var ret = "";
        else 
            var ret = '<li class="list-group-item" id="'+MS_ENTRY+data.nr+'">';
        ret += span_start;
        ret += '<i class="fas fa-user-plus"></i> ';
        ret += data.from;
        ret += span_end;
        var member = data.to == null ? "true" : "false";
        var kicked = data.kicked ? "true" : "false";
        
        ret += '<a href="#" style="float: right; margin-left: 2em;" data-id="'+data.nr+'" data-action="'+actionEdit+'" data-from="';
        ret += data.from + '" data-to="' + data.to+'" data-cause="';
        ret += escapeHtml(data.cause)+'" data-kicked="'+kicked+'" data-member="'+member+'">';
        ret += '<i class="fas fa-pencil-alt"></i> Edit </a>';
        
        ret += '<a href="#" class="critical" data-id="'+data.nr+'" data-action="'+actionDelete+'" style="float: right;"> <i class="fas fa-trash"></i> Delete </a>';

        
        if(data.to == null){
            ret += ' Member ';
        } else {
            ret += ' - ';
            
            ret += span_start;
            ret += '<i class="fas fa-user-times"></i> ';
            ret += data.to+ ' ';
            ret += span_end;
            
            if(data.kicked){
                ret += span_start;
                ret += ' <i class="fas fa-times-circle"> </i> Kicked';
                ret += span_end;
            }
            ret += '<br>';
            ret += escapeHtml(data.cause);
        }
        
        if(!edit)
            ret += '</li>';
        return ret;
    }
    
    function resetMS() {
        $('#msNewAdd').show();
        $('#msForm').hide();
        $('#msNewSave').hide();
        $('#msDefault').show();
        $('#msForm').attr('data-id',null);
    }
    
    function resetAfk(){
        $('#afkAddDiv').show();
        $('#afkAddSubmit').show();
        $('#afkForm').hide();
    }
    
    function resetCaution(){
        $('#cautionAddDiv').show();
        $('#cautionForm').hide();
    }
    
    function resetTrials(){
        $('#trialsListForm').hide();
        $('#trialAdd').show();
    }
    
    $(document).ready(function() {
        var inputAcc = $('#inputAccount');
        var inputSecAccs = $('#accsSearch');
        accountSelect(inputAcc);
        accountSelect(inputSecAccs);
        
        disable(true);
        
        var inputID = $('#inputID');
        
        var inputSecAccsID = $('#AccsID');
        
        var ts3Input = $('#ts3Input');
        ts3Select(ts3Input);
        
        inputAcc.on('select2:select', function (e) {
            var data = e.params.data;
            inputID.val(data.id);
            loadData(data.id);
        });
        inputSecAccs.on('select2:select', function (e) {
            var data = e.params.data;
            inputSecAccsID.val(data.id);
        });
        
        var loadingDiv = $('#loading');
        var errorDiv = $('#error');
        
        var msDefaultDiv = $('#msDefault');
        var msFormDiv = $('#msForm');
        var btnMSAdd = $('#msNewBtn');
        var btnMSNew = $('#msNewAdd');
        var btnMSSave = $('#msNewSave');
        
        var msNewChk = $('#msNewMember');
        var msNewTo = $('#msNewTo');
        var msNewFrom = $('#msNewFrom');
        var msNewKick = $('#msNewKick');
        var msNewCause = $('#msNewCause');
        
        var afkFrom = $('#afkFrom');
        var afkTo = $('#afkTo');
        var afkForm = $('#afkForm');
        var afkAddSubmit = $('#afkAddSubmit');
        var afkAdd = $('#afkAdd');
        var afkEditCancel = $('#afkEditCancel');
        var afkEditSubmit = $('#afkEditSubmit');
        var afkAddDiv = $('#afkAddDiv');
        var afkCause = $('#inputAFKCause');
        var afkEditCancelDiv = $('#afkEditCancelDiv');
        
        var cautionFrom = $('#cautionFrom');
        var cautionTo = $('#cautionTo');
        var cautionForm = $('#cautionForm');
        var cautionAdd = $('#cautionAdd');
        var cautionAddDiv = $('#cautionAddDiv');
        var cautionCause = $('#inputCautionCause');
        
        var trialsFrom = $('#trialFrom');
        var trialsTo = $('#trialTo');
        var trialsMemberChk = $('#trialMember');
        var trialsListForm = $('#trialsListForm');
        var trialsAddNew = $('#trialAdd');
        
        function changeMember(isMember){
            if(isMember){
                msNewFrom.data("DateTimePicker").maxDate(false);
                msNewTo.trigger('dp.change');
            }else{
                msNewTo.data("DateTimePicker").date(
                    msNewFrom.data("DateTimePicker").date()
                );
            }
            msNewTo.attr('disabled',isMember);
            msNewKick.attr('disabled',isMember);
            msNewCause.attr('disabled',isMember);
            msNewTo.attr('required',!isMember);
        }
        msNewChk.change(function() {
            changeMember(this.checked);
        });
        
        trialsMemberChk.change(function() {
            trialsTo.attr('disabled',!this.checked);
        });
        
        msNewFrom.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
        });
        msNewTo.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
        });
        msNewFrom.on("dp.change", function (e) {
            msNewTo.data("DateTimePicker").minDate(e.date);
        });
        
        afkTo.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
        });
        afkFrom.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
        });
        
        cautionTo.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
        });
        cautionFrom.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
        });
        
        trialsFrom.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
        });
        trialsTo.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
        });
        
        $('#msForm').submit(function (e) {
            loadingDiv.show();
            var nr = msFormDiv.attr('data-id');
            var edit = nr != null;
            var type = edit ? 'membership-edit' : 'membership-add';
            var to = msNewChk.is(':checked') ? undefined : msNewTo.val();
            
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : type,
                    'from': msNewFrom.val(),
                    'to': to,
                    'cause': msNewCause.val(),
                    'kicked' : msNewKick.is(':checked') ? "on" : undefined,
                    'id': inputID.val(),
                    'nr': nr
                }
            }).done(function(data){
                if($('#'+MS_ENTRY+data.nr).length){
                    $('#'+MS_ENTRY+data.nr).html(formatMembershipEntry(data,true));
                } else {
                    $('#membership').append(formatMembershipEntry(data,false));
                }
                loadingDiv.hide();
                errorDiv.hide();
                resetMS();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
            });
            e.preventDefault();
        });
        
        $('#msNewCancel').click(function() {
            msFormDiv.hide();
            msDefaultDiv.show();
        });
        
        afkEditCancel.click(function() {
            afkEditCancelDiv.hide();
            afkEditSubmit.hide();
            afkAddSubmit.show();
            afkForm.hide();
            afkAdd.show();
        });
        
        trialsAddNew.click(function(){
            trialsListForm.show();
            trialsAddNew.hide();
        });
        
        btnMSAdd.click(function(){
            btnMSNew.show();
            msFormDiv.show();
            btnMSSave.hide();
            msDefaultDiv.hide();
            msFormDiv.attr('data-id',null);
        });
        
        afkAdd.click(function() {
            afkAddDiv.hide();
            afkForm.attr({'edit':false});
            afkForm.show();
            afkEditSubmit.hide();
            afkEditCancelDiv.hide();
        });
        
        cautionAdd.click(function() {
            cautionAddDiv.hide();
            cautionForm.show();
        });
        
        // handle edit/delete on membership
        $('#membership').on('click', 'a', function (e) {
            loadingDiv.show();
            var div = $(this);
            var id = $(this).attr('data-id');
            var action = $(this).attr('data-action');
            if(action == actionDelete){
                $.ajax({
                    url: URL,
                    type: 'post',
                    dataType: "json",
                    data: {
                        'site' : VAR_SITE,
                        'ajaxCont' : 'data',
                        'type' : 'membership-delete',
                        'nr' : id
                    }
                }).done(function(data){
                    if(data){
                        loadingDiv.hide();
                        errorDiv.hide();
                        $('#'+MS_ENTRY+data.nr).remove();
                        resetMS();
                    }
                }).fail(function(data){
                    errorDiv.html(formatErrorData(data));
                    loadingDiv.hide();
                    errorDiv.show();
                });
            }else if(action == actionEdit) {
                var member = div.attr('data-member') == "true";
                changeMember(member);
                msFormDiv.attr('data-id',id);
                msNewChk.prop('checked',member);
                msNewKick.prop('checked',div.attr('data-kicked') == "true");
                msNewCause.val(div.attr('data-cause'));
                msNewTo.val(div.attr('data-to'));
                msNewFrom.val(div.attr('data-from'));
                btnMSNew.hide();
                msFormDiv.show();
                btnMSSave.show();
                msDefaultDiv.hide();
                loadingDiv.hide();
            }else {
                console.error('unknown action:'+action);
            }
            e.preventDefault();
        });
        
        trialsListForm.submit(function(e) {
            loadingDiv.show();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'member-trial-set',
                    'id' : $('#inputID').val(),
                    'from' : trialsFrom.val(),
                    'to' : trialsMemberChk.is(':checked') ? trialsTo.val() : undefined,
                }
            }).done(function(data){
                $('#trial').hide();
                var elem = $('#'+TRIAL_ENTRY+data.from);
                if(elem.length)
                    elem.remove();
                $('#trials').append(formatTrialEntry(data.from,data.to));
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        
        // handle form for additionals (name,vip etc)
        $("#additionalForm").submit(function(e) {
            loadingDiv.show();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: $(this).serialize()
            }).done(function(data){
                if(data){
                    errorDiv.hide();
                    $('#missingDataDiv').hide();
                }
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        
        $("#ts3Form").submit(function(e) {
            loadingDiv.show();
            var selected = ts3Input.select2('data')[0];
            if($("#"+TS3_ENTRY+selected.id).length) {
                console.log("ts3 entry exists already");
                loadingDiv.hide();
            } else {
                $.ajax({
                    url: URL,
                    type: 'post',
                    dataType: "json",
                    data: {
                        'site' : VAR_SITE,
                        'ajaxCont' : 'data',
                        'type' : 'add-ts3-relation',
                        'id' : inputID.val(),
                        'tsID' : selected.id,
                        'name' : selected.text,
                    }
                }).done(function(data){
                    $('#ts3').append(formatTS3Entry(data.name,data.tsID));
                    errorDiv.hide();
                    loadingDiv.hide();
                }).fail(function(data){
                    errorDiv.html(formatErrorData(data));
                    errorDiv.show();
                    loadingDiv.hide();
                });
            }
            e.preventDefault();
        });
        
        $("#trialForm").submit(function(e) {
            loadingDiv.show();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'member-trial-set',
                    'id' : $('#inputID').val(),
                    'from' : $('#trial-data').attr('data-from'),
                    'to' : moment().subtract(1, 'day').format(DATE_FORMAT),
                }
            }).done(function(data){
                $('#trial').hide();
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        
        cautionForm.submit(function(e) {
            loadingDiv.show();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'add-caution',
                    'id' : $('#inputID').val(),
                    'from' : cautionFrom.val(),
                    'to' : cautionTo.val(),
                    'cause' : cautionCause.val(),
                }
            }).done(function(data){
                var elem = $('#'+CAUTION_ENTRY+data.from);
                if(elem.length){
                    elem.remove();
                }
                $('#caution').append(formatCautionEntry(data.from,data.to,data.cause));
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        
        $('#caution').on('click', 'a', function (e) {
            loadingDiv.show();
            var from = $(this).attr('data-from');
            var to = $(this).attr('data-to');
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'delete-caution',
                    'id' : $('#inputID').val(),
                    'from' : from,
                    'to' : to,
                }
            }).done(function(data){
                $('#'+CAUTION_ENTRY+data.from).remove();
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        
        afkForm.submit(function(e) {
            loadingDiv.show();
            var edit = afkForm.attr('edit');
            if (edit == "true") {
                var from_orig = afkForm.attr('data-from');
                var to_orig = afkForm.attr('data-to');
                console.log('from_orig',from_orig);
                $.ajax({
                    url: URL,
                    type: 'post',
                    dataType: "json",
                    data: {
                        'site' : VAR_SITE,
                        'ajaxCont' : 'data',
                        'type' : 'edit-afk',
                        'id' : $('#inputID').val(),
                        'fromNew' : afkFrom.val(),
                        'toNew' : afkTo.val(),
                        'cause' : afkCause.val(),
                        'from': from_orig,
                        'to': to_orig,
                    }
                }).done(function(data){
                    var elem = $('#'+AFK_ENTRY+from_orig+'-'+to_orig);
                    if(elem.length){
                        elem.remove();
                    }
                    $('#afk').append(formatAFKEntry(data.from,data.to,data.cause));
                    resetAfk();
                    errorDiv.hide();
                    loadingDiv.hide();
                }).fail(function(data){
                    errorDiv.html(formatErrorData(data));
                    errorDiv.show();
                    loadingDiv.hide();
                });
            } else {
                $.ajax({
                    url: URL,
                    type: 'post',
                    dataType: "json",
                    data: {
                        'site' : VAR_SITE,
                        'ajaxCont' : 'data',
                        'type' : 'add-afk',
                        'id' : $('#inputID').val(),
                        'from' : afkFrom.val(),
                        'to' : afkTo.val(),
                        'cause' : afkCause.val(),
                    }
                }).done(function(data){
                    var elem = $('#'+AFK_ENTRY+data.from+'-'+data.to);
                    if(elem.length){
                        elem.remove();
                    }
                    $('#afk').append(formatAFKEntry(data.from,data.to,data.cause));
                    errorDiv.hide();
                    loadingDiv.hide();
                }).fail(function(data){
                    errorDiv.html(formatErrorData(data));
                    errorDiv.show();
                    loadingDiv.hide();
                });
            }
            e.preventDefault();
        });
        
        $('#afk').on('click', 'a', function (e) {
            var from = $(this).attr('data-from');
            var to = $(this).attr('data-to');
            var action = $(this).attr('data-action');
            if(action == actionDelete){
                loadingDiv.show();
                $.ajax({
                    url: URL,
                    type: 'post',
                    dataType: "json",
                    data: {
                        'site' : VAR_SITE,
                        'ajaxCont' : 'data',
                        'type' : 'delete-afk',
                        'id' : $('#inputID').val(),
                        'from' : from,
                        'to' : to,
                    }
                }).done(function(data){
                    $('#'+AFK_ENTRY+data.from+'-'+data.to).remove();
                    errorDiv.hide();
                    loadingDiv.hide();
                }).fail(function(data){
                    errorDiv.html(formatErrorData(data));
                    errorDiv.show();
                    loadingDiv.hide();
                });
            } else if(action == actionEdit) {
                afkTo.val(to);
                afkFrom.val(from);
                afkCause.val($(this).attr('data-cause'));
                afkAddSubmit.hide();
                afkForm.attr({'data-to':to,'data-from':from, 'edit': true});
                afkAddDiv.hide();
                afkEditCancelDiv.show();
                afkEditSubmit.show();
                afkForm.show();
            } else {
                console.error('unknown action:'+action);
            }
            
            e.preventDefault();
        });
        
        $('#trials').on('click', 'a', function (e) {
            loadingDiv.show();
            var from = $(this).attr('data-from');
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'member-trial-delete',
                    'id' : $('#inputID').val(),
                    'from' : from
                }
            }).done(function(data){
                $('#'+TRIAL_ENTRY+data.from).remove();
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        
        $('#ts3').on('click', 'a', function (e) {
            loadingDiv.show();
            var id = $(this).attr('data-id');
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'remove-ts3-relation',
                    'id' : $('#inputID').val(),
                    'tsID' : id,
                }
            }).done(function(data){
                $('#'+TS3_ENTRY+data.tsID).remove();
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        
        $("#accsForm").submit(function(e) {
            loadingDiv.show();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'add-second-account',
                    'id' : inputID.val(),
                    'secID' : inputSecAccsID.val(),
                }
            }).done(function(data){
                $('#Accs').append(formatAccsEntry(data.name,data.secID));
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        
        $('#Accs').on('click', 'a', function (e) {
            loadingDiv.show();
            var id = $(this).attr('data-id');
            if(id != undefined){ // allow link to second accounts
                $.ajax({
                    url: URL,
                    type: 'post',
                    dataType: "json",
                    data: {
                        'site' : VAR_SITE,
                        'ajaxCont' : 'data',
                        'type' : 'remove-second-account',
                        'id' : $('#inputID').val(),
                        'secID' : id,
                    }
                }).done(function(data){
                    $('#accs-'+data.secID).remove();
                    errorDiv.hide();
                    loadingDiv.hide();
                }).fail(function(data){
                    errorDiv.html(formatErrorData(data));
                    errorDiv.show();
                    loadingDiv.hide();
                });
                e.preventDefault();
            }
        });
        
        window.onpopstate = function(event) {
            var id = getUrlParameter('id');
            preselect(id,inputAcc);
        };
        
    <?php if(isset($_REQUEST['id'])) { ?>
        preselect('<?=$_REQUEST['id']?>',inputAcc);
    <?php } else { ?>
        inputAcc.select2('open');
    <?php } ?>
    });
    </script>
    <?php
}

function getMemberLeaveView() { ?>
    <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
    <form class="form-horizontal" id="leaveForm" action="" method="post">
        <input type="hidden" name="site" value="<?=SITE?>">
        <input type="hidden" name="ajaxCont" value="data">
        <input type="hidden" name="type" value="memberLeave">
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <select class="form-control" id="accountSearch">
                    <option></option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="inputID" class="control-label col-xs-2">Account ID / USN</label>
            <div class="col-xs-10">
                <input type="number" name="id" required="true" autocomplete="off" class="form-control" id="inputID" placeholder="ID/USN">
            </div>
        </div>
        <div class="form-group">
            <label for="inputDate" class="control-label col-xs-2">Leave Datum</label>
            <div class="col-xs-10">
                <input id="inputDate" name="date" autocomplete="off" required="true" type="text" class="form-control" />
            </div>
        </div>
        <div class="form-group">
            <label for="inputKicked" class="control-label col-xs-2">Kick</label>
            <div class="col-xs-10">
                <div class="checkbox">
                    <label><input type="checkbox" name="kicked" id="inputKicked"  checked="checked"> Kick</label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="msNewCause" class="control-label col-xs-2">Grund</label>
            <div class="col-xs-10">
                <input type="text" name="cause" autocomplete="off" class="form-control" id="msNewCause"  placeholder="Grund">
            </div>
        </div>
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Leave Einfügen</button>
            </div>
        </div>
    </form>
    <div id="errorInsert" style="display: none;" class="alert alert-danger fade in">
        <a href="?" class="btn btn-default" id="manually"><i class="fas fa-pencil-alt"></i> Joins Einsehen</a>
    </div>
    <div id="result" style="display: none;">
        <h3>Member Leave: <a href="?" class="btn btn-default" id="edit"><i class="fas fa-pencil-alt"></i> Editieren</a></h3>
        <table class="table">
            <tbody>
                <tr><td>Datum</td><td><div id="Rdate"></div></td></tr>
                <tr><td>Account</td><td><div id="Racc"></div></td></tr>
                <tr><td>Kick</td><td><input type="checkbox" id="RKicked" checked="checked" disabled></td></tr>
                <tr><td>Grund</td><td><div id="Rcause"></div></td></tr>
                <tr><td>Pröbling Einträge beendet</td><td><div id="trials"></td></tr>
            </tbody>
        </table>
    </div>
    <script type="text/javascript">
    var EDIT_URL = '<?=MEMBER_DETAIL_URL?>';
    $(document).ready(function() {
        var accSel = $('#accountSearch');
        var inputID = $('#inputID');
        var leaveForm = $('#leaveForm');
        var inputDate = $('#inputDate');
        accountSelect(accSel);
        
        accSel.select2('open');
        
        inputDate.datetimepicker({
            format: DATE_FORMAT,
            useCurrent: true,
            defaultDate: moment()
        });
        
        accSel.on('select2:select', function (e) {
            var data = e.params.data;
            inputID.val(data.id);
        });
        
        leaveForm.submit(function(e) {
            leaveForm.hide();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: $(this).serialize()
                }).done(function(data){
                    if(data.ok && data.size == 1){
                        $('#trials').text(data.trials);
                        $('#edit').attr('href',EDIT_URL + data.id);
                        $('#Racc').text(data.name+' '+data.id);
                        $('#Rdate').text(data.from+' - '+data.to);
                        $('#RKicked').prop('checked', data.kicked);
                        $('#Rcause').text(data.cause);
                        $('#result').show();
                    }else{
                        $('#manually').attr('href',EDIT_URL + data.id);
                        var txt = 'Es wurden '+data.size+' Join Einträge gefunden! Bitte das Problem manuell beheben!';
                        $('#errorInsert').prepend('<span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>'+escapeHtml(txt));
                        $('#errorInsert').show();
                    }
                    $('#error').hide();
                    $('#loading').hide();
                }).fail(function(data){
                    $('#error').html(formatErrorData(data));
                    $('#error').show();
                });
            e.preventDefault();
        });
    });
    </script>
    <?php
}

function getAccountChangeView() { ?>
    <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
    <form class="form-horizontal" id="accChangeForm" action="" method="post">
        <div class="form-group">
            <label for="inputAccount" class="control-label col-xs-2">Current Account</label>
            <div class="col-xs-10">
                <select class="form-control" name="id" required="" id="inputAccount">
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="selInputNewAcc" class="control-label col-xs-2">New Account</label>
            <div class="col-xs-10">
                <select class="form-control" id="selInputNewAcc">
                <option></option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <input type="number" class="form-control" required="" name="inputNewAcc" id="inputNewAcc" placeholder="New Account ID / USN">
            </div>
        </div>
        <!--<div class="form-group">
            <label for="inputCopy" class="control-label col-xs-2">Copy all data</label>
            <div class="col-xs-10">
                <div class="checkbox">
                    <label><input type="checkbox" id="inputCopy" checked="checked"> Copy all data</label>
                </div>
            </div>
        </div>-->
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <button type="submit" class="btn btn-warning btn-primary">
                    <i class="fas fa-user-edit"></i> 
                    Change Account
                </button>
            </div>
        </div>
    </form>
    <div id="errOldAccount" style="display: none;" class="alert alert-danger fade in">
        Keine aktive Memberschaft für den alten Account gefunden!
        <a href="?" class="btn btn-default" id="manually-old"><i class="fas fa-pencil-alt"></i> Account Einsehen</a>
    </div>
    <div id="errNewAcc" style="display: none;" class="alert alert-danger fade in">
        Der neue Account ist aktuell ein Mitglied des Clans!
        <a href="?" class="btn btn-default" id="manually-new"><i class="fas fa-pencil-alt"></i> Account Einsehen</a>
    </div>
    <div id="result" style="display: none;">
        <h3>Account Change vollzogen: <a href="?" class="btn btn-default" id="edit"><i class="fas fa-pencil-alt"></i> Editieren</a></h3>
        <table class="table">
            <tbody>
                <tr><td>Old Account</td><td><div id="Rold"></div></td></tr>
                <tr><td>New Account</td><td><div id="Rnew"></div></td></tr>
                <!--<tr><td>Copy</td><td><input type="checkbox" id="Rcopy" checked="checked" disabled></td></tr>-->
            </tbody>
        </table>
    </div>
    <script type="text/javascript">
    var MEMBER_DETAIL_URL = '<?=MEMBER_DETAIL_URL?>';
    $(document).ready(function() {
        var selCurrAcc = $('#inputAccount');
        var selNewAcc = $('#selInputNewAcc');
        var newAcc = $('#inputNewAcc');
        var form = $('#accChangeForm');
        accountSelect(selCurrAcc);
        accountSelect(selNewAcc);
        selNewAcc.on('select2:select', function (e) {
            var data = e.params.data;
            newAcc.val(data.id);
        });
        
        form.submit(function(e) {
            form.hide();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    //'copy' : $('#inputCopy').is('checked'),
                    'newID' : newAcc.val(),
                    'oldID' : selCurrAcc.select2('data')[0].id,
                    'ajaxCont' : 'data',
                    'type' : 'account-change',
                }
            }).done(function(data){
                if (data.closableJoin && data.clearNewAcc) {
                    $('#edit').attr('href',MEMBER_DETAIL_URL + data.newID);
                    $('#Rnew').text(data.newID);
                    $('#Rold').text(data.oldID);
                    //$('#Rcopy').prop('checked',data.copy);
                    $('#result').show();
                } else {
                    if (!data.closableJoin) {
                        $('#manually-old').attr('href',MEMBER_DETAIL_URL + data.oldID);
                        $('#errOldAccount').show();
                    }
                    if (!data.clearNewAcc) {
                        $('#manually-new').attr('href',MEMBER_DETAIL_URL + data.newID);
                        $('#errNewAcc').show();
                    }
                }
            }).fail(function(data){
                $('#error').html(formatErrorData(data));
                $('#error').show();
            });
            e.preventDefault();
        });
    });
    </script>
    <?php
}

function getMemberJoinView() { ?>
    <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
    <form class="form-horizontal" id="joinForm" action="" method="post">
        <input type="hidden" name="site" value="<?=SITE?>">
        <input type="hidden" name="ajaxCont" value="data">
        <input type="hidden" name="type" value="member-join">
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <select class="form-control" id="accountSearch">
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="inputID" class="control-label col-xs-2">Account ID / USN</label>
            <div class="col-xs-10">
                <input type="number" name="id" autofocus required="" class="form-control" id="inputID" placeholder="ID/USN">
            </div>
        </div>
        <div class="form-group">
            <label for="inputName" class="control-label col-xs-2">Vorname</label>
            <div class="col-xs-10">
                <input type="text" name="name" required="" autocomplete="off" class="form-control" id="inputName" placeholder="Vorname">
            </div>
        </div>
        <div class="form-group">
            <label for="inputTSID" class="control-label col-xs-2">Teamspeak Identität</label>
            <div class="col-xs-10">
                <select class="form-control" id="selTSid">
                <option></option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <input type="number" class="form-control" required="" name="ts3" id="inputTSID" placeholder="TS Client ID">
            </div>
        </div>
        <div class="form-group">
            <label for="inputVIP" class="control-label col-xs-2">VIP</label>
            <div class="col-xs-10">
                <div class="checkbox">
                    <label><input type="checkbox" name="vip" id="inputVIP" checked="checked"> VIP Spieler</label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Join Einfügen</button>
            </div>
        </div>
    </form>
    <div id="result" style="display: none;">
        <h3>Member Hinzugefügt: <a href="?" class="btn btn-default" id="edit"><i class="fas fa-pencil-alt"></i> Editieren</a></h3>
        <div id="warnJoin" style="display: none;" class="alert alert-danger fade in">
            <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
        </div>
        <div id="warnOverride" style="display: none;" class="alert alert-danger fade in">
            <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
        </div>
        <table class="table">
            <tbody>
                <tr><td>Datum</td><td><div id="Rdate"></div></td></tr>
                <tr><td>ID</td><td><div id="RaccID"></div></td></tr>
                <tr><td>Vorname</td><td><div id="RrealName"></div></td></tr>
                <tr><td>VIP</td><td><input type="checkbox" id="Rvip" checked="checked" disabled></td></tr>
                <tr><td>TS3 Account ID</td><td><div id="Rts3"></div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="alert alert-success">
        Bitte den Eintrag in den TS-Channel <b>Memberliste</b> &amp; <b>Download/Upload Ordner</b> nicht vergessen!
    </div>
    <script type="text/javascript">
    var MEMBER_DETAIL_URL = '<?=MEMBER_DETAIL_URL?>';
    $(document).ready(function() {
        var selTS = $('#selTSid');
        var tsID = $('#inputTSID');
        
        var accSel = $('#accountSearch');
        var inputID = $('#inputID');
        accountSelect(accSel);
        accSel.on('select2:select', function (e) {
            var data = e.params.data;
            inputID.val(data.id);
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'id' : data.id,
                    'ajaxCont' : 'data',
                    'type' : 'member-join-prefetch',
                }
            }).done(function(data){
                if(data != null) {
                    $('#inputName').val(data.name);
                    $('#inputVIP').prop('checked',data.vip);
                }
            }).fail(function(data){
                $('#error').html(formatErrorData(data));
                $('#error').show();
            });
        });
        
        ts3Select(selTS);
        
        selTS.on('select2:select', function (e) {
            var data = e.params.data;
            tsID.val(data.id);
        });
        
        $("#joinForm").submit(function(e) {
            $('#joinForm').hide();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: $(this).serialize()
            }).done(function(data){
                if(data.open > 0){
                    $('#warnJoin').append('Es existieren bereits '+data.open+' offene Mitgliedseinträge!');
                    $('#warnJoin').show();
                }
                if(data.overrode){
                    $('#warnOverride').append('Ein bestehender Eintrag wurde überschrieben!');
                    $('#warnOverride').show();
                }
                $('#edit').attr('href',MEMBER_DETAIL_URL + data.id);
                $('#RaccID').text(data.id);
                $('#RrealName').text(data.name);
                $('#Rts3').text(data.ts3);
                $('#Rdate').text(data.date);
                $('#Rvip').prop('checked', data.vip);
                $('#result').show();
            }).fail(function(data){
                $('#error').html(formatErrorData(data));
                $('#error').show();
            });
            e.preventDefault();
        });
    });
    </script>
    <?php
}

function getDatabaseView() {
    ?>
    <div>
        <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
                <div style="position: fixed; left: 50%; top: 50%;">
                    <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                    Loading...
                </div>
        </div>
        <div>
            <h3>Database Statistics</h3>
            <table id="dbstats" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th scope="col">Key</th>
                        <th scope="col">Value</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <div id="erromsg" class="alert alert-danger fade in" style="display: none;"></div>
        </div>
    </div>
    <script type="text/javascript">
    jQuery.ajaxSetup({
    beforeSend: function() {
        $('#loading').show();
    },
    complete: function(){
        $('#loading').hide();
    },
    success: function() {}
    });
    
    $( document ).ready(function() {
        initTableTool();
        $.ajax({
            url: URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'database-json',
            }
            }).done(function(data){
                var sw = {value: ""}; // pointer via object value
                createCol(sw,'IDs',data.ids);
                createCol(sw,'Account Names',data.names);
                createCol(sw,'Member (real) Names',data.realnames);
                createCol(sw,'Exp & CP entries',data.rows);
                createCol(sw,'AFKs',data.afks);
                createCol(sw,'Cautions',data.cautions);
                createCol(sw,'Unlinked ts IDs',data.unlinkedTS);
                createCol(sw,'TS Names',data.tsIDs);
                createCol(sw,'TS Data',data.tsdata);
                createCol(sw,'Joins',data.joins);
                createCol(sw,'Leaves',data.leaves);
                createCol(sw,'Leaves with set cause',data.causes);
                createCol(sw,'2nd Accounts',data.secondAccs);
                createCol(sw,'Amount days without data',data.missing);
                createCol(sw,'Log entries',data.log);
                $('#dbstats tbody').html(sw.value);
                $('#erromsg').hide();
                updateTableTool();
            }).fail(function(data){
                $('#erromsg').html(formatErrorData(data));
                $('#erromsg').show();
            });
    });
    
    function createCol(sw,key,val) {
        sw.value += '<tr><td>'+key+'</td><td>'+val+'</td></tr>';
    }
    </script>
<?php }

function getGeneralView() {
    handleDateOverview();?>
    <div id="overview-ajax">
        <script type="text/javascript">
        $(document).ready(function() {
            $('#dateDiff').daterangepicker({
                "ranges": {
                    'Last 7 Days': [moment().subtract(7, 'days'), moment()],
                    'Last 14 Days': [moment().subtract(14, 'days'), moment()],
                    'Last 4 Weeks': [moment().subtract(28, 'days'), moment()],
                    'Last 6 Weeks': [moment().subtract(6, 'week'), moment()],
                    'This Year': [moment().startOf('year'), moment()],
                },"locale": {
                    "format": DATE_FORMAT,
                    "separator": " - ",
                    "applyLabel": "Apply",
                    "cancelLabel": "Cancel",
                    "fromLabel": "From",
                    "toLabel": "To",
                    "customRangeLabel": "Custom",
                    "weekLabel": "W",
                    "daysOfWeek": DAYS_OF_WEEK,
                    "monthNames": MONTH_NAMES,
                },
                "minDate": moment('<?=DATE_MIN?>',DATE_FORMAT),
                "maxDate": moment(),
                "autoUpdateInput": true
            }, function(start, end, label) {
                var vFrom = start.format(DATE_FORMAT);
                var vTo = end.format(DATE_FORMAT);
                setURLParameter({'dateFromOverview' : vFrom, 'dateToOverview': vTo});
                showOverviewChart(start,end);
            });
            
            var start = moment('<?=$_SESSION[DATE_FROM_OVERVIEW]?>',DATE_FORMAT);
            var end = moment('<?=$_SESSION[DATE_TO_OVERVIEW]?>',DATE_FORMAT);
            
            $('#dateDiff').data('daterangepicker').setStartDate(start);
            $('#dateDiff').data('daterangepicker').setEndDate(end);
                        
            showOverviewChart(start,end);
        });
        function drawTs3Stats(data) {
            if(data === null){
                console.log('null data');
                // no data, use toy values
                data = {x: ['2012-02-24 00:00:00'], total: [-1], console: [-1]};
            }
            var mode; // performance safer
            if ( data.x.length < 2000 ) {
                mode = 'lines+markers';
            } else {
                mode = 'lines';
            }
            var total = {
                x: data.x, 
                y: data.total, 
                fill: 'tonexty', 
                type: 'scatter',
                mode: mode,
                name: 'Total Clients',
                line: {shape: 'hv'},
            };

            var console = {
                x: data.x, 
                y: data.console, 
                fill: 'tozeroy', 
                type: 'scatter',
                name: 'Console Clients',
                mode: mode,
                line: {shape: 'hv'},
            };
            
            layout = {                     // all "layout" attributes: #layout
                title: 'TS3 Stats (~15min behind)',  // more about "layout.title": #layout-title
                xaxis: {                  // all "layout.xaxis" attributes: #layout-xaxis
                    title: 'time'         // more about "layout.xaxis.title": #layout-xaxis-title
                },
                yaxis: {
                    title: 'clients online'
                },
                autosize: true,
            };
            
            Plotly.newPlot('chart-tsrelation',[console,total],layout,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false});
        }
        function showTs3Stats(vFrom,vTo) {
            $.ajax({
                url: URL,
                type: 'get',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'ts3-stats',
                    'dateFromOverview' : vFrom,
                    'dateToOverview' : vTo,
                }
            }).done(function(data){
                drawTs3Stats(data);
                $("#loading").hide();
                $('#erromsg').hide();
            }).fail(function(data){
                $('#erromsg').html('Error!<br>'+formatErrorData(data));
                $("#loading").hide();
                $('#erromsg').show();
            });
        }
        function showOverviewChart(start,end) {
            $("#loading").show();
            var vFrom = start.format(DATE_FORMAT);
            var vTo = end.format(DATE_FORMAT);
            setURLParameter({'dateFromOverview' : vFrom, 'dateToOverview': vTo});
            $.ajax({
                url: URL,
                type: 'get',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'overview-graph',
                    'dateFromOverview' : vFrom,
                    'dateToOverview' : vTo,
                }
            }).done(function(data){
                drawOverviewChart(data);
                drawMissingOverviewEntries(data.missing);
                showTs3Stats(vFrom,vTo); // will hide error,loading
            }).fail(function(data){
                $('#erromsg').html('Error!<br>'+formatErrorData(data));
                $("#loading").hide();
                $('#erromsg').show();
            });
        }
        function drawMissingOverviewEntries(data) {
            if(data == null) {
                $('#missing-overview').empty();
            } else {
                var missing_str = "<b>Missing data for:</b><br>";
                $.each(data,function(i,row){
                    missing_str += row + '<br>';
                });
                $('#missing-overview').html(missing_str);
            }
        }
        function drawOverviewChart(data) {
            var mode; // performance safer
            if ( data.x.length < 2000 ) {
                mode = 'lines+markers';
            } else {
                mode = 'lines';
            }
            var online_ts = {
                x: data.x,
                y: data.ts_count,
                fill: 'tonexty',
                type: 'scatter',
                mode: mode,
                name: 'Identities in TS',
                yaxis: 'y'
            };

            var time_avg = {
                x: data.x, 
                y: data.ts_time_avg, 
                fill: 'tozeroy',
                type: 'scatter',
                name: 'Avg TS Time',
                mode: mode,
                yaxis: 'y2'
            };
            
            var member = {
                x: data.x, 
                y: data.member, 
                fill: 'tozeroy',
                type: 'scatter',
                name: 'Member',
                mode: mode,
                yaxis: 'y',
            };
            
            var wins = {
                x: data.x, 
                y: data.wins, 
                type: 'scatter',
                name: 'Wins CW',
                mode: mode,
                yaxis: 'y2',
            };
            
            var losses = {
                x: data.x, 
                y: data.losses, 
                type: 'scatter',
                name: 'Losses CW',
                mode: mode,
                yaxis: 'y3',
            };
            
            var draws = {
                x: data.x, 
                y: data.draws, 
                type: 'scatter',
                name: 'Draws CW',
                mode: mode,
                yaxis: 'y4',
            };
            
            var active = {
                x: data.x,
                y: data.active,
                type: 'scatter',
                fill: 'tozeroy',
                name: 'Active Ingame (min 5000EXP)',
                mode: mode,
                yaxis: 'y',
            };
            
            var online = {
                x: data.x,
                y: data.online,
                type: 'scatter',
                fill: 'tozeroy',
                name: 'Online Ingame (min 1 EXP)',
                mode: mode,
                yaxis: 'y',
            };
            
            var exp = {
                x: data.x,
                y: data.exp_avg,
                type: 'scatter',
                name: 'AVG Exp',
                mode: mode,
                yaxis: 'y2',
            };
            
            var casher = {
                x: data.x,
                y: data.casher,
                type: 'scatter',
                name: 'Casher',
                mode: mode,
                yaxis: 'y',
            };
            
            layout = {
                title: 'Clan Stats',
                xaxis: {
                    title: 'Day'
                },
                yaxis: {
                    title: 'Member',
                    type: 'line',
                    side: 'left',
                    autorange: true,
                    hoverformat: 'd',
                    anchor: 'free',
                },yaxis2: {
                    title: 'Wins',
                    type: 'line',
                    overlaying: 'y',
                    visible: false,
                    hoverformat: 'd',
                    autorange: true,
                    anchor: 'free',
                },yaxis3: {
                    title: 'Loss',
                    type: 'line',
                    overlaying: 'y',
                    visible: false,
                    autorange: true,
                    hoverformat: 'd',
                    anchor: 'free',
                },yaxis4: {
                    title: 'Draws',
                    type: 'line',
                    overlaying: 'y',
                    visible: false,
                    hoverformat: 'd',
                    autorange: true,
                    anchor: 'free',
                },
                autosize: true,
            };
            
            layout_ts = {
                title: 'TS-Activity Relation',
                xaxis: {
                    title: 'Day'
                },
                yaxis: {
                    title: 'Online Identities',
                    type: 'line',
                    side: 'left'
                },
                yaxis2: {
                    title: 'Average online Time',
                    type: 'date',
                    side: 'right',
                    overlaying: 'y',
                    tickformat: '%H:%M:%S'
                },
                autosize: true,
            };
            
            layout_active = {
                title: 'Activity Stats',
                xaxis: {
                    title: 'Day'
                },
                yaxis: {
                    title: 'Active Players',
                    type: 'line',
                    side: 'left'
                },
                yaxis2: {
                    title: 'Average EXP',
                    type: 'line',
                    side: 'right',
                    autorange: true,
                    overlaying: 'y',
                },
                yaxis3: {
                    title: 'Online',
                    type: 'line',
                    side: 'right',
                    visible: false,
                    autorange: true,
                    overlaying: 'y',
                },
                autosize: true,
            };
            
            Plotly.newPlot('chart-overview',[member, wins, losses, draws],layout,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false});
            
            Plotly.newPlot('chart-activity',[active,exp,online_ts,casher],layout_active,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false});
            
            Plotly.newPlot('chart-tsstats',[online_ts,online,time_avg],layout_ts,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false});

        }
        </script>
        <div class="form-horizontal">
            <div class="form-group">
                <label for="dateDiff" class="control-label col-xs-2">Date Range</label>
                <div class="col-xs-10">
                    <input type="text" id="dateDiff" class="form-control" name="daterange" value="01/01/2015 - 01/31/2015" />
                </div>
            </div>
        </div>
        <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
                <div style="position: fixed; left: 50%; top: 50%;">
                    <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                    <div id="loading-text">Loading...</div>
                </div>
        </div>
        
        <div id="chart-overview"></div>
        <h3 style="color: red;">Beta:</h3>
        <div id="chart-tsrelation"></div>
        <div id="chart-tsstats"></div>
        <div id="chart-activity"></div>
        <div id="erromsg" class="alert alert-danger fade in" style="display: none;"></div>
        <div id="missing-overview" width="auto" height="auto"></div>
    </div>
    <?php
}

function getDifferenceWeeklyView() {
    handleDateWeekly();
    ?>
    <script type="text/javascript">
        const container = "#container";
        var nonMembers = [];
        function showDifference(start,end){
            var vFrom = start.format(DATE_FORMAT);
            var vTo = end.format(DATE_FORMAT);
            setURLParameter({'dateFromW' : vFrom, 'dateToW': vTo});

            showLoading();
            $('#difference-table tbody').empty();
            $.ajax({
                url: URL,
                type: 'get',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'difference-weekly',
                    'dateFromW' : vFrom,
                    'dateToW' : vTo,
                }
            }).done(function(data){
                $('#loading-text').text("Rendering..");
                setTimeout(function() {
                    if (data == null){
                        $("#loading").hide();
                        $('#dateInfo').show();
                        updateTableTool();
                        return;
                    }
                    var str = '<thead><tr class="tablesorter-ignoreRow"><th colspan=5>Kommentar mit Enter bestätigen!</th>';
                    var secondHeader = '<tr><th class="sorter-text">Vorname</th><th class="sorter-text">Account</th><th class="sorter-digit">ID / USN</th><th class="sorter-text">VIP</th><th class="sorter-text">Kommentar</th>';
                    $.each(data.date, function(i,row) {
                        str += '<th colspan=3 ><a href="'+escapeHtml('<?=DIFFERENCE_URL?>'
                        + '&dateFrom=' + row.start
                        + '&dateTo=' + row.end)
                        + '">' + row.end + '</a></th>';
                        secondHeader += '<th class="sorter-text">Flags</th><th class="sorter-digit">EXP</th><th class="sorter-digit">CP</th>';
                    });
                    str += '</tr>';
                    secondHeader += '</tr></thead><tbody>';
                    str += secondHeader;
                    nonMembers = [];
                    $.each(data.data,function(i,acc){
                        var member = acc[Object.keys(acc)[0]];
                        
                        var rid = "acc-"+member.id;
                        
                        str += '<tr id="';
                        str += rid;
                        str += '"><td>';
                        
                        rid = '#'+rid;
                        
                        // no member = no entry for last date
                        if(acc[data.date[data.date.length-1].end] == undefined || 
                            (acc[data.date[data.date.length-2].end] != undefined &&
                            acc[data.date[data.date.length-1].end].days < 7 ))
                        {
                            nonMembers.push(rid);
                        }
                        
                        if(member.vname == null)
                            str += formatMemberDetailLink(member.id,"?");
                        else
                            str += escapeHtml(member.vname);
                        str += '</td>';
                        
                        str += '<td>' + escapeHtml(member.name) + '</td>';
                        str += '<td>'+formatMemberDetailLink(member.id,member.id)+'</td>';
                        
                        str += '<td data-text='; // provide a data-text attribute for sorting
                        if(member.vip == null) {
                            str += '"?">';
                            str += formatMemberDetailLink(member.id,"?");
                        } else if (member.vip == 1) {
                            str += '"1">';
                            str += '<i class="fas fa-check" title="VIP Spieler"></i>';
                        } else {
                            str += '"0">';
                            str += '<i class="fas fa-times" title="Non VIP Spieler"></i>';
                        }
                        str += '</td>';
                        
                        str +='<td class="cell-editable cell-wrap" data-id="'+member.id+'">';
                        str += escapeHtml(member.comment);
                        str +='</td>';// kommentar
                        
                        $.each(data.date,function(i,key) {
                            if(acc[key.end] != undefined) {
                                var row = acc[key.end];
                                var background = '';
                                if(row.cp == 0 && row.exp == 0 && row.days > 6) {
                                    background = 'class="warning"';
                                } else if(row.cp_by_exp >= 10) {
                                    background = 'class="success"';
                                }
                                var dataText = '';
                                if(row.afk == 1)
                                    dataText += 'afk';
                                if(row.trial == 1)
                                    dataText += 'trial';
                                if(row.caution == 1)
                                    dataText += 'caution';
                                
                                str += '<td ' + background + ' data-text="'+dataText+'">';
                                if(row.afk == 1)
                                    str += '<i class="fas fa-clock" title="AFK"></i>';
                                if(row.afk == 1 && row.trial == 1)
                                    str += ' ';
                                if(row.trial == 1)
                                    str += ' <i class="fas fa-plus" title="Pröbling"></i>';
                                if(row.caution == 1)
                                    str += ' <i class="fas fa-exclamation-triangle" title="Verwarnung"></i>';
                                str += '</td>';
                                
                                var possible = row.days * CP_MAX;
                                if(row.cp != row.cp_by_exp && row.cp <= possible){
                                    str += '<td class = "danger">';
                                } else {
                                    str += '<td '+ background + '>';
                                }
                                
                                str += row.exp + '</td>';
                                str += '<td ' + background + '>' + row.cp_by_exp + '</td>';
                            } else {
                                str += '<td colspan=3></td>';
                            }
                        });
                        str += '</tr>';
                    });
                    str += '</tbody>';
                    //console.log(str);
                    $(fixedTableClass).trigger('destroy');
                    $('#difference-table').html(str);
                    $('#amountNonMember').text(nonMembers.length);
                    swapNonMembers($('#nonMembers').prop('checked'));
                    
                    $('#dateInfo').hide();
                    $("#loading").hide();
                    $('#erromsg').hide();
                    initCustomTableTool();
                    $('.cell-editable').makeEditable(updateDiffComment,<?=MAX_DIFF_COMMENT_CHAR?>);
                },0);
            }).fail(function(data){
                $('#erromsg').html(formatErrorData(data));
                $("#loading").hide();
                updateTableTool();
            }); 
        }
        
        function showLoading() {
            $('#loading').show();
            $('#loading-text').text("Loading..");
        }
        
        function updateDiffComment(element,comment){
            showLoading();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'update-diff-comment',
                    'id' : element.attr('data-id'),
                    'comment' : comment
                }
            }).done(function(data){
                if(data){
                    $('#erromsg').hide();
                }
                $('#loading').hide();
            }).fail(function(data){
                $('#erromsg').html(formatErrorData(data));
                $('#erromsg').show();
                $('#loading').hide();
            });
        }
        
        function swapNonMembers(display) {
            for(var i = 0; i < nonMembers.length; i++){
                if(display)
                    $(nonMembers[i]).show();
                else
                    $(nonMembers[i]).hide();
            }
        }
        
        function initCustomTableTool() {
            $(fixedTableClass).tablesorter({
                widgets: ["stickyHeaders"],
                widgetOptions : {
                    stickyHeaders : "fixed-header" // background fix class
                }
            });
        }
    
        $( document ).ready(function() {
            
            $('#nonMembers').change(function() {
                swapNonMembers(this.checked);
            });
        
            $('#dateDiff').daterangepicker({
                "ranges": {
                    'Last 4 Weeks': [moment().subtract(4, 'week'), moment()],
                    'Last 6 Weeks': [moment().subtract(6, 'week'), moment()],
                    'Last 2 Months': [moment().subtract(2, 'month'), moment()],
                    'Last 4 Months': [moment().subtract(4, 'month'), moment()],
                    'Last 12 Months': [moment().subtract(12, 'month'), moment()],
                    'This Month': [moment().startOf('month'), moment()],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },"locale": {
                    "format": DATE_FORMAT,
                    "separator": " - ",
                    "applyLabel": "Apply",
                    "cancelLabel": "Cancel",
                    "fromLabel": "From",
                    "toLabel": "To",
                    "customRangeLabel": "Custom",
                    "weekLabel": "W",
                    "daysOfWeek": DAYS_OF_WEEK,
                    "monthNames": MONTH_NAMES,
                },
                "minDate": moment('<?=DATE_MIN?>',DATE_FORMAT),
                "maxDate": moment(),
                "autoUpdateInput": true
            }, function(start, end, label) {
                var vFrom = start.format(DATE_FORMAT);
                var vTo = end.format(DATE_FORMAT);
                setURLParameter({'dateFromW' : vFrom, 'dateToW': vTo});
                showDifference(start,end);
            });
            
            var start = moment('<?=$_SESSION[DATE_FROM_WEEKLY]?>',DATE_FORMAT);
            var end = moment('<?=$_SESSION[DATE_TO_WEEKLY]?>',DATE_FORMAT);
            
            $('#dateDiff').data('daterangepicker').setStartDate(start);
            $('#dateDiff').data('daterangepicker').setEndDate(end);
            
            showDifference(start,end);
        });
    </script>
    <div id="difference-weekly">
        <div class="form-horizontal">
            <div class="form-group">
                <label for="dateDiff" class="control-label col-xs-2">Date Range</label>
                <div class="col-xs-10">
                    <input type="text" id="dateDiff" class="form-control" name="daterange" value="01/01/2015 - 01/31/2015" />
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <div class="checkbox">
                        <label><input type="checkbox" id="nonMembers" <?php
                    checkboxStatus($_SESSION[SHOW_NONMEMBERS]); ?>> Zeige "Nicht Member" (mit unvollständiger letzten Woche)<label id="amountNonMember"></label></label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                  A week goes from sunday to saturday. (z8 data offset aware)
                </div>
            </div>
        </div>
        <div id="dateInfo" class="alert alert-warning" style="display: none;">
            <h2>No data for selected range!</h2>
            Entweder es fehlen Daten für die Auswahl oder es wurden noch keine Erhoben.
        </div>
        <div id="container">
            <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
            <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
                    <div style="position: fixed; left: 50%; top: 50%;">
                        <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                        <div id="loading-text">Loading...</div>
                    </div>
            </div>
            <table id="difference-table" class="table table-bordered table-hover table_nowrap fixed-table">
            <thead>
            </thead>
            <tbody>
            </tbody>
            </table>
        </div>
    </div>
    <?php
}

function getDifferenceView() {
    handleDateDifference();
    ?>
    <div id="difference-ajax">
        <script type="text/javascript">
        const table = '#difference-table';
        
        $( document ).ready(function() {
            $('#dateDiff').daterangepicker({
                "ranges": {
                    'Last 7 Days': [moment().subtract(7, 'days'), moment()],
                    'Last 14 Days': [moment().subtract(14, 'days'), moment()],
                    'Last 3 Weeks': [moment().subtract(21, 'days'), moment()],
                    'Last 4 Weeks': [moment().subtract(28, 'days'), moment()],
                    'Last 6 Weeks': [moment().subtract(6, 'week'), moment()],
                    'This Month': [moment().startOf('month'), moment()],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },"locale": {
                    "format": DATE_FORMAT,
                    "separator": " - ",
                    "applyLabel": "Apply",
                    "cancelLabel": "Cancel",
                    "fromLabel": "From",
                    "toLabel": "To",
                    "customRangeLabel": "Custom",
                    "weekLabel": "W",
                    "daysOfWeek": DAYS_OF_WEEK,
                    "monthNames": MONTH_NAMES,
                },
                "minDate": moment('<?=DATE_MIN?>',DATE_FORMAT),
                "maxDate": moment(),
                "autoUpdateInput": true
            }, function(start, end, label) {
                var vFrom = start.format(DATE_FORMAT);
                var vTo = end.format(DATE_FORMAT);
                setURLParameter({'dateFrom' : vFrom, 'dateTo': vTo});
                showDifference(start,end);
            });
            
            var start = moment('<?=$_SESSION[DATE_FROM]?>',DATE_FORMAT);
            var end = moment('<?=$_SESSION[DATE_TO]?>',DATE_FORMAT);
            
            $('#dateDiff').data('daterangepicker').setStartDate(start);
            $('#dateDiff').data('daterangepicker').setEndDate(end);
            
            showDifference(start,end);
            
            var myTextExtraction = function(node) 
            {
                //console.log(node.cellIndex + " " + node.innerText);
                if(node.cellIndex != 3){
                    return node.innerText; 
                } else {
                    return node.innerHTML;
                }
            }
            
            initTableTool(myTextExtraction);
        });
        
        function differenceSwapDatabaseView(show){
            var max = 11;
            var start = 5;
            if(show) {
                for( i=start; i < max; i++) {
                    $('#difference-table td:nth-child('+i+'),th:nth-child('+i+')').show();
                }
            }else{
                for( i=start; i < max; i++) {
                    $('#difference-table td:nth-child('+i+'),th:nth-child('+i+')').hide();
                }
            }
            updateTableTool();
        }
        
        // HOTFIX: replaced bootstrap-daterangepicker's clock icons with Font Awesome 5 clock icons
        var existingClockIcons = document.getElementsByClassName('daterangepicker');
        if(existingClockIcons.length > 0) {
            existingClockIcons = existingClockIcons.item(0)
            .getElementsByClassName('fa-clock-o');
            const newClockIcon = document.createElement('i');
            newClockIcon.classList.add('far', 'fa-clock');
            existingClockIcons.item(0).parentNode.replaceChild(newClockIcon, existingClockIcons.item(0));
            existingClockIcons.item(0).parentNode.replaceChild(newClockIcon.cloneNode(), existingClockIcons.item(0));
        }
        
        function showDifference(start,end) {
            var vFrom = start.format(DATE_FORMAT);
            var vTo = end.format(DATE_FORMAT);
            setURLParameter({'dateFrom' : vFrom, 'dateTo': vTo});
            
            $('#loading').show();
            $('#difference-table tbody').empty();
            $.ajax({
                url: URL,
                type: 'get',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'difference-json',
                    'dateFrom' : vFrom,
                    'dateTo' : vTo,
                }
            }).done(function(data){
                if (data == null){
                    $("#loading").hide();
                    $('#dateInfo').show();
                    updateTableTool();
                    return;
                }
                var str = '';
                $.each(data,function(i,row){
                    str += '<tr ';
                    if(row.cp == 0 && row.exp == 0 && row.days > 6) {
                        str += 'class="warning"';
                    } else if(row.cp_by_exp >= 10) {
                        str += 'class="success"';
                    }
                    str += '>';
                    str += '<td>';
                    if(row.vname == null)
                        str += formatMemberDetailLink(row.id,"?");
                    else
                        str += escapeHtml(row.vname);
                    if(row.afk == 1)
                        str += ' <i class="fas fa-clock" title="AFK"></i>';
                    if(row.trial == 1)
                        str += ' <i class="fas fa-plus" title="Pröbling"></i>';
                    str += '</td>';
                    
                    str += '<td>'+escapeHtml(row.name) + '</td>';
                    str += '<td>'+formatMemberDetailLink(row.id,row.id)+'</td>';
                                        
                    str += '<td>';
                    if(row.vip == null)
                        str += formatMemberDetailLink(row.id,"?");
                    else
                        str += row.vip == 1 ? '<i class="fas fa-check" title="VIP Spieler"></i>' : '<i class="fas fa-times" title="Non VIP Spieler"></i>';
                    str += '</td>';
                    
                    str += '<td>' + row.date1 + '</td>';
                    str += '<td>' + row.exp1 + '</td>';
                    str += '<td>' + row.cp1 + '</td>';
                    str += '<td>' + row.date2 + '</td>';
                    str += '<td>' + row.exp2 + '</td>';
                    str += '<td>' + row.cp2 + '</td>';
                    str += '<td>' + row.cp + '</td>';
                    var possible = row.days * CP_MAX;
                    if(row.cp != row.cp_by_exp && row.cp <= possible){
                        str += '<td class = "danger">';
                    } else {
                        str += '<td>';
                    }
                    
                    str += row.cp_by_exp + '</td>';
                    str += '<td>' + row.exp + '</td>';
                    str += '<td>' + row.days + '</td>';
                    str += '</tr>';
                });
                
                //setTimeout(function() {
                    $('#dateInfo').hide();
                    $('#difference-table tbody').html(str);
                    differenceSwapDatabaseView($('#dataBasis').is(':checked'));
                    updateTableTool();
                    $("#loading").hide();
                    $('#erromsg').hide();
                //}, 3000);
                
            }).fail(function(data){
                $('#erromsg').html('Error!<br>'+formatErrorData(data));
                $('#erromsg').show();
                $("#loading").hide();
                updateTableTool();
            });
        }
        </script>
        <div class="form-horizontal">
            <div class="form-group">
                <label for="dateDiff" class="control-label col-xs-2">Date Range</label>
                <div class="col-xs-10">
                    <input type="text" id="dateDiff" class="form-control" name="daterange" value="01/01/2015 - 01/31/2015" />
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <div class="checkbox">
                        <label><input onclick="differenceSwapDatabaseView($('#dataBasis').is(':checked'))" type="checkbox" id="dataBasis" <?php
                    checkboxStatus($_SESSION[SHOW_BASE]); ?>> Zeige Datenbasis</label>
                    </div>
                </div>
            </div>
        </div>
        <div id="dateInfo" class="alert alert-warning" style="display: none;">
            <h2>No data for selected range!</h2>
            Entweder es fehlen Daten für die Auswahl oder es wurden noch keine Erhoben.
        </div>
        <div id="container">
            <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
                <div style="position: fixed; left: 50%; top: 50%;">
                    <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                    Loading...
                </div>
            </div>
            <table id="difference-table" class="table table-striped table-bordered table-hover table_nowrap fixed-table" data-cols-number="2">
                <thead>
                    <tr>
                        <th scope="col" class="sorter-text">Vorname</th>
                        <th scope="col" class="sorter-text">Account</th>
                        <th scope="col" class="sorter-digit">ID / USN</th>
                        <th scope="col" class="sorter-text">VIP</th>
                        <th scope="col" class="sorter-text">Date1</th>
                        <th scope="col" class="sorter-digit">Exp1</th>
                        <th scope="col" class="sorter-digit">CP1</th>
                        <th scope="col" class="sorter-digit">Date2</th>
                        <th scope="col" class="sorter-digit">Exp2</th>
                        <th scope="col" class="sorter-digit">CP2</th>
                        <th scope="col" class="sorter-digit">CP Differenz</th>
                        <th scope="col" class="sorter-digit">CP nach EXP</th>
                        <th scope="col" class="sorter-digit">EXP Differenz</th>
                        <th scope="col" class="sorter-digit">Tage</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <div id="erromsg" class="alert alert-danger fade in" style="display: none;"></div>
        </div>
    </div>
    <?php
}

function getTSView() {
    handleDateTS();
    ?>
    <div id="ts-ajax">
        <script type="text/javascript">
        
        function preselect(id,inputAcc) {
            $('#loading').show();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'id' : id,
                    'ajaxCont' : 'data',
                    'type' : 'account-select',
                }
            }).done(function(data){
                if(data.valid){
                    var option = new Option(data.acc.text, data.acc.id, true, true);
                    inputAcc.append(option).trigger('change');
                    inputAcc.trigger({
                        type: 'select2:select',
                        params: {
                            data: data.acc
                        }
                    });
                } else {
                    $('#error').html(
                    '<span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>'
                    +'<span class="sr-only">Error:</span> '
                    +'Invalid Account id!'
                    );
                    $('#error').show();
                    $('#loading').hide();
                }
            }).fail(function(data){
                $('#error').html(formatErrorData(data));
                $('#error').show();
                $('#loading').hide();
            });
        }
        
        $( document ).ready(function() {
            var inputAcc = $('#inputAccount');
            accountSelect(inputAcc);
            
            initTableTool();
            
            var inputDate = $('#dateDiff');
            
            inputAcc.on('select2:select', function (e) {
                renderTSChart();
            });
            
            inputDate.daterangepicker({
                "ranges": {
                    'Last Week': [moment().subtract(7, 'days'), moment()],
                    'Last 4 Weeks': [moment().subtract(4, 'weeks'), moment()],
                    'Last 6 Weeks': [moment().subtract(6, 'week'), moment()],
                    'Last 8 Weeks': [moment().subtract(8, 'weeks'), moment()],
                    'Last 16 Weeks': [moment().subtract(16, 'weeks'), moment()],
                    'This Month': [moment().startOf('month'), moment()],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },"locale": {
                    "format": DATE_FORMAT,
                    "separator": " - ",
                    "applyLabel": "Apply",
                    "cancelLabel": "Cancel",
                    "fromLabel": "From",
                    "toLabel": "To",
                    "customRangeLabel": "Custom",
                    "weekLabel": "W",
                    "daysOfWeek": DAYS_OF_WEEK,
                    "monthNames": MONTH_NAMES,
                },
                "minDate": moment('<?=DATE_MIN?>',DATE_FORMAT),
                "maxDate": moment(),
                "autoUpdateInput": true
            }, function(start, end, label) {
                renderTSChart();
            });
            
            var start = moment('<?=$_SESSION[DATE_FROM_TS]?>',DATE_FORMAT);
            var end = moment('<?=$_SESSION[DATE_TO_TS]?>',DATE_FORMAT);
            
            inputDate.data('daterangepicker').setStartDate(start);
            inputDate.data('daterangepicker').setEndDate(end);
            
            <?php if(isset($_REQUEST['id'])) { ?>
                preselect('<?=$_REQUEST['id']?>',inputAcc);
            <?php } else { ?>
                inputAcc.select2('open');
            <?php } ?>
        });
        
        function renderTSChart() {
            var picker = $('#dateDiff');
            var acc = $('#inputAccount').select2('data');
            if(acc.length == 0){
                cleanupCharts();
                $('#member-table tbody').html("");
                return;
            }
            $('#loading').show();
            var id = acc[0].id;
            var vFrom = picker.data('daterangepicker').startDate.format(DATE_FORMAT);
            var vTo = picker.data('daterangepicker').endDate.format(DATE_FORMAT);
            setURLParameter({'dateFromTS' : vFrom, 'dateToTS': vTo, 'id' : id});
            $.ajax({
                url: 'index.php',
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'member-ts',
                    'dateFromTS' : vFrom,
                    'dateToTS' : vTo,
                    'id' : id,
                }
            }).done(function(data){
                if(data != null) {
                    drawTSChart(data);
                    $('#dateInfo').hide();
                } else {
                    $('#member-table tbody').empty();
                    $('#dateInfo').show();
                }
                $('#memberLink').attr('href','<?=MEMBER_DETAIL_URL?>' + id);
                $('#loading').hide();
                $('#error').hide();
            }).fail(function(data){
                $('#error').html(formatErrorData(data));
                $('#error').show();
                $('#loading').hide();
                updateTableTool();
            });
        }
        function drawTSChart(data) {
            
            const labelDateFormat = "DD.MM";
            
            var averageData = [];
            for(var i = 0; i < data.average.length; i++) {
                averageData.push(moment(data.average[i],'SS'));
            }
            
            var labels = [];
            for(var i = 0; i < data.date.length; i++) {
                var elem = data.date[i];
                var fStart = moment(elem.start, DATE_FORMAT).format(labelDateFormat);
                var fEnd = moment(elem.end, DATE_FORMAT).format(labelDateFormat);
                labels.push('Week '+fStart + ' - '+ fEnd);
            }
            
            cleanupCharts();
            var ctx = document.getElementById("chart-ts");
            charts.push(new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        type: 'bar',
                        fillColor: "rgba(220,220,220,0.5)",
                        strokeColor: "rgba(220,220,220,0.8)",
                        highlightFill: "rgba(220,220,220,0.75)",
                        highlightStroke: "rgba(220,220,220,1)",

                        borderColor: 'rgba(255, 0, 0,0.5)',
                        backgroundColor: 'rgba(255, 0, 0,0.2)',
                        label: 'Days active',
                        data: data.days,
                        borderWidth: 1,
                        yAxisID: 'y-axis-1',
                    },{
                        type: 'line',
                        borderColor: 'rgba(255, 0, 255,0.5)',
                        backgroundColor: 'rgba(255, 0, 255,0.2)',
                        label: 'Average time',
                        data: data.average,
                        borderWidth: 1,
                        yAxisID: 'y-axis-2',
                    }],
                },
                options: {
                    tooltips: {
                        mode: 'index',
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var index = tooltipItem.datasetIndex;
                                if (index != 1) {
                                    return data.datasets[index].label + ' ' + tooltipItem.yLabel;
                                } else {
                                    return data.datasets[index].label + ' ' +
                                    moment("2015-01-01").startOf('day')
                                    .seconds(tooltipItem.yLabel)
                                    .format('H:mm:ss');
                                }
                            },
                        },
                    },
                    hover: {
                        mode: 'nearest',
                        intersect: true
                    },
                    scales: {
                        xAxes: [{
                            display: true,
                        }],yAxes: [{
                            ticks: {
                                min: 0,
                                max: TS_DIFF_MIN,
                                stepSize: 1,
                                beginAtZero: true,
                            },
                            id: 'y-axis-1',
                            display: true,
                            position: 'left',
                        },{
                            ticks: {
                                min: 0,
                                beginAtZero: true,
                                callback: function(value, index, values) {
                                    return moment("2015-01-01").startOf('day')
                                    .seconds(value)
                                    .format('H:mm:ss');;
                                }
                            },
                            fill: true,
                            id: 'y-axis-2',
                            display: true,
                            position: 'right',
                            gridLines: {
                                drawOnChartArea: false, // only want the grid lines for one axis to show up
                            },
                        }]
                    }
                }
            }));
        }
        </script>
        <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
        <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
                <div style="position: fixed; left: 50%; top: 50%;">
                    <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                    Loading...
                </div>
        </div>
        <div id="dateInfo" class="alert alert-warning" style="display: none;">
            <h2>No data for selected range!</h2>
            Entweder es fehlen Daten für die Auswahl oder es wurden noch keine Erhoben.
        </div>
        <p>Data incorrect ? <a href="index.php?site=clantool2&view=ts3&id=<?=TS_REFERENCE_ACCOUNT?>">This account</a> has to have 100% online time!</p>
        <div class="form-horizontal">
            <div class="form-group">
                <label for="dateDiff" class="control-label col-xs-2">Date Range</label>
                <div class="col-xs-10">
                    <input type="text" id="dateDiff" class="form-control" name="daterange" value="01/01/2015 - 01/31/2015" />
                </div>
            </div>
            <div class="form-group">
                <label for="inputAccount" class="control-label col-xs-2">Account</label>
                <div class="col-xs-10">
                    <select class="form-control" name="id" required="" id="inputAccount">
                    </select>
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                  A week goes from sunday to saturday.  
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <a class="btn btn-default" type="button" required="" id="memberLink">
                        <i class="fas fa-address-book"></i> Member Details
                    </a>
                </div>
            </div>
        </div>
        <canvas id="chart-ts" width="auto" height="auto"></canvas>
    </div>
    <?php
}

function getMemberDifferenceView() {
    handleDateDifference(); ?>
    <div id="member-ajax">
        <script type="text/javascript">
        
        function preselect(id,inputAcc) {
            $('#loading').show();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'id' : id,
                    'ajaxCont' : 'data',
                    'type' : 'account-select',
                }
            }).done(function(data){
                if(data.valid){
                    var option = new Option(data.acc.text, data.acc.id, true, true);
                    inputAcc.append(option).trigger('change');
                    inputAcc.trigger({
                        type: 'select2:select',
                        params: {
                            data: data.acc
                        }
                    });
                } else {
                    $('#error').html(
                    '<span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>'
                    +'<span class="sr-only">Error:</span> '
                    +'Invalid Account id!'
                    );
                    $('#error').show();
                    $('#loading').hide();
                }
            }).fail(function(data){
                $('#error').html(formatErrorData(data));
                $('#error').show();
                $('#loading').hide();
            });
        }
        
        $( document ).ready(function() {
            var inputAcc = $('#inputAccount');
            accountSelect(inputAcc);
            
            
            initTableTool();
            
            var inputDate = $('#dateDiff');
            
            inputAcc.on('select2:select', function (e) {
                showMemberChart();
            });
            
            inputDate.daterangepicker({
                "ranges": {
                    'Last 7 Days': [moment().subtract(7, 'days'), moment()],
                    'Last 14 Days': [moment().subtract(14, 'days'), moment()],
                    'Last 3 Weeks': [moment().subtract(21, 'days'), moment()],
                    'Last 4 Weeks': [moment().subtract(28, 'days'), moment()],
                    'Last 6 Weeks': [moment().subtract(6, 'week'), moment()],
                    'This Month': [moment().startOf('month'), moment()],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },"locale": {
                    "format": DATE_FORMAT,
                    "separator": " - ",
                    "applyLabel": "Apply",
                    "cancelLabel": "Cancel",
                    "fromLabel": "From",
                    "toLabel": "To",
                    "customRangeLabel": "Custom",
                    "weekLabel": "W",
                    "daysOfWeek": DAYS_OF_WEEK,
                    "monthNames": MONTH_NAMES,
                },
                "minDate": moment('<?=DATE_MIN?>',DATE_FORMAT),
                "maxDate": moment(),
                "autoUpdateInput": true
            }, function(start, end, label) {
                showMemberChart();
            });
            
            var start = moment('<?=$_SESSION[DATE_FROM]?>',DATE_FORMAT);
            var end = moment('<?=$_SESSION[DATE_TO]?>',DATE_FORMAT);
            
            inputDate.data('daterangepicker').setStartDate(start);
            inputDate.data('daterangepicker').setEndDate(end);
            
            showMemberChart(start,end);
            
            <?php if(isset($_REQUEST['id'])) { ?>
                preselect('<?=$_REQUEST['id']?>',inputAcc);
            <?php } else { ?>
                inputAcc.select2('open');
            <?php } ?>
        });
        
        function showMemberChart() {
            var picker = $('#dateDiff');
            var acc = $('#inputAccount').select2('data');
            if(acc.length == 0){
                cleanupCharts();
                $('#member-table tbody').html("");
                return;
            }
            $('#loading').show();
            var id = acc[0].id;
            var vFrom = picker.data('daterangepicker').startDate.format(DATE_FORMAT);
            var vTo = picker.data('daterangepicker').endDate.format(DATE_FORMAT);
            setURLParameter({'dateFrom' : vFrom, 'dateTo': vTo, 'id' : id});
            $.ajax({
                url: 'index.php',
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'member-difference',
                    'dateFrom' : vFrom,
                    'dateTo' : vTo,
                    'id' : id,
                }
            }).done(function(data){
                if(data != null) {
                    drawMemberChart(data);
                    drawMemberTable(data);
                    $('#dateInfo').hide();
                } else {
                    $('#member-table tbody').empty();
                    $('#dateInfo').show();
                }
                $('#memberLink').attr('href','<?=MEMBER_DETAIL_URL?>' + id);
                $('#loading').hide();
                $('#error').hide();
            }).fail(function(data){
                $('#error').html(formatErrorData(data));
                $('#error').show();
                $('#loading').hide();
                updateTableTool();
            });
        }
        function drawMemberChart(data) {
            cleanupCharts();
            var ctx = document.getElementById("chart-member");
            charts.push(new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: [{
                        type: 'line',
                        borderColor: 'rgba(255, 0, 0,0.5)',
                        backgroundColor: 'rgba(255, 0, 0,0.2)',
                        label: 'EXP Differenz',
                        data: data.exp_diff,
                        borderWidth: 1,
                        yAxisID: "y-axis-1"
                    },{
                        borderColor: 'rgba(0, 255, 0,0.5)',
                        backgroundColor: 'rgba(0, 255, 0,0.2)',
                        label: 'CP Differenz',
                        data: data.cp_diff,
                        borderWidth: 1,
                        yAxisID: "y-axis-2"
                    }]
                },
                options: {
                    tooltips: {
                        mode: 'index',
                    },
                    hover: {
                        mode: 'index',
                        intersect: true
                    },
                    scales: {xAxes: [{
                        type: "time",
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Date'
                        }
                    }],
                    yAxes: [{
                        type: "linear",// logarithmic
                        display: true,
                        position: "left",
                        ticks: {
                            min: 0,
                            beginAtZero: true,
                        },
                        id: "y-axis-1",
                    },{
                        type: "linear",
                        display: true,
                        position: "right",
                        ticks: {
                            min: 0,
                            beginAtZero: true,
                            //max: 1000,
                        },
                        id: "y-axis-2",
                        gridLines: {
                            drawOnChartArea: true,
                        },
                    }]
                    }
                }
            }));
        }
        function drawMemberTable(data) {
            var str = '';
            var length = data.exp_diff.length;
            for( var i = 0; i < length; i++) {
                str += '<tr ';
                var lExp = data.exp_diff[i].y;
                var lCP = data.cp_diff[i].y;
                if(lExp >= EXP_MAX_CP && lCP < CP_MAX) {
                    str += 'class="danger"';
                }else if(lCP < CP_MAX && lCP < (Math.trunc(lExp / EXP_REQUIRED_CP)) ) {
                    str += 'class="danger"';
                } else if (lCP <= CP_MAX && lCP > (Math.trunc(lExp / EXP_REQUIRED_CP))) {
                    str += 'class="danger"';
                }
                str += '>';
                str += '<td>' + data.cp[i].x + '</td>';
                str += '<td>' + data.exp[i].y + '</td>';
                str += '<td>' + data.exp_diff[i].y + '</td>';
                str += '<td>' + data.cp[i].y + '</td>';
                str += '<td>' + data.cp_diff[i].y + '</td>';
                str += '</tr>';
            }
            $('#member-table tbody').html(str);
            updateTableTool();
        }
        </script>
        <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
        <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
                <div style="position: fixed; left: 50%; top: 50%;">
                    <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                    Loading...
                </div>
        </div>
        <div id="dateInfo" class="alert alert-warning" style="display: none;">
            <h2>No data for selected range!</h2>
            Entweder es fehlen Daten für die Auswahl oder es wurden noch keine Erhoben.
        </div>
        <div class="form-horizontal">
            <div class="form-group">
                <label for="dateDiff" class="control-label col-xs-2">Date Range</label>
                <div class="col-xs-10">
                    <input type="text" id="dateDiff" class="form-control" name="daterange" value="01/01/2015 - 01/31/2015" />
                </div>
            </div>
            <div class="form-group">
                <label for="inputAccount" class="control-label col-xs-2">Account</label>
                <div class="col-xs-10">
                    <select class="form-control" name="id" required="" id="inputAccount">
                    </select>
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <a class="btn btn-default" type="button" required="" id="memberLink">
                        <i class="fas fa-address-book"></i> Member Details
                    </a>
                </div>
            </div>
        </div>
        <canvas id="chart-member" width="auto" height="auto"></canvas>
        <h4>Rohdaten:</h4>
        <table id="member-table" class="table table-striped table-bordered table-hover fixed-table">
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Exp</th>
                    <th scope="col">Exp Differenz</th>
                    <th scope="col">CP</th>
                    <th scope="col">CP Differenz</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <?php
}

function getSettingsView() {
    ?>
    <div class="col-sm-12 col-xs-11">
        <div id="error" style="display: none;" class="alert alert-danger fade in"></div>
        <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
                <div style="position: fixed; left: 50%; top: 50%;">
                    <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                    Loading...
                </div>
        </div>
        <h3>System Settings</h3>
        Some of these settings can be overriden in the configuration file.<br>
        Operate with caution.
        <form class="form-horizontal" id="settingsForm" action="" method="post">
            <input type="hidden" name="site" value="<?=SITE?>">
            <input type="hidden" name="ajaxCont" value="data">
            <input type="hidden" name="type" value="settings-set">
            <div class="form-group">
                <label for="leave-detection" class="control-label col-xs-2">Auto Leave Detection</label>
                <div class="col-xs-10">
                    <div class="checkbox">
                        <label><input type="checkbox" name="leave-detection" id="leave-detection" checked="checked"> Auto Leave Detection</label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="leave-cause" class="control-label col-xs-2">Auto-Leave Cause</label>
                <div class="col-xs-10">
                    <input type="text" name="leave-cause" required="" autocomplete="on" class="form-control" id="leave-cause" placeholder="Cause to use for auto leaves">
                </div>
            </div>
            <div class="form-group">
                <label for="ts3-removal" class="control-label col-xs-2">TS3 Group removal</label>
                <div class="col-xs-10">
                    <div class="checkbox">
                        <label><input type="checkbox" name="ts3-removal" id="ts3-removal" checked="checked"> TS3 Group removal</label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="ts3-whitelist" class="control-label col-xs-2">TS3 Group whitelist</label>
                <div class="col-xs-10">
                    <input type="text" name="ts3-whitelist" required="" autocomplete="on" class="form-control" id="ts3-whitelist" placeholder="Groups staying after leave">
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-10">
                    <button type="submit" class="btn btn-warning" id="submitSettings"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </form>
    </div>
    <script type="text/javascript">
    $( document ).ready(function() {
        const loadingDiv = $('#loading');
        const errorDiv = $('#error');
        function loadSettings() {
            loadingDiv.show();
            $.ajax({
                url: 'index.php',
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'settings-load',
                }
            }).done(function(data){
                if(data != null){
                    $('#leave-detection').prop('checked',data['leave-detection']);
                    $('#leave-cause').val(data['leave-cause']);
                    $('#ts3-removal').prop('checked',data['ts3-removal']);
                    $('#ts3-whitelist').val(data['ts3-whitelist']);
                }
                loadingDiv.hide();
            }).fail(function(data){
                console.error(data);
                loadingDiv.hide();
            });
        }
        $("#settingsForm").submit(function(e) {
            loadingDiv.show();
            $.ajax({
                url: URL,
                type: 'post',
                dataType: "json",
                data: $(this).serialize()
            }).done(function(data){
                if(data){
                    errorDiv.hide();
                }else{
                    errorDiv.show();
                    errorDiv.txt('Unable to save!');
                }
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        });
        loadSettings();
    });
    </script>
    <?php
}

function getLogView() {
    handleDateLog();
    ?>
    <div class="form-horizontal">
        <div class="form-group">
            <label for="dateDiff" class="control-label col-xs-2">Date Range</label>
            <div class="col-xs-10">
                <input type="text" id="dateDiff" class="form-control" name="daterange" value="01/01/2015 - 01/31/2015" />
            </div>
        </div>
    </div>
    <div id="content">
        <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
            <div style="position: fixed; left: 50%; top: 50%;">
                <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                Loading...
            </div>
        </div>
        <table id="log" class="table table-striped table-bordered table-hover">
            <thead>
                <tr><th>Date</th><th>Entry</th></tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <script type="text/javascript">
    $( document ).ready(function() {
        const loadingDiv = $('#loading');
        const picker = $('#dateDiff');
        const dateFrom = 'dateFromLog';
        const dateTo = 'dateToLog';
        const log = $('#log tbody');
        
        picker.daterangepicker({
            "ranges": {
                'Last 7 Days': [moment().subtract(7, 'days'), moment()],
                'Last 14 Days': [moment().subtract(14, 'days'), moment()],
                'Last 3 Weeks': [moment().subtract(21, 'days'), moment()],
                'Last 4 Weeks': [moment().subtract(28, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment()],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            },"locale": {
                "format": DATE_FORMAT,
                "separator": " - ",
                "applyLabel": "Apply",
                "cancelLabel": "Cancel",
                "fromLabel": "From",
                "toLabel": "To",
                "customRangeLabel": "Custom",
                "weekLabel": "W",
                "daysOfWeek": DAYS_OF_WEEK,
                "monthNames": MONTH_NAMES,
            },
            "minDate": moment('<?=DATE_MIN?>',DATE_FORMAT),
            "maxDate": moment(),
            "autoUpdateInput": true
        }, function(start, end, label) {
            loadLog();
        });
        
        function loadLog() {
            loadingDiv.show();
            var vFrom = picker.data('daterangepicker').startDate.format(DATE_FORMAT);
            var vTo = picker.data('daterangepicker').endDate.format(DATE_FORMAT);
            setURLParameter({dateFrom : vFrom, dateTo : vTo});
            $.ajax({
                url: 'index.php',
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'log',
                    [dateFrom] : vFrom,
                    [dateTo] : vTo,
                }
            }).done(function(data){
                console.log(data);
                var str = '';
                $.each(data,function(i,row){
                    str += '<tr><td>';
                    str += row.date;
                    str += '</td><td>';
                    str += row.msg;
                    str += '</td></tr>';
                });
                log.html(str);
                updateTableTool();
                loadingDiv.hide();
            }).fail(function(data){
                console.error(data);
                log.empty();
                loadingDiv.hide();
            });
        }
        
        var start = moment('<?=$_SESSION[DATE_FROM_LOG]?>',DATE_FORMAT);
        var end = moment('<?=$_SESSION[DATE_TO_LOG]?>',DATE_FORMAT);
            
        picker.data('daterangepicker').setStartDate(start);
        picker.data('daterangepicker').setEndDate(end);
        
        initTableTool();
        
        loadLog();
    });
    </script>
    <?php
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
<?php }
