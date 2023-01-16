<?php
require 'includes/clantool.db.inc.php';
$clanDB = new clanDB();
?>
<h3>Global Notes</h3>
Werden in der Diff-Weekly oben eingeblendet, effektiv globale AFKs.
<div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
            <div style="position: fixed; left: 50%; top: 50%;">
                <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                Loading...
            </div>
    </div>
<div id="error" style="display: none;" class="alert alert-danger fade in"></div>
<form class="form-horizontal" id="globalNoteForm" action="" method="post">
    <div class="form-group">
        <label for="dateDiff" class="control-label col-xs-2">Date range</label>
        <div class="col-xs-10">
            <input type="text" id="dateDiff" class="form-control" name="daterange" value="01/01/2015 - 01/31/2015" />
        </div>
    </div>
    <div class="form-group">
        <label for="inputMessage" class="control-label col-xs-2">Message</label>
        <div class="col-xs-10">
            <input type="text" name="message" autocomplete="off" required="true" class="form-control" id="inputMessage" placeholder="Message">
        </div>
    </div>
    <div class="form-group">
        <div class="col-xs-offset-2 col-xs-10">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Global Note</button>
        </div>
    </div>
</form>
<table class="table table-striped table-bordered table-hover" id="entries">
    <thead>
        <tr>
            <th class="sorter-text">From</th>
            <th class="sorter-text">To</th>
            <th class="sorter-text">Message</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php            
        $res = $clanDB->getGlobalNotes();
        
        foreach($res as &$elem){ ?>
            <tr>
                <td><?=htmlspecialchars($elem['from'])?></td>
                <td><?=htmlspecialchars($elem['to'])?></td>
                <td><?=htmlspecialchars($elem['message'])?></td>
                <td><a href="#" class="critical" data-id="<?=htmlspecialchars($elem['id'])?>" data-action="delete"><i class="fas fa-trash"></i> Delete</a></td>
            </tr>
        <?php
        }
        ?>
    </tbody>
</table>

<script type="text/javascript">
$(document).ready(function() {
    const loadingDiv = $('#loading');
    const errorDiv = $('#error');
    const dateDiff = $('#dateDiff');
    const inputMessage = $('#inputMessage');
    const form = $('#globalNoteForm');
    const entryTable = $('#entries');
    
    const start = moment().subtract(1, 'week');
    const end = moment();
    var vFrom = start.format(DATE_FORMAT);
    var vTo = end.format(DATE_FORMAT);
    dateDiff.daterangepicker({
        "locale": {
            "format": DATE_FORMAT,
            "separator": " - ",
            "applyLabel": "Apply",
            "cancelLabel": "Cancel",
            "fromLabel": "From",
            "toLabel": "To",
            /*"customRangeLabel": "Custom",*/
            "weekLabel": "W",
            "daysOfWeek": DAYS_OF_WEEK,
            "monthNames": MONTH_NAMES,
        },
        "alwaysShowCalendars": true,
//         "minDate": moment('2016-11-31',DATE_FORMAT),
        /*"maxDate": moment(),*/
        "startDate": start,
        "endDate": end,
        "autoUpdateInput": true
    }, function(start, end, label) {
        vFrom = start.format(DATE_FORMAT);
        vTo = end.format(DATE_FORMAT);
    });
    
    form.submit(function(e) {
        e.preventDefault();
        loadingDiv.show();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'from' : vFrom,
                'to' : vTo,
                'message' : inputMessage.val(),
                'ajaxCont' : 'data',
                'type' : 'global-note-add',
            }
        }).done(function(data){
            window.location.reload();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
    });
    entryTable.on('click', 'a', function (e) {
        var id = $(this).attr('data-id');
        loadingDiv.show();
        var action = $(this).attr('data-action');
        if(action == 'delete'){
            $.ajax({
                url: SITE_URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'global-note-delete',
                    'id' : id,
                }
            }).done(function(data){
                window.location.reload();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
        } else {
            console.error('unknown action:'+action);
        }
        
        e.preventDefault();
    });
});
</script>
