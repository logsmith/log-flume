(function($) {

    $(document).on( 'click', '.nav-tab-wrapper a', function() {
        $('section').hide();
        $('section').eq($(this).index()).show();
        return false;
    })

})( jQuery );
