document.addEventListener("DOMContentLoaded", setupEditor);

var coachesAV = [];
var defaultcoachimg = false;
var dep_times_up_keys = {};
var dep_times_down_keys = {};

function setupEditor() {

    for (i in dep_times_up) {
        dep_times_up_keys[dep_times_up[i].key] = i;
    }

    for (i in dep_times_down) {
        dep_times_down_keys[dep_times_down[i].key] = i;
    }

    check_all_dep_times();

    for (i in coaches) {
        var ch = {};
        ch.key = i;
        ch.name = coaches[i].name;
        coachesAV.push(ch);
        if (!defaultcoachimg) {
            defaultcoachimg = coaches[i].image;
        }
    }

    renderEditorSP();
    renderEditorCoachSets();
    renderServiceAllocation();
    renderEditorData();
}

function renderEditorSP() {
    var sp = document.getElementById('railticket_serviceeditor_sp');
    var sptempl = document.getElementById('serviceparams_tmpl').innerHTML;
    var spdata = {};
    spdata.daytypes = processSelect(daytypes, data.daytype);
    spdata.allocateby = processSelect(allocateby, data.allocateby);

    sp.innerHTML = Mustache.render(sptempl, spdata);

    addActionListeners('railticket_sp_opt', 'change', setSPOpt);
}

function renderEditorCoachSets() {
    var cdata = {};
    cdata.sets = [];
    cdata.avcoaches = coachesAV;
    cdata.defaultcoachimg = defaultcoachimg;

    switch (data.daytype) {
        case 'pertrain':
            for (i in data.coachsets) {
                var parts = i.split('_');
                cdata.sets.push(processCoachSet(parts[1], data.coachsets[i].coachset, data.coachsets[i].reserve));
            }
            break;
        case 'simple':
            cdata.sets.push(processCoachSet(0, data.coachset, data.reserve));
            break;
    }

    var c = document.getElementById('railticket_serviceeditor_c');
    var ctempl = document.getElementById('coachsets_tmpl').innerHTML;
    c.innerHTML = Mustache.render(ctempl, cdata);

    addActionListeners('addcoachsel', 'change', setCoachImage);
    addActionListeners('addcoachbtn', 'click', addCoachToSet);
    addActionListeners('deleteset', 'click', deleteCoachSet);
    addActionListeners('coachcount', 'click', coachSetCount);
    addActionListeners('reserveselect', 'change', coachSetReserve);

    document.getElementById('addsetbtn', 'click', addCoachSet).addEventListener('click', addCoachSet);

    if (data.daytype == 'simple') {
        document.getElementById('addsetbtn').style.display = 'none';
        var dels = document.getElementsByClassName('deleteset');
        for (i=0; i<dels.length; i++) {
            dels[i].style.display = 'none';
        }
    } else {
        if (Object.keys(data.coachsets).length < 3) {
            var dels = document.getElementsByClassName('deleteset');
            for (i=0; i<dels.length; i++) {
                dels[i].style.display = 'none';
            }
        }
    }


}

function renderServiceAllocation() {
    var a = document.getElementById('railticket_serviceeditor_a');
    if (data.daytype == 'simple') {
        a.innerHTML = '';
        return;
    }

    var adata = {};

    var atempl = document.getElementById('depallocation_tmpl').innerHTML;
    a.innerHTML = Mustache.render(atempl, adata);
}

function renderEditorData() {
    var cdata = {
        composition: JSON.stringify(data, null, 4)
    };

    var c = document.getElementById('railticket_serviceeditor_data');
    var ctempl = document.getElementById('servicedata_tmpl').innerHTML;
    c.innerHTML = Mustache.render(ctempl, cdata);

    document.getElementById('showserviceparams', 'click').addEventListener('click', function (evt) {
        var div = document.getElementById('railticket_serviceparameters');
        if (div.style.display == 'none') {
            div.style.display = '';
            evt.target.value = 'Hide Service Parameter Data';
        } else {
            div.style.display = 'none';
            evt.target.value = 'Show Service Parameter Data';
        }
    });

    document.getElementById('setserviceparams', 'click').addEventListener('click', function (evt) {
        try {
            var ndata = JSON.parse(document.getElementById('servicecomp').value);
            data = ndata;
        } catch (err) {
            var e = document.getElementById('serviceparseerror');
            e.innerHTML = '<p style="color:red">Error parsing service data: '+err+'</p>';
            e.style.display='';
            return;
        }

        renderEditorSP();
        renderEditorCoachSets();
        renderServiceAllocation();
        renderEditorData();
    });
}

