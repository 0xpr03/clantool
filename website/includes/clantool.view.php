<?php
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
            <?=generateViewLink('tsIdentity','fas fa-question-circle fa-lg','TS3 Ident')?>
            <?php
            if(hasPermission(PERM_CLANTOOL_TEST)) { // alpha/beta views
                //echo generateViewLink('tsIdentity','fas fa-list-ol fa-lg','TS3 Ident');
            }?>
        </ul>
    </div>
    <div class="column col-sm-9 col-xs-11 container-fluid" id="main">
    <?php        
        $view = $_GET[VIEW];
        $ok_view = true;
        switch($_GET[VIEW]){
            case 'difference':
                require 'includes/clantool.difference.view.php';
                break;
            case 'differenceWeekly':
                require 'includes/clantool.differenceweekly.view.php';
                break;
            case 'memberJoin':
                require 'includes/clantool.memberjoin.view.php';
                break;
            case 'accChange':
                require 'includes/clantool.accountchange.view.php';
                break;
            case 'memberLeave':
                require 'includes/clantool.memberleave.view.php';
                break;
            case 'database':
                require 'includes/clantool.database.view.php';
                break;
            case SITE_MEMBER:
                require 'includes/clantool.memberdetail.view.php';
                break;
            case 'memberDiff':
                require 'includes/clantool.memberdifference.view.php';
                break;
            case 'away':
                require 'includes/clantool.away.view.php';
                break;
            case 'tsTop':
                require 'includes/clantool.tstop.view.php';
                break;
            case 'general':
                require 'includes/clantool.general.view.php';
                break;
            case 'ts3':
                require 'includes/clantool.ts.view.php';
                break;
            case DEFAULT_VIEW:
                require 'includes/clantool.mschanges.view.php';
                break;
            case 'log':
                require 'includes/clantool.log.view.php';
                break;
            case 'settings':
                require 'includes/clantool.settings.view.php';
                break;
            case 'tsIdentity':
                require 'includes/clantool.tsidentity.view.php';
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
        const MEMBER_DETAIL_URL = "<?=MEMBER_DETAIL_URL?>";
        const SITE_URL = "<?=URL?>"; // sites URL to do ajax on (relative)
        const DATE_FORMAT = "YYYY-MM-DD";
        const P_ACC_ID = 'id';
        const TS_DIFF_MIN = <?=TS_DIFF_MIN?>;
        </script>
        Copyright Aron Heinecke 2017-2020 <a href="https://github.com/0xpr03/clantool">Sourcecode</a>
    </div>
    <?php
} 
