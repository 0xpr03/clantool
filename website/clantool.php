<?php

define('DATE_FROM', 'clantool_dateFrom');
define('DATE_TO', 'clantool_dateTo');
define('SHOW_BASE', 'clantool_showBase');
define('MEMBER_ID', 'clantool_memberID');
define('SEARCH_KEY', 'clantool_Key');
define('C_TOTAL_ROWS', 'clantool_rows');
define('C_TOTAL_NAMES', 'clantool_names');
define('C_DIFFERENCE', 'clantool_c_overview');
define('C_DATE1', 'clantool_c_date1');
define('C_DATE2', 'clantool_c_date2');

function getContent() {
	getCTTemplate();
}

function increaseSite($site) {
    \main\getDB()->increaseSiteStats('clantool_' . $site, $_SESSION[\main\C_USER][\main\C_U_UID]);
}

function getCTTemplate() {
	if(!(isset($_SESSION[DATE_FROM]) || isset($_SESSION[DATE_TO]))){
		$_SESSION[DATE_FROM] = date('Y-m-d', strtotime('-7 days'));
		$_SESSION[DATE_TO] = date('Y-m-d', strtotime('now'));
	}
	if (!isset($_SESSION[SHOW_BASE])) {
		$_SESSION[SHOW_BASE] = false;
	}
	if (!isset($_SESSION[MEMBER_ID])) {
		$_SESSION[MEMBER_ID] = 0;
	}
	if (!isset($_SESSION[SEARCH_KEY])) {
		$_SESSION[SEARCH_KEY] = '';
	}
	
	if (!isset($_SESSION[C_TOTAL_ROWS]) || !isset($_SESSION[C_TOTAL_NAMES])) {
		require 'includes/clantool.db.inc.php';
		$db = new clanDB();
		$_SESSION[C_TOTAL_ROWS] = $db->getDBStats();
		$_SESSION[C_TOTAL_NAMES] = $db->getDBNameStats();
	}
	
	if(!isset($_SESSION[C_DATE1]) || !isset($_SESSION[C_DATE2])) {
		$_SESSION[C_DATE1] = 0;
		$_SESSION[C_DATE2] = 0;
		$_SESSION[C_DIFFERENCE] = 'a';
	}
	
	?>
	<div class="container">
		<h2>Clantool</h2>Dataset of <?=$_SESSION[C_TOTAL_ROWS]?> entries<br>
		<div class="container-fluid">
			<ul class="nav nav-tabs" id="maintabs">
				<li role="presentation" class="active"><a href="#overview" data-url="index.php?site=clantool&amp;ajaxCont=site&amp;ajaxsite=overview">Allgemein</a></li>
				<li role="presentation"><a href="#difference" data-url="index.php?site=clantool&amp;ajaxCont=site&amp;ajaxsite=difference">Differenz</a></li>
				<li role="presentation"><a href="#member" data-url="index.php?site=clantool&amp;ajaxCont=site&amp;ajaxsite=member">Member</a></li>
				<li role="presentation"><a href="#search" data-url="index.php?site=clantool&amp;ajaxCont=site&amp;ajaxsite=search">Search</a></li>
				<li role="presentation"><a href="#misc" data-url="index.php?site=clantool&amp;ajaxCont=site&amp;ajaxsite=misc">Misc</a></li>
			</ul>
			<div class="tab-content">
				<div role="tabpanel" class="tab-pane active" id="overview"></div>
				<div role="tabpanel" class="tab-pane" id="difference"></div>
				<div role="tabpanel" class="tab-pane" id="member"></div>
				<div role="tabpanel" class="tab-pane" id="search"></div>
				<div role="tabpanel" class="tab-pane" id="misc"></div>
			</div>
		</div>
		<style>
		.popover{
			max-width: 100%; /* Max Width of the popover (depending on the container!) */
		}
		</style>
		<script type="text/javascript">
		const VAR_SITE = "clantool";
		const EXP_MAX_CP = 5000;
		const EXP_REQUIRED_CP = 500;
		const CP_MAX = 10;
		var charts = [];
		function cleanupCharts(){
			var length = charts.length;
			for (var i = 0; i < length; i++ ){
				charts[i].destroy();
			}
			charts = [];
		}
		$( document ).ready(function() {
			$( "#maintabs a" ).click( function (e) {
				e.preventDefault();

				var url = $(this).attr("data-url");
				var href = this.hash;
				var pane = $(this);

				$(href).load(url, function (result){
					pane.tab('show');
					runAfterTab();
				});
			});
			$('#overview').load($('#maintabs .active a').attr("data-url"),function(result){
				  $('.active a').tab('show');
				  runAfterTab();
			});
		});
		</script>
		Copyright Aron Heinecke 2017 <a href="https://github.com/0xpr03/clantool">Sourcecode</a>
	</div>
<?php }

