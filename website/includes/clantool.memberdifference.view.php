<?php handleDateDifference(); ?>
<div id="member-ajax">
    <script type="text/javascript">
    
    function preselect(id,inputAcc) {
        $('#loading').show();
        $.ajax({
            url: SITE_URL,
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
