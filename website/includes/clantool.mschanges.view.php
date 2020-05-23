<?php
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
