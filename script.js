(function($) {

    $(document).on( 'click', '.log-flume-tabs a', function() {

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.section').removeClass('visible_section');
        $('.section').eq($(this).index()).addClass('visible_section');

        return false;
    })


    $(".trigger").click( function(e) {
        e.preventDefault()

        post_id = $(this).attr("data-post_id")
        nonce = $(this).attr("data-nonce")

        $.ajax({
            type : "post",
            dataType : "json",
            url : log_flume.ajaxurl,
            data : {action: "log_flume_transfer", post_id : post_id, nonce: nonce},
            success: function(response) {
                if(response.type == "success") {
                    // response.variable
                }
                else {
                    alert("Error :(")
                }
            }
        })

    })



})( jQuery );
