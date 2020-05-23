<div id="error" style="display: none;" class="alert alert-danger fade in"></div>
<form class="form-horizontal" id="accChangeForm" action="" method="post">
    <div class="form-group">
        <label for="inputAccount" class="control-label col-xs-2">Current Account</label>
        <div class="col-xs-10">
            <select class="form-control" name="id" required="" id="inputAccount">
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="selInputNewAcc" class="control-label col-xs-2">New Account</label>
        <div class="col-xs-10">
            <select class="form-control" id="selInputNewAcc">
            <option></option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <div class="col-xs-offset-2 col-xs-10">
            <input type="number" class="form-control" required="" name="inputNewAcc" id="inputNewAcc" placeholder="New Account ID / USN">
        </div>
    </div>
    <!--<div class="form-group">
        <label for="inputCopy" class="control-label col-xs-2">Copy all data</label>
        <div class="col-xs-10">
            <div class="checkbox">
                <label><input type="checkbox" id="inputCopy" checked="checked"> Copy all data</label>
            </div>
        </div>
    </div>-->
    <div class="form-group">
        <div class="col-xs-offset-2 col-xs-10">
            <button type="submit" class="btn btn-warning btn-primary">
                <i class="fas fa-user-edit"></i> 
                Change Account
            </button>
        </div>
    </div>
</form>
<div id="errOldAccount" style="display: none;" class="alert alert-danger fade in">
    Keine aktive Memberschaft für den alten Account gefunden!
    <a href="?" class="btn btn-default" id="manually-old"><i class="fas fa-pencil-alt"></i> Account Einsehen</a>
</div>
<div id="errNewAcc" style="display: none;" class="alert alert-danger fade in">
    Der neue Account ist aktuell ein Mitglied des Clans!
    <a href="?" class="btn btn-default" id="manually-new"><i class="fas fa-pencil-alt"></i> Account Einsehen</a>
</div>
<div id="result" style="display: none;">
    <h3>Account Change vollzogen: <a href="?" class="btn btn-default" id="edit"><i class="fas fa-pencil-alt"></i> Editieren</a></h3>
    <table class="table">
        <tbody>
            <tr><td>Old Account</td><td><div id="Rold"></div></td></tr>
            <tr><td>New Account</td><td><div id="Rnew"></div></td></tr>
            <!--<tr><td>Copy</td><td><input type="checkbox" id="Rcopy" checked="checked" disabled></td></tr>-->
        </tbody>
    </table>
</div>
<script type="text/javascript">
$(document).ready(function() {
    var selCurrAcc = $('#inputAccount');
    var selNewAcc = $('#selInputNewAcc');
    var newAcc = $('#inputNewAcc');
    var form = $('#accChangeForm');
    accountSelect(selCurrAcc);
    accountSelect(selNewAcc);
    selNewAcc.on('select2:select', function (e) {
        var data = e.params.data;
        newAcc.val(data.id);
    });
    
    form.submit(function(e) {
        form.hide();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                //'copy' : $('#inputCopy').is('checked'),
                'newID' : newAcc.val(),
                'oldID' : selCurrAcc.select2('data')[0].id,
                'ajaxCont' : 'data',
                'type' : 'account-change',
            }
        }).done(function(data){
            if (data.closableJoin && data.clearNewAcc) {
                $('#edit').attr('href',MEMBER_DETAIL_URL + data.newID);
                $('#Rnew').text(data.newID);
                $('#Rold').text(data.oldID);
                //$('#Rcopy').prop('checked',data.copy);
                $('#result').show();
            } else {
                if (!data.closableJoin) {
                    $('#manually-old').attr('href',MEMBER_DETAIL_URL + data.oldID);
                    $('#errOldAccount').show();
                }
                if (!data.clearNewAcc) {
                    $('#manually-new').attr('href',MEMBER_DETAIL_URL + data.newID);
                    $('#errNewAcc').show();
                }
            }
        }).fail(function(data){
            $('#error').html(formatErrorData(data));
            $('#error').show();
        });
        e.preventDefault();
    });
});
</script>
