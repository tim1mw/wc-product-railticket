document.addEventListener("DOMContentLoaded", setupTickets);

var lastout=-1, lastret=-1, ticketdata, deplegs = false, laststage, capacityCheckRunning = false, rerunCapacityCheck = false;
var overridevalid = 0, overridecode = false, sameservicereturn = false, outtimemap = new Array(), hasSpecials = false, specialSelected = false, specialsData = false, journeytype = false;
var ticketSelections = {};
var ticketsAllocated = {};
const months = ["Jan", "Feb", "Mar","Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
var stationData = [];
var fromstationdata, tostationdata, journeychoicedata, journeytypedata, alljourneys;
var manual = false;


function setupTickets() {
    var dbuttons = document.getElementsByClassName("railticket_datebuttons");
    for (var i = 0; i < dbuttons.length; i++) {
        dbuttons[i].addEventListener('click', function(evt) {
            setBookingDate(evt.target.getAttribute('data'));
        });   
    }

    // Add listeners for all the stuff in the main form
    railTicketAddListener('validateOverrideIn', 'click', validateOverride);
    railTicketAddListener('confirmchoices', 'click', checkCapacity);
    railTicketAddListener('addtocart_button', 'click', cartTickets);
    railTicketAddListener('applydiscount_button', 'click', validateDiscount);
    railTicketAddListener('discountcode', 'keypress', discountCodeBox);
    railTicketAddListener('disabledrequest', 'change', disabledRequest);
    
    if (guard) {
        railTicketAddListener('createbooking', 'click', manualTickets);
        railTicketAddListener('nominimum', 'click', allocateTickets);
        railTicketAddListener('bypass', 'click', allocateTickets);
        railTicketAddListener('onlineprice', 'click', onlinePriceChanged);
    } else {
        railTicketAddListener('termsinput', 'click', termsClicked);
    }
    if (guard) {
        overridevalid = 1;
        var odiv = document.getElementById('overridevalid');
        odiv.innerHTML='<p>Guard options unlocked</p>';
    }
    if (a_dateofjourney !== false) {
        setBookingDate(a_dateofjourney);
        a_dateofjourney = false;
    } else {
        showTicketStages('date', true);
    }
}

function railTicketAddListener(id, type, func) {
    var ele = document.getElementById(id);
    if (ele != null) {
        ele.addEventListener(type, func);
    }
}

function railTicketoffset(el) {
    var rect = el.getBoundingClientRect(),
    scrollLeft = window.pageXOffset || document.documentElement.scrollLeft,
    scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    return { top: rect.top + scrollTop, left: rect.left + scrollLeft }
}

function railTicketAjax(datareq, spinner, callback) {
    if (spinner) {
        var spinnerdiv = document.getElementById('pleasewait');
        var pos = ((window.scrollY - railTicketoffset(spinnerdiv.parentElement).top) + (window.innerHeight/2)) - 50;
        spinnerdiv.style.paddingTop = pos+"px";
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
    data.append('action', 'railticket_ajax');
    data.append('function', datareq);
    data.append('dateoftravel', document.getElementById('dateoftravel').value);
    data.append('fromstation', getFormValue('fromstation'));

    if (specialSelected) {
        var times = ["s:"+getFormValue('specials')];
        data.append('times', JSON.stringify(times));
    } else if (deplegs) {
        var times = [];
        for (var l = 0; l < deplegs.length; l++) {
            var timeindex = getFormValue('dep_'+l);
            for (var i = 0; i < deplegs[l].times.length; i++) {
                if (deplegs[l].times[i].index == timeindex) {
                    times.push(deplegs[l].times[i].key);
                }
            }
            
        }
        data.append('times', JSON.stringify(times));
    }

    data.append('journeychoice', getFormValue('journeychoice'));
    data.append('ticketselections', JSON.stringify(ticketSelections));
    data.append('ticketallocated', JSON.stringify(ticketsAllocated));
    data.append('overridevalid', overridevalid);
    data.append('disabledrequest', getCBFormValue('disabledrequest'));
    data.append('notes', getFormValue('notes'));
    data.append('nominimum', getCBFormValue('nominimum'));
    data.append('onlineprice', getCBFormValue('onlineprice'));
    data.append('discountcode', getFormValue('discountcode').trim());
    data.append('manual', manual);
    request.send(data);
}

function getCBFormValue(param) {
    if (param in document.railticketbooking) {
        return document.railticketbooking[param].checked;
    }

    return false;
}

function getFormValue(param) {
    if (param in document.railticketbooking) {
        return document.railticketbooking[param].value;
    }

    return false;
}

function validateOverride() {
    var odiv = document.getElementById('overridevalid');
    if (overridecode != false &&
        document.getElementById('overrideval').value.trim() == overridecode) {
        overridevalid = true;
        odiv.innerHTML='<p>Override code valid - booking options unlocked.</p>';
        doStations();
    } else {
        overridevalid = false;
        odiv.innerHTML='<p>Override code invalid - please try again.</p>';
    }
}

function validateDiscount(evt) {
    evt.preventDefault();
    var dc = getFormValue('discountcode').trim();
    if (dc.length == 0) {
        var dv = document.getElementById('discountvalid');
        dv.innerHTML = '<p><span>No code entered.<span></p>';
        return;
    }

    railTicketAjax('validate_discount', true, function(response) {
        var dv = document.getElementById('discountvalid');
        if (response.valid) {
            dv.innerHTML = '<p><span>Discount validated: '+response.name+'<span></p>';
        } else {
            dv.innerHTML = '<p><span>Sorry, this discount is not valid.<span></p>';
        }
        ticketdata = response['tickets'];
console.log(ticketdata);
        renderTicketSelector();
    });
}

function discountCodeBox(evt) {
    if (evt.charCode == 13) {
        validateDiscount(evt);
    }
}

function setBookingDate(bdate) {
    setChosenDate("Date of Travel", bdate);
    overridevalid = false;
    doStations();
}

function doStations() {
    railTicketAjax('bookable_stations', true, function(response) {

        stationData = response['stations'];
        specialsData = response['specials'];

        var stc = document.getElementById('stations_container');
        if (response['specialonly'] == 0) {
            renderFromStations();
        }

        renderSpecials();

        overridecode = response['override'];

        if (a_station) {
            var stn = document.getElementById('fromstation'+a_station);
            a_station = false;
            stn.checked = true;
            fromStationChanged(false);
        } else {
            showTicketStages('stations', true);
        }
    });
}

function renderSpecials() {
    var div = document.getElementById('railticket_specials');
    if (specialsData == false) {
        div.innerHTML = '';
        return;
    } 

    var spltemplate = document.getElementById('specials_tmpl').innerHTML;
    var data = {specials: specialsData};

    div.innerHTML = Mustache.render(spltemplate, data);

    var spls = document.getElementsByClassName('railticket_specials');
    for (var i = 0; i < spls.length; i++) {
        spls[i].addEventListener('click', specialClicked);
    }
}

function renderFromStations() {
    var mainstns = [];
    var otherstnsleft = [];
    var otherstnsright = [];

    var next = true;

    for (i in stationData) {
        if (stationData[i].hidden == 1) {
            continue;
        }

        var stn = {};
        stn.name = stationData[i].name;
        stn.stnid = stationData[i].stnid;
        stn.description = stationData[i].description;

        if (stationData[i].closed == 1) {
            stn.closed = 'disabled';
        } else {
            stn.closed = '';
        }

        if (stationData[i].principal == 1) {
            mainstns.push(stn);
        } else {
            if (next) {
                otherstnsleft.push(stn);
                next = false;
            } else {
                otherstnsright.push(stn);
                next = true;
            }
        }
    }
 
    var stntemplate = document.getElementById('stationchoice_tmpl').innerHTML;
    var div = document.getElementById('fromstations_container');
    var data = {"mainstns": mainstns, "otherstnsleft": otherstnsleft, "otherstnsright": otherstnsright};

    if (mainstns.length == 0 || (otherstnsleft.length == 0 && otherstnsright.length == 0)) {
        data.noptitle = 'display:none';
        data.nootitle = 'display:none';
    }

    div.innerHTML = Mustache.render(stntemplate, data);

    var fromstations = document.getElementsByClassName('railticket_fromstation');
    for (var i = 0; i < fromstations.length; i++) {
        fromstations[i].addEventListener('click', fromStationChanged);
    }
}

function notBookable(bdate) {
    setChosenDate("Not available to book yet", bdate);
    showTicketStages('date', true);
}

function soldOut(bdate) {
   setChosenDate("Sold out", bdate);
   showTicketStages('date', true);
}

function setChosenDate(text, bdate) {
    var ele = document.getElementById('datechosen');
    var ddate = new Date(bdate);
    ele.innerHTML = text+": "+ddate.getDate() + "-" + months[ddate.getMonth()] + "-" + ddate.getFullYear();
    var dot = document.getElementById('dateoftravel');
    dot.value = bdate;
    lastto = -1;
    lastfrom = -1;
    var over = document.getElementById('overridecodediv');
    if (bdate == today) {
        over.style.display = 'block';
    } else {
        over.style.display = 'none';
    }
}

function fromStationChanged(evt) {
    if (specialSelected) {
        uncheckAll('railticket_specials');
        specialSelected = false;
        showTicketStages('stations', false)
    }

    railTicketAjax('journey_opts', true, function(response) {

        alljourneys = response['popular'].concat(response['other']);

        var stntemplate = document.getElementById('journeychoice_tmpl').innerHTML;
        var div = document.getElementById('journeychoice_container');

        var data = {}
        data.popular =  response['popular'];
        data.otherleft = [];
        data.otherright = [];
        var next = true;
        for (i in response['other']) {
            if (next) {
                data.otherleft.push(response['other'][i]);
                next = false;
            } else {
                data.otherright.push(response['other'][i]);
                next = true;
            }
        }

        if (data.popular.length == 0 || (data.otherleft.length == 0 && data.otherright.length == 0)) {
            data.noptitle = 'display:none';
            data.nootitle = 'display:none';
        }

        div.innerHTML = Mustache.render(stntemplate, data);

        var jcs = document.getElementsByClassName('railticket_journeychoice');
        for (var i = 0; i < jcs.length; i++) {
            jcs[i].addEventListener('click', getDepTimes);
        }

        if (a_journeychoice) {
            var ajc = document.getElementById('journeychoice'+a_journeychoice);
            ajc.checked = true;
            a_journeychoice = false;
            getDepTimes();
        } else {
            showTicketStages('journeychoice', true);
        }
    });
}

function specialClicked(evt) {
    // Clear out any existing journeychoices.
    uncheckAll('railticket_fromstation');
    var journeychoice = document.getElementById('journeychoice');
    journeychoice.style.display = 'none';
    var jcdiv = document.getElementById('journeychoice_container');
    jcdiv.innerHTML = '';
    var deptimes = document.getElementById('deptimes');
    deptimes.style.display = 'none';
    var dpdiv = document.getElementById('deptimes_data');
    dpdiv.innerHTML = '';


    specialSelected = true;
    railTicketAjax('ticket_data', true, function(response) {
        ticketdata = response;
        renderTicketSelector();
    } );
}


function uncheckAll(classname) {
    var tt = document.getElementsByClassName(classname);
    for (t in tt) {
        if (typeof(tt[t].value) == 'undefined') {
            continue;
        }
        tt[t].checked = false;
    }
}


function getDepTimes() {
    railTicketAjax('bookable_trains', true, function(response) {
        sameservicereturn = response['sameservicereturn'];
        var div = document.getElementById('deptimes_data');
        deplegs = response['legs'];
        ticketdata = response['tickets'];

        if (deplegs.length == 0) {
            div.innerHTML = '<p>No bookable services found. Sorry!</p>';
            showTicketStages('deptimes', true);
            return;
        }

        var deptemplate = document.getElementById('deplist_tmpl').innerHTML;
        var data = {};
        data.legs = [];
        for (i in deplegs) {
            for (t in deplegs[i].times) {
                if (deplegs[i].times[t].hasOwnProperty('seatsleftstr') && deplegs[i].times[t].seatsleftstr.length > 0) {
                    deplegs[i].times[t].sep = ', ';
                }
            }
            data.legs.push(Mustache.render(deptemplate, deplegs[i]));
        }

        var depctemplate = document.getElementById('depchoice_tmpl').innerHTML;
        div.innerHTML = Mustache.render(depctemplate, data);

        for (var l = 0; l < deplegs.length; l++) {
            var deps = document.getElementsByClassName('railticket_dep_'+l);
            for (var i = 0; i < deps.length; i++) {
                deps[i].addEventListener('click', depTimeChanged);
            }
        }

        if (a_deptime) {
            for (i in deplegs[0].times) {
                if (deplegs[0].times[i].key == a_deptime && deplegs[0].times[i].notbookable == false) {
                    var adep = document.getElementById('dep_0_'+deplegs[0].times[i].index);
                    adep.checked = true;
                    break;
                }
            }
            a_deptime = false;

            if (deplegs.length == 1) {
                renderTicketSelector();
                return;
            }
        }

        showTicketStages('deptimes', true);
    });
}


function depTimeChanged(evt) {
    var parts = evt.target.id.split('_');
    var changedLeg = parts[1];
    var changedIndex = parts[2];

    // If we only have one leg, or the leg that changed is the last one, we can skip checking the input disabling
    if (deplegs.length == 1) {
        renderTicketSelector();
        return;
    }

    // TODO Do I need to worry about the override code here?

    var legselections = [];
    for (var leg = 0; leg < deplegs.length; leg++) {
        legselections[leg] = parseInt(getFormValue('dep_'+leg));
        var deptime = 0;
        var offset = 0;
        if (leg > 0) {
            offset = (deplegs[leg-1].times.length - deplegs[leg].times.length);
            deptime = (legselections[leg-1].hour * 60) + legselections[leg-1].min;
        }

        for (var ti = 0; ti < deplegs[leg].times.length; ti++) {
            var time = deplegs[leg].times[ti];
            if (time.notbookable) {
                continue;
            }

            var ele = document.getElementById('dep_'+leg+'_'+time.index);

            if (leg > 0 && time.index-offset < legselections[leg-1]) {
                ele.disabled = true;
                ele.checked = false;
            } else {
                ele.disabled = false;
            }

        }
    }

    // See if we still have a valid set of dep times
    var count = 0;
    for (var leg = 0; leg < deplegs.length; leg++) {
        legselections[leg] = getFormValue('dep_'+leg);
        if (legselections[leg].length > 0) {
            count++
        }
    }

    if (count == deplegs.length) {
        setTimeout(renderTicketSelector, 500);
    } else {
        showTicketStages('deptimes', false); 
    }

}

function convert24hour(time) {
    var parts = time.split(".");
    var twelve = (parts[0] % 12) || 12;
    return twelve+"."+parts[1];
}

function renderTicketSelector() {
    if (!specialSelected) {
        var jc = getFormValue('journeychoice');
        var jcparts = jc.split('_');
        journeytype = jcparts[0];
        fromindex = getFormValue('fromstation');
        toindex = jcparts[1];

        for (si in stationData) {
            if (stationData[si].stnid == fromindex) {
                fromstationdata = stationData[si];
            }
            if (stationData[si].stnid == toindex) {
                tostationdata = stationData[si];
            }
        }

        for (i in alljourneys) {
            if (alljourneys[i].code == jc) {
                journeychoicedata = alljourneys[i];
            }
        }
    }

    var summary = document.getElementById('railticket_summary_service');
    summary.innerHTML = getSelectionSummary();

    var travellers = "";
    var nTicketSelections = {};
    for (i in ticketdata.travellers) {
        var value = '';
        var code = ticketdata.travellers[i].code;
        if (code in ticketSelections) {
            ticketdata.travellers[i].value = ticketSelections[ticketdata.travellers[i].code];
            nTicketSelections[code] = value;
        }
    }

    ticketSelections = nTicketSelections;

    var tn = document.getElementById('ticket_travellers')
    tn.style.display = "block";
    var tratemplate = document.getElementById('travellers_tmpl').innerHTML;
    tn.innerHTML = Mustache.render(tratemplate, ticketdata);
    travellersChanged();

    showTicketStages('tickets', true);
}

function getSelectionSummary() {
    // TODO Obey configured date formatting.
    var ddate = new Date(document.getElementById('dateoftravel').value);
    var tdate = ddate.getDate()+"-"+months[ddate.getMonth()]+"-"+ddate.getFullYear();

    if (specialSelected) {
        var selected = getFormValue('specials');
        var special = false;
        for (index in specialsData) {
            if (specialsData[index].id == selected) {
                special = specialsData[index];
            }
        }

        return "<div class='railticket_container'><p>"+special.name+" - "+tdate+"</p><p class='railticket_arrtime'>"+special.description+"</p></div>";
    } else {
        var index = getFormValue('dep_0');
        var dep;
        for (i in deplegs[0].times) {
            if (deplegs[0].times[i].index == index) {
                dep = deplegs[0].times[i];
                break;
            }
        }
        return "<div class='railticket_container'><p>"+tdate+". Departing from "+fromstationdata.name+" at "+dep.formatted+". "+journeychoicedata.journeydesc+".</p></div>";
    }
}

function travellersChanged() {
    setTimeout(allocateTickets, 10);
}

function onlinePriceChanged() {
    railTicketAjax('ticket_data', true, function(response) {
        ticketdata = response;
        allocateTickets();
    } );
}

function allocateTickets() {
    showTicketStages('tickets', false);
    var capacitydiv = document.getElementById('ticket_capacity');
    capacitydiv.style.display = 'none';
    var allocation = new Array();
    allocationTotal = 0;
    ticketsAllocated = {};

    for (i in ticketdata.travellers) {
        var code = ticketdata.travellers[i].code;
        var v=document.getElementById("q_"+code);

        var tnum = 0;
        if (v.value != '') {
            tnum = v.value;
        }
        if (tnum > 0) {
            ticketSelections[code] = parseInt(v.value);
            allocation[code] = parseInt(v.value);
            allocationTotal += parseInt(v.value);
        } else {
            v.value = '';
            ticketSelections[code] = 0;
        }
    }

    var minstr = "";
    if (minprice !== false) {
        minstr = "<p>All purchases are currently subject to a minimum booking price of £"+minprice+". If your booking is below £"+
           minprice+" a supplement will be added to make up the difference.</p>";
    }
    var confirm = document.getElementById('confirmchoices');
    var summary = document.getElementById('ticket_summary');
    if (allocationTotal == 0) {
        summary.innerHTML = '<h4>No Tickets Chosen</h4>'+minstr;
        confirm.style.display = 'none';
        return;
    }

    var count = 0;
    while (allocationTotal > 0) {
        // See if we can find a ticket to match the travellers we have
        var tkt = matchTicket(allocation);
        // Allocate the actual ticket if we found one
        if (tkt !== false) {
            if (tkt == 2) {
                allocationTotal --;
                summary.innerHTML='<h4>This type of ticket cannot be bought alone.</h4>';
                return;
            }
            
            for (i in allocation) {
                if (typeof tkt.composition[i] == "undefined") {
                    continue;
                }
                allocation[i] = allocation[i] - tkt.composition[i];
                allocationTotal = allocationTotal - tkt.composition[i];
            }

            if (tkt.tickettype in ticketsAllocated) {
                ticketsAllocated[tkt.tickettype] ++;
            } else {
                ticketsAllocated[tkt.tickettype] = 1;
            }
        }
        // Get out of here if stuck
        count++;
        if (count > 1000) {
            summary.innerHTML='<h4>Something has gone badly wrong.... Please give us call or email.</h4>';
            return;
        }
    }
    
    confirm.style.display = 'inline';
    // Now show off what we have
    var td = {};
    td.allocated = [];
    td.total = 0;
    for (i in ticketsAllocated) {
        var tkt = ticketdata.prices[i];
        td.total += parseFloat(tkt.price) * ticketsAllocated[i];

        var t = {};
        t.num = ticketsAllocated[i];
        t.name = tkt.name;
        t.price = formatter.format(tkt.price * ticketsAllocated[i]);
        t.image = tkt.image;
        t.description = tkt.description;

        td.allocated.push(t);
    }

    td.supplement = 0;
    if (minprice !== false) {
        if (td.total < minprice && td.total != 0) {
            var nm = getCBFormValue('nominimum');
            if (guard && nm == true) {
                td.supplement = 0;
            } else {
                td.supplement = minprice - td.total;
                td.total = minprice;
            }
        }
        td.minstr = minstr;
    } else {
        td.hidemin = 'display:none;';
    }

    td.total = formatter.format(td.total);
    td.supplement = formatter.format(td.supplement);

    var tkttemplate = document.getElementById('tickets_tmpl').innerHTML;
    summary.innerHTML = Mustache.render(tkttemplate, td);
}

const formatter = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits: 2,
    currencyDisplay: 'symbol'
})

