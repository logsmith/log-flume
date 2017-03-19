<?php
/*
Plugin Name: Logsmith - Log Flume
Plugin URI: http://www.atomicsmash.co.uk
Description: Sync development media files
Version: 0.0.4
Author: Atomic Smash
Author URI: http://www.atomicsmash.co.uk
*/
if (!defined('ABSPATH'))exit; //Exit if accessed directly

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
//
// class MyRecursiveFilterIterator extends RecursiveFilterIterator {
//
//     public static $FILTERS = array(
//         '__MACOSX',
//     );
//
//     public function accept() {
//         return !in_array(
//             $this->current()->getFilename(),
//             self::$FILTERS,
//             true
//         );
//     }
//
// }

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


class DevelopmentSyncing {

    private $setup;

    function __construct() {

        add_action( 'load-upload.php', array($this, 'indexButton'));
        add_action( 'admin_menu', array($this, 'submenu') );
        add_action( 'admin_enqueue_scripts', array($this, 'tabs') );

        $this->setup = true;

        if ( !defined('AWS_ACCESS_KEY_ID') || !defined('AWS_SECRET_ACCESS_KEY') || AWS_ACCESS_KEY_ID == "" || AWS_SECRET_ACCESS_KEY == "") {
            add_action( 'admin_notices', array($this, 'sample_admin_notice__success') );
            $this->setup = false;
        };



    }


    function tabs() {

        wp_enqueue_script( 'welcome_screen_js', plugin_dir_url( __FILE__ ) . '/script.js', array( 'jquery' ), '1.0.0', true );

    }
    function sample_admin_notice__success() {

        echo "<div class='notice notice-error'><p>Please complete the setup of <a href='".admin_url('upload.php?page=log-flume')."'>Log Flume</a></p></div>";

    }

    static function getUrl() {
        return add_query_arg( array('page'=>'log-flume'), admin_url('upload.php') );
    }

    function submenu() {
        $hook = add_media_page( 'Sync Media to S3', 'Sync Media to S3', 'upload_files', 'log-flume', array($this, 'admin_page') );

        echo "<pre>";
        print_r($hook);
        echo "</pre>";


        add_action( "load-$hook", array( $this, 'screen_option' ));

    }

    function indexButton() {
        if ( ! current_user_can( 'upload_files' ) ) return;
        add_filter( 'esc_html', array(__CLASS__, 'h2Button'), 999, 2 );
    }

    static function h2Button( $safe_text, $text ) {
        // if ( ! current_user_can( 'upload_files' ) ) return $safe_text;
        if ( $text === __('Media Library') && did_action( 'all_admin_notices' ) ) {
            remove_filter( 'esc_html', array(__CLASS__, 'h2Button'), 999, 2 );
            $format = ' <a href="%s" class="add-new-h2">%s</a>';
            $mybutton = sprintf($format, esc_url(self::getUrl()), 'Sync Media to S3' );
            $safe_text .= $mybutton;
        }
        return $safe_text;
    }

