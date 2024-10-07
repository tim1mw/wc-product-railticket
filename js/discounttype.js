var RTDiscountType = {
    data: false,

    setup: function() {
        var ruleData = document.getElementById('rt_ruledata');
        RTDiscountType.data = JSON.parse(ruleData.value);
        console.log(RTDiscountType.data);

        document.getElementById('rt_discounttypeform').addEventListener('submit', RTDiscountType.submit);
        document.getElementById('rt_showrules').addEventListener('click', RTDiscountType.showRules);
    },

    submit: function(e) {
    
        //e.preventDefault();
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
