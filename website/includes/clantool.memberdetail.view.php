<div class="row container-fluid">
<div id="error" style="display: none;" class="alert alert-danger fade in"></div>
<div class="col-sm-12 col-xs-11">
    <div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
            <div style="position: fixed; left: 50%; top: 50%;">
                <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
                <div id="loading-content">Loading...</div>
            </div>
    </div>
    <form class="form-horizontal" id="additionalForm" action="" method="post">
        <input type="hidden" name="site" value="<?=SITE?>">
        <input type="hidden" name="ajaxCont" value="data">
        <input type="hidden" name="type" value="member-addition-set">
        <div class="form-group">
            <label for="inputAccount" class="control-label col-xs-2">Account</label>
            <div class="col-xs-10">
                <select class="form-control" name="id" required="" id="inputAccount">
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="inputID" class="control-label col-xs-2">z8 ID</label>
            <div class="col-xs-10">
                <input type="number" required="" class="form-control" id="inputID" placeholder="ID" disabled>
            </div>
        </div>
        <div class="form-group">
            <label for="inputName" class="control-label col-xs-2">Vorname</label>
            <div class="col-xs-10">
                <input type="text" name="name" autocomplete="off" required="" class="form-control" id="inputName" placeholder="Vorname">
            </div>
        </div>
        <div class="form-group">
            <label for="inputComment" autocomplete="off" class="control-label col-xs-2">Kommentar</label>
            <div class="col-xs-10">
                <textarea rows="3" name="comment" class="form-control" id="inputComment" placeholder="Kommentar"></textarea>
            </div>
        </div>
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <div class="checkbox">
                    <label><input type="checkbox" id="inputVIP" name="vip" checked="checked"> VIP Spieler</label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <button type="submit" class="btn btn-primary" id="submitMember"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
        <div class="form-group" id="missingDataDiv" style="display: none;">
            <div class="col-xs-offset-2 col-xs-10">
                <div class="alert alert-warning">
                    <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                    <span class="sr-only">Error:</span>
                    Folgende Angaben fehlen: Vorname und VIP Status!
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="col-xs-offset-2 col-xs-10">
                <a type="button" class="btn btn-default" id="showDifference"><i class="fas fa-chart-area"></i> Member CP &amp; EXP</a>
                <a type="button" class="btn btn-default" id="showTSChart"><i class="fas fa-chart-bar"></i> TS Chart</a>
                <a type="button" target="_blank" class="btn btn-default" id="showZ8Account"><i class="fas fa-external-link-alt"></i> z8 Account</a>
            </div>
        </div>
    </form>
    <div id="trial" class="panel panel-default" style="display:none;">
        <div class="panel-heading">
            <form id="trialForm" action="" method="post">
                <div id="trial-data" class="form-group"></div>
                <div class="form-group">
                    <div class="">
                        <button type="submit" class="btn btn-warning" id="submitTrialEnd">Zum Member machen</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="col-sm-6 col-xs-11">
    <div class="panel panel-default">
        <div class="panel-heading"><i class="fas fa-user-plus"></i> Mitgliedsdaten</div>
        <ul class="list-group">
            <div id="membership">
            </div>
            <li class="list-group-item">
                <div id="msDefault" class="form-horizontal">
                    <div class="form-horizontal form-group" >
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="submit" id="msNewBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Neuer Eintrag</button>
                        </div>
                    </div>
                </div>
                <form id="msForm" style="display:none;" class="form-horizontal" action="" method="post">
                    <div class="form-group">
                        <label for="msNewFrom" class="control-label col-xs-2">Join Datum</label>
                        <div class="col-xs-10">
                            <input id="msNewFrom" required="true" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <div class="checkbox">
                                <label><input type="checkbox" name="trial" id="msNewMember" checked="checked"> Member</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="msNewTo" class="control-label col-xs-2">Leave Datum</label>
                        <div class="col-xs-10">
                            <input id="msNewTo" required="false" type="text" disabled="true" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <div class="checkbox">
                                <label><input type="checkbox" name="kick" id="msNewKick" disabled="true" checked="checked"> Kick</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="msNewCause" class="control-label col-xs-2">Grund</label>
                        <div class="col-xs-10">
                            <input type="text" name="cause" class="form-control" id="msNewCause" autocomplete="off" disabled="true" placeholder="Grund">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="submit" id="msNewAdd" class="btn btn-primary"><i class="fas fa-plus"></i> Eintrag Hinzufügen</button>
                            <button type="submit" id="msNewSave" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                        </div>
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="button" id="msNewCancel" class="btn btn-danger"><i class="fas fa-times"> </i> Abbrechen</button>
                        </div>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</div>
