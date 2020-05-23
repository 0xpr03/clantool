<div id="error" style="display: none;" class="alert alert-danger fade in"></div>
<form class="form-horizontal" id="joinForm" action="" method="post">
    <input type="hidden" name="site" value="<?=SITE?>">
    <input type="hidden" name="ajaxCont" value="data">
    <input type="hidden" name="type" value="member-join">
    <div class="form-group">
        <div class="col-xs-offset-2 col-xs-10">
            <select class="form-control" id="accountSearch">
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="inputID" class="control-label col-xs-2">Account ID / USN</label>
        <div class="col-xs-10">
            <input type="number" name="id" autofocus required="" class="form-control" id="inputID" placeholder="ID/USN">
        </div>
    </div>
    <div class="form-group">
        <label for="inputName" class="control-label col-xs-2">Vorname</label>
        <div class="col-xs-10">
            <input type="text" name="name" required="" autocomplete="off" class="form-control" id="inputName" placeholder="Vorname">
        </div>
    </div>
    <div class="form-group">
        <label for="inputTSID" class="control-label col-xs-2">Teamspeak Identität</label>
        <div class="col-xs-10">
            <select class="form-control" id="selTSid">
            <option></option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <div class="col-xs-offset-2 col-xs-10">
            <input type="number" class="form-control" required="" name="ts3" id="inputTSID" placeholder="TS Client ID">
        </div>
    </div>
    <div class="form-group">
        <label for="inputVIP" class="control-label col-xs-2">VIP</label>
        <div class="col-xs-10">
            <div class="checkbox">
                <label><input type="checkbox" name="vip" id="inputVIP" checked="checked"> VIP Spieler</label>
            </div>
        </div>
    </div>
    <div class="form-group">
        <div class="col-xs-offset-2 col-xs-10">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Join Einfügen</button>
        </div>
    </div>
</form>
<div id="result" style="display: none;">
    <h3>Member Hinzugefügt: <a href="?" class="btn btn-default" id="edit"><i class="fas fa-pencil-alt"></i> Editieren</a></h3>
    <div id="warnJoin" style="display: none;" class="alert alert-danger fade in">
        <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
    </div>
    <div id="warnOverride" style="display: none;" class="alert alert-danger fade in">
        <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
    </div>
    <table class="table">
        <tbody>
            <tr><td>Datum</td><td><div id="Rdate"></div></td></tr>
            <tr><td>ID</td><td><div id="RaccID"></div></td></tr>
            <tr><td>Vorname</td><td><div id="RrealName"></div></td></tr>
            <tr><td>VIP</td><td><input type="checkbox" id="Rvip" checked="checked" disabled></td></tr>
            <tr><td>TS3 Account ID</td><td><div id="Rts3"></div></td></tr>
        </tbody>
    </table>
</div>
<div class="alert alert-success">
    Bitte den Eintrag in den TS-Channel <b>Memberliste</b> &amp; <b>Download/Upload Ordner</b> nicht vergessen!
</div>
<script type="text/javascript">
$(document).ready(function() {
    var selTS = $('#selTSid');
    var tsID = $('#inputTSID');
    
    var accSel = $('#accountSearch');
    var inputID = $('#inputID');
    accountSelect(accSel);
    accSel.on('select2:select', function (e) {
        var data = e.params.data;
        inputID.val(data.id);
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'id' : data.id,
                'ajaxCont' : 'data',
                'type' : 'member-join-prefetch',
            }
        }).done(function(data){
            if(data != null) {
                $('#inputName').val(data.name);
                $('#inputVIP').prop('checked',data.vip);
            }
        }).fail(function(data){
            $('#error').html(formatErrorData(data));
            $('#error').show();
        });
    });
    
    ts3Select(selTS);
    
    selTS.on('select2:select', function (e) {
        var data = e.params.data;
        tsID.val(data.id);
    });
    
    $("#joinForm").submit(function(e) {
        $('#joinForm').hide();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: $(this).serialize()
        }).done(function(data){
            if(data.open > 0){
                $('#warnJoin').append('Es existieren bereits '+data.open+' offene Mitgliedseinträge!');
                $('#warnJoin').show();
            }
            if(data.overrode){
                $('#warnOverride').append('Ein bestehender Eintrag wurde überschrieben!');
                $('#warnOverride').show();
            }
            $('#edit').attr('href',MEMBER_DETAIL_URL + data.id);
            $('#RaccID').text(data.id);
            $('#RrealName').text(data.name);
            $('#Rts3').text(data.ts3);
            $('#Rdate').text(data.date);
            $('#Rvip').prop('checked', data.vip);
            $('#result').show();
        }).fail(function(data){
            $('#error').html(formatErrorData(data));
            $('#error').show();
        });
        e.preventDefault();
    });
});
</script> 
