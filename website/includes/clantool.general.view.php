<?php handleDateOverview(); ?>
<div id="overview-ajax">
    <script type="text/javascript">
    $(document).ready(function() {
        $('#dateDiff').daterangepicker({
            "ranges": {
                'Last 7 Days': [moment().subtract(7, 'days'), moment()],
                'Last 14 Days': [moment().subtract(14, 'days'), moment()],
                'Last 4 Weeks': [moment().subtract(28, 'days'), moment()],
                'Last 6 Weeks': [moment().subtract(6, 'week'), moment()],
                'This Year': [moment().startOf('year'), moment()],
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
            setURLParameter({'dateFromOverview' : vFrom, 'dateToOverview': vTo});
            showOverviewChart(start,end);
        });
        
        var start = moment('<?=$_SESSION[DATE_FROM_OVERVIEW]?>',DATE_FORMAT);
        var end = moment('<?=$_SESSION[DATE_TO_OVERVIEW]?>',DATE_FORMAT);
        
        $('#dateDiff').data('daterangepicker').setStartDate(start);
        $('#dateDiff').data('daterangepicker').setEndDate(end);
                    
        showOverviewChart(start,end);
    });
    function drawTs3Stats(data) {
        if(data === null){
            console.log('null data');
            // no data, use toy values
            data = {x: ['2012-02-24 00:00:00'], total: [-1], console: [-1]};
        }
        var mode; // performance safer
        if ( data.x.length < 2000 ) {
            mode = 'lines+markers';
        } else {
            mode = 'lines';
        }
        var total = {
            x: data.x, 
            y: data.total, 
            fill: 'tonexty', 
            type: 'scatter',
            mode: mode,
            name: 'Total Clients',
            line: {shape: 'hv'},
        };

        var console = {
            x: data.x, 
            y: data.console, 
            fill: 'tozeroy', 
            type: 'scatter',
            name: 'Console Clients',
            mode: mode,
            line: {shape: 'hv'},
        };
        
        layout = {                     // all "layout" attributes: #layout
            title: 'TS3 Stats (~15min behind)',  // more about "layout.title": #layout-title
            xaxis: {                  // all "layout.xaxis" attributes: #layout-xaxis
                title: 'time'         // more about "layout.xaxis.title": #layout-xaxis-title
            },
            yaxis: {
                title: 'clients online'
            },
            autosize: true,
        };
        
        Plotly.newPlot('chart-tsrelation',[console,total],layout,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false});
    }
    function showTs3Stats(vFrom,vTo) {
        $.ajax({
            url: SITE_URL,
            type: 'get',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'ts3-stats',
                'dateFromOverview' : vFrom,
                'dateToOverview' : vTo,
            }
        }).done(function(data){
            drawTs3Stats(data);
            $("#loading").hide();
            $('#erromsg').hide();
        }).fail(function(data){
            $('#erromsg').html('Error!<br>'+formatErrorData(data));
            $("#loading").hide();
            $('#erromsg').show();
        });
    }
    function showOverviewChart(start,end) {
        $("#loading").show();
        var vFrom = start.format(DATE_FORMAT);
        var vTo = end.format(DATE_FORMAT);
        setURLParameter({'dateFromOverview' : vFrom, 'dateToOverview': vTo});
        $.ajax({
            url: SITE_URL,
            type: 'get',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'overview-graph',
                'dateFromOverview' : vFrom,
                'dateToOverview' : vTo,
            }
        }).done(function(data){
            drawOverviewChart(data);
            drawMissingOverviewEntries(data.missing);
            showTs3Stats(vFrom,vTo); // will hide error,loading
        }).fail(function(data){
            $('#erromsg').html('Error!<br>'+formatErrorData(data));
            $("#loading").hide();
            $('#erromsg').show();
        });
    }
    function drawMissingOverviewEntries(data) {
        if(data == null) {
            $('#missing-overview').empty();
        } else {
            var missing_str = "<b>Missing data for:</b><br>";
            $.each(data,function(i,row){
                missing_str += row + '<br>';
            });
            $('#missing-overview').html(missing_str);
        }
    }
    function drawOverviewChart(data) {
        var mode; // performance safer
        if ( data.x.length < 2000 ) {
            mode = 'lines+markers';
        } else {
            mode = 'lines';
        }
        var online_ts = {
            x: data.x,
            y: data.ts_count,
            fill: 'tonexty',
            type: 'scatter',
            mode: mode,
            name: 'Identities in TS',
            yaxis: 'y'
        };

        var time_avg = {
            x: data.x, 
            y: data.ts_time_avg, 
            fill: 'tozeroy',
            type: 'scatter',
            name: 'Avg TS Time',
            mode: mode,
            yaxis: 'y2'
        };
        
        var member = {
            x: data.x, 
            y: data.member, 
            fill: 'tozeroy',
            type: 'scatter',
            name: 'Member',
            mode: mode,
            yaxis: 'y',
        };
        
        var wins = {
            x: data.x, 
            y: data.wins, 
            type: 'scatter',
            name: 'Wins CW',
            mode: mode,
            yaxis: 'y2',
        };
        
        var losses = {
            x: data.x, 
            y: data.losses, 
            type: 'scatter',
            name: 'Losses CW',
            mode: mode,
            yaxis: 'y3',
        };
        
        var draws = {
            x: data.x, 
            y: data.draws, 
            type: 'scatter',
            name: 'Draws CW',
            mode: mode,
            yaxis: 'y4',
        };
        
        var active = {
            x: data.x,
            y: data.active,
            type: 'scatter',
            fill: 'tozeroy',
            name: 'Active Ingame (min 5000EXP)',
            mode: mode,
            yaxis: 'y',
        };
        
        var online = {
            x: data.x,
            y: data.online,
            type: 'scatter',
            fill: 'tozeroy',
            name: 'Online Ingame (min 1 EXP)',
            mode: mode,
            yaxis: 'y',
        };
        
        var exp = {
            x: data.x,
            y: data.exp_avg,
            type: 'scatter',
            name: 'AVG Exp',
            mode: mode,
            yaxis: 'y2',
        };
        
        var casher = {
            x: data.x,
            y: data.casher,
            type: 'scatter',
            name: 'Casher',
            mode: mode,
            yaxis: 'y',
        };
        
        layout = {
            title: 'Clan Stats',
            xaxis: {
                title: 'Day'
            },
            yaxis: {
                title: 'Member',
                type: 'line',
                side: 'left',
                autorange: true,
                hoverformat: 'd',
                anchor: 'free',
            },yaxis2: {
                title: 'Wins',
                type: 'line',
                overlaying: 'y',
                visible: false,
                hoverformat: 'd',
                autorange: true,
                anchor: 'free',
            },yaxis3: {
                title: 'Loss',
                type: 'line',
                overlaying: 'y',
                visible: false,
                autorange: true,
                hoverformat: 'd',
                anchor: 'free',
            },yaxis4: {
                title: 'Draws',
                type: 'line',
                overlaying: 'y',
                visible: false,
                hoverformat: 'd',
                autorange: true,
                anchor: 'free',
            },
            autosize: true,
        };
        
        layout_ts = {
            title: 'TS-Activity Relation',
            xaxis: {
                title: 'Day'
            },
            yaxis: {
                title: 'Online Identities',
                type: 'line',
                side: 'left'
            },
            yaxis2: {
                title: 'Average online Time',
                type: 'date',
                side: 'right',
                overlaying: 'y',
                tickformat: '%H:%M:%S'
            },
            autosize: true,
        };
        
        layout_active = {
            title: 'Activity Stats',
            xaxis: {
                title: 'Day'
            },
            yaxis: {
                title: 'Active Players',
                type: 'line',
                side: 'left'
            },
            yaxis2: {
                title: 'Average EXP',
                type: 'line',
                side: 'right',
                autorange: true,
                overlaying: 'y',
            },
            yaxis3: {
                title: 'Online',
                type: 'line',
                side: 'right',
                visible: false,
                autorange: true,
                overlaying: 'y',
            },
            autosize: true,
        };
        
        Plotly.newPlot('chart-overview',[member, wins, losses, draws],layout,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false});
        
        Plotly.newPlot('chart-activity',[active,exp,online_ts,casher],layout_active,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false});
        
        Plotly.newPlot('chart-tsstats',[online_ts,online,time_avg],layout_ts,{responsive: true, modeBarButtonsToRemove: ['sendDataToCloud', 'autoScale2d', 'resetScale2d'] ,displaylogo: false, showTips:false});

    }
    </script>
    <div class="form-horizontal">
        <div class="form-group">
            <label for="dateDiff" class="control-label col-xs-2">Date Range</label>
            <div class="col-xs-10">
                <input type="text" id="dateDiff" class="form-control" name="daterange" value="01/01/2015 - 01/31/2015" />
            </div>
        </div>
    </div>
    <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
            <div style="position: fixed; left: 50%; top: 50%;">
                <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                <div id="loading-text">Loading...</div>
            </div>
    </div>
    
    <div id="chart-overview"></div>
    <h3 style="color: red;">Beta:</h3>
    <div id="chart-tsrelation"></div>
    <div id="chart-tsstats"></div>
    <div id="chart-activity"></div>
    <div id="erromsg" class="alert alert-danger fade in" style="display: none;"></div>
    <div id="missing-overview" width="auto" height="auto"></div>
</div> 
