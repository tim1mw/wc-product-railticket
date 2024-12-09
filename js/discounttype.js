var RTDiscountType = {
    data: false,

    setup: function() {
        var ruleData = document.getElementById('rt_ruledata');
        RTDiscountType.data = JSON.parse(ruleData.value);
        console.log(RTDiscountType.data);

        document.getElementById('rt_discounttypeform').addEventListener('submit', RTDiscountType.submit);
        document.getElementById('rt_showrules').addEventListener('click', RTDiscountType.showRules);

        RTDiscountType.render();
    },

    submit: function(e) {
    
        //e.preventDefault();
    },

    render: function() {
        var c = document.getElementById('rt_rules');
        var ctempl = document.getElementById('discounttypes_tmpl').innerHTML;

        var discounts = [];
        for (i in RTDiscountType.data.discounts) {
            RTDiscountType.data.discounts[i].key = i;
            discounts.push(RTDiscountType.data.discounts[i]);
        }

        c.innerHTML = Mustache.render(ctempl, {
            "tickettypes": tickettypes,
            "excludes": RTDiscountType.data.excludes,
            "discounts": discounts
        });
    },

    showRules: function(e) {
        var rules = document.getElementById("rt_ruledata");
        if (e.target.checked) {
            rules.style.display = 'block';
        } else {
            rules.style.display = 'none';
        }
    }
};

document.addEventListener("DOMContentLoaded", RTDiscountType.setup);
