document.addEventListener("DOMContentLoaded", setupEditor);

var notify = true;

function setupEditor() {
    renderEditor(defaultData);
}

function dataChanged(e) {
    railTicketEditAjax('moveorderdata', true, dataUpdated);
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
    railTicketEditAjax('editorder', true, editCommitted);
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
}

function railTicketEditAjax(datareq, spinner, callback) {
    if (spinner) {
        var spinnerdiv = document.getElementById('pleasewait');
        spinnerdiv.style.display = 'block';
    }

    var request = new XMLHttpRequest();
    request.open('POST', ajaxurl, true);
    request.onload = function () {
        if (request.status >= 200 && request.status < 400) {
            callback(JSON.parse(request.responseText).data);
            var spinnerdiv = document.getElementById('pleasewait');
            spinnerdiv.style.display = 'none';
        }
    };

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

    request.send(data);
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

