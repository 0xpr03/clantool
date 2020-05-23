<?php handleDateWeekly(); ?>
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
            url: SITE_URL,
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
            url: SITE_URL,
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
