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

    var request = new XMLHttpRequest();
    request.open('POST', ajaxurl, true);
    request.onload = function () {
        console.log(request);
        if (request.status >= 200 && request.status < 400) {
            callback(JSON.parse(request.responseText).data);
        }
    };

    var data = new FormData();
    data.append('action', 'railticket_ajax');
    data.append('function', datareq);
    data.append('dateoftravel', document.getElementById('dateoftravel').value);
    data.append('fromstation', document.railticketbooking['fromstation']);
    data.append('tostation', document.railticketbooking['tostation']);
    console.log("railTicketAjax "+document.getElementById('dateoftravel').value);
    request.send(data);
}

function setBookingDate(bdate) {
    setChosenDate("Date of Travel", bdate);
    showTicketStages('stations');
    railTicketAjax('bookable_stations', function(response) {
        enableStations('from', response);
        enableStations('to', response);
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
        showTicketStages('deptimes');
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
        showTicketStages('deptimes');
        getDepTimes();
    }
}

function getDepTimes() {
    railTicketAjax('bookable_trains', function(response) {
        showTimes(response['out'], 'out', "Outbound");
        showTimes(response['ret'], 'ret', "Return");
  
        var str = "<ul>";
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
        document.getElementById('ticket_type').innerHTML = str;
    });
}

function showTimes(times, type, header) {
    var str = "<h3>"+header+"</h3>";
    if (times.length == 0) {
        str += '<h4>No Trains</h4>';
    }
    str += '<ul>';
    for (index in times) {
        str += "<li><input type='radio' name='"+type+"time' id='dep"+type+index+"' class='tickettype"+type+"' /><label for='dep"+type+index+"'>"+times[index]['dep']+"</label></li>";
    }
    str += "</ul>";
    document.getElementById('deptimes_data_'+type).innerHTML = str;
}

function ticketTypeChanged(type) {
    var d = false;
    if (type == 'single') {
        d = true;
    }

    var tt = document.getElementsByClassName('tickettyperet');
    for (t in tt) {
        tt[t].disabled = d;
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
