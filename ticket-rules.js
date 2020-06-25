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

    console.log(ajaxurl);
    var request = new XMLHttpRequest();
	request.open('POST', ajaxurl, true);
    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

	request.onload = function () {
		if (request.status >= 200 && request.status < 400) {
			var result = request.responseText;
			console.log(result);
		}
	};

	var whatever = 10;
	request.send('action=my_action&whatever=xxx');
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
    }
}




function setBookingDate(bdate) {
    setChosenDate("Date of Travel", bdate);
    showTicketStages('stations');
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
