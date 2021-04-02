document.addEventListener("DOMContentLoaded", setupEditor);

var notify = true;
var notifyover = true;

function setupEditor() {
    renderEditor(defaultData);
    renderOverride(defaultData)
}

function dataChanged(e) {
    railTicketEditAjax(getEditFormData('moveorderdata'), true, dataUpdated);
}

function dataUpdated(response) {
    if (response.bkerror) {
        var bkerror = document.getElementById('railticket_bkerror');
        bkerror.innerHTML = response.bkerror;
        var r = document.getElementById('railticket_commitmessage');
        r.innerHTML = '';
        var cb = document.getElementById('railticket_commit');
        cb.disabled = true;
        return;
    }

    renderEditor(response);
}

function commitEdit() {
    railTicketEditAjax(getEditFormData('editorder'), true, editCommitted);
}

function editCommitted(response) {
    var r = document.getElementById('railticket_commitmessage');
    r.innerHTML = response.message;
    setTimeout(function () {
        var back = document.getElementById('railticket_backtoview');
        back.submit();
    }, 1500);
}

function renderEditor(data) {
    var mvtempl = document.getElementById('movebooking_tmpl').innerHTML;
    var mv=document.getElementById('railticket_movebooking');

    if (notify) {
        data.notify = 'checked';
    }

    mv.innerHTML = Mustache.render(mvtempl, data);

    var eles = document.getElementsByClassName('railticket_refeshdata');
    for (var i = 0; i < eles.length; i++) {
        eles[i].addEventListener('change', dataChanged);
    }
    var com = document.getElementById('railticket_commit');
    com.addEventListener('click', commitEdit);

    var disable = false;
    for (i in data.bookings) {
        if (data.bookings[i].deps.length == 0) {
            disable = true;
        }
    }
    var cb = document.getElementById('railticket_commit');
    cb.disabled = disable;

    var nodes = document.getElementById('railticket_overridebays').getElementsByTagName('*');
    for(var i = 0; i < nodes.length; i++){
        nodes[i].disabled = true;
    }
}
    

function renderOverride(data) {
    var ovtempl = document.getElementById('overridebays_tmpl').innerHTML;
    var ov=document.getElementById('railticket_overridebays'); 
    ov.disabled = false;
    ov.innerHTML = Mustache.render(ovtempl, data);

    var over = document.getElementById('railticket_commitover');
    over.addEventListener('click', commitOverride);
}

function railTicketEditAjax(data, spinner, callback) {
    if (spinner) {
        var spinnerdiv = document.getElementById('pleasewait');
        spinnerdiv.style.display = 'block';
    }

    var request = new XMLHttpRequest();
    request.open('POST', ajaxurl, true);
    request.onload = function () {
console.log(request);
        if (request.status >= 200 && request.status < 400) {
            callback(JSON.parse(request.responseText).data);
            var spinnerdiv = document.getElementById('pleasewait');
            spinnerdiv.style.display = 'none';
        }
    };

    request.send(data);
}

function getEditFormData(datareq) {
    notify = getCBFormValue('notify');
    var data = new FormData();
    data.append('action', 'railticket_adminajax');
    data.append('orderid', orderid);
    data.append('function', datareq);
    data.append('dateoftravel', getFormValue('dateoftravel'));
    data.append('notify', notify);
    var legs = [];
    for (i=0; i < defaultData.bookings.length; i++) {
        legs[i] = {};
        legs[i].from = getFormValue('fromstation'+(i+1));
        legs[i].to = getFormValue('tostation'+(i+1));
        legs[i].dep = getFormValue('dep'+(i+1));
    }

    data.append('legs', JSON.stringify(legs));
    return data;
}

function getCBFormValue(param) {
    if (param in document.movebooking) {
        return document.movebooking[param].checked;
    }

    return false;
}

function getFormValue(param) {
    if (param in document.movebooking) {
        return document.movebooking[param].value;
    }

    return false;
}

function commitOverride() {
    railTicketEditAjax( getOverFormData(), true, overrideCommitted);
}

function overrideCommitted(response) {
    var r = document.getElementById('railticket_commitovermessage');
    r.innerHTML = response.message;
    setTimeout(function () {
        var back = document.getElementById('railticket_backtoview');
        back.submit();
    }, 1500);
}

function getOverFormData() {
    notifyover = getCBFormValue('notify');
    var data = new FormData();
    data.append('action', 'railticket_adminajax');
    data.append('orderid', orderid);
    data.append('function', 'overridebays');
    data.append('notify', notifyover);
    var legbays = [];
    for (i=0; i < defaultData.bookings.length; i++) {
        legbays[i] = {};
        for (bi=0; bi < defaultData.bookings[i].bays.length; bi++) {
            key = defaultData.bookings[i].bays[bi].key;
            legbays[i][key] = getOverFormValue('leg|'+(i+1)+'|'+key);
        }
    }

    data.append('legbays', JSON.stringify(legbays));
    return data;
}

function getCBOverFormValue(param) {
    if (param in document.bayeditor) {
        return document.bayeditor[param].checked;
    }

    return false;
}

function getOverFormValue(param) {
    if (param in document.bayeditor) {
        return document.bayeditor[param].value;
    }

    return 0;
}
