(function($) {

    $(document).on( 'click', '.nav-tab-wrapper a', function() {

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.section').removeClass('visible_section');
        $('.section').eq($(this).index()).addClass('visible_section');
        
        return false;
    })

})( jQuery );
