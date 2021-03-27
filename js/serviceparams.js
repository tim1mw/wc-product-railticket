document.addEventListener("DOMContentLoaded", setupEditor);

var coachesAV = [];
var defaultcoachimg = false;

function setupEditor() {

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
        document.getElementById('deleteset_0').style.display = 'none';
    } else {
        if (Object.keys(data.coachsets).length < 3) {
            document.getElementById('deleteset_0').style.display = 'none';
            document.getElementById('deleteset_1').style.display = 'none';
        }
    }


}

function renderServiceAllocation() {

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
    console.log(evt.target.value);
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

    for (i in reserve) {
        var type = i.split('_');
        var r = {};
        r.desc = type[0]+"&nbsp;Seat&nbsp;"+baytypes[type[1]];
        r.value = reserve[i];
        r.key = i;
        c.reserve.push(r);
    }

    var stats = getCoachSetStats(set);
    c.seats = stats.seats;
    c.bays = formatBays(stats.bays);

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
            data.seats += bay.quantity*bay.baysize;
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
    console.log(evt.target.id);
    //var coachid = getCoachID(evt.target.id);
}

function addCoachSet(evt) {
    // Sanity check....
    if (data.daytype == 'simple') {
        console.log("This is a simple day...");
        return;
    }
    console.log(evt.target.id);
    Object.keys(data.coachsets).length;

    data.coachsets['set_'+Object.keys(data.coachsets).length] = {
        "coachset": {},
        "reserve": {}
    };
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
    //var bays = getCoachSetStats(setid);
    var coachset = getCoachSet(setid);
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
            data.coachset = data.coachsets['set_0'].coachset;
            data.reserve = data.coachsets['set_0'].reserve;
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
    for (i in times) {
        tt[times[i].key] = "set_"+count;
        count++;
        if (count > 1) {
            count = 0;
        }
    }

    return tt;
}
