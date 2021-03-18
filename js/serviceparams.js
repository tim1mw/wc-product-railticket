document.addEventListener("DOMContentLoaded", setupEditor);
var data = {};
var coachesAV = [];
var defaultcoachimg = false;

function setupEditor() {
    var dd = getFormValue('composition');
    data = JSON.parse(dd);

    for (i in coaches) {
        var ch = {};
        ch.key = i;
        ch.name = coaches[i].name;
        coachesAV.push(ch);
        if (!defaultcoachimg) {
            defaultcoachimg = coaches[i].image;
        }
    }

console.log(coachesAV);

    renderEditorSP();
    renderEditorCoachSets();
}

function renderEditorSP() {
    var sp = document.getElementById('railticket_serviceeditor_sp');
    var sptempl = document.getElementById('serviceparams_tmpl').innerHTML;
    var spdata = {};
    spdata.daytypes = processSelect(daytypes, data.daytype);
    spdata.allocateby = processSelect(allocateby, data.allocateby);

    sp.innerHTML = Mustache.render(sptempl, spdata);
}

function renderEditorCoachSets() {
    var cdata = {};
    cdata.sets = [];
    cdata.avcoaches = coachesAV;
    cdata.defaultcoachimg = defaultcoachimg;

    // Detect what we have rather than relying on the daytype so we don't loose too much when switching
    if (data.hasOwnProperty('coachsets')) {
        for (i in data.coachsets) {
            var parts = i.split('_');
            cdata.sets.push(processCoachSet(parts[1], data.coachsets[i].coachset));
        }
    } else {
        if (data.hasOwnProperty('coachset')) {
            cdata.sets.push(processCoachSet(0, data.coachset));
        }
    }

    var c = document.getElementById('railticket_serviceeditor_c');
    var ctempl = document.getElementById('coachsets_tmpl').innerHTML;
    c.innerHTML = Mustache.render(ctempl, cdata);

    var addsels = document.getElementsByClassName('addcoachsel');
    for (i in addsels) {
        addsels[i].addEventListener('change', setCoachImage);
    }
}

function setCoachImage(evt) {
    console.log(evt.target.value);
    var parts = evt.target.value.split('_');
    var img = document.getElementById('addcoachimg_'+parts[1]);
    img.src = coaches[parts[0]].image;
}

function processCoachSet(num, set) {
    var c = {};
    c.num = num;
    c.disp = parseInt(num)+1;
    c.disabled = '';
    c.selectedcoaches = [];
    for (i in set) {
        var tc = {};
        if (coaches.hasOwnProperty(i)) {
            tc.name = coaches[i].name;
            tc.img = coaches[i].image;
        } else {
            tc.name = tc.code+" (unknown coach)";
        }
        tc.code = i;
        tc.count = set[i];
        c.selectedcoaches.push(tc);
    }
    return c;
}

function processSelect(sdata, selected) {
    var p = [];
    var count = 0;
    for (i in sdata) {
        p[count] = {};
        p[count].name = sdata[i];
        p[count].value = i;
        if (selected == i) {
            p[count].selected = 'selected';
        }
        count++;
    }
    return p;
}

 
function railTicketSPAjax(datareq, spinner, callback) {
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

    var data = new FormData();
    data.append('action', 'railticket_adminajax');
    data.append('function', datareq);


    request.send(data);
}

function getCBFormValue(param) {
    if (param in document.bookableday) {
        return document.bookableday[param].checked;
    }

    return false;
}

function getFormValue(param) {
    if (param in document.bookableday) {
        return document.bookableday[param].value;
    }

    return false;
}