function checkCapacity() {
    railTicketAjax('capacity', true, function(response) {
        showCapacity(response);
    });
}

function showCapacity(response) {

    var allok = true, anyerror = false, anydisablewarn = false;

    var renderdata = {};
    renderdata.legs = [];

    // TODO Account for seat only allocation here
    for (i in response.capacity) {
        var legdata = {};
        legdata.bays = [];
        switch (journeytype) {
            case 'single': legdata.name = ''; break;
            case 'return':
                if (i == 0) {
                    legdata.name = 'Outbound';
                } else {
                    legdata.name = 'Return';
                }
                break;
            case 'round':
                switch (i) {
                    case 0: legdata.name = '1st Trip'; break;
                    case 1: legdata.name = '2nd Trip'; break;
                    case 2: legdata.name = '3rd Trip'; break;
                }
                break;
        }

        var legcap = response.capacity[i];

        if (!legcap.ok) {
            allok = false;
        }
        if (legcap.error) {
            anyerror = true;
        }
        if (legcap.disablewarn) {
            anydisablewarn = true;
        }

        if (legcap.bays.length == 0) {
            if (overridevalid && !guard) {
                legdata.bays.push("Please take seats as directed by the guard");
            } else {
                legdata.bays.push("Insufficient space");
            }
        } else {
            for (bi in legcap.bays) {
                var baydata = {};
                var desc = bi.split('_');
                baydata = legcap.bays[bi]+'x '+desc[0];
                switch(desc[1]) {
                    case 'normal': baydata += ' seat bay'; break;
                    case 'priority': baydata += ' seat bay (with wheelchair space)'; break;
                }
                legdata.bays.push(baydata);
            }
        }
        renderdata.legs.push(legdata);
    }

    if (allok) {
        // TODO Account for seat only allocation here       
        renderdata.message = 'The following seating bay(s) are available for your journey:';
        
        if (getCBFormValue('disabledrequest')) {
            renderdata.disabledrequest = 'If there is more than one wheelchair user, or you need to communicate any other special requests '+
                'for the disabled traveller, please add the details in additional information box on the checkout page.';
        } else {
            renderdata.hidedisabledrequest = 'display:none;';
        }
    } else {
        renderdata.message = 'Sorry, but we do not have space for a party of this size on your chosen departure(s).';
        renderdata.hidedisabledrequest = 'display:none;';
    }

    if (anydisablewarn) {
        renderdata.warning = 'WARNING: We could not allocate a wheelchair space for all or part of your journey. '
            'You may continue with the selection shown, or try a different train.';
        renderdata.hidewarning = '';
    } else {
        renderdata.hidewarning = 'display:none;';
    }

    var capacitydiv = document.getElementById('ticket_capacity');
    var bdtemplate = document.getElementById('bays_tmpl').innerHTML;
    capacitydiv.innerHTML = Mustache.render(bdtemplate, renderdata);
    capacitydiv.style.display = 'block';
    capacitydiv.scrollIntoView(true);
    if (window.innerWidth >= 1010) {
        window.scrollBy(0, -80); 
    }

    if (allok || overridevalid || guard) {
        showTicketStages('addtocart', false);
    }
}


