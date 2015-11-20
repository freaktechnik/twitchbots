document.getElementById("checkform").addEventListener("submit", function(e) {
    e.preventDefault();
    document.getElementById("checkloading").removeAttribute("hidden");
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "http://api.twitchbots.info/v1/bot/"+document.getElementById("checkuser").value);
    xhr.onreadystatechange = function() {
        if(xhr.readyState == 4) {
            document.getElementById("checkloading").setAttribute("hidden", true);
            var bot = JSON.parse(xhr.response);
            if(bot.isBot) {
                document.getElementById("botuser").removeAttribute("hidden");
                document.getElementById("realuser").setAttribute("hidden", true);
                document.querySelector("#botuser .name").textContent = bot.username;
            }
            else {
                document.getElementById("botuser").setAttribute("hidden", true);
                document.getElementById("realuser").removeAttribute("hidden");
                document.querySelector("#realuser .name").textContent = bot.username;
            }
        }
    };
    xhr.send();
}, false);
