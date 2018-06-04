$(document).ready(function() {
    $('.nav-tabs a').click(function(e) {
        e.preventDefault();
        $(this).tab('show');
        if(window.location.hash) {
            window.location.hash = '';
        }
    });
    if(window.location.hash) {
        $('.nav-tabs a[href="' + window.location.hash + '"]').tab('show');
    }
});