function matchTicket(allocation) {
   var ret = false;
   for (i in ticketdata.prices) {
       var tkt = ticketdata.prices[i];
       var matches = 0;
       var count = 0;

       for (ci in tkt.composition) {
           if (tkt.composition[ci] == 0) {
               continue;
           }
           if (allocation[ci] >= tkt.composition[ci]) {
               matches ++;
           }
           count++;
       }

       if (matches > 0 && count == matches) {
           if (tkt.depends.length == 0) {
               return tkt;
           }

           for (di in tkt.depends) {
               if (tkt.depends[di] in ticketsAllocated) {
                   return tkt;
               }
           }
           var bypass = document.getElementById('bypass');
           if (bypass != 'undefined' && bypass != null && bypass.checked) {
               return tkt;
           }
           var ret = 2;
       }
   }

   return ret;
}

function termsClicked() {
    var cart = document.getElementById('addticketstocart');
    if (document.railticketbooking['terms'].checked) {
        cart.style.display='inline';
    } else {
        cart.style.display='none';
    }
}

function manualTickets() {
    manual = true;
    submitTickets();
}

function cartTickets() {
    manual = false;
    submitTickets();
}

function submitTickets() {
    var b = document.getElementById('createbooking');
    if (b != null) {
        b.disabled = true;
        b.style.display='none';
    }

    var b = document.getElementById('addtocart_button');
    if (b != null) {
        b.disabled = true;
        b.style.display='none';
    }

    var p = document.getElementById('railticket_processing');
    p.style.display = 'block';
    
    railTicketAjax('purchase', false, function(response) {
        if (response.ok) {
            if (guard && manual) {
                var ele = document.getElementById('railticket_processing');
                ele.innerHTML = 'Booking created with reference: "'+response.id+'"'+
                    '<br />'+
                    getSelectionSummary()+
                    '<br />'+
                    '<a href="/wp-admin/admin.php?page=railticket-top-level-handle">Tap or Click here to return to service summary</a>';
            } else {
                window.location.replace('/basket');
            }
        } else {
            var errordiv = document.getElementById('railticket_error');
            if (response.duplicate) {
                errordiv.innerHTML = "<p>Sorry, but you already have a ticket selection in your shopping cart, you can only have one ticket selection per order. Please remove the existing ticket selection if you wish to create a new one, or complete the purchase for the existing one.</p>";
            } else {
                errordiv.innerHTML = "<p>Sorry, we were unable to reserve your tickets for you because somebody else has just purchased some or all of your selections. Please reload this page to try an alternative date or service.</p>";
            }
            errordiv.style.display='block';
        }
    });
}

