document.addEventListener("DOMContentLoaded", setupTickets);

var lastto=-1, lastfrom=-1, lastout=-1, lastret=-1, ticketdata, laststage, capacityCheckRunning = false, rerunCapacityCheck = false;
var overridevalid = 0, overridecode = false, sameservicereturn = false, outtimemap = new Array(), hasSpecials = false, specialSelected = false, specialsData = false;
var ticketSelections = {};
var ticketsAllocated = {};
const months = ["Jan", "Feb", "Mar","Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];


function setupTickets() {
    var dbuttons = document.getElementsByClassName("railticket_datebuttons");
    for (var i = 0; i < dbuttons.length; i++) {
        dbuttons[i].addEventListener('click', function(evt) {
            setBookingDate(evt.target.getAttribute('data'));
        });   
    }

    var fromstation = document.getElementsByClassName("railticket_fromstation");
    var tostation = document.getElementsByClassName("railticket_tostation");
    for (var i = 0; i < fromstation.length; i++) {
        fromstation[i].addEventListener('click', fromStationChanged);
        tostation[i].addEventListener('click', toStationChanged);
    }

    // Add listeners for all the stuff in the main form
    railTicketAddListener('validateOverrideIn', 'click', validateOverride);
    railTicketAddListener('confirmchoices', 'click', checkCapacity);

    if (guard) {
        railTicketAddListener('createbooking', 'click', cartTickets);
        railTicketAddListener('nominimum', 'click', allocateTickets);
        railTicketAddListener('bypass', 'click', allocateTickets);
    } else {
        railTicketAddListener('termsinput', 'click', termsClicked);
        railTicketAddListener('addticketstocart', 'click', cartTickets);
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
    ele.addEventListener(type, func);
}

function railTicketAjax(datareq, spinner, callback) {
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
    data.append('action', 'railticket_ajax');
    data.append('function', datareq);
    data.append('dateoftravel', document.getElementById('dateoftravel').value);
    data.append('fromstation', document.railticketbooking['fromstation'].value);
    data.append('tostation', document.railticketbooking['tostation'].value);
    if (specialSelected) {
        data.append('outtime', "s:"+document.railticketbooking['specials'].value);
        data.append('rettime', "s:"+document.railticketbooking['specials'].value);
    } else {
        data.append('outtime', document.railticketbooking['outtime'].value);
        data.append('rettime', document.railticketbooking['rettime'].value);
    }
    data.append('journeytype', document.railticketbooking['journeytype'].value);
    data.append('ticketselections', JSON.stringify(ticketSelections));
    data.append('ticketallocated', JSON.stringify(ticketsAllocated));
    data.append('overridevalid', overridevalid);
    data.append('disabledrequest', document.getElementById('disabledrequest').checked);
    data.append('notes', document.railticketbooking['notes'].value);
    data.append('nominimum', document.getElementById('nominimum').checked);

    request.send(data);
}

function validateOverride() {
    var odiv = document.getElementById('overridevalid');
    if (overridecode != false &&
        document.getElementById('overrideval').value.trim() == overridecode) {
        overridevalid = 1;
        odiv.innerHTML='<p>Override code valid - booking options unlocked.</p>';
        doStations();
    } else {
        overridevalid = 0;
        odiv.innerHTML='<p>Override code invalid - please try again.</p>';
    }
}

function setBookingDate(bdate) {
    setChosenDate("Date of Travel", bdate);
    doStations();
}

function doStations() {
    railTicketAjax('bookable_stations', true, function(response) {
        enableStations('from', response, a_station);
        enableStations('to', response, a_destination);
        if (a_deptime !== false && a_deptime.indexOf("s:") > -1) {
            specialSeleted = true;
            var sp = a_deptime.split(':');
            document.getElementById('specials'+sp[1]).checked = true;
            specialClicked(sp[1], a_station, a_destination);
            a_station = false;
            a_destination = false;
            a_deptime = false;
        }

        overridecode = response['override'];
        if (a_station !== false && a_destination !== false) {
            a_station = false;
            a_destination = false;
            getDepTimes();
        } else {
            showTicketStages('stations', true);
        }
    });
}

function enableStations(type, response, defstn) {
    for (stnid in response[type]) {
        var stn = document.getElementById(type+'station'+stnid);

        if (response[type][stnid]) {
            stn.disabled = false;
            //stn.title = 'Click to select this station';
        } else {
            stn.disabled = true;
            //stn.title = 'No tickets are available for this station';
        }
        if (defstn !== false && defstn == stnid && stn.disabled == false) {
            stn.checked = true;
        } else {
            stn.checked = false;
        }
    }

    if (response['specials']) {
        specialsData = response['specials'];
        hasSpecials = true;
        str = "<h3>Or choose one of today's specials:</h3><ul>";
        for (index in response['specials']) {
           var special = response['specials'][index];
           var selected = '';

           str += "<li class='railticket_hlist'><input class='railticket_specials' type='radio' name='specials' id='specials"+
               special.id+"' "+selected+" value='"+special.id+"' onclick='specialClicked("+special.id+", \""+special.fromstation+"\", \""+special.tostation+"\")' />"+
               "<label class='railticket_caplitalise' for='specials"+
               special.id+"'>"+special.name+"<div class='railticket_arrtime'>"+special.description+"</div></label></li>";
        }
        str += "</ul>";
        document.getElementById('railticket_specials').innerHTML = str;
    } else {
        hasSpecials = false;
        document.getElementById('railticket_specials').innerHTML = '';
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

    var to = document.getElementById('tostation'+evt.target.value);
    var from = document.getElementById('fromstation'+evt.target.value);

    if (to.checked) {
        to.checked = false;
        if (lastfrom!=-1 && lastfrom!=evt.target.value) {
            var nto = document.getElementById('tostation'+lastfrom);
            nto.checked=true;
            lastto=lastfrom;
        }
    }

    lastfrom=evt.target.value;
    lastout=-1;
    lastret=-1;
    if (specialSelected) {
        uncheckAll('railticket_specials');
        specialSelected = false;
        showTicketStages('stations', false)
    }

    if (document.railticketbooking['fromstation'].value != '' && 
        document.railticketbooking['tostation'].value != '' &&
        document.railticketbooking['fromstation'].value != 'undefined' && 
        document.railticketbooking['tostation'].value != 'undefined')
    {
        getDepTimes();
    }
}

function toStationChanged(evt) {

    var from = document.getElementById('fromstation'+evt.target.value);
    var to = document.getElementById('tostation'+evt.target.value);

    if (from.checked) {
        from.checked = false;
        if (lastto!=-1 && lastto!=evt.target.value) {
            var nfrom = document.getElementById('fromstation'+lastto);
            nfrom.checked=true;
            lastfrom=lastto;
        }  
    }

    lastto=evt.target.value;
    lastout=-1;
    lastret=-1;
    if (specialSelected) {
        uncheckAll('railticket_specials');
        specialSelected = false;
        showTicketStages('stations', false)
    }

    if (document.railticketbooking['fromstation'].value != '' && 
        document.railticketbooking['tostation'].value != '' &&
        document.railticketbooking['fromstation'].value != 'undefined' && 
        document.railticketbooking['tostation'].value != 'undefined')
    {
        getDepTimes();
    }
}

function specialClicked(index, fromstation, tostation) {
    specialSelected = true;
    document.getElementById("fromstation"+fromstation).checked = true;
    document.getElementById("tostation"+tostation).checked = true;
    
    railTicketAjax('tickets', true, renderTicketSelector);
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

        if (a_deptime !== false && a_deptime.indexOf("s:") > -1) {
            showTimes(response['out'], 'out', "Outbound", false);
        } else {
            showTimes(response['out'], 'out', "Outbound", a_deptime);
        }
        showTimes(response['ret'], 'ret', "Return", false);

        var str = "";
        if (response['tickets'].length == 0) {
            str += "<h4>Sorry, no services can be booked on line for these choices. Please try a different selection.</h4>"+
                "<input type='hidden' name='journeytype' />";
            document.getElementById('ticket_type').innerHTML = str;
        } else {
            sameservicereturn = response['sameservicereturn'];
            if (sameservicereturn) {
                str += "  <p class='railticket_help'>Services today require you to return on the same train you started on.</p>";
            }
            str += "<ul>";
            for (index in response['tickets']) {
                var selected ="";
                if (index == response['tickets'].length-1) {
                    selected = " checked ";
                }
                var type = response['tickets'][index];
                str += "<li class='railticket_hlist'><input type='radio' name='journeytype' id='journeytype"+
                    type+"' "+selected+" value='"+type+"' /><label class='railticket_caplitalise' for='journeytype"+
                    type+"'>"+type+"</label></li>";
            }
            str += "</ul>";

            document.getElementById('ticket_type').innerHTML = str;
            for (index in response['tickets']) {
                var type = response['tickets'][index];
                railTicketAddListener('journeytype'+type, 'click', journeyTypeChanged);
            }
        }
        if (a_deptime !== false) {
            updateTimesList();
        }

        a_deptime = false;
        showTicketStages('deptimes', true);
    });
}


function showTimes(times, type, header, selecttime) {
    var str = "<h3>"+header+"</h3>";
    if (times.length == 0) {
        str += '<h4>No Trains</h4><input type="hidden" name="'+type+'time" value="" />';
    }
    str += '<ul>';
    var countenabled = 0;

    var nowdate = new Date();
    var nowtotal = (nowdate.getHours()*60)+nowdate.getMinutes();
    var selected = false;
    if (type == 'out') {
        outtimemap = new Array();
    }
    for (index in times) {
        if (times[index].length == 0) {
            str += "<li><div class='timespacer'></div></li>";     
        } else {
            var disabled = '';
            var tclass = "journeytype"+type;
            var title = "";

            if (!times[index]['bookable']) {
                disabled = ' disabled ';
                //title = "Sorry, this train cannot be booked online";
            }
            var lateclass = "";

            if (document.getElementById('dateoftravel').value == today) {
                var depparts = times[index]['dep'].split(".");
                var deptotal = (parseInt(depparts[0])*60)+parseInt(depparts[1]);
                if (nowtotal > deptotal && disabled == '') {
                    lateclass = "class='railticket_late'";
                }
            }

            var checked = "";
            if (times[index]['dep'] == selecttime && times[index]['bookable']) {
                checked = " checked ";
                selected = index;
            }

            str += "<li id='lidep"+type+index+"' title='"+title+"'><input type='radio' name='"+type+"time' id='dep"+
                type+index+"' class='"+tclass+"' "+
                "value='"+times[index]['dep']+"' "+
                "onclick='trainTimeChanged("+index+", \""+type+"\", false)' "+disabled+" "+checked+" />"+
                "<label "+lateclass+" for='dep"+type+index+"'>"+times[index]['depdisp']+
                "<div class='railticket_arrtime'>(arrives: "+times[index]['arrdisp']+")</div></label></li>";
            if (type == 'out') {
                outtimemap[times[index]['dep']] = times[index]['arr'];
            }
        }
    }

    str += "</ul>";
    document.getElementById('deptimes_data_'+type).innerHTML = str;
    return index;
}

function trainTimeChanged(index, type, skip) {
    if (type == 'out') {
        if (index == lastout) {
            return;
        } else {
            lastout = index;
        }
    }

    if (type == 'ret') {
        if  (index == lastret) {
            return;
        } else {
            lastret = index;
        }
    }

    var journeytype = document.railticketbooking['journeytype'].value;
    if (type == 'ret' || journeytype == 'single') {
        showTicketSelector();
        return;
    }
    updateTimesList();

    if (type == 'ret') {
        if (document.railticketbooking['outtime'].value == '' || document.railticketbooking['outtime'].value == 'undefined') {
            lastout = -1;
        }
    }
    if (type == 'out') {
        if (document.railticketbooking['rettime'].value == '' || document.railticketbooking['rettime'].value == 'undefined') {
            lastret = -1;
        }
    }

    showTicketSelector();
}

function updateTimesList() {
    var outdeptime = document.railticketbooking['outtime'].value;
    var outarrtime = outtimemap[outdeptime];

    var outtime = convertTime(outarrtime);
    var tt = document.getElementsByClassName('journeytyperet');
    var ct = 0;

    for (t in tt) {
        if (typeof(tt[t].value) == 'undefined') {
            continue;
        }
        if (sameservicereturn && ct > 0) {
            tt[t].disabled = true;
            tt[t].checked = false;
            continue;
        }

        var tval = convertTime(tt[t].value);
        if (tval > outtime) {
            tt[t].disabled = false;
            ct++;
        } else {
            tt[t].disabled = true;
            tt[t].checked = false;
        }
    }
}

function convertTime(time) {
   var parts = time.split(".");
   return (parseInt(parts[0])*60)+parseInt(parts[1]);
}

function journeyTypeChanged(evt) {
    type = evt.target.value;

    if (type == 'return') {
        var ot = document.getElementsByClassName('journeytypeout');
        lastout = -1;
        for (i in ot) {
            if (ot[i].checked == true) {
                trainTimeChanged(i, 'out', true)
                break;
            }
        }
    } else {
        lastret = -1;
        var tt = document.getElementsByClassName('journeytyperet');
        for (t in tt) {
            //if (typeof(tt[t].id) !== 'undefined') {
            //    continue;
            //}
            tt[t].disabled = true;
            tt[t].checked = false;
            var li = document.getElementById("li"+tt[t].id);
            //li.title = "Return tickets only";
        }
    }
    showTicketSelector();
}

function showTicketSelector() {

    if ( (document.railticketbooking['outtime'].value != "" && 
          document.railticketbooking['outtime'].value != "undefined" && 
          document.railticketbooking['journeytype'].value == "single") ||
        (document.railticketbooking['outtime'].value != "" &&
          document.railticketbooking['rettime'].value != "" &&
          document.railticketbooking['outtime'].value != "undefined" &&
          document.railticketbooking['rettime'].value != "undefined" &&
          document.railticketbooking['journeytype'].value == "return") ) {
        railTicketAjax('tickets', true, renderTicketSelector);  
    } else {
        showTicketStages('deptimes', true);
    }

}

function convert24hour(time) {
    var parts = time.split(".");
    var twelve = (parts[0] % 12) || 12;
    return twelve+"."+parts[1];
}

function renderTicketSelector(response) {
    ticketdata = response;
    var summary = document.getElementById('railticket_summary_service');
    var ddate = new Date(document.getElementById('dateoftravel').value);
    var tdate = ddate.getDate()+"-"+months[ddate.getMonth()]+"-"+ddate.getFullYear();
    if (specialSelected) {
        var special = false;
        for (index in specialsData) {
            if (specialsData[index].id == document.railticketbooking['specials'].value) {
                special = specialsData[index];
            }
        }

        summary.innerHTML = "<p>"+special.name+" - "+tdate+"</p><p class='railticket_arrtime'>"+special.description+"</p>";
    } else {
        var fromindex = document.railticketbooking['fromstation'].value;
        var toindex = document.railticketbooking['tostation'].value;
        var f='', t='';
        for (si in stationData) {
            if (stationData[si].id == fromindex) {
                f = stationData[si].name;
            }
            if (stationData[si].id == toindex) {
                t = stationData[si].name;
            }
        }

        document.railticketbooking['rettime'].value;
        var str = "<p>"+tdate+", Outbound:"+convert24hour(document.railticketbooking['outtime'].value)+" from "+f;
        if (document.railticketbooking['journeytype'].value == 'return') {
            str += ", Return: "+convert24hour(document.railticketbooking['rettime'].value+" from "+t);
        }
        str += "</p>";

        summary.innerHTML = str;
    }

    if (response.length == 0) {
        document.getElementById('ticket_type').style.display = "none";
        document.getElementById('ticket_numbers').style.display = "none";
        document.getElementById('ticket_summary').innerHTML = "<h3>Sorry, no tickets were found for this journey</h3>";
        showTicketStages('tickets', true);
        return;
    }

    var travellers = "";
    for (i in response.travellers) {
        var value = '';
        var code = response.travellers[i].code;
        if (code in ticketSelections) {
           value = ticketSelections[ticketdata.travellers[i].code];
        }

        travellers += "<div class='railticket_travellers'>"+
            "<div class='railticket_travellers_numbers woocommerce'><div class='quantity'>"+
            " <input type='number' id='q_"+code+"' name='q_"+code+"' "+
            " class='input-text qty text' min='0' max='99' value='"+value+"' oninput='travellersChanged()'> "+
            " <label for='q_"+code+"'>"+response.travellers[i].name+"</label> ";
        if (response.travellers[i].description.length > 0) {
            travellers += " <span>("+response.travellers[i].description+")</span>";
        }
        travellers += "</div>"+
            "</div>";
    }

    var tn = document.getElementById('ticket_travellers')
    tn.style.display = "block";
    tn.innerHTML = travellers;
    travellersChanged();

    showTicketStages('tickets', true);
}

function travellersChanged() {
    setTimeout(allocateTickets, 10);
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
    var str = "<div class='railticket_travellers_table_container'><h4>My Tickets</h4>"+
        minstr+
        "<table class='railticket_travellers_table'>";
    var total = 0;
    for (i in ticketsAllocated) {
        var tkt = ticketdata.prices[i];
        str += '<tr>'+
            '<td><span>'+ticketsAllocated[i]+'&nbspx</span></td>'+
            '<td><span>'+tkt.name+'</span><br />'+tkt.description+'</td>'+
            '<td><span>'+formatter.format(tkt.price * ticketsAllocated[i])+'</span></td>'+
            '<td><img src="'+tkt.image+'" class="railticket_image" /></td>'+
            '</tr>';
        total += parseFloat(tkt.price) * ticketsAllocated[i];
    }
    var supplement = 0;

    if (total < minprice && total != 0) {
        var nm = document.getElementById('nominimum');
        if (guard && nm.checked == true) {
            supplement = 0;
        } else {
            supplement = minprice - total;
            total = minprice;
        }
        
        str += '<tr>'+
            '<td><span>&nbsp</span></td>'+
            '<td><span>Minimum price supplement</td>'+
            '<td><span>'+formatter.format(supplement)+'</span></td>'+
            '<td></td>'+
            '</tr>';
    }

    str += "<tr><td></td><td><span>Total</span></td><td><span>"+formatter.format(total)+"</span></td><td></td></tr>";
    str += '</table></div>';
    summary.innerHTML = str;
}

const formatter = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
    minimumFractionDigits: 2
})

