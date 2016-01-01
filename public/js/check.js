document.getElementById("checkform").addEventListener("submit", function(e) {
    e.preventDefault();
    document.getElementById("checkloading").removeAttribute("hidden");
    var xhr = new XMLHttpRequest();
    var username = document.getElementById("checkuser").value;
    xhr.open("GET", "//api.twitchbots.info/v1/bot/"+username);
    xhr.onreadystatechange = function() {
        if(xhr.readyState == 4) {
            document.getElementById("checkloading").setAttribute("hidden", true);
            if(xhr.status == 200) {
                var bot = JSON.parse(xhr.response);
                document.getElementById("botuser").removeAttribute("hidden");
                document.getElementById("realuser").setAttribute("hidden", true);
                document.querySelector("#botuser .name").textContent = bot.username;
            }
            else {
                document.getElementById("botuser").setAttribute("hidden", true);
                document.getElementById("realuser").removeAttribute("hidden");
                document.querySelector("#realuser .name").textContent = username;
            }
        }
    };
    xhr.send();
}, false);
