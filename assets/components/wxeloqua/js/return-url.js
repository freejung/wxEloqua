function getUrlVars()
{
//simple function to read get parameters
    var vars = [], hash;
    var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
    for(var i = 0; i < hashes.length; i++)
    {
        hash = hashes[i].split('=');
        vars.push(hash[0]);
        vars[hash[0]] = hash[1];
    }
    return vars;
}
function makeDoubleDelegate(function1, function2) {
    return function() {
        if (function1)
            function1();
        if (function2)
            function2();
    }
}
function setReturnURL() {
	var patt1 = /^[0-9]+$/;
	var patt2 = /^[\w\s%]+$/;
    if(getUrlVars()['returnStepID']) {
        var returnStepID = getUrlVars()['returnStepID'].match(patt1);
        oFormObject = document.forms['modx-login-form'];
        var returnUrl = '/webinar-config.html?StepID='+returnStepID;
        if(getUrlVars()['returnStepType']) {
        	var returnStepType = getUrlVars()['returnStepType'].match(patt2);
        	returnUrl = returnUrl+'&stepType='+returnStepType;
        }
        oFormObject.elements["returnUrl"].value = returnUrl;
    }
}

window.onload = makeDoubleDelegate(window.onload, setReturnURL() );
