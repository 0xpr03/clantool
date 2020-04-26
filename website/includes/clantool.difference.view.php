<?php handleDateDifference(); ?>
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
            url: SITE_URL,
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