//@Override
function getAjax(){
	switch($_REQUEST['ajaxCont']){
		case 'site':
			switch($_REQUEST['ajaxsite']) {
			case 'overview':
				getOverviewContent();
				break;
			case 'difference':
				getDifferenceContent();
				break;
			case 'member':
				getMemberContent();
				break;
			case 'search':
				getMSearchContent();
				break;
			case 'misc':
				getMiscContent();
				break;
			default:
				http_response_code(404);
				echo 'Sub-Site not found!';
				break;
			}
			break;
		case 'data':
			require 'includes/clantool.db.inc.php';
			$clanDB = new clanDB();
			if(isset($_REQUEST['dateFrom']) && isset($_REQUEST['dateTo'])){
				$_SESSION[DATE_FROM] = $_REQUEST['dateFrom'];
				$_SESSION[DATE_TO] = $_REQUEST['dateTo'];
			}
			if(isset($_REQUEST['showBase'])){
				$_SESSION[SHOW_BASE] = $_REQUEST['showBase'];
			}
			$date1 = $_SESSION[DATE_FROM];
			$date2 = $_SESSION[DATE_TO];
			
			if($date1 != $_SESSION[C_DATE1] || $date2 != $_SESSION[C_DATE2]) {
				$_SESSION[C_DIFFERENCE] = null;
			}
			$_SESSION[C_DATE1] = $date1;
			$_SESSION[C_DATE2] = $date2;
			
			
			switch($_REQUEST['type']) {
			case 'difference-json':
                increaseSite('difference');
				if($_SESSION[C_DIFFERENCE] === null){
					$_SESSION[C_DIFFERENCE] = json_encode($clanDB->getDifference($date1,$date2));
				}
				echo $_SESSION[C_DIFFERENCE];
				break;
			case 'overview-json':
                increaseSite('overview');
                $result = array(
                'graph' => $clanDB->getOverview($date1,$date2),
                'missing' => $clanDB->getMissingEntries($date1,$date2),
                );
				echo json_encode($clanDB->getOverview($date1,$date2));
				break;
			case 'member-json':
                increaseSite('member');
				if(isset($_REQUEST['memberID'])){
					$_SESSION[MEMBER_ID] = $_REQUEST['memberID'];
				}
				echo json_encode($clanDB->getMemberChange($date1,$date2,$_SESSION[MEMBER_ID]));
				break;
			case 'search-json':
                increaseSite('search');
				if(isset($_REQUEST['key'])){
					$_SESSION[SEARCH_KEY] = $_REQUEST['key'];
				}
				echo json_encode($clanDB->searchForMemberName($_SESSION[SEARCH_KEY]));
				break;
			case 'misc-json':
                increaseSite('misc');
				$res = array(
				'left' => $clanDB->getMemberDifference($date1,$date2,true),
				'joined' => $clanDB->getMemberDifference($date1,$date2,false),
				);
				echo json_encode($res);
				break;
			default:
				http_response_code(404);
				echo 'data type not found!';
				break;
			}
			break;
		default:
			http_response_code(404);
			echo 'Case not found!';
			break;
	}
}

