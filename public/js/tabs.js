require(['bootstrap', 'jquery'], function() {
    $('.nav-tabs a').click(function(e) {
        e.preventDefault();
        $(this).tab('show');
        window.location.hash = $(this).prop('href');
    });
    if(window.location.hash) {
        $('.nav-tabs a[href="' + window.location.hash + '"]').tab('show');
    }
});
