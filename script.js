(function($) {

    $(document).on( 'click', '.log-flume-tabs a', function() {

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.log_flume_section').removeClass('log_flume_visible_section');
        $('.log_flume_section').eq($(this).index()).addClass('log_flume_visible_section');

        return false;
    })


    $(".logflume_sync_media_button").click( function(e) {

        e.preventDefault()

        post_id = ''
        nonce = $(this).attr("data-nonce")
        url = $(this).attr("href")


        console.log(nonce)
        console.log(url)

        $.ajax({
            type : "post",
            dataType : "json",
            url : url,
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