function getOverviewContent() { ?>
	<div id="overview-ajax">
		<script>
		function runAfterTab() {
			showOverviewChart();
			$('#overview-info').popover({ trigger: "hover",container: 'body' });
			$('.active .datepicker').datepicker({
				format: 'yyyy-mm-dd',
			});
		}
		function showOverviewChart() {
			$.ajax({
				url: 'index.php',
				type: 'post',
				dataType: "json",
				data: {
					'site' : VAR_SITE,
					'ajaxCont' : 'data',
					'type' : 'overview-json',
					'dateFrom' : $('#overDate1').val(),
					'dateTo' : $('#overDate2').val(),
				}
			}).done(function(data){
                drawOverviewChart(data.graph);
                drawMissingOverviewEntries(data.missing);
            }).fail(function(data){
                console.log(data);
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
			cleanupCharts();
			var ctx = document.getElementById("chart-overview");
			charts.push(new Chart(ctx, {
				type: 'line',
				data: {
					datasets: [{
						borderColor: 'rgba(255, 0, 0,0.5)',
						backgroundColor: 'rgba(255, 0, 0,0.2)',
						label: 'Wins',
						data: data.wins,
						borderWidth: 1,
						yAxisID: "y-axis-1"
					},{
						borderColor: 'rgba(0, 255, 0,0.5)',
						backgroundColor: 'rgba(0, 255, 0,0.2)',
						label: 'Draws',
						data: data.draws,
						borderWidth: 1,
						yAxisID: "y-axis-2"
					},{
						borderColor: 'rgba(0, 0, 255,0.5)',
						backgroundColor: 'rgba(0, 0, 255,0.2)',
						label: 'Losses',
						data: data.losses,
						borderWidth: 1,
						yAxisID: "y-axis-3"
					},{
						label: 'Member',
						data: data.member,
						borderWidth: 1,
						yAxisID: "y-axis-4"
					}]
				},
				options: {
					scales: {xAxes: [{
						type: "time",
						display: true,
						scaleLabel: {
							display: true,
							labelString: 'Date'
						}
					}],
                    yAxes: [{
                        type: "linear",
                        display: false,
                        position: "left",
                        id: "y-axis-1",
                    },{
                        type: "linear",
                        display: false,
                        position: "right",
                        id: "y-axis-2",
                        gridLines: {
                            drawOnChartArea: true,
                        },
                    },{
                        type: "linear",
                        display: false,
                        position: "right",
                        id: "y-axis-3",
                        gridLines: {
                            drawOnChartArea: true,
                        },
                    },{
                        type: "linear",
                        display: false,
                        position: "right",
                        id: "y-axis-4",
                        gridLines: {
                            drawOnChartArea: true,
                        },
                    }]
					}
				}
			}));
		}
		</script>
		<div class="form-group">
			<label for="overDate1" class = "col-sm-2 control-label">1. Datum</label>
			<div class="col-xs-10">
				<input class="form-control datepicker" value="<?=$_SESSION[DATE_FROM]?>" type="date" id="overDate1">
			</div>
			<label for="overDate2" class = "col-sm-2 control-label">2. Datum</label>
			<div class="col-xs-10">
				<input class="form-control datepicker" value="<?=$_SESSION[DATE_TO]?>" type="date" id="overDate2">
			</div>
			<div class="btn-group" style="margin-top: 5px;">
				<button type="button" class="btn btn-submit" data-dismiss="modal" onclick="showOverviewChart()">Zeige Verlauf</button>
				<button type="button" class="btn btn-info" id="overview-info" data-toggle="popover" title="Info" data-content="Zeigt den Verlauf des Clans im Ausgewählten Bereich. <?=printDisclaimer()?>">?</button>
			</div>
		</div>
		<canvas id="chart-overview" width="auto" height="auto"></canvas>
        <div id="missing-overview" width="auto" height="auto"></div>
	</div>
	<?php
}

function getDifferenceContent() { ?>
	<div id="difference-ajax">
		<script>
		function runAfterTab() {
			showDifference();
			$('#difference-info').popover({ trigger: "hover",container: 'body' });
			$('.datepicker').datepicker({
				format: 'yyyy-mm-dd',
			});
			$(".active .table").tablesorter();
		}
		
		function differenceSwapDatabaseView(show){
			if(show) {
				for( i=3; i < 9; i++) {
					$('#difference-table td:nth-child('+i+'),th:nth-child('+i+')').show();
				}
			}else{
				for( i=3; i < 9; i++) {
					$('#difference-table td:nth-child('+i+'),th:nth-child('+i+')').hide();
				}
			}
		}
		
		function showDifference() {
			$.ajax({
				url: 'index.php',
				type: 'post',
				dataType: "json",
				data: {
					'site' : VAR_SITE,
					'ajaxCont' : 'data',
					'type' : 'difference-json',
					'dateFrom' : $('#diffDdate1').val(),
					'dateTo' : $('#diffDate2').val(),
				}
			}).done(function(data){
				if (data == null){
					$('#difference-table tbody').empty();
					return;
				}
				var str = '';
				$.each(data,function(i,row){
					str += '<tr ';
					if(row.cp == 0 && row.exp != 0 ) {
						str += 'class="danger"';
					}else if(row.cp == 0 && row.exp == 0 && row.days > 6) {
						str += 'class="warning"';
					}else if(row.cp >= 10) {
						str += 'class="success"';
					}
					str += '>';
					str += '<td>' + row.name + '</td>';
					str += '<td><a href="http://crossfire.z8games.com/profile/' + row.id + '">'
							+ row.id + '</a></td>';
					str += '<td>' + row.date1 + '</td>';
					str += '<td>' + row.exp1 + '</td>';
					str += '<td>' + row.cp1 + '</td>';
					str += '<td>' + row.date2 + '</td>';
					str += '<td>' + row.exp2 + '</td>';
					str += '<td>' + row.cp2 + '</td>';
					str += '<td>' + row.cp + '</td>';
					str += '<td>' + row.exp + '</td>';
					str += '<td>' + row.days + '</td>';
					str += '</tr>';
				});
				$('#difference-table tbody').html(str);
				differenceSwapDatabaseView($('#dataBasis').is(':checked'));
				$(".active .table").trigger("update");
			}).fail(function(data){
				console.log(data);
			});
		}
		</script>
		<div class="form-group">
			<label for="diffDdate1" class = "col-sm-2 control-label">1. Datum</label>
			<div class="col-xs-10">
				<input class="form-control datepicker" value="<?=$_SESSION[DATE_FROM]?>" type="date" id="diffDdate1">
			</div>
			<label for="diffDate2" class = "col-sm-2 control-label">2. Datum</label>
			<div class="col-xs-10">
				<input class="form-control datepicker" value="<?=$_SESSION[DATE_TO]?>" type="date" id="diffDate2">
			</div>
			<div class="btn-group" style="margin-top: 5px;">
				<button type="button" class="btn btn-submit" data-dismiss="modal" onclick="showDifference()">Zeige Differenz</button>
				<button type="button" class="btn btn-info" id="difference-info" data-toggle="popover" title="Info" data-content="Zeigt die EXP & CP Differenz pro Member zwischen Datum 1 und maximal Datum 2. <?=printDisclaimer()?>">?</button>
				<input type="checkbox" style="margin-top: 10px; margin-left: 5px;" id="dataBasis" onclick="differenceSwapDatabaseView($('#dataBasis').is(':checked'))" <?php
				checkboxStatus($_SESSION[SHOW_BASE]); ?>> Zeige Datenbasis
			</div>
		</div>
		<div id="dateInfo"></div>
		<table id="difference-table" class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th scope="col">Name</th>
					<th scope="col">ID</th>
					<th scope="col">Date1</th>
					<th scope="col">Exp1</th>
					<th scope="col">CP1</th>
					<th scope="col">Date2</th>
					<th scope="col">Exp2</th>
					<th scope="col">CP2</th>
					<th scope="col">CP Differenz</th>
					<th scope="col">EXP Differenz</th>
					<th scope="col">Tage</th>
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>
	</div>
	<?php
}

function getMemberContent() { ?>
	<div id="member-ajax">
		<script>
		function runAfterTab() {
			showMemberChart();
			$('#member-info').popover({ trigger: "hover",container: 'body' });
			$('#memberID').focus();
			$('.datepicker').datepicker({
				format: 'yyyy-mm-dd',
			});
			$(".active .table").tablesorter();
		}
		function showMemberChart() {
			$.ajax({
				url: 'index.php',
				type: 'post',
				dataType: "json",
				data: {
					'site' : VAR_SITE,
					'ajaxCont' : 'data',
					'type' : 'member-json',
					'dateFrom' : $('#memberDate1').val(),
					'dateTo' : $('#memberDate2').val(),
					'memberID' : $('#memberID').val(),
				}
			}).done(function(data){
				if(data != null) {
					drawMemberChart(data);
					drawMemberTable(data);
				} else {
					$('#member-table tbody').empty();
				}
			}).fail(function(data){
				console.log(data);
			});
		}
		function drawMemberChart(data) {
			cleanupCharts();
			var ctx = document.getElementById("chart-member");
			charts.push(new Chart(ctx, {
				type: 'line',
				data: {
					datasets: [{
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
					scales: {xAxes: [{
						type: "time",
						display: true,
						scaleLabel: {
							display: true,
							labelString: 'Date'
						}
					}],
                    yAxes: [{
                        type: "linear",
                        display: true,
                        position: "left",
                        id: "y-axis-1",
                    },{
                        type: "linear",
                        display: true,
                        position: "right",
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
				console.log("lExp: "+lExp+" lCP: "+lCP+ " wanted: "+Math.trunc(lExp / EXP_REQUIRED_CP));
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
			$(".active .table").trigger("update");
		}
		</script>
		<div class="form-group">
			<label for="memberDate1" class = "col-sm-2 control-label">1. Datum</label>
			<div class="col-xs-10">
				<input class="form-control datepicker" value="<?=$_SESSION[DATE_FROM]?>" type="date" id="memberDate1">
			</div>
			<label for="memberDate2" class = "col-sm-2 control-label">2. Datum</label>
			<div class="col-xs-10">
				<input class="form-control datepicker" value="<?=$_SESSION[DATE_TO]?>" type="date" id="memberDate2">
			</div>
			<label for="memberID" class = "col-sm-2 control-label">Account ID</label>
			<div class="col-xs-10">
				<input class="form-control" placeholder="Account ID" value="<?=$_SESSION[MEMBER_ID]?>" type="number" id="memberID">
			</div>
			<div class="btn-group" style="margin-top: 5px;">
				<button type="button" class="btn btn-submit" data-dismiss="modal" onclick="showMemberChart()">Zeige Änderungsverlauf</button>
				<button type="button" class="btn btn-info" id="member-info" data-toggle="popover" title="Info" data-content="Zeigt die Änderung von CP & EXP pro Tag für einen Member im Ausgewählten Bereich. <?=printDisclaimer()?>">?</button>
			</div>
		</div>
		<canvas id="chart-member" width="auto" height="auto"></canvas>
		<h4>Rohdaten:</h4>
		<table id="member-table" class="table table-striped table-bordered table-hover">
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
	<?php
}

function getMSearchContent() {	?>
	<div id="search-ajax">
		<script>
		function runAfterTab() {
			showSearchResults();
			$('#searchKey').on('input',function(e){showSearchResults()});
			$('#search-info').popover({ trigger: "hover",container: 'body' });
			$(".active .table").tablesorter();
		}
		function showSearchResults() {
			$('#search-table tbody').empty();
			if($('#searchKey').val() == '') {
				return;
			}
			$.ajax({
				url: 'index.php',
				type: 'post',
				dataType: "json",
				data: {
					'site' : VAR_SITE,
					'ajaxCont' : 'data',
					'type' : 'search-json',
					'key' : $('#searchKey').val(),
				}
			}).done(function(data){
				if(data != null)
					drawSearchResults(data);
			}).fail(function(data){
				console.log(data);
			});
		}
		function drawSearchResults(data) {
			var str = '';
			$.each(data,function(i,row){
				str += '<tr>';
				str += '<td>' + row.name + '</td>';
				str += '<td><a href="http://crossfire.z8games.com/profile/' + row.id + '">'
								+ row.id + '</a></td>';
				str += '<td>' + row.date + '</td>';
// 				str += '<td>'++'</td>';
				str += '</tr>';
			});
			$('#search-table tbody').html(str);
			$(".active .table").trigger("update");
		}
		</script>
		<div class="form-group">
			<label for="searchKey" class = "col-sm-2 control-label">Suchwort</label>
			<div class="col-xs-10">
				<input class="form-control" value="<?=$_SESSION[SEARCH_KEY]?>" type="text" id="searchKey" placeholder="Name/ID">
			</div>
			<div class="btn-group" style="margin-top: 5px;">
				<button type="button" class="btn btn-info" id="search-info" data-toggle="popover" title="Info" data-content="Suche für Namen &amp; IDs von (ehemaligen) Membern.">?</button>
			</div>
			<div stlye="margin-left:10px;">Namen gespeichert: <?=$_SESSION[C_TOTAL_NAMES]?></div>
		</div>
		<table id="search-table" class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th scope="col">Name</th>
					<th scope="col">ID</th>
					<th scope="col">Datum</th>
					<!--<th scope="col">Details</th>-->
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>
	</div>
	<?php
}


function getMiscContent() {	?>
	<div id="search-ajax">
		<script>
		function runAfterTab() {
			showMemberJoinLeave();
			$('.datepicker').datepicker({
				format: 'yyyy-mm-dd',
			});
			$('#misc-info').popover({ trigger: "hover",container: 'body' });
			$(".active .table").tablesorter();
		}
		function showMemberJoinLeave() {
            $('#left-table tbody').empty();
			$('#joined-table tbody').empty();
			$.ajax({
				url: 'index.php',
				type: 'post',
				dataType: "json",
				data: {
					'site' : VAR_SITE,
					'ajaxCont' : 'data',
					'type' : 'misc-json',
					'dateFrom' : $('#miscDate1').val(),
					'dateTo' : $('#miscDate2').val(),
				}
			}).done(function(data){
				if(data != null){
					drawLJRes(data.left,'left-table');
					drawLJRes(data.joined,'joined-table');
					$(".active .table").trigger("update");
				}
			}).fail(function(data){
				console.log(data);
			});
		}
		function drawLJRes(data,table) {
			var str = '';
			$.each(data,function(i,row){
				str += '<tr>';
				str += '<td>' + row.name + '</td>';
				str += '<td><a href="http://crossfire.z8games.com/profile/' + row.id + '">'
								+ row.id + '</a></td>';
				str += '</tr>';
			});
			$('#'+table+' tbody').html(str);
		}
		</script>
		<div class="form-group">
			<label for="miscDate1" class = "col-sm-2 control-label">1. Datum</label>
			<div class="col-xs-10">
				<input class="form-control datepicker" value="<?=$_SESSION[DATE_FROM]?>" type="date" id="miscDate1">
			</div>
			<label for="miscDate2" class = "col-sm-2 control-label">2. Datum</label>
			<div class="col-xs-10">
				<input class="form-control datepicker" value="<?=$_SESSION[DATE_TO]?>" type="date" id="miscDate2">
			</div>
			<div class="btn-group" style="margin-top: 5px;">
				<button type="button" class="btn btn-submit" data-dismiss="modal" onclick="showMemberJoinLeave()">Zeige Differenz</button>
				<button type="button" class="btn btn-info" id="misc-info" data-toggle="popover" title="Info" data-content="Zeigt left/joined Member zwischen Datum 1 und Datum 2. <?=printDisclaimer()?>">?</button>
			</div>
		</div>
		<h3>Left</h3>
		<table id="left-table" class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th scope="col">Name</th>
					<th scope="col">ID</th>
					<!--<th scope="col">Details</th>-->
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>
		<h3>Joined</h3>
		<table id="joined-table" class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
					<th scope="col">Name</th>
					<th scope="col">ID</th>
					<!--<th scope="col">Details</th>-->
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>
	</div>
	<?php
}



function printDisclaimer(){
	echo 'DISCLAIMER: Bitte das Datum der Datenerhebung sowie die Abhängigkeit von z8-games beachten.';
}

function checkboxStatus($checked) {
	if ($checked) {
		echo 'checked="checked"';
	}
}

//@Override
function getTitle() {
	return 'Clantool';
}
//@Override
function getHead() {?>
<script src="js/jquery.tablesorter.min.js" type="text/javascript"></script>
<link rel="stylesheet" href="css/datepicker.css">
<script src="js/bootstrap-datepicker.js"></script>
<script src="js/chart.bundle.min.js"></script>
<?php }
