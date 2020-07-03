document.addEventListener("DOMContentLoaded", setupTickets);

var lastto=-1, lastfrom=-1, lastout=-1, lastret=-1, ticketdata, laststage, capacityCheckRunning = false, rerunCapacityCheck = false;
var ticketSelections = {};
var ticketsAllocated = {};

function setupTickets() {
    var todaybutton = document.getElementById('todaybutton');
    if (todaybutton != null) {
        todaybutton.addEventListener('click', function () {
            setBookingDate(today);
        });
    }

    var tomorrowbutton = document.getElementById('tomorrowbutton');
    if (tomorrowbutton !=null) {
        tomorrowbutton.addEventListener('click', function () {
            setBookingDate(tomorrow);
        });
    }

    var fromstation = document.getElementsByClassName("railticket_fromstation");
    var tostation = document.getElementsByClassName("railticket_tostation");
    for (var i = 0; i < fromstation.length; i++) {
        fromstation[i].addEventListener('click', fromStationChanged);
        tostation[i].addEventListener('click', toStationChanged);
    }

    showTicketStages('date', true);
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
    data.append('outtime', document.railticketbooking['outtime'].value);
    data.append('rettime', document.railticketbooking['rettime'].value);
    data.append('journeytype', document.railticketbooking['journeytype'].value);
    data.append('ticketselections', JSON.stringify(ticketSelections));
    data.append('ticketallocated', JSON.stringify(ticketsAllocated));

    request.send(data);
}

function setBookingDate(bdate) {
    setChosenDate("Date of Travel", bdate);

    railTicketAjax('bookable_stations', true, function(response) {
        enableStations('from', response);
        enableStations('to', response);
        showTicketStages('stations', true);
    });
}

