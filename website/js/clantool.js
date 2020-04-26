var charts = []; // keep track of charts for cleanup
const MONTH_NAMES = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December"
];
const DAYS_OF_WEEK = [
    "Su",
    "Mo",
    "Tu",
    "We",
    "Th",
    "Fr",
    "Sa"
];

// add map if not existing
if (!Array.prototype.map)
{
    Array.prototype.map = function(fun /*, thisp*/)
    {
        var len = this.length;
        
        if (typeof fun != "function")
        throw new TypeError();
        
        var res = new Array(len);
        var thisp = arguments[1];
        
        for (var i = 0; i < len; i++)
        {
            if (i in this)
            res[i] = fun.call(thisp, this[i], i, this);
        }
        return res;
    };
}

function cleanupCharts(){
    var length = charts.length;
    for (var i = 0; i < length; i++ ){
        charts[i].destroy();
    }
    charts = [];
}

function escapeHtml(unsafe) {
    if(unsafe == null)
        return "";
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function ts3Select(element){
    element.select2({
        ajax: {
            url: SITE_URL,
            type: 'get',
            dataType: 'json',
            delay: 500, // ms
            data: function(params) {
                var query = {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'ts3-search-select2',
                    'key' : params.term
                }
                
                return query;
            }
        },
        placeholder: "TS3 Account Namenssuche",
        allowClear: true
    });
}

/* Init ts channel selector, container is modal container */
function tsChannelSelect(element,container){
    element.select2({
        ajax: {
            url: SITE_URL,
            type: 'get',
            dataType: 'json',
            delay: 500, // ms
            data: function(params) {
                var query = {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'ts-channel-search-select2',
                    'key' : params.term
                }
                
                return query;
            }
        },
        placeholder: "TS3 Channel Suche",
        allowClear: true,
        dropdownParent: container
    });
}

function setURLParameter(param){
    var url = window.location.href;
    var obj = "";
    for (var key in param) {
        url = updateURLParameter(url,key,param[key]);
        obj += param[key];
    }
    window.history.replaceState(obj,"",url);
}

function setURLParameterHistory(obj,param,val){
    window.history.pushState(obj,"",
    updateURLParameter(window.location.href,param,val));
}

function updateURLParameter(url, param, paramVal){
    var newAdditionalURL = "";
    var tempArray = url.split("?");
    var baseURL = tempArray[0];
    var additionalURL = tempArray[1];
    var temp = "";
    if (additionalURL) {
        tempArray = additionalURL.split("&");
        for (var i=0; i<tempArray.length; i++){
            if(tempArray[i].split('=')[0] != param){
                newAdditionalURL += temp + tempArray[i];
                temp = "&";
            }
        }
    }

    var rows_txt = temp + "" + param + "=" + paramVal;
    return baseURL + "?" + newAdditionalURL + rows_txt;
}

function getUrlParameter(sParam) {
    var sPageURL = decodeURIComponent(window.location.search.substring(1)),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : sParameterName[1];
        }
    }
};

function accountSelect(element){
    element.select2({
        ajax: {
            url: SITE_URL,
            type: 'get',
            dataType: 'json',
            delay: 600, // ms
            data: function(params) {
                var query = {
                    'site' : VAR_SITE,
                    'ajaxCont' : 'data',
                    'type' : 'member-search-select2',
                    'key' : params.term
                }
                
                return query;
            }
        },
        placeholder: "Account ID/Namensuche",
        allowClear: true
    });
}

const fixedTableClass = '.table';

function updateTableTool() {
    $(fixedTableClass).trigger('update');
}

function initTableTool() {
    console.log("default sorter");
    var myTextExtraction = function(node) 
    {
        return node.innerHTML;
    };
    initTableTool(myTextExtraction);
}

function initTableTool(extractor) {
    console.log("extractor sorter..");
    $(fixedTableClass).tablesorter({
        textExtraction: extractor,
        widgets: ["stickyHeaders"],
        widgetOptions : {
            stickyHeaders : "fixed-header" // background fix class
        }
    });
}

// jquery function to allow editing of td's with payload function
// function called upon change, receiving td & new value
$.fn.makeEditable = function(payload,maxlength) {
    $(this).on('click',function(){
        if($(this).find('input').is(':focus')) return this;
        var cell = $(this);
        var content = $(this).html();
        var lAddition = maxlength == null ? '' : 'maxlength="'+maxlength+'"';
        $(this).html('<input type="text" class="form-control" value="' + $(this).html() + '"'+lAddition+' />')
        .find('input')
        .trigger('focus')
        .on({
            'blur': function(){
                $(this).trigger('closeEditable');
            },
            'keyup':function(e){
                if(e.which == '13'){ // enter
                    $(this).trigger('saveEditable');
                } else if(e.which == '27'){ // escape
                    $(this).trigger('closeEditable');
                }
            },
            'closeEditable':function(){
                cell.html(content);
            },
            'saveEditable':function(){
                content = $(this).val();
                $(this).trigger('closeEditable');
                payload(cell,content);
            }
        });
    });
    return this;
}

/**
    * Format ajax fail(data) objects
    */
function formatErrorData(data) {
    return '<h3><span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>'
                +'<span class="sr-only">Error:</span>Error:</h3><br><b>Status:</b> '+data.status+' '+data.statusText+'<br>'+data.responseText;
}

function processParams(type, addition) {
    let params = new URLSearchParams(addition);
    params.append('site',VAR_SITE);
    params.append('type', type);
    params.append('ajaxCont', 'data');
    return params;
}

function postJson(type,bodyAddition) {
    let body = processParams(type,bodyAddition);
    return fetch(SITE_URL, {
        method: 'POST',
        headers: {
            'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept' : 'application/json, text/javascript, */*; q=0.01'
        },
        body: body
    }).then(res => res.json());
}

function getJson(type,bodyAddition) {
    let url = new URL(window.location.href);
    url.search = processParams(type,bodyAddition).toString();
    return fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept' : 'application/json, text/javascript, */*; q=0.01'
        }
    }).then(res => res.json());
}

function formatMemberDetailLink(id,text){
    return '<a href="'+escapeHtml(MEMBER_DETAIL_URL+id)+'">'+text+'</a>';
}