function addActionListeners(clss, event, method) {
    var d = document.getElementsByClassName(clss);
    for (i=0; i<d.length; i++) {
       d[i].addEventListener(event, method);
    } 
}

function setCoachImage(evt) {
    var parts = evt.target.value.split('_');
    var img = document.getElementById('addcoachimg_'+parts[1]);
    img.src = coaches[parts[0]].image;
}

function processCoachSet(num, set, reserve) {
    var c = {};
    c.num = num;
    c.disp = parseInt(num)+1;
    c.disabled = '';
    c.selectedcoaches = [];
    c.reserve = [];

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

    var stats = getCoachSetStats(set);
    c.seats = stats.seats;
    c.bays = formatBays(stats.bays);

    for (i in reserve) {
        var type = i.split('_');
        var r = {};
        r.desc = type[0]+"&nbsp;Seat&nbsp;"+baytypes[type[1]];
        r.value = reserve[i];
        r.key = i;
        r.max = stats.bays[i];
        c.reserve.push(r);
    }

    return c;
}

function formatBays(bays) {
    var data = [];
    for (i in bays) {
        var parts = i.split('_');
        if (parts[1] == 'priority') {
            data.push(bays[i]+"x "+parts[0]+" Seat Disabled Bay");    
        } else {
            data.push(bays[i]+"x "+parts[0]+" Seat Normal Bay");  
        }
    }
    return data;
}

