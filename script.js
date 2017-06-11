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

        // remove sync button
        $(this).remove();

        // Change column name
        $('.column-location').text('Synced');

        // dump table contents
        $('#the-list').empty();

        // dump old nav buttons
        $('.tablenav-pages').empty();
        $('body').addClass('syncing');


        $.ajax({
            type : "post",
            dataType : "json",
            url : url,
            data : {action: "log_flume_file_list", nonce: nonce},
            success: function(response) {
                // console.log(response);
                if(response.type == "success") {
                    // alert("success :)")

                    response.files.display.forEach(function(entry){
                        $('#the-list').append('<tr class="animated fadeInUp"><td class="file column-file has-row-actions column-primary" data-colname="Files">'+entry.file+'</td><td class="sync_col">Syncing...<span class="syncing_check"></span></td></tr>');
                    })

                    // console.log(response.files);
                    // console.log(response.files.display.length);

                    var i,j,temparray,chunk = 6;
                    for (i=0,j=response.files.display.length; i<j; i+=chunk) {
                        batch = response.files.display.slice(i,i+chunk);
                        // console.log(batch);
                        transfer_batch(batch);
                    }

                } else {
                    // alert("Error :(")
                }
            }
        })

    })

    function transfer_batch(files){
        // console.log(files)

        $.ajax({
            type : "post",
            dataType : "json",
            url : url,
            data : {action: "log_flume_transfer", files : files, nonce: nonce},
            success: function(response) {
                // console.log(response);
                if(response.type == "success") {

                    response.files.forEach(function(file){

                        $( "td:contains('"+file+"')" ).parent().children('.sync_col').html('<span class="dashicons dashicons-yes"></span>');
                        // console.log(file);

                    })

                    check_all_synced();

                } else {
                    // alert("Error :(")
                }
            }
        })
    }

    function check_all_synced(){
        // alert("Error :(")
        console.log($('.syncing_check').length);

        if($('.syncing_check').length != 0){
            $('.total_files_to_sync').html( "<strong>" + $('.syncing_check').length + "</strong> files left to sync" );
        }else{
            $('.total_files_to_sync').html( "<strong>All files synced 😎</strong>" );
        }

    }

})( jQuery );