    public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => 'Entries',
			'default' => 20,
			'option'  => 'entries_per_page'
		];

		add_screen_option( $option, $args );

		$this->entry_obj = new API_List();
	}

    function admin_page() {

        ?>
        <div class="wrap">
            <h2>Sync media library to S3</h2>
        </div>
        <?php



        // check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {



            return;
        }


        if( $this->setup != true ){

            echo "<h2>AWS setting missing :(</h2>";

            echo "<strong>DAMN!!!</strong> Looks like you need to add these Constants to your config file.";

            echo "<pre>";
                echo "define('AWS_ACCESS_KEY_ID','')\n";
                echo "define('AWS_SECRET_ACCESS_KEY','')";
            echo "</pre>";


            echo "Once these are in place, come back here to select your bucket.";


            return;
        };

        ?>
        <!-- https://premium.wpmudev.org/blog/tabbed-interface/ -->
        <h2 class="nav-tab-wrapper">
            <!-- <a href="#" class="nav-tab nav-tab-active">Sync media</a>
            <a href="#" class="nav-tab">Social Options</a> -->

            <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>/index.php?page=welcome-screen-about">Sync media</a>
            <a class="nav-tab" href="<?php echo admin_url() ?>/index.php?page=welcome-screen-credits">Select Bucket</a>

        </h2>
        <ul class="subsubsub">
        	<li class="all"><a href="edit.php?post_type=post">All <span class="count">(2)</span></a> |</li>
        	<li class="publish"><a href="edit.php?post_status=publish&amp;post_type=post" class="current">Published <span class="count">(2)</span></a></li>
        </ul>

        <?php


        $selected_s3_bucket = get_option('logflume_s3_bucket');

        ?>
    	    <div class="wrap">
        	    <form method="post" action="options.php">
        	        <?php
        	            settings_fields("section");
        	            do_settings_sections("theme-options");
        	            submit_button();
        	        ?>
        	    </form>

                <!-- <h1>Create a bucket</h1>

                <form method="POST">
                    <label for="awesome_text">Awesome Text</label>
                    <input type="text" name="awesome_text" id="awesome_text" value="">
                    <input type="submit" value="Save" class="button button-primary button-large">
                </form> -->

    		</div>
    	<?php

        $ignore = array("DS_Store");

        echo "<a href='".admin_url('upload.php?page=log-flume&sync=1')."' class='button button-primary'>Sync now</a>";

        ?>
        <!-- <div class="wrap">

        <table class="wp-list-table widefat fixed striped media">
        	<thead>
            	<tr>
                    <th scope="col" id="title" class="manage-column">
                        <span>File</span>
                    </th>
                    <th scope="col" id="author" class="manage-column">
                            <span>Author</span>
                    </th>
                    <th scope="col" id="parent" class="manage-column column-parent sortable desc"><a href="http://wordpress.dev/wp-admin/upload.php?orderby=parent&amp;order=asc"><span>Uploaded to</span><span class="sorting-indicator"></span></a></th>
                    <th scope="col" id="comments" class="manage-column column-comments num sortable desc"><a href="http://wordpress.dev/wp-admin/upload.php?orderby=comment_count&amp;order=asc"><span><span class="vers comment-grey-bubble" title="Comments"><span class="screen-reader-text">Comments</span></span></span><span class="sorting-indicator"></span></a></th><th scope="col" id="date" class="manage-column column-date sortable asc"><a href="http://wordpress.dev/wp-admin/upload.php?orderby=date&amp;order=desc"><span>Date</span><span class="sorting-indicator"></span></a>
                    </th>
                </tr>
        	</thead>

        	<tbody id="the-list">
        		<tr id="post-57" class="author-self status-inherit">
                    <td class="title column-title has-row-actions column-primary" data-colname="File">
                        <strong class="has-media-icon">
        			        <a href="http://wordpress.dev/wp-admin/post.php?post=57&amp;action=edit" aria-label="“computer-hard-drive” (Edit)">				<span class="media-icon image-icon"><img width="60" height="60" src="http://wordpress.dev/wp-content/uploads/2017/03/computer-hard-drive-150x150.jpg" class="attachment-60x60 size-60x60" alt=""></span>
        			computer-hard-drive
                            </a>
                        </strong>
            		<p class="filename">
        			<span class="screen-reader-text">File name: </span>
        			computer-hard-drive.jpg		</p>
        		<div class="row-actions"><span class="edit"><a href="http://wordpress.dev/wp-admin/post.php?post=57&amp;action=edit" aria-label="Edit “computer-hard-drive”">Edit</a> | </span><span class="delete"><a href="post.php?action=delete&amp;post=57&amp;_wpnonce=a3f242382f" class="submitdelete aria-button-if-js" onclick="return showNotice.warn();" aria-label="Delete “computer-hard-drive” permanently" role="button">Delete Permanently</a> | </span><span class="view"><a href="http://wordpress.dev/?attachment_id=57" aria-label="View “computer-hard-drive”" rel="permalink">View</a></span></div><button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button></td><td class="author column-author" data-colname="Author"><a href="upload.php?author=1">David</a></td><td class="parent column-parent" data-colname="Uploaded to">(Unattached)			<br><a href="#the-list" onclick="findPosts.open( 'media[]', '57' ); return false;" class="hide-if-no-js aria-button-if-js" aria-label="Attach “computer-hard-drive” to existing content" role="button">Attach</a></td><td class="comments column-comments" data-colname="Comments"><div class="post-com-count-wrapper"><span aria-hidden="true">—</span><span class="screen-reader-text">No comments</span><span class="post-com-count post-com-count-pending post-com-count-no-pending"><span class="comment-count comment-count-no-pending" aria-hidden="true">0</span><span class="screen-reader-text">No comments</span></span></div></td><td class="date column-date" data-colname="Date">2017/03/03</td>
                </tr>
        	</tbody>


        </table>
    </div> -->
        <?php

        if(isset($_GET['sync'])){

            // Instantiate an Amazon S3 client.
            $s3 = new S3Client([
                'version'     => 'latest',
                'region'      => 'eu-west-2',
                'credentials' => [
                    'key'    => AWS_ACCESS_KEY_ID,
                    'secret' => AWS_SECRET_ACCESS_KEY,
                ],
            ]);



            echo "<hr>";
            echo "<h3>S3 Files</h3>";


            $iterator = $s3->getIterator('ListObjects', array(
                'Bucket' => $selected_s3_bucket
            ));

            $found_files_remotely = array();

            foreach ($iterator as $object) {
                // echo $object['Key'] . "<br>";
                $found_files_remotely[] = $object['Key'];

            }

            // echo "<pre>";
            // print_r($found_files_remotely);
            // echo "</pre>";

            $wp_upload_dir = wp_upload_dir();

            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($wp_upload_dir['basedir'], RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
            );

            // $paths = array($wp_upload_dir['basedir']);

            foreach ($iter as $path => $dir) {
                // if ($dir->isDir()) {

                $filetype = pathinfo($dir);

                // echo "<pre>";
                // print_r($filetype);
                // echo "</pre>";


                //This would be nicer to have this in the RecursiveIteratorIterator
                if (isset($filetype['extension']) && !in_array($filetype['extension'], $ignore)) {
                    $found_files_locally[] = str_replace($wp_upload_dir['basedir'].'/','',$path);
                    // echo $filetype['filename']." - ".str_replace($wp_upload_dir['basedir'].'/','',$path)."<br>";
                    // echo $filetype['filename']."<br>";
                }

                    // $filetype = pathinfo($path);
                    //
                    // echo "<pre>";
                    // print_r($filetype);
                    // echo "</pre>";

                    // echo $filetype."<br><br>";


                    // }
            }

            // echo "<h3>Local Files</h3>";
            //
            // echo "<pre>";
            // print_r($found_files_locally);
            // echo "</pre>";


            echo "<h3>Files Missing locally</h3>";

            $missing_locally = array_diff($found_files_remotely,$found_files_locally);

            echo "<pre>";
            print_r($missing_locally);
            echo "</pre>";



            echo "<h3>Files Missing remotely</h3>";

            $missing_remotely = array_diff($found_files_locally,$found_files_remotely);

            echo "<pre>";
            print_r($missing_remotely);
            echo "</pre>";

            $entry_obj = new Media_List();

            $testing_array[0] = array(
                // array(
                    'id' => 2818,
                    'created_at' => 'asdasda',
                    'tweet' => 'asdasda'
                // )
            );


            // Array
            // (
            //     [0] => Array
            //         (
            //             [id] => 838756743127629824
            //             [tweet] => Create your own simple @WordPress plugin to customise the Admin Interface by following our guide @WPBristolPeeps… https://t.co/KnLOJZ5FO8
            //             [user_id] => 213256209
            //             [user_name] => Atomic Smash
            //             [user_handle] => atomicsmash
            //             [user_image] => http://pbs.twimg.com/profile_images/692639579338326016/iwaOsRJn_normal.png
            //             [user_location] => Bristol
            //             [serial_number] => 0
            //             [hidden] => 0
            //             [created_at] => 2017-03-06 02:22:45
            //             [updated_at] => 2017-03-07 10:32:39
            //         )


            echo "<pre>";
            print_r($testing_array);
            echo "</pre>";
            ?>
            <div id="poststuff">
				<div id="post-body" class="metabox-holder columns-3">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$entry_obj->prepare_items($testing_array);
								$entry_obj->display(); ?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
            <?php

            // $entry_obj->prepare_items($missing_remotely);
            // $entry_obj->prepare_items($testing_array);
            // $entry_obj->display();


            try {

                $keyPrefix = '';
                $options = array(
                    // 'params'      => array('ACL' => 'public-read'),
                    'concurrency' => 20,
                    'debug'       => true
                );



                // http://docs.aws.amazon.com/aws-sdk-php/v3/guide/service/s3-transfer.html
                // $source = 'wp-content/uploads/2017/';

                // $filetype['filename'];

                // echo "<pre>";
                // print_r($wp_upload_dir);
                // echo "</pre>";



                // $uploadList = array_diff($localFiles, $s3Files); // returns green.jpg





                foreach($missing_locally as $file){

                    echo $file.'';

                    // $result = $s3->getObject([
                    //     'Bucket' => $selected_s3_bucket,
                    //     'Key'    => $file,
                    //     'SaveAs' => $wp_upload_dir['basedir']."/".$file
                    // ]);


                }


                foreach($missing_remotely as $file){


                    echo $wp_upload_dir['basedir']."/".$file."<br>";
                    // $dest = 's3://';
                    // $manager = new \Aws\S3\Transfer($s3, $wp_upload_dir['basedir']."/".$file, $dest);
                    // $manager->transfer();

                    // $result = $s3->putObject(array(
                    //     'Bucket' => $selected_s3_bucket,
                    //     'Key'    => $file,
                    //     'SourceFile' => $wp_upload_dir['basedir']."/".$file
                    // ));

                }



            } catch (Aws\S3\Exception\S3Exception $e) {
                echo "There was an error uploading the file.<br><br> Exception: $e";
            }
        }
    }


}



