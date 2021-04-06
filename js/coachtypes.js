document.addEventListener("DOMContentLoaded", setupEditor);

var compositions = [];

function setupEditor() {
    var baytmpl = document.getElementById('composition_tmpl').innerHTML;
    var comps = document.getElementsByClassName('railticket-coachcomp');
    for (i=0; i<comps.length; i++) {
        var id = comps[i].name.split('_')[1];
        compositions[id] = JSON.parse(comps[i].value);
        
        var data = { bays: [], id: id };
        for (ci in compositions[id]) {
            var bay = {
                byid: ci,
                baysize: compositions[id][ci].baysize,
                quantity: compositions[id][ci].quantity
            }
            if (compositions[id][ci].priority) {
                bay.priority = 'checked';
            }
            data.bays.push(bay);
        }

        var div = document.getElementById('railticket-'+comps[i].name);
        div.innerHTML = Mustache.render(baytmpl, data);
    }

    addActionListeners('railticket-bay', 'change', valueChanged);
    addActionListeners('railticket-baycb', 'click', cbValueChanged);
    addActionListeners('railticket-addbtn', 'click', addClicked);
    sanityCheck();
}

function sanityCheck() {
    var insane = false;
    for (id in compositions) {
        var div = document.getElementById('bayerror_'+id);
        for (i in compositions[id]) {
            if (countBays(compositions[id], compositions[id][i]) > 1) {
                div.innerHTML = 'WARNING: Matching Bay Types';
                insane = true;
                break;
            }
            div.innerHTML = '';
        }
    }
    var up = document.getElementById('railticket-update-coach');
    up.disabled = insane;
}

function countBays(comp, bay) {
    var count = 0;
    for (i in comp) {
        if (comp[i].baysize == bay.baysize && comp[i].priority == bay.priority) {
            count++;
        }
    }

    return count;
}

function addActionListeners(clss, event, method) {
    var d = document.getElementsByClassName(clss);
    for (i=0; i<d.length; i++) {
       d[i].addEventListener(event, method);
    } 
}

function valueChanged(evt) {
    var parts = evt.target.name.split('_');
    var ta = document.getElementById('composition_'+parts[1]);

    if (evt.target.value > 0) {
        compositions[parts[1]][parts[2]][parts[0]] = evt.target.value;
        ta.value = JSON.stringify(compositions[parts[1]]);
        sanityCheck();
    } else {
        var ndata = [];
        for (i in compositions[parts[1]]) {
            if (i == parts[2]) {
                continue;
            }
            ndata.push(compositions[parts[1]][i]);
        }
        compositions[parts[1]] = ndata;
        ta.value = JSON.stringify(compositions[parts[1]]);
        setupEditor();
    }
}

function cbValueChanged(evt) {
    var parts = evt.target.name.split('_');
    var ta = document.getElementById('composition_'+parts[1]);
    if (evt.target.checked) {
        compositions[parts[1]][parts[2]].priority = true;
    } else {
        compositions[parts[1]][parts[2]].priority = false;
    }
    ta.value = JSON.stringify(compositions[parts[1]]);
    sanityCheck();
}

function addClicked(evt) {
    var parts = evt.target.name.split('_');
    compositions[parts[1]].push({
        baysize: 1,
        quantity: 1,
        priority: false
    });
    var ta = document.getElementById('composition_'+parts[1]);
    ta.value = JSON.stringify(compositions[parts[1]]);
    setupEditor();
}
