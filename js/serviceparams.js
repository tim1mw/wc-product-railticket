document.addEventListener("DOMContentLoaded", setupEditor);

var coachesAV = [];
var defaultcoachimg = false;
var dep_times_up_keys = {};
var dep_times_down_keys = {};
var specials_keys = {};

function setupEditor() {

    for (i in dep_times_up) {
        dep_times_up_keys[dep_times_up[i].key] = i;
    }

    for (i in dep_times_down) {
        dep_times_down_keys[dep_times_down[i].key] = i;
    }

    for (i in specials) {
        specials_keys[specials[i].id] = i;
    }
console.log(compdata);
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
    spdata.daytypes = processSelect(daytypes, compdata.daytype);
    spdata.allocateby = processSelect(allocateby, compdata.allocateby);

    sp.innerHTML = Mustache.render(sptempl, spdata);

    addActionListeners('railticket_sp_opt', 'change', setSPOpt);
}

function renderEditorCoachSets() {
    var cdata = {};
    cdata.sets = [];
    cdata.avcoaches = coachesAV;
    cdata.defaultcoachimg = defaultcoachimg;

    switch (compdata.daytype) {
        case 'pertrain':
            for (i in compdata.coachsets) {
                var parts = i.split('_');
                cdata.sets.push(processCoachSet(parts[1], compdata.coachsets[i].coachset, compdata.coachsets[i].reserve));
            }
            break;
        case 'simple':
            cdata.sets.push(processCoachSet(0, compdata.coachset, compdata.reserve));
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

    if (compdata.daytype == 'simple') {
        document.getElementById('addsetbtn').style.display = 'none';
        var dels = document.getElementsByClassName('deleteset');
        for (i=0; i<dels.length; i++) {
            dels[i].style.display = 'none';
        }
    } else {
        if (Object.keys(compdata.coachsets).length < 3) {
            var dels = document.getElementsByClassName('deleteset');
            for (i=0; i<dels.length; i++) {
                dels[i].style.display = 'none';
            }
        }
    }


}

function renderServiceAllocation() {
    var a = document.getElementById('railticket_serviceeditor_a');
    if (compdata.daytype == 'simple') {
        a.innerHTML = '';
        return;
    }

    var adata = {
        "alldeps": [],
        "sets": [],
        "specials": specials
    };

    if (specials.length == 0) {
        adata.hidespecials = 'display:none';
    }

    var total = dep_times_up.length;
    // Sanity check....
    if (dep_times_down.length > dep_times_up.length) {
        total = dep_times_down.length;
    }

    for (i=0; i<total; i++) {
        var row = {};
        row.upkey = dep_times_up[i].key;
        row.uptime = dep_times_up[i].formatted;
        row.downkey = dep_times_down[i].key;
        row.downtime = dep_times_down[i].formatted;
        adata.alldeps.push(row);
    }

    var setkeys = Object.keys(compdata.coachsets);
    for (i in setkeys) {
        var parts = setkeys[i].split('_');

        var row = {
            "value": setkeys[i],
            "name": "Set "+(parseInt(parts[1])+1)
        };
        adata.sets.push(row);
    }

    var atempl = document.getElementById('depallocation_tmpl').innerHTML;
    a.innerHTML = Mustache.render(atempl, adata);

    var elements = document.getElementsByClassName('railticketdep');
    for (i=0; i<elements.length; i++) {
        var e = elements[i];
        e.addEventListener('change', dep_set_changed);
        var parts=e.id.split('_');
        var selected = compdata[parts[0]][parts[2]];
        e.value = selected;
    }
}

function renderEditorData() {
    var cdata = {
        composition: JSON.stringify(compdata, null, 4)
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
            compdata = ndata;
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

    if (compdata.allocateby == 'bay') {
        var stats = getCoachBayStats(set);
        c.seats = stats.seats;
        c.bays = formatBays(stats.bays);
        c.style = 'display:none;';
        c.maxseats = 'n/a';
        c.priority = 'n/a';
        for (i in reserve) {
            var type = i.split('_');
            var r = {};
            r.desc = type[0]+"&nbsp;Seat&nbsp;"+baytypes[type[1]];
            r.value = reserve[i];
            r.key = i;
            r.max = stats.bays[i];
            c.reserve.push(r);
        }
    } else {
        var stats = getCoachSeatStats(set);
        c.seats = stats.seats;
        c.maxseats = stats.maxseats;
        c.priority = stats.priority;

        var r = {};
        r.desc = "Normal Seats";
        r.value = reserve['1_normal'];
        r.key = '1_normal';
        r.max = stats.seats;
        c.reserve.push(r);

        var r = {};
        r.desc = "Wheelchair Spaces";
        r.value = reserve['1_priority'];
        r.key = '1_priority';
        r.max = stats.priority;
        c.reserve.push(r);
    }

    return c;
}

function formatBays(bays) {
    var baydata = [];
    for (i in bays) {
        var parts = i.split('_');
        if (parts[1] == 'priority') {
            baydata.push(bays[i]+"x "+parts[0]+" Seat Wheelchair Bay");    
        } else {
            baydata.push(bays[i]+"x "+parts[0]+" Seat Normal Bay");  
        }
    }
    return baydata;
}

function getCoachSeatStats(coachset) {
    var stats = {
        "seats": 0,
        "bays": {},
        "maxseats": 0,
        "priority": 0
    };

    for (i in coachset) {
        var coachdata = coaches[i];
        stats.seats += parseInt(coachdata.capacity)*coachset[i];
        stats.maxseats += parseInt(coachdata.maxcapacity)*coachset[i];
        stats.priority += parseInt(coachdata.priority)*coachset[i];
    }

    return stats;
}

function getCoachBayStats(coachset) {
    var stats = {
        "seats": 0,
        "bays": {},
        "maxseats": false,
        "priority": false
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

            if (stats.bays.hasOwnProperty(baykey)) {
                stats.bays[baykey]+=bay.quantity*coachset[i];
            } else {
                stats.bays[baykey]=bay.quantity*coachset[i];
            }
            stats.seats += (bay.quantity*bay.baysize)*coachset[i];
        }
    }

    return stats;
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

    var fdata = new FormData();
    fdata.append('action', 'railticket_adminajax');
    fdata.append('function', datareq);

    request.send(fdata);
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
    if (compdata.daytype == 'simple') {
        console.log("This is a simple day...");
        return;
    }
    var parts = evt.target.id.split('_');

    var delkey = 'set_'+parts[1];
    delete compdata.coachsets[delkey];

    // Remove this set from the allocatiosn
    remove_set_from_allocation(delkey, compdata.up);
    remove_set_from_allocation(delkey, compdata.down);

    renderEditorData();
    renderEditorCoachSets();
    renderServiceAllocation();
}

function remove_set_from_allocation(delkey, allocation) {
    var setkeys = Object.keys(compdata.coachsets);
    for (i in allocation) {
        if (allocation[i] == delkey) {
            allocation[i] = setkeys[0];
        }
    }
}

function addCoachSet(evt) {
    // Sanity check....
    if (compdata.daytype == 'simple') {
        console.log("This is a simple day...");
        return;
    }

    for (i = 0; i<100; i++) {
        var setkey = "set_"+i;
        if (compdata.coachsets.hasOwnProperty(setkey)) {
            continue;
        }

        compdata.coachsets['set_'+i] = {
            "coachset": {},
            "reserve": {}
        };
        break;
    }

    var keys = Object.keys(compdata.coachsets);
    keys.sort();

    var ncoachsets = {};
    for (i = 0; i < keys.length; i++) {
        ncoachsets[keys[i]] = compdata.coachsets[keys[i]];
    }

    compdata.coachsets = ncoachsets;

    renderEditorData();
    renderEditorCoachSets();
    renderServiceAllocation();
}

function coachSetCount(evt) {
    var parts = evt.target.id.split('_');
    var setid = parts[1];
    var coach = parts[2];
    var coachset = getCoachSet(parts[1]);
    if (evt.target.value.length == 0) {
        evt.target.value = 0;
    }
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
    if (evt.target.value.length == 0) {
        evt.target.value = 0;
    }
    resset[parts[2]] = evt.target.value;
    renderEditorData();
    renderEditorCoachSets();
}

function validateReserve(setid) {
    if (compdata.allocateby == 'seat') {
        return;
    }

    var coachset = getCoachSet(setid);
    var bays = getCoachBayStats(coachset).bays;
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
    switch (compdata.daytype) {
        case 'simple':
            return compdata.coachset;
            break;
        case 'pertrain':
            return compdata.coachsets['set_'+setid].coachset;
            break;
    }

    console.log("bad day type... "+compdata.daytype);
}

function getReserve(setid) {
    switch (compdata.daytype) {
        case 'simple':
            return compdata.reserve;
            break;
        case 'pertrain':
            return compdata.coachsets['set_'+setid].reserve;
            break;
    }

    console.log("bad day type... "+compdata.daytype);
}

function setSPOpt(evt) {
    switch(evt.target.name) {
        case 'sp_daytype':
            compdata.daytype = evt.target.value;
            convertDayType();
            break;
        case 'sp_allocateby':
            compdata.allocateby = evt.target.value;
            convertAllocateType();
            break;
    }
    renderEditorCoachSets();
    renderServiceAllocation();
    renderEditorData();
}

function convertAllocateType() {
    if (compdata.allocateby == 'bay') {
        if (compdata.daytype == 'simple') {
            compdata.reserve = {};
            validateReserve(0);
        } else {
            for (i in compdata.coachsets) {
                var parts = i.split('_');
                compdata.coachsets[i].reserve = {};
                validateReserve(parts[1]);
            }
        }
    } else {
        if (compdata.daytype == 'simple') {
           compdata.reserve = {"1_normal" : 0, "1_priority" : 0};
        } else {
            for (i in compdata.coachsets) {
                compdata.coachsets[i].reserve = {"1_normal" : 0, "1_priority" : 0};
            }
        }
    }
}

function convertDayType() {
    // Detect what we have rather than relying on the daytype so we don't loose too much when switching
    if (compdata.hasOwnProperty('coachsets')) {
        if (compdata.daytype == 'simple') {
            // Convert Structure
            var sets = Object.keys(compdata.coachsets);
            compdata.coachset = compdata.coachsets[sets[0]].coachset;
            compdata.reserve = compdata.coachsets[sets[0]].reserve;
            delete compdata.coachsets;
            delete compdata.down;
            delete compdata.up;
        }
    } else {
        if (compdata.hasOwnProperty('coachset')) {
            if (compdata.daytype != 'simple') {
                // Convert Structure
                compdata.coachsets = {
                    "set_0": {
                        "coachset": compdata.coachset,
                        "reserve": compdata.reserve
                    },
                    "set_1": {
                        "coachset": {},
                        "reserve": {}
                    }
                };
                delete compdata.coachset;
                delete compdata.reserve;

                compdata.up = get_dep_times(dep_times_up);
                compdata.down = get_dep_times(dep_times_down);
            }
        }
    }
}

function get_dep_times(times) {
    var tt = {}
    var count = 0;
    var sets = Object.keys(compdata.coachsets);

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
    if (compdata.daytype != 'pertrain') {
        return;
    }

    // Do a sanity check on the dep times in the config against the ones for the time configured at load time.
    compdata.up = check_dep_times(compdata.up, dep_times_up_keys, false);
    compdata.down = check_dep_times(compdata.down, dep_times_down_keys, false);

    if (specials.length > 0) {
       if (!compdata.hasOwnProperty('specials')) {
           compdata.specials = {};
           var sets = Object.keys(compdata.coachsets);
           for (i in specials) {
               compdata.specials[specials[i].id] = sets[0];
           }

       } else {
           compdata.specials = check_dep_times(compdata.specials, specials_keys, true);
       }
    } else {
       delete compdata.specials;
    }
}

function check_dep_times(ctimes, ttimes, nosort) {
    // Remove any times that don't exist.
    for (i in ctimes) {
        if (ttimes.hasOwnProperty(i)) {
            continue;
        }
        delete ctimes[i];
    }

    var sets = Object.keys(compdata.coachsets);
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

    if (nosort) {
        return ctimes;
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

function dep_set_changed(evt) {
    var parts = evt.target.id.split('_');
    compdata[parts[0]][parts[2]] = evt.target.value;
    renderEditorData();
}