function getCoachSetStats(coachset) {
    var data = {
        "seats": 0,
        "bays": {}
    };
    for (i in coachset) {
        var coachdata = coaches[i];
        for (ci in coachdata.composition) {
            var bay = coachdata.composition[ci];
            var baykey = '';
            if (bay.priority) {
                baykey = bay.baysize+'_priority';
            } else {
                baykey = bay.baysize+'_normal';
            }

            if (data.bays.hasOwnProperty(baykey)) {
                data.bays[baykey]+=bay.quantity*coachset[i];
            } else {
                data.bays[baykey]=bay.quantity*coachset[i];
            }
            data.seats += (bay.quantity*bay.baysize)*coachset[i];
        }
    }

    return data;
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

function addCoachToSet(evt) {
    var setid = evt.target.id.split('_')[1];
    var coach = document.getElementById('coaches_'+setid).value.split('_')[0];
    var coachset = getCoachSet(setid);
    if (coachset.hasOwnProperty(coach)) {
        coachset[coach]++;
    } else {
        coachset[coach] = 1;
    }

    validateReserve(setid);
    renderEditorData();
    renderEditorCoachSets();
}

function deleteCoachSet(evt) {
    // Sanity check....
    if (data.daytype == 'simple') {
        console.log("This is a simple day...");
        return;
    }
    var parts = evt.target.id.split('_');

    var delkey = 'set_'+parts[1];
    delete data.coachsets[delkey];
    renderEditorData();
    renderEditorCoachSets();
}

function addCoachSet(evt) {
    // Sanity check....
    if (data.daytype == 'simple') {
        console.log("This is a simple day...");
        return;
    }

    for (i = 0; i<100; i++) {
        var setkey = "set_"+i;
        if (data.coachsets.hasOwnProperty(setkey)) {
            continue;
        }

        data.coachsets['set_'+i] = {
            "coachset": {},
            "reserve": {}
        };
        break;
    }

    var keys = Object.keys(data.coachsets);
    keys.sort();

    var ncoachsets = {};
    for (i = 0; i < keys.length; i++) {
        ncoachsets[keys[i]] = data.coachsets[keys[i]];
    }

    data.coachsets = ncoachsets;

    renderEditorData();
    renderEditorCoachSets();
}

function coachSetCount(evt) {
    var parts = evt.target.id.split('_');
    var setid = parts[1];
    var coach = parts[2];
    var coachset = getCoachSet(parts[1]);
    coachset[parts[2]] = evt.target.value;

    if (coachset[parts[2]] < 1) {
        delete  coachset[coach];
        validateReserve(setid);
    }

    renderEditorData();
    renderEditorCoachSets();
}

function coachSetReserve(evt) {
    var parts = evt.target.id.split('-');
    var resset = getReserve(parts[1]);
    resset[parts[2]] = evt.target.value;
    renderEditorData();
    renderEditorCoachSets();
}

function validateReserve(setid) {
    var coachset = getCoachSet(setid);
    var bays = getCoachSetStats(coachset).bays;
    var reserve = getReserve(setid);

    // Check all the bays in the current reserve exist in the set and remove those that don't
    for (i in reserve) {
        if (bays.hasOwnProperty(i)) {
            continue;
        }

        delete reserve[i];
    }

    // Add any bay types in that are missing
    for (i in bays) {
        if (!reserve.hasOwnProperty(i)) {
            reserve[i] = 0;
        }
    }
}

function getCoachSet(setid) {
    switch (data.daytype) {
        case 'simple':
            return data.coachset;
            break;
        case 'pertrain':
            return data.coachsets['set_'+setid].coachset;
            break;
    }

    console.log("bad day type... "+data.daytype);
}

function getReserve(setid) {
    switch (data.daytype) {
        case 'simple':
            return data.reserve;
            break;
        case 'pertrain':
            return data.coachsets['set_'+setid].reserve;
            break;
    }

    console.log("bad day type... "+data.daytype);
}

function setSPOpt(evt) {
    switch(evt.target.name) {
        case 'sp_daytype':
            data.daytype = evt.target.value;
            convertDayType();
            break;
        case 'sp_allocateby':
            data.allocateby = evt.target.value;
            break;
    }
    renderEditorCoachSets()
    renderEditorData();
}


function convertDayType() {
    // Detect what we have rather than relying on the daytype so we don't loose too much when switching
    if (data.hasOwnProperty('coachsets')) {
        if (data.daytype == 'simple') {
            // Convert Structure
            var sets = Object.keys(data.coachsets);
            data.coachset = data.coachsets[sets[0]].coachset;
            data.reserve = data.coachsets[sets[0]].reserve;
            delete data.coachsets;
            delete data.down;
            delete data.up;
        }
    } else {
        if (data.hasOwnProperty('coachset')) {
            if (data.daytype != 'simple') {
                // Convert Structure
                data.coachsets = {
                    "set_0": {
                        "coachset": data.coachset,
                        "reserve": data.reserve
                    },
                    "set_1": {
                        "coachset": {},
                        "reserve": {}
                    }
                };
                delete data.coachset;
                delete data.reserve;

                data.up = get_dep_times(dep_times_up);
                data.down = get_dep_times(dep_times_down);
            }
        }
    }
}

function get_dep_times(times) {
    var tt = {}
    var count = 0;
    var sets = Object.keys(data.coachsets);

    for (i in times) {
        tt[times[i].key] = sets[count];
        count++;
        if (count >= sets.length) {
            count = 0;
        }
    }

    return tt;
}

function check_all_dep_times() {
    if (data.daytype == 'simple') {
        return;
    }

    // Do a sanity check on the dep times in the config against the ones for the time configured at load time.
    data.up = check_dep_times(data.up, dep_times_up_keys);
    data.down = check_dep_times(data.down, dep_times_down_keys);
}

function check_dep_times(ctimes, ttimes) {
    // Remove any times that don't exist.
    for (i in ctimes) {
        if (ttimes.hasOwnProperty(i)) {
            continue;
        }
        delete ctimes[i];
    }

    var sets = Object.keys(data.coachsets);
    var count = 0;

    for (i in ttimes) {
        if (ctimes.hasOwnProperty(i)) {
            continue;
        }
        ctimes[i] = sets[count];
        count++;
        if (count >= sets.length) {
            count = 0;
        }
    }

    var keys = Object.keys(ctimes);
    keys.sort(function(a, b) {
        var partsa = a.split('.');
        var partsb = b.split('.');
        var ta = parseInt(partsa[0]+""+partsa[1]);
        var tb = parseInt(partsb[0]+""+partsb[1]);

        if (ta > tb) {
            return true;
        }
        return false;
    });

    var ntimes = {};
    for (i = 0; i < keys.length; i++) {
        ntimes[keys[i]] = ctimes[keys[i]];
    }

    return ntimes;
}