<div class="col-sm-6 col-xs-11">
    <div class="panel panel-default">
        <div class="panel-heading"><i class="fas fa-exclamation-triangle"></i> Verwarnungen</div>
        <ul class="list-group">
            <div id="caution">
            </div>
            <li class="list-group-item">
                <div id="cautionAddDiv" class="form-horizontal">
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="submit" id="cautionAdd" class="btn btn-primary"><i class="fas fa-plus"></i> Neuer Eintrag</button>
                        </div>
                    </div>
                </div>
                <form id="cautionForm" style="display: none;" class="form-horizontal" action="" method="post">
                    <div class="form-group">
                        <label for="cautionFrom" class="control-label col-xs-2">Von</label>
                        <div class="col-xs-10">
                            <input id="cautionFrom" required="true" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cautionTo" class="control-label col-xs-2">Bis</label>
                        <div class="col-xs-10">
                            <input id="cautionTo" required="true" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="inputCautionCause" class="control-label col-xs-2">Grund</label>
                        <div class="col-xs-10">
                            <input type="text" name="name" autocomplete="off" required="true" class="form-control" id="inputCautionCause" placeholder="Grund">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="submit" id="cautionAddSubmit" class="btn btn-primary"><i class="fas fa-plus"></i> Eintrag Hinzufügen</button>
                        </div>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</div>
<div class="col-sm-6 col-xs-11">
    <div class="panel panel-default">
        <div class="panel-heading"><i class="fas fa-clock"></i> AFK</div>
        <ul class="list-group">
            <div id="afk">
            </div>
            <li class="list-group-item">
                <div id="afkAddDiv" class="form-horizontal">
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="submit" id="afkAdd" class="btn btn-primary"><i class="fas fa-plus"></i> Neuer Eintrag</button>
                        </div>
                    </div>
                </div>
                <form id="afkForm" style="display: none;" class="form-horizontal" action="" method="post">
                    <div class="form-group">
                        <label for="afkFrom" class="control-label col-xs-2">Von</label>
                        <div class="col-xs-10">
                            <input id="afkFrom" required="true" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="afkTo" class="control-label col-xs-2">Bis</label>
                        <div class="col-xs-10">
                            <input id="afkTo" required="true" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="inputAFKCause" class="control-label col-xs-2">Grund</label>
                        <div class="col-xs-10">
                            <input type="text" name="name" autocomplete="off" required="true" class="form-control" id="inputAFKCause" placeholder="Grund">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="submit" id="afkAddSubmit" class="btn btn-primary"><i class="fas fa-plus"></i> Eintrag Hinzufügen</button>
                            <button type="submit" stlye="display:none;" id="afkEditSubmit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                        </div>
                        <div style="display:none;" id="afkEditCancelDiv" class="col-xs-offset-2 col-xs-10">
                            <button type="button" id="afkEditCancel" class="btn btn-danger"><i class="fas fa-times"> </i> Abbrechen</button>
                        </div>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</div>
<div class="col-sm-6 col-xs-11">
    <div class="panel panel-default">
        <div class="panel-heading"><i class="fas fa-id-badge"></i> Namechanges</div>
        <table class="table" id="names">
            <tbody>
            
            </tbody>
        </table>
    </div>
</div>
<div class="col-sm-6 col-xs-11">
    <div class="panel panel-default">
        <div class="panel-heading"><i class="fas fa-user-friends"></i> Zweit Accounts</div>
        <ul class="list-group">
            <div id="Accs">
            </div>
            <li class="list-group-item">
                <form id="accsForm" action="" method="post">
                    <div class="form-group">
                        <select class="form-control" id="accsSearch">
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="number" class="form-control" required="true" name="accID" id="AccsID" placeholder="ID / USN">
                    </div>
                    <div class="form-group">
                        <button type="submit" id="AccsSubmit" class="btn btn-primary"><i class="fas fa-plus"></i> Hinzufügen</button>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</div>
