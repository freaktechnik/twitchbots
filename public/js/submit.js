var select = document.getElementById('existing-type');
var input = document.getElementById("bottype");
var channel = document.getElementById("channel");
var username = document.getElementById("username");
var description = document.getElementById("type");
var form = document.getElementById("submit-form");

// Conditionally show description field if the type is a new one.
function update() {
    if(parseInt(select.value, 10) == 0) {
        input.removeAttribute("hidden");
        description.required = true;
    }
    else {
        input.setAttribute("hidden", true);
        description.required = false;
    }
}
select.addEventListener("change", update);
update();

// Validation
function formChecker() {
    if(channel.value == username.value)
        channel.setCustomValidity("The bot user has to be different from the channel it is for.");
    else
        channel.setCustomValidity("");

   return channel.validity.valid && username.validity.valid;
}
formChecker();

channel.addEventListener("keyup", formChecker);
username.addEventListener("keyup", formChecker);
form.addEventListener("submit", function(e) {
    if(!formChecker())
        e.preventDefault();
});

// Twitch user checking
function checkUser(username, cbk) {
    var url = "https://api.twitch.tv/kraken/users/"+username;

    var xhr = new XMLHttpRequest();

    xhr.open("HEAD", url, true);
    xhr.onreadystatechange = function(e) {
        if(xhr.readyState == 2) {
            // Only reject if the status is 404 not found.
            cbk(xhr.status !== 404);
        }
    };

    xhr.send();
}

function validateFieldContent(e) {
    var field = e.target;
    field.checkValidity();
    if(field.validity.valid && field.value.length) {
        checkUser(field.value, function(exists) {
            if(exists)
                field.setCustomValidity("");
            else
                field.setCustomValidity("Must be an existing Twitch user.");
        });
    }
}

channel.addEventListener("blur", validateFieldContent);
username.addEventListener("blur", validateFieldContent);
validateFieldContent({ target: channel });
validateFieldContent({ target: username });