function checkCapacity() {
    railTicketAjax('capacity', true, function(response) {
        showCapacity(response);
    });
}

function showCapacity(response) {
    var capacitydiv = document.getElementById('ticket_capacity');
    var str = "<div class='railticket_travellers_table_container' >";
    if (response.ok || guard) {
        str = "Socially distanced seating bay(s) are available for your journey:<br /><table class='railticket_travellers_table'><tr><td>Outbound</td><td>";
        for (i in response.outbays) {
            var desc = i.split('_');
            str += response.outbays[i]+"x "+desc[0]+" seat bay";
            if (desc[1] == 'priority') {
                str += ' (with disabled space)';   
            }
            str += '<br />';
        }
        str += "</td></tr>";
        var journeytype = document.railticketbooking['journeytype'].value;
        if (journeytype == "return") {
            str += "<tr><td>Return</td><td>";
            for (i in response.retbays) {
                var desc = i.split('_');
                str += response.retbays[i]+"x "+desc[0]+" seat bay";
                if (desc[1] == 'priority') {
                    str += ' (with disabled space)';   
                }
                str += '<br />';
            }
            str += "</td></tr>";
        }
        str +="</table>";

        if (response.disablewarn) {
            str += "<p class='railticketwarn'>WARNING: We could not allocate wheelchair space for all or part of your journey. "+
                "You may continue with the selection shown, or try a different train.</p>";
        }

        showTicketStages('addtocart', false);
    } else {
        if (overridevalid) {
            str = "Please take seats as directed by the guard";
            showTicketStages('addtocart', false);
        } else {
            if (response.tobig) {
                str += "Parties with more than 12 members are requested to make seperate booking, or call to make a group booking.";
            } else {
                str += "Sorry, but we do not have space for a party of this size";
            }
            showTicketStages('tickets', false);
        }
    }
    capacitydiv.innerHTML = str+"</div>";
    capacitydiv.style.display = 'block';
    capacitydiv.scrollIntoView(true);
    if (window.innerWidth >= 1010) {
        window.scrollBy(0, -80); 
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

function cartTickets() {
    if (guard) {
       var b = document.getElementById('createbooking');
       b.disabled = true;
       b.style.display='none';
    } else {
       var b = document.getElementById('addtocart_button');
       b.disabled = true;
       b.style.display='none';
    }

    var p = document.getElementById('railticket_processing');
    p.style.display = 'block';
    
    railTicketAjax('purchase', false, function(response) {
        if (response.ok) {
            if (guard) {
                //window.location.replace('/wp-admin/admin.php?page=railticket-top-level-handle');
                var ele = document.getElementById('railticket_processing');
                ele.innerHTML = 'Booking created with reference: "'+response.id+'"'+
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

    // If there is only one bookable station for both from and to, pre-select and skip this step.
    if (skipStations()) {
        stage = 'deptimes';
    }

    if (stage == 'stations') {
        display = 'none';
        scroll = datechoosen;
    }

    // Deptimes aren't shown for specials
    var deptimes = document.getElementById('deptimes');
    if (specialSelected) {
        deptimes.style.display = 'none';
    } else {
        deptimes.style.display = display;
    }
    // If there is only one bookable train, pre-select the only time available and skip this step.
    if (skipDepTimes()) {
        stage = 'tickets';
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

    if (scroll !=null && stage != laststage && doscroll) {
        scroll.scrollIntoView(true);
        if (window.innerWidth >= 1010) {
            window.scrollBy(0, -80); 
        }
    }
    laststage = stage;
}

function skipStations() {
    return false;
}

function skipDepTimes() {
    return false;
}
