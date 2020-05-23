<div id="error" style="display: none;" class="alert alert-danger fade in"></div>
<form class="form-horizontal" id="leaveForm" action="" method="post">
    <input type="hidden" name="site" value="<?=SITE?>">
    <input type="hidden" name="ajaxCont" value="data">
    <input type="hidden" name="type" value="memberLeave">
    <div class="form-group">
        <div class="col-xs-offset-2 col-xs-10">
            <select class="form-control" id="accountSearch">
                <option></option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="inputID" class="control-label col-xs-2">Account ID / USN</label>
        <div class="col-xs-10">
            <input type="number" name="id" required="true" autocomplete="off" class="form-control" id="inputID" placeholder="ID/USN">
        </div>
    </div>
    <div class="form-group">
        <label for="inputDate" class="control-label col-xs-2">Leave Datum</label>
        <div class="col-xs-10">
            <input id="inputDate" name="date" autocomplete="off" required="true" type="text" class="form-control" />
        </div>
    </div>
    <div class="form-group">
        <label for="inputKicked" class="control-label col-xs-2">Kick</label>
        <div class="col-xs-10">
            <div class="checkbox">
                <label><input type="checkbox" name="kicked" id="inputKicked"  checked="checked"> Kick</label>
            </div>
        </div>
    </div>
    <div class="form-group">
        <label for="msNewCause" class="control-label col-xs-2">Grund</label>
        <div class="col-xs-10">
            <input type="text" name="cause" autocomplete="off" class="form-control" id="msNewCause"  placeholder="Grund">
        </div>
    </div>
    <div class="form-group">
        <div class="col-xs-offset-2 col-xs-10">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Leave Einfügen</button>
        </div>
    </div>
</form>
<div id="errorInsert" style="display: none;" class="alert alert-danger fade in">
    <a href="?" class="btn btn-default" id="manually"><i class="fas fa-pencil-alt"></i> Joins Einsehen</a>
</div>
<div id="result" style="display: none;">
    <h3>Member Leave: <a href="?" class="btn btn-default" id="edit"><i class="fas fa-pencil-alt"></i> Editieren</a></h3>
    <table class="table">
        <tbody>
            <tr><td>Datum</td><td><div id="Rdate"></div></td></tr>
            <tr><td>Account</td><td><div id="Racc"></div></td></tr>
            <tr><td>Kick</td><td><input type="checkbox" id="RKicked" checked="checked" disabled></td></tr>
            <tr><td>Grund</td><td><div id="Rcause"></div></td></tr>
            <tr><td>Pröbling Einträge beendet</td><td><div id="trials"></td></tr>
        </tbody>
    </table>
</div>
<script type="text/javascript">
var EDIT_URL = '<?=MEMBER_DETAIL_URL?>';
$(document).ready(function() {
    var accSel = $('#accountSearch');
    var inputID = $('#inputID');
    var leaveForm = $('#leaveForm');
    var inputDate = $('#inputDate');
    accountSelect(accSel);
    
    accSel.select2('open');
    
    inputDate.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
        defaultDate: moment()
    });
    
    accSel.on('select2:select', function (e) {
        var data = e.params.data;
        inputID.val(data.id);
    });
    
    leaveForm.submit(function(e) {
        leaveForm.hide();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: $(this).serialize()
            }).done(function(data){
                if(data.ok && data.size == 1){
                    $('#trials').text(data.trials);
                    $('#edit').attr('href',EDIT_URL + data.id);
                    $('#Racc').text(data.name+' '+data.id);
                    $('#Rdate').text(data.from+' - '+data.to);
                    $('#RKicked').prop('checked', data.kicked);
                    $('#Rcause').text(data.cause);
                    $('#result').show();
                }else{
                    $('#manually').attr('href',EDIT_URL + data.id);
                    var txt = 'Es wurden '+data.size+' Join Einträge gefunden! Bitte das Problem manuell beheben!';
                    $('#errorInsert').prepend('<span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>'+escapeHtml(txt));
                    $('#errorInsert').show();
                }
                $('#error').hide();
                $('#loading').hide();
            }).fail(function(data){
                $('#error').html(formatErrorData(data));
                $('#error').show();
            });
        e.preventDefault();
    });
});
</script>
