<h3>Unknown TS Identities</h3>
Die folgenden Identitäten haben Member-Status, gehören aber zu keiner Member-Kartei!
<div id="error" style="display: none;" class="alert alert-danger fade in"></div>
<div id="loading" style="position: fixed; display: none;z-index: 10; background: rgba(255,255,255,0.5); width: 100%; height: 100%;">
    <div style="position: fixed; left: 50%; top: 50%;">
        <i class="fas fa-spinner fa-pulse fa-3x"></i><br>
        <div id="loading-content">Loading...</div>
    </div>
</div>
<ul id="ts_identities" class="list-group">
<li class="list-group-item list-group-item-disabled">Loading..</li>
</ul>
<script type="text/javascript">
$(document).ready(function() {
    $.ajax({
        url: SITE_URL,
        type: 'get',
        dataType: "json",
        data: {
            'site' : VAR_SITE,
            'ajaxCont' : 'data',
            'type' : 'ts3-unknown-identities',
        }
    }).done(function(data){
        renderData(data);
        $('#loading').hide();
        $('#error').hide();
    }).fail(function(data){
        $('#error').html(formatErrorData(data));
        $('#error').show();
        $('#loading').hide();
    });
    /*let data = [{'name': "asd",'id': 12345},{'name': "fgh", 'id': 2345},{'name': "jkl", 'id': 34567}];
    renderData(data);*/
});
function renderData(data) {
    var elements = "";
    
    for(var i = 0; i < data.length; i++) {
        let elem = data[i];
        elements += formatIdentity(elem);
    }
    $('#ts_identities').html(elements);
    $('.accountSelector').each(function (i,obj) {
        accountSelect($(obj));
    });
    $('.addAccountForm').submit(function (e) {
        e.preventDefault();
        console.log(e);
        handleAccountAdd(e.target.getAttribute('data-cid'));
    });
    $('.addIgnore').submit(function (e) {
        e.preventDefault();
        console.log(e);
        handleIgnore(e.target.getAttribute('data-cid'));
    });
}

function formatIdentity(elem) {
    return '<li class="list-group-item" id="li-'+elem.id+'">'
        + '<h4 class="list-group-item-heading">' + escapeHtml(elem.name) + '</h4>'
        + `<form class="form-horizontal addAccountForm" data-cid="`+elem.id+`" action="" method="post">
            <div class="form-group">
                <label for="inputAccount" class="control-label col-xs-2">Add to account</label>
                <div class="col-xs-10">
                    <select class="form-control accountSelector" name="id" required=""
                        id="accountSelect`+elem.id+`" >
                    </select>
                </div>
            </div>
            <div class="form-group">
                        <div class="col-xs-offset-2 col-xs-10">
                <button class="btn btn-primary" type="submit">Add to account</button>
                        </div>
            </div>
            </form>`
        + '<button data-cid="'+elem.id+'" class="btn btn-warning addIgnore">Ignore TS Identity</button>'
        + '</li>';
}

function handleIgnore(cid) {
    $.ajax({
        url: SITE_URL,
        type: 'post',
        dataType: "json",
        data: {
            'site' : VAR_SITE,
            'ajaxCont' : 'data',
            'type' : 'ignore-ts3-id',
            'tsID' : cid,
        }
    }).done(function(data){
        $('#li-'+cid).remove();
    }).fail(function(data){
        $('#error').html(formatErrorData(data));
        $('#error').show();
        $('#loading').hide();
    });
}

function handleAccountAdd(cid) {
    var acc = $('#accountSelect'+cid).select2('data')[0];
    $.ajax({
        url: SITE_URL,
        type: 'post',
        dataType: "json",
        data: {
            'site' : VAR_SITE,
            'ajaxCont' : 'data',
            'type' : 'add-ts3-relation',
            'id' : acc.id,
            'tsID' : cid,
            'name' : "",
        }
    }).done(function(data){
        $('#li-'+cid).remove();
    }).fail(function(data){
        $('#error').html(formatErrorData(data));
        $('#error').show();
        $('#loading').hide();
    });
}
</script>
