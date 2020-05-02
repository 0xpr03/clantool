<?php handleDateTS(); ?>
<div id="ts-ajax">
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
        
        checkTSIdentities();
        
        var inputDate = $('#dateDiff');
        
        inputAcc.on('select2:select', function (e) {
            renderTSChart();
            renderTSChartOld();
            <?php if(hasPerm(PERM_CLANTOOL_ADMIN)) {
                echo 'renderTSChartDetailed();';
            } ?>
        });
        
        inputDate.daterangepicker({
            "ranges": {
                'Last Week': [moment().subtract(7, 'days'), moment()],
                'Last 4 Weeks': [moment().subtract(4, 'weeks'), moment()],
                'Last 6 Weeks': [moment().subtract(6, 'week'), moment()],
                'Last 8 Weeks': [moment().subtract(8, 'weeks'), moment()],
                'Last 16 Weeks': [moment().subtract(16, 'weeks'), moment()],
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
            renderTSChartOld();
            renderTSChart();
            <?php if(hasPerm(PERM_CLANTOOL_ADMIN)) {
                echo 'renderTSChartDetailed();';
            } ?>
        });
        
        var start = moment('<?=$_SESSION[DATE_FROM_TS]?>',DATE_FORMAT);
        var end = moment('<?=$_SESSION[DATE_TO_TS]?>',DATE_FORMAT);
        
        inputDate.data('daterangepicker').setStartDate(start);
        inputDate.data('daterangepicker').setEndDate(end);
        
        <?php if(isset($_REQUEST['id'])) { ?>
            preselect('<?=$_REQUEST['id']?>',inputAcc);
        <?php } else { ?>
            inputAcc.select2('open');
        <?php } ?>
    });
    
    function checkTSIdentities() {
        $.ajax({
            url: 'index.php',
            type: 'get',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'has-ts3-unknown-identities',
            }
        }).done(function(data){
            if(data) {
                $('#identityWarn').show();
            } else {
                $('#identityWarn').hide();
            }
            $('#error').hide();
        }).fail(function(data){
            $('#error').html(formatErrorData(data));
            $('#error').show();
            updateTableTool();
        });
    }
    function renderTSChartDetailed() {
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
        setURLParameter({'dateFromTS' : vFrom, 'dateToTS': vTo, 'id' : id});
        $.ajax({
            url: 'index.php',
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'member-ts-detailed',
                'dateFromTS' : vFrom,
                'dateToTS' : vTo,
                'id' : id,
            }
        }).done(function(data){
            if(data != null) {
                drawTSChartDetailed(data);
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
    function renderTSChart() {
        var picker = $('#dateDiff');
        var acc = $('#inputAccount').select2('data');
        if(acc.length == 0){
            cleanupCharts();
            $('#member-table tbody').html("");
            return;
        }
        
        var id = acc[0].id;
        var vFrom = picker.data('daterangepicker').startDate.format(DATE_FORMAT);
        var vTo = picker.data('daterangepicker').endDate.format(DATE_FORMAT);
        
        let switch_date = moment(DATE_SWITCH_TS_DATA, "YYYY-MM-DD");
        let old_data = switch_date.isAfter(vTo);
        if (old_data) {
            console.log("too old data, hiding new chart");
            $('#chart-ts').hide();
            return;
        }
        
        $('#loading').show();
        
        setURLParameter({'dateFromTS' : vFrom, 'dateToTS': vTo, 'id' : id});
        $.ajax({
            url: 'index.php',
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'member-ts',
                'dateFromTS' : vFrom,
                'dateToTS' : vTo,
                'id' : id,
            }
        }).done(function(data){
            if(data != null) {
                drawTSChart(data);
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
    var objectToArray = function(obj) {
        var arr =[];
        for(let o in obj) {
            if (obj.hasOwnProperty(o)) {
            arr.push(obj[o]);
            }
        }
        return arr;
    };
    function drawTSChartDetailed(data) {
        
        const labelDateFormat = "DD.MM";
        
        let labels = [];
        for(var i = 0; i < data.date.length; i++) {
            var elem = data.date[i];
            var fStart = moment(elem.start, DATE_FORMAT).format(labelDateFormat);
            var fEnd = moment(elem.end, DATE_FORMAT).format(labelDateFormat);
            labels.push('Week '+fStart + ' - '+ fEnd);
        }
        
        
        let datasets = [];
        for(var key in data.average) {
            datasets.push({
                x: labels,
                y: objectToArray(data.average[key].data),
                name: data.average[key].channel,
                type: 'bar',
                yaxis: 'y',
                xaxis: 'x',
            });
        }
        console.log(datasets);
        
        let layout = {
            /*xaxis: {                  // all "layout.xaxis" attributes: #layout-xaxis
                title: 'date'         // more about "layout.xaxis.title": #layout-xaxis-title
            },*/
            yaxis: {
                type: 'date',
                tickformat: '%H:%M:%S'
            },
            barmode: 'stack',
        };
        //cleanupCharts();
        $('#chart-ts-detailed').show();
        Plotly.newPlot('chart-ts-detailed',datasets,layout,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false})
    }
    function drawTSChart(data) {
        
        const labelDateFormat = "DD.MM";
        
        let labels = [];
        for(var i = 0; i < data.date.length; i++) {
            var elem = data.date[i];
            var fStart = moment(elem.start, DATE_FORMAT).format(labelDateFormat);
            var fEnd = moment(elem.end, DATE_FORMAT).format(labelDateFormat);
            labels.push('Week '+fStart + ' - '+ fEnd);
        }
        
        
        let datasets = [];
        for(var key in data.average) {
            datasets.push({
                x: labels,
                y: objectToArray(data.average[key].data),
                name: data.average[key].group,
                type: 'bar',
                yaxis: 'y',
                xaxis: 'x',
            });
        }
        console.log(datasets);
        
        let layout = {
            /*xaxis: {                  // all "layout.xaxis" attributes: #layout-xaxis
                title: 'date'         // more about "layout.xaxis.title": #layout-xaxis-title
            },*/
            yaxis: {
                type: 'date',
                tickformat: '%H:%M:%S'
            },
            barmode: 'stack',
        };
        //cleanupCharts();
        $('#chart-ts').show();
        Plotly.newPlot('chart-ts',datasets,layout,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false})
    }
    
    function renderTSChartOld() {
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
        
        let switch_date = moment(DATE_SWITCH_TS_DATA, "YYYY-MM-DD");
        let new_data = switch_date.isBefore(vFrom);
        if (new_data) {
            console.log("too new data, hiding old");
            $('#chart-ts-old').hide();
            return;
        }
        
        setURLParameter({'dateFromTS' : vFrom, 'dateToTS': vTo, 'id' : id});
        $.ajax({
            url: 'index.php',
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'member-ts-old',
                'dateFromTS' : vFrom,
                'dateToTS' : vTo,
                'id' : id,
            }
        }).done(function(data){
            if(data != null) {
                drawTSChartOld(data);
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
    function drawTSChartOld(data) {
        
        const labelDateFormat = "DD.MM";
        
        var averageData = [];
        for(var i = 0; i < data.average.length; i++) {
            averageData.push(moment(data.average[i],'SS'));
        }
        
        var labels = [];
        for(var i = 0; i < data.date.length; i++) {
            var elem = data.date[i];
            var fStart = moment(elem.start, DATE_FORMAT).format(labelDateFormat);
            var fEnd = moment(elem.end, DATE_FORMAT).format(labelDateFormat);
            labels.push('Week '+fStart + ' - '+ fEnd);
        }
        
        cleanupCharts();
        $('#chart-ts-old').show();
        var ctx = document.getElementById("chart-ts-old");
        charts.push(new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    type: 'bar',
                    fillColor: "rgba(220,220,220,0.5)",
                    strokeColor: "rgba(220,220,220,0.8)",
                    highlightFill: "rgba(220,220,220,0.75)",
                    highlightStroke: "rgba(220,220,220,1)",

                    borderColor: 'rgba(255, 0, 0,0.5)',
                    backgroundColor: 'rgba(255, 0, 0,0.2)',
                    label: 'Days active',
                    data: data.days,
                    borderWidth: 1,
                    yAxisID: 'y-axis-1',
                },{
                    type: 'line',
                    borderColor: 'rgba(255, 0, 255,0.5)',
                    backgroundColor: 'rgba(255, 0, 255,0.2)',
                    label: 'Average time',
                    data: data.average,
                    borderWidth: 1,
                    yAxisID: 'y-axis-2',
                }],
            },
            options: {
                tooltips: {
                    mode: 'index',
                    callbacks: {
                        label: function(tooltipItem, data) {
                            var index = tooltipItem.datasetIndex;
                            if (index != 1) {
                                return data.datasets[index].label + ' ' + tooltipItem.yLabel;
                            } else {
                                return data.datasets[index].label + ' ' +
                                moment("2015-01-01").startOf('day')
                                .seconds(tooltipItem.yLabel)
                                .format('H:mm:ss');
                            }
                        },
                    },
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    xAxes: [{
                        display: true,
                    }],yAxes: [{
                        ticks: {
                            min: 0,
                            max: TS_DIFF_MIN,
                            stepSize: 1,
                            beginAtZero: true,
                        },
                        id: 'y-axis-1',
                        display: true,
                        position: 'left',
                    },{
                        ticks: {
                            min: 0,
                            beginAtZero: true,
                            callback: function(value, index, values) {
                                return moment("2015-01-01").startOf('day')
                                .seconds(value)
                                .format('H:mm:ss');;
                            }
                        },
                        fill: true,
                        id: 'y-axis-2',
                        display: true,
                        position: 'right',
                        gridLines: {
                            drawOnChartArea: false, // only want the grid lines for one axis to show up
                        },
                    }]
                }
            }
        }));
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
    <div id="identityWarn" class="alert alert-warning" style="display: none;">
        <h2>Unknown TS3 Identities!</h2>
        Es gibt unbekannte TS3 Identitäten mit Member-Gruppen! <a href="index.php?site=clantool2&view=tsIdentity">Mehr</a>
    </div>
    <p>Data incorrect ? <a href="index.php?site=clantool2&view=ts3&id=<?=TS_REFERENCE_ACCOUNT?>">This account</a> has to have 100% online time!</p>
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
                A week goes from sunday to saturday.  
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
    <canvas id="chart-ts-old" style="display: none;" width="auto" height="auto"></canvas>
    <div id="chart-ts" style="display: none;" width="auto" height="auto"></div>
    <div id="chart-ts-detailed" style="display: none;" width="auto" height="auto"></div>
</div>
