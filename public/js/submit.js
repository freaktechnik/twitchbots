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
function usernameChecker() {
    if(channel.value == username.value) {
        channel.setCustomValidity("The bot user has to be different from the channel it is for");
    }
    else {
        channel.setCustomValidity("");
    }
}
channel.addEventListener("keypress", usernameChecker);
username.addEventListener("keypress", usernameChecker);
