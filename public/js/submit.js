var select = document.getElementById('existing-type');
var input = document.getElementById("bottype");
var channel = document.getElementById("channel");
var username = document.getElementById("username");
var description = document.getElementById("type");
var form = document.getElementById("submit-form");
var stNew = document.getElementById("new-bot");
var stCorrect = document.getElementById("correction");
var chanGroup = document.getElementById("channel-group");

// Conditionally show description field if the type is a new one.
function update() {
    if(parseInt(select.value, 10) == 0) {
        input.removeAttribute("hidden");
        description.required = true;
        description.disabled = false;
    }
    else {
        input.setAttribute("hidden", true);
        description.required = false;
        description.disabled = true;
    }
}
select.addEventListener("change", update);
update();

// Validation
function formChecker() {
    if(channel.value.toLowerCase() == username.value.toLowerCase() && stNew.checked)
        channel.setCustomValidity("The bot user has to be different from the channel it is for.");
    else
        channel.setCustomValidity("");

   return channel.validity.valid && username.validity.valid;
}
formChecker();

channel.addEventListener("keyup", formChecker);
username.addEventListener("keyup", function() {
    formChecker();
    username.setCustomValidity("");
});
form.addEventListener("submit", function(e) {
    if(!formChecker())
        e.preventDefault();

    //TODO also track errors/successes
    var _paq = _paq || [];
    _paq.push(['trackEvent', 'Submission', stNew.checked ? "Submit" : "Correct", username.value]);
});

// User checking
function checkBot(botID, cbk) {
    var url = "https://api.twitchbots.info/v2/bot/" + botID;

    var xhr = new XMLHttpRequest();

    xhr.open("HEAD", url, true);
    xhr.onreadystatechange = function(e) {
        if(xhr.readyState === 2)
            cbk(xhr.status !== 404);
    };

    xhr.send();
}

function checkTwitchUser(username, cbk) {
    var url = "https://api.twitch.tv/helix/users?login=" + username;

    var xhr = new XMLHttpRequest();

    xhr.open("GET", url, true);
    xhr.setRequestHeader('Client-ID', form.dataset.clientid);
    xhr.responseType = "json";
    xhr.onload = function(e) {
        var body = xhr.response;
        if(!body || typeof body === "string") {
            body = JSON.parse(xhr.responseText);
        }
        cbk(body.data || []);
    }

    xhr.send();
}

function validateFieldContent(field, shouldExist) {
    field.checkValidity();
    if(field.validity.valid && field.value.length) {
        if(shouldExist && !stNew.checked) {
            checkTwitchUser(field.value, function(data) {
                if(data.length) {
                    checkBot(data[0].id, function(exists) {
                        if(exists)
                            field.setCustomValidity("");
                        else
                            field.setCustomValidity("Only known bots can be corrected.");
                    });
                }
            });
        }
        else {
            checkTwitchUser(field.value, function(data) {
                if(data.length)
                    field.setCustomValidity("");
                else
                    field.setCustomValidity("Must be an existing Twitch user.");


                if(shouldExist && field.validity.valid) {
                    checkBot(data[0].id, function(exists) {
                        if(exists)
                            field.setCustomValidity("We already know about this bot.");
                        else
                            field.setCustomValidity("");
                    });
                }
            });
        }
    }
}

function stListener() {
    username.setCustomValidity("");
    for(var i = 0; i < fields.length; ++i) {
        validateFieldContent(fields[i].field, fields[i].shouldExist);
    }

    if(stNew.checked) {
        chanGroup.removeAttribute("hidden");
    }
    else {
        chanGroup.setAttribute("hidden", true);
    }
}

var fields = [
    {
        field: channel,
        shouldExist: false
    },
    {
        field: username,
        shouldExist: true
    }
];

for(var i = 0; i < fields.length; ++i) {
    fields[i].field.addEventListener("blur", validateFieldContent.bind(null, fields[i].field, fields[i].shouldExist));
    validateFieldContent(fields[i].field, fields[i].shouldExist);
}

stNew.addEventListener("change", stListener);
stCorrect.addEventListener("change", stListener);
