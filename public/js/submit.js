var select = document.getElementById('existing-type');
var input = document.getElementById("bottype");
function update() {
    if(parseInt(select.value, 10) == 0)
        input.removeAttribute("hidden");
    else
        input.setAttribute("hidden", true);
}
select.addEventListener("change", update);
update();

var channel = document.getElementById("channel");
var username = document.getElementById("username");
var description = document.getElementById("type");
function formChecker() {
    if(channel.value == username.value)
        channel.setCustomValidity("The bot user has to be different from the channel it is for.");
    else
        channel.setCustomValidity("");

    if(parseInt(select.value, 10) == 0 && !description.value)
        description.setCustomValidity("Please describe the new type.");
    else
        description.setCustomValidity("");
        
   return channel.validity.valid && description.validity.valid;
}

var form = document.getElementById("submit-form");

channel.addEventListener("keyup", formChecker);
username.addEventListener("keyup", formChecker);
description.addEventListener("keyup", formChecker);
form.addEventListener("submit", function(e) {
    if(!formChecker())
        e.preventDefault();
});
