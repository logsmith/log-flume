(function($) {

    $(document).on( 'click', '.log-flume-tabs a', function() {

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.log_flume_section').removeClass('log_flume_visible_section');
        $('.log_flume_section').eq($(this).index()).addClass('log_flume_visible_section');

        return false;
    })

})( jQuery );
