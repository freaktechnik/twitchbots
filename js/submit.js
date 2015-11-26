var select = document.getElementById('existingtype');
var input = document.getElementById("bottype");
select.addEventListener("change", function() {
    if(select.value == 0)
        input.removeAttribute("hidden");
    else
        input.setAttribute("hidden", true);
});
