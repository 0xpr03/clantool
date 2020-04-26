<?php handleDateLog(); ?>
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
                str += escapeHtml(row.msg);
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
