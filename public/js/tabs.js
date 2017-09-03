require(['bootstrap', 'jquery'], function() {
    $('.nav-tabs a').click(function(e) {
        e.preventDefault();
        $(this).tab('show');
    });
    if(navigator.location.hash) {
        $('.nav-tabs a[href="' + navigator.location.hash + '"]').tab('show');
    }
});
