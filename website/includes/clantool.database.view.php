<div>
    <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
            <div style="position: fixed; left: 50%; top: 50%;">
                <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                Loading...
            </div>
    </div>
    <div>
        <h3>Database Statistics</h3>
        <table id="dbstats" class="table table-striped table-bordered table-hover">
            <thead>
                <tr>
                    <th scope="col">Key</th>
                    <th scope="col">Value</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <div id="erromsg" class="alert alert-danger fade in" style="display: none;"></div>
    </div>
</div>
<script type="text/javascript">
jQuery.ajaxSetup({
beforeSend: function() {
    $('#loading').show();
},
complete: function(){
    $('#loading').hide();
},
success: function() {}
});

$( document ).ready(function() {
    initTableTool();
    $.ajax({
        url: SITE_URL,
        type: 'post',
        dataType: "json",
        data: {
            'site' : VAR_SITE,
            'ajaxCont' : 'data',
            'type' : 'database-json',
        }
        }).done(function(data){
            var sw = {value: ""}; // pointer via object value
            createCol(sw,'IDs',data.ids);
            createCol(sw,'Account Names',data.names);
            createCol(sw,'Member (real) Names',data.realnames);
            createCol(sw,'Exp & CP entries',data.rows);
            createCol(sw,'AFKs',data.afks);
            createCol(sw,'Cautions',data.cautions);
            createCol(sw,'Unlinked ts IDs',data.unlinkedTS);
            createCol(sw,'TS Names',data.tsIDs);
            createCol(sw,'TS Data',data.tsdata);
            createCol(sw,'TS Data Old Schema',data.tsdataold);
            createCol(sw,'Joins',data.joins);
            createCol(sw,'Leaves',data.leaves);
            createCol(sw,'Leaves with set cause',data.causes);
            createCol(sw,'2nd Accounts',data.secondAccs);
            createCol(sw,'Amount days without data',data.missing);
            createCol(sw,'Log entries',data.log);
            $('#dbstats tbody').html(sw.value);
            $('#erromsg').hide();
            updateTableTool();
        }).fail(function(data){
            $('#erromsg').html(formatErrorData(data));
            $('#erromsg').show();
        });
});

function createCol(sw,key,val) {
    sw.value += '<tr><td>'+key+'</td><td>'+val+'</td></tr>';
}
</script> 