<div class="col-sm-6 col-xs-11">
    <div class="panel panel-default">
        <div class="panel-heading"><i class="fas fa-plus" title="Pröbling"></i>  Pröblings Daten</div>
        <ul class="list-group">
            <div id="trials">
            </div>
            <li class="list-group-item">
                <div id="trialAddDiv" class="form-horizontal">
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="submit" id="trialAdd" class="btn btn-primary"><i class="fas fa-plus"></i> Neuer Eintrag</button>
                        </div>
                    </div>
                </div>
                <form id="trialsListForm" style="display: none;" class="form-horizontal" action="" method="post">
                    <div class="form-group">
                        <label for="trialFrom" class="control-label col-xs-2">Von</label>
                        <div class="col-xs-10">
                            <input id="trialFrom" required="true" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <div class="checkbox">
                                <label><input type="checkbox" id="trialMember" name="member" checked="checked"> Member</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="trialTo" class="control-label col-xs-2">Bis</label>
                        <div class="col-xs-10">
                            <input id="trialTo" required="true" type="text" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                            <button type="submit" id="trialAddSubmit" class="btn btn-primary"><i class="fas fa-plus"></i> Eintrag Hinzufügen</button>
                        </div>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</div>
<div class="col-sm-6 col-xs-11">
    <div class="panel panel-default">
        <div class="panel-heading">Teamspeak Identitäten</div>
        <ul class="list-group">
            <div id="ts3">
            </div>
            <li class="list-group-item">
                <form id="ts3Form" action="" method="post">
                    <div class="form-group">
                        <select class="form-control" required="" id="ts3Input">
                        <option></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" id="ts3submit" class="btn btn-primary"><i class="fas fa-plus"></i> Hinzufügen</button>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</div>
<div class="col-sm-6 col-xs-11">
    
</div>
</div>
<script type="text/javascript">
const TS3_ENTRY = "ts3-";
const AFK_ENTRY = "afk-";
const CAUTION_ENTRY = "caution-";
const MS_ENTRY = "ms-";
const TRIAL_ENTRY = "trial-";
const actionEdit = 'edit';
const actionDelete = 'delete';

function setLoadingMsg(msg){
    $('#loading-content').text(msg);
}

/* taken from http://bdadam.com/blog/automatically-adapting-the-height-textarea.html */
function adjustHeight(comp, minHeight) {
    var outerHeight = parseInt(window.getComputedStyle(comp).height, 10);
    var diff = outerHeight - comp.clientHeight;
    
    comp.style.height = 0;
    
    comp.style.height = Math.max(minHeight, comp.scrollHeight + diff) + 10 + 'px';
}

function loadData(id) {
    if(getUrlParameter('id') != id) {
        setURLParameterHistory({'id' : id},'id',id);
    } else {
        setURLParameter({'id' : id});
    }
    $('#loading').show();
    disable(true);
    $.ajax({
        url: SITE_URL,
        type: 'post',
        dataType: "json",
        data: {
            'site' : VAR_SITE,
            'id' : id,
            'ajaxCont' : 'data',
            'type' : 'member-data',
        }
    }).done(function(data){
        setLoadingMsg('Rendering..');
        setTimeout(function() {
            $('#inputName').val(data.name);
            $('#inputComment').val(data.comment);
            if(data.vip == null){
                $('#missingDataDiv').show();
            } else {
                $('#inputVIP').prop('checked',data.vip);
                $('#missingDataDiv').hide();
            }
            $('#showDifference').attr('href',"<?=MEMBER_DIFF_URL?>"+data.id);
            $('#showZ8Account').attr('href',"<?=Z8PROFILE?>"+data.id);
            $('#showTSChart').attr('href',"<?=MEMBER_TS_URL?>"+data.id);
            renderMembership(data.membership);
            renderTs3(data.ts3);
            renderTrial(data.trial);
            renderTrialsList(data.trials);
            renderSecondsAccounts(data.secAccs);
            renderAFK(data.afk);
            renderCaution(data.caution);
            renderNames(data.names);
            $('#error').hide();
            disable(false);
            resetMS();
            resetAfk();
            resetTrials();
            $('#loading').hide();
            // reset for next time
            setLoadingMsg('Loading...');
            adjustHeight(document.getElementById("inputComment"), 40);
        },0);
    }).fail(function(data){
        $('#error').html(formatErrorData(data));
        $('#error').show();
        $('#loading').hide();
    });
}