function disabledRequest() {
    var capacitydiv = document.getElementById('ticket_capacity');
    capacitydiv.style.display = 'none';
    showTicketStages('tickets', false);
}


function showTicketStages(stage, doscroll) {
    var display = 'block';
    var scroll = null;

    var datechooser = document.getElementById('datechooser');
    datechooser.style.display = display;

    if (stage == 'date') {
        display = 'none';
        scroll = document.getElementById('datechoosetitle');
    }

    var stations = document.getElementById('stations');
    stations.style.display = display;
    var datechoosen = document.getElementById('datechooser');

    if (stage == 'stations') {
        display = 'none';
        scroll = stations;
    }

    var journeychoice = document.getElementById('journeychoice');
    journeychoice.style.display = display;

    if (stage == 'journeychoice') {
        display = 'none';
        scroll = journeychoice;
    }  

    // Deptimes aren't shown for specials
    var deptimes = document.getElementById('deptimes');
    if (specialSelected) {
        deptimes.style.display = 'none';
    } else {
        deptimes.style.display = display;
    }

    if (stage == 'deptimes') {
        display = 'none';
        scroll = deptimes;
    }

    var tickets = document.getElementById('tickets');
    tickets.style.display = display;

    if (stage == 'tickets') {
        display = 'none';
        scroll = tickets;
    }

    var tickets = document.getElementById('addtocart');
    addtocart.style.display = display;

    if (guard) {
        var cart = document.getElementById('addticketstocart');
        cart.style.display = display;
    }

    if (scroll !=null && stage != laststage && doscroll) {
        scroll.scrollIntoView(true);
        if (window.innerWidth >= 1010) {
            window.scrollBy(0, -80); 
        }
    }
    laststage = stage;
}

