<?php
function ajax() {
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
                    'ts3-check' => $clanDB->getSetting(KEY_TS3_CHECK),
                    'ts3-member-groups' => $clanDB->getSetting(KEY_TS3_MEMBER_GROUPS),
                    'leave-detection' => $clanDB->getSetting(KEY_AUTO_LEAVE),
                    
                    'GUEST_NOTIFY_ENABLE' => $clanDB->getSetting(KEY_TS3_GUEST_NOTIFY_ENABLE),
                    'GUEST_WATCHER_GROUP' => $clanDB->getSetting(KEY_TS3_GUEST_WATCHER_GROUP),
                    'GUEST_GROUP' => $clanDB->getSetting(KEY_TS3_GUEST_GROUP),
                    'GUEST_POKE_MSG' => $clanDB->getSetting(KEY_TS3_GUEST_POKE_MSG),
                    'GUEST_CHANNEL' => $clanDB->getSetting(KEY_TS3_GUEST_CHANNEL),

                    'AFK_MOVE_ENABLE' => $clanDB->getSetting(KEY_TS3_AFK_MOVE_ENABLED),
                    'AFK_IGNORE_GROUP' => $clanDB->getSetting(KEY_TS3_AFK_IGNORE_GROUP),
                    'AFK_MOVE_CHANNEL' => $clanDB->getSetting(KEY_TS3_AFK_MOVE_CHANNEL),
                    'AFK_MOVE_IGNORE_CHANNELS' => $clanDB->getSetting(KEY_TS3_AFK_IGNORE_CHANNELS),
                    'AFK_MOVE_TIME' => $clanDB->getSetting(KEY_TS3_AFK_TIME),
                    
                    'FETCH_MISSING_NAMES_ENABLE' => $clanDB->getSetting(KEY_FETCH_MISSING_NAMES_ENABLE),
                )
            );
            break;
        case 'settings-set':
            // manually to disallow sender dictating key names
            $clanDB->setSetting(KEY_LEAVE_CAUSE,$_POST['leave-cause']);
            $clanDB->setSetting(KEY_TS3_CHECK,isset($_POST['ts3-check']));
            $clanDB->setSetting(KEY_TS3_MEMBER_GROUPS,$_POST['ts3-member-groups']);
            $clanDB->setSetting(KEY_AUTO_LEAVE,isset($_POST['leave-detection']));
            
            $clanDB->setSetting(KEY_TS3_GUEST_NOTIFY_ENABLE,isset($_POST['GUEST_NOTIFY_ENABLE']));
            $clanDB->setSetting(KEY_TS3_GUEST_WATCHER_GROUP,$_POST['GUEST_WATCHER_GROUP']);
            $clanDB->setSetting(KEY_TS3_GUEST_GROUP,$_POST['GUEST_GROUP']);
            $clanDB->setSetting(KEY_TS3_GUEST_POKE_MSG,$_POST['GUEST_POKE_MSG']);
            $clanDB->setSetting(KEY_TS3_GUEST_CHANNEL,$_POST['GUEST_CHANNEL']);

            $clanDB->setSetting(KEY_TS3_AFK_MOVE_ENABLED,isset($_POST['AFK_MOVE_ENABLE']));
            $clanDB->setSetting(KEY_TS3_AFK_IGNORE_GROUP,$_POST['AFK_IGNORE_GROUP']);
            $clanDB->setSetting(KEY_TS3_AFK_MOVE_CHANNEL,$_POST['AFK_MOVE_CHANNEL']);
            $clanDB->setSetting(KEY_TS3_AFK_IGNORE_CHANNELS,$_POST['AFK_MOVE_IGNORE_CHANNELS']);
            $clanDB->setSetting(KEY_TS3_AFK_TIME,$_POST['AFK_MOVE_TIME']);
            
            $clanDB->setSetting(KEY_FETCH_MISSING_NAMES_ENABLE,isset($_POST['FETCH_NAMES_ENABLE']));
            
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
                $notes = $clanDB->getGlobalNoteForRange($chunkstart,$chunkend);
                
                //var_dump($chunk);
                if($chunk != null) {
                    $result['date'][] = array(
                        'start' => $chunkstart,
                        'end' => $chunkend,
                        'notes' => $notes);
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
        case 'has-ts3-unknown-identities':
            echo json_encode($clanDB->hasUnknownTs3IDs());
            break;
        case 'ts3-unknown-identities':
            echo json_encode($clanDB->getUnknownTSIdentities());
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
        case 'ignore-ts3-id':
            $clanDB->ignoreTS3Identity($_POST['tsID']);
            echo json_encode(true);
            break;
        case 'add-ts3-relation':
            $clanDB->startTransaction();
            $clanDB->insertTSRelation($_POST['id'],$_POST['tsID']);
            $clanDB->endTransaction();
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
                'tsdataold' => $clanDB->getTSOldDataCount(),
                'missing' => $clanDB->getMissingEntriesCount(),
                'log' => $clanDB->getLogEntryCount(),
                'tsdatastats' => $clanDB->getTSStatsDataCount(),
                'tsidentignored' => $clanDB->getTSIgnoreCount(),
                'tschannelcount' => $clanDB->getTSChannelCount(),
                'tsidentities' => $clanDB->getTSIdentityCount(),
                'tsidentitiesrelated' => $clanDB->getTSRelationsCount(),
                'globalnotes' => $clanDB->getGlobalNotesCount()
            );
            echo json_encode($res);
            break;
        case 'ts-channels-group-rename':
            $group = $_POST['gid'];
            $name = $_POST['name'];
            $clanDB->ts3RenameChannelGroup($group,$name);
            echo json_encode(true);
            break;
        case 'ts-channels-ungrouped':
            echo json_encode($clanDB->ts3UngroupedChannels());
            break;
        case 'ts-channel-group-create':
            $name = $_POST['name'];
            $id = $clanDB->addTs3ChannelGroup($name);
            echo json_encode(array('id' => $id,'gname' => $name, 'channels' => array()));
            break;
        case 'ts-channel-group-delete':
            $clanDB->deleteTs3ChannelGroup($_POST['gid']);
            echo json_encode(true);
            break;
        case 'ts-channel-group-add-channel':
            $clanDB->addTs3CGChannel($_POST['gid'],$_POST['cid']);
            echo json_encode(true);
            break;
        case 'ts-channel-group-remove-channel':
            $clanDB->removeTs3CGChannel($_POST['gid'],$_POST['cid']);
            echo json_encode(true);
            break;
        case 'ts-channel-search-select2':
            if(isset($_REQUEST['key']) && $_REQUEST['key'] != null) {
                $result = array(
                    'results' => $clanDB->searchTs3Channel($_REQUEST['key']),
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
        case 'ts-channel-groups':
            $start_time = microtime(true);
            $data = array('data' => $clanDB->getTsChannelGroups());
            $data['elapsed'] = (microtime(true) - $start_time) / 1000;
            echo json_encode($data);
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
        case 'member-ts-old':
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
            $start_time = microtime(true);
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
                    
                    $chunk = $clanDB->getMemberTSSummaryOld($chunkstart,
                    $chunkend,$id);
                    
                    $data['date'][] = array(
                        'start' => $chunkstart,
                        'end' => $chunkend,
                    );
                    $data['days'][] = $chunk['days'] == null ? 0 : $chunk['days'];
                    $data['average'][] = $chunk['avg_raw'] == null ? 0 : $chunk['avg_raw'];
                }
            }
            $data['elapsed'] = (microtime(true) - $start_time) / 1000;
            echo json_encode($data);
            break;
        case 'member-ts-detailed':
            if(!hasPerm(PERM_CLANTOOL_ADMIN)) {
                http_response_code(403);
                echo 'Missing permissions!';
                return;
            }
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
            
            // trick plotly into thinking this is a date
            $zero = "1970-01-01 00:00:00";
            $start_time = microtime(true);
            $db_time = 0;
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
                $i = 0;
                $average = &$data['average'];
                foreach($daterange as $date) {
                    $chunkstart = $date->format($FORMAT);
                    $chunkend = $date->modify('+'.(TS_DIFF_MIN-1).' day')->format($FORMAT); // go till (inclusive) sunday
                    
                    $db_start = microtime(true);
                    $chunk = $clanDB->getMemberTSSummaryDetailed($chunkstart,
                    $chunkend,$id);
                    $db_time += microtime(true) - $db_start;
                    
                    $data['date'][] = array(
                        'start' => $chunkstart,
                        'end' => $chunkend,
                    );
                    if ($chunk == null) {
                        // fill up with 0 for every channel if no data
                        foreach($average as &$channel) {
                            $channel['data'][$i] = $zero;
                        }
                        unset($channel);
                        $data['days'][] = 0;
                    } else {
                        $data['days'][] = $chunk['days'];
                        foreach($chunk['data'] as $cid => $channel) {
                            if (!isset($average[$cid])) {
                                $average[$cid] = array('data' => array(),
                                    'channel' => $channel['channel']);
                            }
                            $average[$cid]['data'][$i] = $channel['timeAvg'];
                        }
                    }
                    $i++;
                }
                
                // now 0 out missing values, not all channels always have values
                foreach($average as $channel => $_unused) {
                    for ($j = 0; $j < $i; $j++) {
                        if (!isset($average[$channel]['data'][$j])) {
                            $average[$channel]['data'][$j] = $zero;
                        }
                    }
                }
            }
            $time_elapsed_secs = microtime(true) - $start_time;
            $data['elapsed'] = $time_elapsed_secs / 1000;
            $data['db'] = $db_time / 1000;
            $data['cleaned'] = ($time_elapsed_secs - $db_time) / 1000;
            echo json_encode($data);
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
            
            // trick plotly into thinking this is a date
            $zero = "1970-01-01 00:00:00";
            $start_time = microtime(true);
            $db_time = 0;
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
                $i = 0;
                $average = &$data['average'];
                foreach($daterange as $date) {
                    $chunkstart = $date->format($FORMAT);
                    $chunkend = $date->modify('+'.(TS_DIFF_MIN-1).' day')->format($FORMAT); // go till (inclusive) sunday
                    
                    $db_start = microtime(true);
                    $chunk = $clanDB->getMemberTSSummary($chunkstart,
                    $chunkend,$id);
                    
                    $db_time += microtime(true) - $db_start;
                    
                    $data['date'][] = array(
                        'start' => $chunkstart,
                        'end' => $chunkend,
                    );
                    if ($chunk == null) {
                        // fill up with 0 for every channel if no data
                        foreach($average as &$group) {
                            $group['data'][$i] = $zero;
                        }
                        unset($group);
                        $data['days'][] = 0;
                    } else {
                        $data['days'][] = $chunk['days'];
                        foreach($chunk['data'] as $gid => $group) {
                            if (!isset($average[$gid])) {
                                $average[$gid] = array('data' => array(),
                                    'group' => $group['group']);
                            }
                            $average[$gid]['data'][$i] = $group['timeAvg'];
                        }
                    }
                    $i++;
                }
                // now 0 out missing values, not all channels always have values
                foreach($average as $group => $_unused) {
                    for ($j = 0; $j < $i; $j++) {
                        //echo json_encode($group)."</group>\n";
                        //echo json_encode($j)."</j>\n";
                        if (!isset($average[$group]['data'][$j])) {
                            //echo "not set\n";
                            $average[$group]['data'][$j] = $zero;
                        }
                    }
                }
                //echo json_encode($average);
                //echo "</av end>\n";
            }
            $time_elapsed_secs = microtime(true) - $start_time;
            $data['elapsed'] = $time_elapsed_secs / 1000;
            $data['db'] = $db_time / 1000;
            $data['cleaned'] = ($time_elapsed_secs - $db_time) / 1000;
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
        case 'global-note-add':
            $from = $_POST['from'];
            $to = $_POST['to'];
            $message = $_POST['message'];
            $clanDB->insertGlobalNote($from,$to,$message);
            echo json_encode(true);
            break;
        case 'global-note-delete':
            $id = $_POST['id'];
            $clanDB->deleteGlobalNote($id);
            echo json_encode(true);
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
}}