$log_flume = new DevelopmentSyncing;





//ASTODO get these functions in the the main class
function display_s3_selection(){

    // echo "<pre>";
    // print_r($_POST);
    // echo "</pre>";



    $s3 = new S3Client([
        'version'     => 'latest',
        'region'      => 'eu-west-2',
        'credentials' => [
            'key'    => AWS_ACCESS_KEY_ID,
            'secret' => AWS_SECRET_ACCESS_KEY,
        ],
    ]);

    $result = $s3->listBuckets(array());


    // $new_bucket_name = 'logflume-tes2t';
    //
    // $does_bucket_exist = $s3->doesBucketExist( $new_bucket_name );
    //
    //
    // if( $does_bucket_exist == false ){
    //
    //     // Create a valid bucket and use a LocationConstraint
    //     $result = $s3->createBucket(array(
    //         'Bucket'             => $new_bucket_name,
    //         'LocationConstraint' => 'eu-west-2',
    //     ));
    //
    //     echo "<h3>Bucket created</h3>";
    //
    // }else{
    //
    //     echo "<h3>Bucket already exists</h3>";
    //
    // };
    //





    //ASTODO this is dupe
    $selected = get_option('logflume_s3_bucket');

    echo "<select name='logflume_s3_bucket' id='logflume_s3_bucket'>";
    foreach ($result['Buckets'] as $bucket) {

        if($bucket['Name'] == $selected){
            echo "<option selected='selected' value='".$bucket['Name']."'>".$bucket['Name']."</option>";
        }else{
            echo "<option value='".$bucket['Name']."'>".$bucket['Name']."</option>";
        }

    }
    echo "</select>";



}


