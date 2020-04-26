<?php
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
