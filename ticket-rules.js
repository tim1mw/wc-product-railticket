document.addEventListener("DOMContentLoaded", setupTickets);

var lastto=-1, lastfrom=-1, lastout=-1, lastret=-1, ticketdata;
var ticketselections = new Array();
var ticketsAllocated = new Array();

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

    showTicketStages('date');
}

function railTicketAjax(datareq, callback) {
    var spinner = document.getElementById('pleasewait');
    spinner.style.display = 'block';

    var request = new XMLHttpRequest();
    request.open('POST', ajaxurl, true);
    request.onload = function () {
        //console.log(request);
        if (request.status >= 200 && request.status < 400) {
            callback(JSON.parse(request.responseText).data);
            spinner.style.display = 'none';
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

    request.send(data);
}

function setBookingDate(bdate) {
    setChosenDate("Date of Travel", bdate);

    railTicketAjax('bookable_stations', function(response) {
        enableStations('from', response);
        enableStations('to', response);
        showTicketStages('stations');
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
    showTicketStages('date');
}

function soldOut(bdate) {
   setChosenDate("Sold out", bdate);
   showTicketStages('date');
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
    railTicketAjax('bookable_trains', function(response) {
        showTimes(response['out'], 'out', "Outbound");
        showTimes(response['ret'], 'ret', "Return");
        var str = "";
        if (response['tickets'].length == 0) {
            str += "<h4>Sorry, no services can be booked on line for these choices. Please try a different selection.</h4>";
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
        showTicketStages('deptimes');
    });
}

function showTimes(times, type, header) {
    var str = "<h3>"+header+"</h3>";
    if (times.length == 0) {
        str += '<h4>No Trains</h4><input type="hidden" name="'+type+'time" value="" />';
    }
    str += '<ul>';
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

        if (typeof(tt[t].id) !== 'undefined') {
            continue;
        }
        var li = document.getElementById("li"+tt[t].id);
        if (d) {
            tt[t].checked = false;
            //li.title = "Only available with an earlier departure";
        } else {
            //li.title = "Click to book this train";
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
        //var tt = document.getElementsByClassName('journeytyperet');
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
        railTicketAjax('tickets', renderTicketSelector);  
    } else {
        showTicketStages('deptimes');
    }

}

function renderTicketSelector(response) {
    ticketdata = response;

    if (response.length == 0) {
        document.getElementById('ticket_type').style.display = "none";
        document.getElementById('ticket_numbers').style.display = "none";
        document.getElementById('ticket_summary').innerHTML = "<h3>Sorry, no tickets were found for this journey</h3>";
        showTicketStages('tickets');
        return;
    }

    var travellers = "";

    for (i in response.travellers) {
        var value = 0;
        var code = response.travellers[i].code;
        if (code in ticketselections) {
           value = ticketselections[ticketdata.travellers[i].code];
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

    showTicketStages('tickets');
}

function travellersChanged() {
   var allocation = new Array();
   allocationTotal = 0;
   ticketsAllocated = new Array();

   for (i in ticketdata.travellers) {
       var code = ticketdata.travellers[i].code;
       var v=document.getElementById("q_"+code);
       if (v.value > -1) {
           ticketselections[code] = parseInt(v.value);
           allocation[code] = parseInt(v.value);
           allocationTotal += parseInt(v.value);
       } else {
           v.value = 0;
       }
   }

   console.log(allocation);

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
       if (count > 1000) break;
   }

   console.log(ticketsAllocated);
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
           console.log(tkt.depends);
           console.log(tkt.depends.length);
           if (tkt.depends.length == 0) {
               console.log("match "+tkt.tickettype);
               return tkt;
           }

           for (di in tkt.depends) {
               if (tkt.depends[di] in ticketsAllocated) {
                   console.log("depend match "+tkt.tickettype);
                   return tkt;
               }
           }
       }
   }

   return false;
}

function showTicketStages(stage) {
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

    if (scroll == null) {
        scroll = addtocart;
    }

    scroll.scrollIntoView();
}

function skipStations() {
    return false;
}

function skipDepTimes() {
    return false;
}
