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

        $(this).remove();


        $('.column-location').text('Synced');


        // if (!$('body').hasClass( "syncing" ) ) {

        $('#the-list').empty();
        $('.tablenav-pages').empty();
        $('body').addClass('syncing');

        // }


        $.ajax({
            type : "post",
            dataType : "json",
            url : url,
            data : {action: "log_flume_transfer", post_id : post_id, nonce: nonce},
            success: function(response) {
                // console.log(response);
                if(response.type == "success") {

                    response.files.forEach(function(file){

                        // $('td:contains('+ file +')').css('background-color', 'red');

                        $('#the-list').append('<tr><td class="file column-file has-row-actions column-primary" data-colname="Files">'+file+'</td><td><span class="dashicons dashicons-yes"></span></td></tr>');


                    })

                } else {
                    alert("Error :(")
                }
            }
        })

    })



})( jQuery );