function display_theme_panel_fields(){

	add_settings_section("section", "", null, "theme-options");

	add_settings_field("logflume_s3_bucket", "Select bucket", "display_s3_selection", "theme-options", "section");

    register_setting("section", "logflume_s3_bucket");

}

add_action("admin_init", "display_theme_panel_fields");




class Media_List extends WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Tweet', 'sp' ), //singular name of the listed records
			'plural'   => __( 'Tweets', 'sp' ), //plural name of the listed records
			'ajax'     => false //does this table support ajax?
		] );

	}


	// public static function get_entries( $per_page = 20, $page_number = 1 ) {
    //
	// 	global $twitter_api;
    //
	// 	$result = $twitter_api->entries($per_page,$page_number);
	// 	// $result = parent::tweets($per_page,$page_number);
	// 	// parent::__construct( [
    //
	// 	return $result;
    //
	// 	// global $wpdb;
	// 	//
	// 	// $sql = "SELECT * FROM {$wpdb->prefix}api_twitter";
	// 	//
	// 	// if ( ! empty( $_REQUEST['orderby'] ) ) {
	// 	// 	$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
	// 	// 	$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' DESC';
	// 	// }else{
	// 	// 	$sql .= " ORDER BY `id` DESC";
	// 	// }
	// 	//
	// 	//
	// 	// $sql .= " LIMIT $per_page";
	// 	// $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;
	// 	//
	// 	//
	// 	// $result = $wpdb->get_results( $sql, 'ARRAY_A' );
	// 	//
	// 	// return $result;
	// }


	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}api_twitter";

		return $wpdb->get_var( $sql );
	}


	/** Text displayed when no data is available */
	public function no_items() {
		_e( 'No entries avaliable.', 'sp' );
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
        switch( $column_name ) {
			// case 'tweet':
            // case 'added_at':
            // case 'user_location':
            // return $item[ $column_name ];
			case 'created_at':
				// echo $item[ $column_name ];
				return time_elapsed_string($item[ $column_name ]);
			case 'user_image':
				return "<img src='".$item[ $column_name ]."' />";
			case 'user_handle':
				return "@".$item[ $column_name ];
	        default:
	            return $item[ $column_name ]; //Show the whole array for troubleshooting purposes
        }
	}




	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = array(
            'tweet'    => 'Tweets',
            // 'user_handle'      => 'Username',
            // 'user_image'      => 'Profile Image',
            'created_at'      => 'When'
        );

		return $columns;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items( $items = array() ) {

		$this->_column_headers = $this->get_column_info();

		$per_page     = $this->get_items_per_page( 'entries_per_page', 20 );
		$current_page = $this->get_pagenum();
		// $total_items  = self::record_count();
		$total_items  = 1;

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		// $this->items = self::get_entries( $per_page, $current_page );
		$this->items = $items;
	}


}