function enableStations(type, response) {
    for (stnid in response[type]) {
        var stn = document.getElementById(type+'station'+stnid);

        if (response[type][stnid]) {
            stn.disabled = false;
            //stn.title = 'Click to select this station';
        } else {
            stn.disabled = true;
            //stn.title = 'No tickets are available for this station';
        }
        stn.checked = false;
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
    ele.innerHTML = text+": "+ddate.toLocaleDateString();
    var dot = document.getElementById('dateoftravel');
    dot.value = bdate;
    lastto = -1;
    lastfrom = -1;
}

function fromStationChanged(evt) {
    var to = document.getElementById('tostation'+evt.target.value);
    if (to.checked) {
        to.checked = false;
        if (lastfrom!=-1 && lastfrom!=evt.target.value) {
            var nto = document.getElementById('tostation'+lastfrom);
            nto.checked=true;
            lastto=lastfrom;
        }
    }
    lastfrom=evt.target.value;

    if (lastto!=-1) {
        getDepTimes();
    }
}

function toStationChanged(evt) {
    var from = document.getElementById('fromstation'+evt.target.value);
    if (from.checked) {
        from.checked = false;
        if (lastto!=-1 && lastto!=evt.target.value) {
            var nfrom = document.getElementById('fromstation'+lastto);
            nfrom.checked=true;
            lastfrom=lastto;
        }  
    }
    lastto=evt.target.value;
    if (lastfrom!=-1) {
        getDepTimes();
    }
}

function getDepTimes() {
    railTicketAjax('bookable_trains', true, function(response) {
        showTimes(response['out'], 'out', "Outbound");
        showTimes(response['ret'], 'ret', "Return");
        var str = "";
        if (response['tickets'].length == 0) {
            str += "<h4>Sorry, no services can be booked on line for these choices. Please try a different selection.</h4>"+
                "<input type='hidden' name='journeytype' />";
        } else {
            str += "<ul>";
            for (index in response['tickets']) {
                var selected ="";
                if (index == response['tickets'].length-1) {
                    selected = " checked ";
                }
                var type = response['tickets'][index];
                str += "<li class='railticket_hlist'><input type='radio' name='journeytype' id='journeytype"+
                    type+"' "+selected+" onclick='journeyTypeChanged(\""+type+"\")' value='"+type+"' /><label class='railticket_caplitalise' for='journeytype"+
                    type+"'>"+type+"</label></li>";
            }
            str += "</ul>";
        }
        document.getElementById('ticket_type').innerHTML = str;
        showTicketStages('deptimes', true);
    });
}

function showTimes(times, type, header) {
    var str = "<h3>"+header+"</h3>";
    if (times.length == 0) {
        str += '<h4>No Trains</h4><input type="hidden" name="'+type+'time" value="" />';
    }
    str += '<ul>';
    var countenabled = 0;
    for (index in times) {
        if (times[index].length == 0) {
            str += "<li><div class='timespacer'></div></li>";     
        } else {
            var disabled = '';
            var tclass = "journeytype"+type;
            var title = "";

            if (!times[index]['bookable']) {
                disabled = ' disabled ';
                tclass = '';
                //title = "Sorry, this train cannot be booked online";
            }

            str += "<li id='lidep"+type+index+"' title='"+title+"'><input type='radio' name='"+type+"time' id='dep"+
                type+index+"' class='"+tclass+"' "+
                "value='"+times[index]['dep']+"' "+
                "onclick='trainTimeChanged("+index+", \""+type+"\", false)' "+disabled+" />"+
                "<label for='dep"+type+index+"'>"+times[index]['depdisp']+
                "<div class='railticket_arrtime'>(arrives: "+times[index]['arrdisp']+")</div></label></li>";
        }
    }
    str += "</ul>";
    document.getElementById('deptimes_data_'+type).innerHTML = str;
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

    var tt = document.getElementsByClassName('journeytyperet');
    var d = true;
    for (t in tt) { 
        if (t == index) {
            d = false;
        }
        tt[t].disabled = d;

        //if (typeof(tt[t].id) !== 'undefined') {
        //    continue;
        //}
        var li = document.getElementById("li"+tt[t].id);
        if (d) {
            tt[t].checked = false;
            //li.title = "Only available with an earlier departure";
        } else {
            //li.title = "Click to book this train";
        }
        if (d == false && sameservicereturn) {
            d = true;
        }
    }
    showTicketSelector();
}

function journeyTypeChanged(type) {
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

    if ( (document.railticketbooking['outtime'].value != "" && document.railticketbooking['journeytype'].value == "single") ||
        (document.railticketbooking['outtime'].value != "" &&
        document.railticketbooking['rettime'].value != "" &&
        document.railticketbooking['journeytype'].value == "return") ) {
        railTicketAjax('tickets', true, renderTicketSelector);  
    } else {
        showTicketStages('deptimes', true);
    }

}

function renderTicketSelector(response) {
    ticketdata = response;

    if (response.length == 0) {
        document.getElementById('ticket_type').style.display = "none";
        document.getElementById('ticket_numbers').style.display = "none";
        document.getElementById('ticket_summary').innerHTML = "<h3>Sorry, no tickets were found for this journey</h3>";
        showTicketStages('tickets', true);
        return;
    }

    var travellers = "";

    for (i in response.travellers) {
        var value = 0;
        var code = response.travellers[i].code;
        if (code in ticketSelections) {
           value = ticketSelections[ticketdata.travellers[i].code];
        }

        travellers += "<div class='railticket_travellers'>"+
            "<div class='railticket_travellers_numbers woocommerce'><div class='quantity'>"+
            " <input type='number' id='q_"+code+"' name='q_"+code+"' "+
            " class='input-text qty text' min='0' max='99' value='"+value+"' oninput='travellersChanged()'> "+
            " <label for='q_"+code+"'>"+response.travellers[i].name+"</label> "+
            " <span>("+response.travellers[i].description+")</span>"+
            "</div>"+
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
        if (v.value > -1) {
            ticketSelections[code] = parseInt(v.value);
            allocation[code] = parseInt(v.value);
            allocationTotal += parseInt(v.value);
        } else {
            v.value = 0;
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

    confirm.style.display = 'inline';

    var count = 0;
    while (allocationTotal > 0) {
        // See if we can find a ticket to match the travellers we have
        var tkt = matchTicket(allocation);
        // Allocate the actual ticket if we found one
        if (tkt !== false) {
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
            summary.innerHTML='<p>Something has gone badly wrong.... Please give us call or email.</p>';
            return;
        }
    }

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
        total += parseInt(tkt.price) * ticketsAllocated[i];
    }
    var supplement = 0;

    if (total < minprice) {
        supplement = minprice - total;
        str += '<tr>'+
            '<td><span>&nbsp</span></td>'+
            '<td><span>Minimum price supplement</td>'+
            '<td><span>'+formatter.format(supplement)+'</span></td>'+
            '<td></td>'+
            '</tr>';
        total = minprice
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
    if (response.ok) {
        str = "Socially distanced seating bay(s) are available for your journey:<br /><table class='railticket_travellers_table'><tr><td>Outbound</td><td>";
        for (i in response.outbays) {
            str += response.outbays[i]+"x "+i+" seat bay &nbsp&nbsp;";
        }
        str += "</td></tr>";
        var journeytype = document.railticketbooking['journeytype'].value;
        if (journeytype == "return") {
            str += "<tr><td>Return</td><td>";
            for (i in response.retbays) {
                str += response.retbays[i]+"x "+i+" seat bay&nbsp&nbsp;";
            }
            str += "</td></tr>";
        }
        str +="</table>";

        showTicketStages('addtocart', false);
    } else {
        if (response.tobig) {
            str += "Parties with more than 12 members are requested to make seperate booking, or call to make a group booking.";
        } else {
            str += "Sorry, but we do not have space for a party of this size";
        }
        showTicketStages('tickets', false);
    }
    capacitydiv.innerHTML = str+"</div>";
    capacitydiv.style.display = 'block';
}




function matchTicket(allocation) {
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
       }
   }

   return false;
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
    railTicketAjax('purchase', false, function(response) {
        if (response.ok) {
            window.location.replace('/basket');
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

    var deptimes = document.getElementById('deptimes');
    deptimes.style.display = display;

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
