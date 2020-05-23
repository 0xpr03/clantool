<?php
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
