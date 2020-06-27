document.addEventListener("DOMContentLoaded", setupTickets);

var lastto=-1, lastfrom=-1;

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
        console.log(request);
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
    data.append('tickettype', document.railticketbooking['tickettype'].value);

    request.send(data);
}

function setBookingDate(bdate) {
    setChosenDate("Date of Travel", bdate);
    showTicketStages('date');
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
            stn.title = 'Click to select this station';
        } else {
            stn.disabled = true;
            stn.title = 'No tickets are available for this station';
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
        showTicketStages('stations');
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
        showTicketStages('stations');
        getDepTimes();
    }
}

function getDepTimes() {
    railTicketAjax('bookable_trains', function(response) {
         showTicketStages('deptimes');
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
                str += "<li class='railticket_hlist'><input type='radio' name='tickettype' id='tickettype"+
                    type+"' "+selected+" onclick='ticketTypeChanged(\""+type+"\")'/><label class='railticket_caplitalise' for='tickettype"+
                    type+"'>"+type+"</label></li>";
            }
            str += "</ul>";
        }
        document.getElementById('ticket_type').innerHTML = str;
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
            var tclass = "tickettype"+type;
            if (!times[index]['bookable']) {
                disabled = ' disabled ';
                tclass = '';
            }
            str += "<li><input type='radio' name='"+type+"time' id='dep"+type+index+"' class='"+tclass+"' value='"+times[index]['dep']+"' "+
                "onclick='trainTimeChanged("+index+", \""+type+"\")' "+disabled+" />"+
                "<label for='dep"+type+index+"'>"+times[index]['depdisp']+
                "<div class='railticket_arrtime'>(arrives: "+times[index]['arrdisp']+")</div></label></li>";
        }
    }
    str += "</ul>";
    document.getElementById('deptimes_data_'+type).innerHTML = str;
}

function trainTimeChanged(index, type) {
    if (type == 'ret' || document.railticketbooking['tickettype'] == 'single') {
        return;
    }

    var tt = document.getElementsByClassName('tickettyperet');
    var d = true;
    for (t in tt) { 
        if (t == index) {
            d = false;
        }
        tt[t].disabled = d;
        if (d) {
            tt[t].checked = false;
        }
    }

}

function ticketTypeChanged(type) {
    if (type == 'return') {
        var ot = document.getElementsByClassName('tickettypeout');
        for (i in ot) {
            if (ot[i].checked == true) {
                trainTimeChanged(i, 'out')
                break;
            }
        }
        return;
    }

    var tt = document.getElementsByClassName('tickettyperet');
    for (t in tt) {
        tt[t].disabled = true;
        tt[t].checked = false;
    }
}

function showTicketStages(stage) {
    var display = 'block';
    
    var datechooser = document.getElementById('datechooser');
    datechooser.style.display = display;

    if (stage == 'date') {
        display = 'none';
    }

    var stations = document.getElementById('stations');
    stations.style.display = display;

    // If there is only one bookable station for both from and to, pre-select and skip this step.
    if (skipStations()) {
        stage = 'deptimes';
    }

    if (stage == 'stations') {
        display = 'none';
    }

    var deptimes = document.getElementById('deptimes');
    deptimes.style.display = display;

    // If there is only one bookable train, pre-select the only time available and skip this step.
    if (skipDepTimes()) {
        stage = 'tickets';
    }

    if (stage == 'deptimes') {
        display = 'none';
    }

    var tickets = document.getElementById('tickets');
    tickets.style.display = display;

    if (stage == 'tickets') {
        display = 'none';
    }

    var tickets = document.getElementById('addtocart');
    addtocart.style.display = display;
}

function skipStations() {
    return false;
}

function skipDepTimes() {
    return false;
}