function preselect(id,inputAcc) {
    $('#loading').show();
    disable(true);
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

function renderTrialsList(data) {
    var elements = "";
    if(data != null) {
        for(var i = 0; i < data.length; i++) {
            var elem = data[i];
            elements += formatTrialEntry(elem.start,elem.end);
        }
    }
    $('#trials').html(elements);
}

function renderNames(data) {
    var elements = "";
    if(data != null) {
        for(var i = 0; i < data.length; i++) {
            var elem = data[i];
            elements += formatNameEntry(elem.name,elem.date);
        }
    }
    $('#names tbody').html(elements);
}

function renderCaution(data) {
    var elements = "";
    if(data != null){
        for(var i = 0; i < data.length; i++) {
            var elem = data[i];
            elements += formatCautionEntry(elem.from,elem.to,elem.cause);
        }
    }
    $('#caution').html(elements);
}

function renderAFK(data) {
    var elements = "";
    if(data != null){
        for(var i = 0; i < data.length; i++) {
            var elem = data[i];
            elements += formatAFKEntry(elem.from,elem.to,elem.cause);
        }
    }
    $('#afk').html(elements);
}

function renderSecondsAccounts(data) {
    var elements = "";
    if(data != null){
        for(var i = 0; i < data.length; i++) {
            var elem = data[i];
            elements += formatAccsEntry(elem.name,elem.id_sec);
        }
    }
    $('#Accs').html(elements);
}

function renderTrial(data) {
    if(data == null){
        $('#trial').hide();
    }else{
        $('#trial-data').text('Pröbling seit '+data.from);
        $('#trial-data').attr('data-from',data.from);
        $('#trial').show();
    }
}

function renderTs3(data){
    var elements = "";
    if(data != null){
        for(var i = 0; i < data.length; i++) {
            var elem = data[i];
            elements += formatTS3Entry(elem.name,elem.cID);
        }
    }
    $('#ts3').html(elements);
}

function formatTrialEntry(from,to){
    var str = '<li class="list-group-item" id="'+TRIAL_ENTRY+from+'">'
        + from
        + '<a href="#" class="critical" data-from="'+from+'" data-to="'+to+'" style="float: right;"> <i class="fas fa-trash"></i> Delete</a>'
    if(to == null){
        str += ' open';
    }else{
        str += ' - ' + to;
    }
    str += '</li>';
    return str;
}
    
function formatNameEntry(name,date) {
    return '<tr><td>'
        + escapeHtml(name) + '</td><td>'+ date
        + '</tr>';
}

function formatCautionEntry(from,to,cause) {
    return '<li class="list-group-item" id="'+CAUTION_ENTRY+from+'">'
        + '<a href="#" class="critical" data-from="'+from+'" data-to="'+to+'" style="float: right;"> <i class="fas fa-trash"></i> Delete</a>'
        + from + ' - ' + to + '<br>'
        + escapeHtml(cause)
        + '</li>';
}

function formatAFKEntry(from,to,cause,nr) {
    return '<li class="list-group-item" id="'+AFK_ENTRY+from+'-'+to+'">'
        + '<a href="#" style="float: right; margin-left: 2em;" data-action="'+actionEdit+'" data-from="'
        + from + '" data-to="' + to +'" data-cause="'
        + escapeHtml(cause) +'">'
        + '<i class="fas fa-pencil-alt"></i> Edit </a>'
        + '<a href="#" class="critical" data-action="'+actionDelete+'" data-from="'+from+'" data-to="'+to+'" style="float: right;"> <i class="fas fa-trash"></i> Delete</a>'
        + from + ' - ' + to + '<br>'
        + escapeHtml(cause)
        + '</li>';
}

function formatTS3Entry(name,id) {
    return '<li class="list-group-item" id="'+TS3_ENTRY+id+'">'
        + '<a href="#" class="critical" data-id="'+id+'" style="float: right;"> <i class="fas fa-trash"></i> Remove</a>'
        + escapeHtml(name)
        + '</li>';
}

function formatAccsEntry(name,id) {
    var vname = "&lt;unknown&gt;";
    if(name != null && name != "")
        vname = name;
    return '<li class="list-group-item" id="accs-'+id+'">'
        + '<a href="#" class="critical" data-id="'+id+'" style="float: right;"> <i class="fas fa-trash"></i> Remove</a>'
        + escapeHtml(vname) +' ('+formatMemberDetailLink(id,id)+')'
        + '</li>';
}    

function disable(disable) {
    var lst = ['#ts3Input','#ts3submit','#submitMember','#inputName',
    '#membershipSubmit','#inputVIP','#msNewBtn','#showDifference',
    '#AccsSubmit','#AccsID','#accsSearch','#afkAdd','#afkAddSubmit',
    '#trialAddSubmit','#trialAdd','#showZ8Account','#inputComment',
    '#showTSChart','#cautionAdd','#cautionAddSubmit'];
    for(var i = 0; i < lst.length; i++) {
        $(lst[i]).prop('disabled', disable);
    }
}

function renderMembership(data) {
    $('#membership').empty();
    for(var i = 0; i < data.length; i++){
        $('#membership').append(formatMembershipEntry(data[i],false));
    }
}
// generate membership entry, edit = true will suppress the main <li>
function formatMembershipEntry(data,edit) {
    var span_start = '<span class="avoidwrap">'; // avoid bad linebreaks
    var span_end = '</span>';
    if(edit)
        var ret = "";
    else 
        var ret = '<li class="list-group-item" id="'+MS_ENTRY+data.nr+'">';
    ret += span_start;
    ret += '<i class="fas fa-user-plus"></i> ';
    ret += data.from;
    ret += span_end;
    var member = data.to == null ? "true" : "false";
    var kicked = data.kicked ? "true" : "false";
    
    ret += '<a href="#" style="float: right; margin-left: 2em;" data-id="'+data.nr+'" data-action="'+actionEdit+'" data-from="';
    ret += data.from + '" data-to="' + data.to+'" data-cause="';
    ret += escapeHtml(data.cause)+'" data-kicked="'+kicked+'" data-member="'+member+'">';
    ret += '<i class="fas fa-pencil-alt"></i> Edit </a>';
    
    ret += '<a href="#" class="critical" data-id="'+data.nr+'" data-action="'+actionDelete+'" style="float: right;"> <i class="fas fa-trash"></i> Delete </a>';

    
    if(data.to == null){
        ret += ' Member ';
    } else {
        ret += ' - ';
        
        ret += span_start;
        ret += '<i class="fas fa-user-times"></i> ';
        ret += data.to+ ' ';
        ret += span_end;
        
        if(data.kicked){
            ret += span_start;
            ret += ' <i class="fas fa-times-circle"> </i> Kicked';
            ret += span_end;
        }
        ret += '<br>';
        ret += escapeHtml(data.cause);
    }
    
    if(!edit)
        ret += '</li>';
    return ret;
}

function resetMS() {
    $('#msNewAdd').show();
    $('#msForm').hide();
    $('#msNewSave').hide();
    $('#msDefault').show();
    $('#msForm').attr('data-id',null);
}

function resetAfk(){
    $('#afkAddDiv').show();
    $('#afkAddSubmit').show();
    $('#afkForm').hide();
}

function resetCaution(){
    $('#cautionAddDiv').show();
    $('#cautionForm').hide();
}

function resetTrials(){
    $('#trialsListForm').hide();
    $('#trialAdd').show();
}

$(document).ready(function() {
    var inputAcc = $('#inputAccount');
    var inputSecAccs = $('#accsSearch');
    accountSelect(inputAcc);
    accountSelect(inputSecAccs);
    
    disable(true);
    
    var inputID = $('#inputID');
    
    var inputSecAccsID = $('#AccsID');
    
    var ts3Input = $('#ts3Input');
    ts3Select(ts3Input);
    
    inputAcc.on('select2:select', function (e) {
        var data = e.params.data;
        inputID.val(data.id);
        loadData(data.id);
    });
    inputSecAccs.on('select2:select', function (e) {
        var data = e.params.data;
        inputSecAccsID.val(data.id);
    });
    
    var loadingDiv = $('#loading');
    var errorDiv = $('#error');
    
    var msDefaultDiv = $('#msDefault');
    var msFormDiv = $('#msForm');
    var btnMSAdd = $('#msNewBtn');
    var btnMSNew = $('#msNewAdd');
    var btnMSSave = $('#msNewSave');
    
    var msNewChk = $('#msNewMember');
    var msNewTo = $('#msNewTo');
    var msNewFrom = $('#msNewFrom');
    var msNewKick = $('#msNewKick');
    var msNewCause = $('#msNewCause');
    
    var afkFrom = $('#afkFrom');
    var afkTo = $('#afkTo');
    var afkForm = $('#afkForm');
    var afkAddSubmit = $('#afkAddSubmit');
    var afkAdd = $('#afkAdd');
    var afkEditCancel = $('#afkEditCancel');
    var afkEditSubmit = $('#afkEditSubmit');
    var afkAddDiv = $('#afkAddDiv');
    var afkCause = $('#inputAFKCause');
    var afkEditCancelDiv = $('#afkEditCancelDiv');
    
    var cautionFrom = $('#cautionFrom');
    var cautionTo = $('#cautionTo');
    var cautionForm = $('#cautionForm');
    var cautionAdd = $('#cautionAdd');
    var cautionAddDiv = $('#cautionAddDiv');
    var cautionCause = $('#inputCautionCause');
    
    var trialsFrom = $('#trialFrom');
    var trialsTo = $('#trialTo');
    var trialsMemberChk = $('#trialMember');
    var trialsListForm = $('#trialsListForm');
    var trialsAddNew = $('#trialAdd');
    
    function changeMember(isMember){
        if(isMember){
            msNewFrom.data("DateTimePicker").maxDate(false);
            msNewTo.trigger('dp.change');
        }else{
            msNewTo.data("DateTimePicker").date(
                msNewFrom.data("DateTimePicker").date()
            );
        }
        msNewTo.attr('disabled',isMember);
        msNewKick.attr('disabled',isMember);
        msNewCause.attr('disabled',isMember);
        msNewTo.attr('required',!isMember);
    }
    msNewChk.change(function() {
        changeMember(this.checked);
    });
    
    trialsMemberChk.change(function() {
        trialsTo.attr('disabled',!this.checked);
    });
    
    msNewFrom.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
    });
    msNewTo.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
    });
    msNewFrom.on("dp.change", function (e) {
        msNewTo.data("DateTimePicker").minDate(e.date);
    });
    
    afkTo.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
    });
    afkFrom.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
    });
    
    cautionTo.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
    });
    cautionFrom.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
    });
    
    trialsFrom.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
    });
    trialsTo.datetimepicker({
        format: DATE_FORMAT,
        useCurrent: true,
    });
    
    $('#msForm').submit(function (e) {
        loadingDiv.show();
        var nr = msFormDiv.attr('data-id');
        var edit = nr != null;
        var type = edit ? 'membership-edit' : 'membership-add';
        var to = msNewChk.is(':checked') ? undefined : msNewTo.val();
        
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : type,
                'from': msNewFrom.val(),
                'to': to,
                'cause': msNewCause.val(),
                'kicked' : msNewKick.is(':checked') ? "on" : undefined,
                'id': inputID.val(),
                'nr': nr
            }
        }).done(function(data){
            if($('#'+MS_ENTRY+data.nr).length){
                $('#'+MS_ENTRY+data.nr).html(formatMembershipEntry(data,true));
            } else {
                $('#membership').append(formatMembershipEntry(data,false));
            }
            loadingDiv.hide();
            errorDiv.hide();
            resetMS();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
        });
        e.preventDefault();
    });
    
    $('#msNewCancel').click(function() {
        msFormDiv.hide();
        msDefaultDiv.show();
    });
    
    afkEditCancel.click(function() {
        afkEditCancelDiv.hide();
        afkEditSubmit.hide();
        afkAddSubmit.show();
        afkForm.hide();
        afkAdd.show();
    });
    
    trialsAddNew.click(function(){
        trialsListForm.show();
        trialsAddNew.hide();
    });
    
    btnMSAdd.click(function(){
        btnMSNew.show();
        msFormDiv.show();
        btnMSSave.hide();
        msDefaultDiv.hide();
        msFormDiv.attr('data-id',null);
    });
    
    afkAdd.click(function() {
        afkAddDiv.hide();
        afkForm.attr({'edit':false});
        afkForm.show();
        afkEditSubmit.hide();
        afkEditCancelDiv.hide();
    });
    
    cautionAdd.click(function() {
        cautionAddDiv.hide();
        cautionForm.show();
    });
    
    // handle edit/delete on membership
    $('#membership').on('click', 'a', function (e) {
        loadingDiv.show();
        var div = $(this);
        var id = $(this).attr('data-id');
        var action = $(this).attr('data-action');
        if(action == actionDelete){
            $.ajax({
                url: SITE_URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'membership-delete',
                    'nr' : id
                }
            }).done(function(data){
                if(data){
                    loadingDiv.hide();
                    errorDiv.hide();
                    $('#'+MS_ENTRY+data.nr).remove();
                    resetMS();
                }
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                loadingDiv.hide();
                errorDiv.show();
            });
        }else if(action == actionEdit) {
            var member = div.attr('data-member') == "true";
            changeMember(member);
            msFormDiv.attr('data-id',id);
            msNewChk.prop('checked',member);
            msNewKick.prop('checked',div.attr('data-kicked') == "true");
            msNewCause.val(div.attr('data-cause'));
            msNewTo.val(div.attr('data-to'));
            msNewFrom.val(div.attr('data-from'));
            btnMSNew.hide();
            msFormDiv.show();
            btnMSSave.show();
            msDefaultDiv.hide();
            loadingDiv.hide();
        }else {
            console.error('unknown action:'+action);
        }
        e.preventDefault();
    });
    
    trialsListForm.submit(function(e) {
        loadingDiv.show();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'member-trial-set',
                'id' : $('#inputID').val(),
                'from' : trialsFrom.val(),
                'to' : trialsMemberChk.is(':checked') ? trialsTo.val() : undefined,
            }
        }).done(function(data){
            $('#trial').hide();
            var elem = $('#'+TRIAL_ENTRY+data.from);
            if(elem.length)
                elem.remove();
            $('#trials').append(formatTrialEntry(data.from,data.to));
            errorDiv.hide();
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    
    // handle form for additionals (name,vip etc)
    $("#additionalForm").submit(function(e) {
        loadingDiv.show();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: $(this).serialize()
        }).done(function(data){
            if(data){
                errorDiv.hide();
                $('#missingDataDiv').hide();
            }
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    
    $("#ts3Form").submit(function(e) {
        loadingDiv.show();
        var selected = ts3Input.select2('data')[0];
        if($("#"+TS3_ENTRY+selected.id).length) {
            console.log("ts3 entry exists already");
            loadingDiv.hide();
        } else {
            $.ajax({
                url: SITE_URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'add-ts3-relation',
                    'id' : inputID.val(),
                    'tsID' : selected.id,
                    'name' : selected.text,
                }
            }).done(function(data){
                $('#ts3').append(formatTS3Entry(data.name,data.tsID));
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
        }
        e.preventDefault();
    });
    
    $("#trialForm").submit(function(e) {
        loadingDiv.show();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'member-trial-set',
                'id' : $('#inputID').val(),
                'from' : $('#trial-data').attr('data-from'),
                'to' : moment().subtract(1, 'day').format(DATE_FORMAT),
            }
        }).done(function(data){
            $('#trial').hide();
            errorDiv.hide();
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    
    cautionForm.submit(function(e) {
        loadingDiv.show();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'add-caution',
                'id' : $('#inputID').val(),
                'from' : cautionFrom.val(),
                'to' : cautionTo.val(),
                'cause' : cautionCause.val(),
            }
        }).done(function(data){
            var elem = $('#'+CAUTION_ENTRY+data.from);
            if(elem.length){
                elem.remove();
            }
            $('#caution').append(formatCautionEntry(data.from,data.to,data.cause));
            errorDiv.hide();
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    
    $('#caution').on('click', 'a', function (e) {
        loadingDiv.show();
        var from = $(this).attr('data-from');
        var to = $(this).attr('data-to');
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'delete-caution',
                'id' : $('#inputID').val(),
                'from' : from,
                'to' : to,
            }
        }).done(function(data){
            $('#'+CAUTION_ENTRY+data.from).remove();
            errorDiv.hide();
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    
    afkForm.submit(function(e) {
        loadingDiv.show();
        var edit = afkForm.attr('edit');
        if (edit == "true") {
            var from_orig = afkForm.attr('data-from');
            var to_orig = afkForm.attr('data-to');
            console.log('from_orig',from_orig);
            $.ajax({
                url: SITE_URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'edit-afk',
                    'id' : $('#inputID').val(),
                    'fromNew' : afkFrom.val(),
                    'toNew' : afkTo.val(),
                    'cause' : afkCause.val(),
                    'from': from_orig,
                    'to': to_orig,
                }
            }).done(function(data){
                var elem = $('#'+AFK_ENTRY+from_orig+'-'+to_orig);
                if(elem.length){
                    elem.remove();
                }
                $('#afk').append(formatAFKEntry(data.from,data.to,data.cause));
                resetAfk();
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
        } else {
            $.ajax({
                url: SITE_URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'add-afk',
                    'id' : $('#inputID').val(),
                    'from' : afkFrom.val(),
                    'to' : afkTo.val(),
                    'cause' : afkCause.val(),
                }
            }).done(function(data){
                var elem = $('#'+AFK_ENTRY+data.from+'-'+data.to);
                if(elem.length){
                    elem.remove();
                }
                $('#afk').append(formatAFKEntry(data.from,data.to,data.cause));
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
        }
        e.preventDefault();
    });
    
    $('#afk').on('click', 'a', function (e) {
        var from = $(this).attr('data-from');
        var to = $(this).attr('data-to');
        var action = $(this).attr('data-action');
        if(action == actionDelete){
            loadingDiv.show();
            $.ajax({
                url: SITE_URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'delete-afk',
                    'id' : $('#inputID').val(),
                    'from' : from,
                    'to' : to,
                }
            }).done(function(data){
                $('#'+AFK_ENTRY+data.from+'-'+data.to).remove();
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
        } else if(action == actionEdit) {
            afkTo.val(to);
            afkFrom.val(from);
            afkCause.val($(this).attr('data-cause'));
            afkAddSubmit.hide();
            afkForm.attr({'data-to':to,'data-from':from, 'edit': true});
            afkAddDiv.hide();
            afkEditCancelDiv.show();
            afkEditSubmit.show();
            afkForm.show();
        } else {
            console.error('unknown action:'+action);
        }
        
        e.preventDefault();
    });
    
    $('#trials').on('click', 'a', function (e) {
        loadingDiv.show();
        var from = $(this).attr('data-from');
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'member-trial-delete',
                'id' : $('#inputID').val(),
                'from' : from
            }
        }).done(function(data){
            $('#'+TRIAL_ENTRY+data.from).remove();
            errorDiv.hide();
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    
    $('#ts3').on('click', 'a', function (e) {
        loadingDiv.show();
        var id = $(this).attr('data-id');
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'remove-ts3-relation',
                'id' : $('#inputID').val(),
                'tsID' : id,
            }
        }).done(function(data){
            $('#'+TS3_ENTRY+data.tsID).remove();
            errorDiv.hide();
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    
    $("#accsForm").submit(function(e) {
        loadingDiv.show();
        $.ajax({
            url: SITE_URL,
            type: 'post',
            dataType: "json",
            data: {
                'site' : VAR_SITE,
                'ajaxCont' : 'data',
                'type' : 'add-second-account',
                'id' : inputID.val(),
                'secID' : inputSecAccsID.val(),
            }
        }).done(function(data){
            $('#Accs').append(formatAccsEntry(data.name,data.secID));
            errorDiv.hide();
            loadingDiv.hide();
        }).fail(function(data){
            errorDiv.html(formatErrorData(data));
            errorDiv.show();
            loadingDiv.hide();
        });
        e.preventDefault();
    });
    
    $('#Accs').on('click', 'a', function (e) {
        loadingDiv.show();
        var id = $(this).attr('data-id');
        if(id != undefined){ // allow link to second accounts
            $.ajax({
                url: SITE_URL,
                type: 'post',
                dataType: "json",
                data: {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'remove-second-account',
                    'id' : $('#inputID').val(),
                    'secID' : id,
                }
            }).done(function(data){
                $('#accs-'+data.secID).remove();
                errorDiv.hide();
                loadingDiv.hide();
            }).fail(function(data){
                errorDiv.html(formatErrorData(data));
                errorDiv.show();
                loadingDiv.hide();
            });
            e.preventDefault();
        }
    });
    
    window.onpopstate = function(event) {
        var id = getUrlParameter('id');
        preselect(id,inputAcc);
    };
    
<?php if(isset($_REQUEST['id'])) { ?>
    preselect('<?=$_REQUEST['id']?>',inputAcc);
<?php } else { ?>
    inputAcc.select2('open');
<?php } ?>
});
</script>
