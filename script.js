(function($) {

    $(document).on( 'click', '.nav-tab-wrapper a', function() {
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.section').hide();
        $('.section').eq($(this).index()).show();
        return false;
    })

})( jQuery );
