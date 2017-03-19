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


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


class DevelopmentSyncing {

    private $setup;

    function __construct() {

        add_action( 'load-upload.php', array($this, 'indexButton'));
        add_action( 'admin_menu', array($this, 'submenu') );
        add_action( 'admin_enqueue_scripts', array($this, 'tabs_js') );

        $this->setup = true;

        if ( !defined('AWS_ACCESS_KEY_ID') || !defined('AWS_SECRET_ACCESS_KEY') || AWS_ACCESS_KEY_ID == "" || AWS_SECRET_ACCESS_KEY == "") {
            add_action( 'admin_notices', function(){
				echo "<div class='notice notice-error'><p>Please complete the setup of <a href='".admin_url('upload.php?page=log-flume')."'>Log Flume</a></p></div>";
			} );
            $this->setup = false;
        };

        add_action("admin_init", array($this, 'display_theme_panel_fields' ));


    }


    function tabs_js() {
        wp_enqueue_script( 'welcome_screen_js', plugin_dir_url( __FILE__ ) . '/script.js', array( 'jquery' ), '1.0.0', true );
    }

	//ASTODO - pretty this doesn't need to be a function
    static function getUrl() {
        return add_query_arg( array('page'=>'log-flume'), admin_url('upload.php') );
    }

    function submenu() {
        $hook = add_media_page( 'Sync Media to S3', 'Sync Media to S3', 'upload_files', 'log-flume', array($this, 'admin_page') );

        add_action( "load-$hook", array( $this, 'screen_option' ));
    }

	//ASTODO - pretty this doesn't need to be a function
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


    public function display_theme_panel_fields(){

    	add_settings_section("section", "", null, "theme-options");

    	add_settings_field("logflume_s3_bucket", "Select bucket", array( $this, "display_s3_selection" ), "theme-options", "section");

        register_setting("section", "logflume_s3_bucket");

    }

	function display_s3_selection(){



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

    public function screen_option() {

		$option = 'per_page';
		$args   = [
			'label'   => 'Entries',
			'default' => 20,
			'option'  => 'entries_per_page'
		];

		add_screen_option( $option, $args );

		$this->entry_obj = new Media_List();
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

        <?php

		echo "<div class='wrap section'>";

        $selected_s3_bucket = get_option('logflume_s3_bucket');


        $ignore = array("DS_Store");

        // Instantiate an Amazon S3 client.
        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'eu-west-2',
            'credentials' => [
                'key'    => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ],
        ]);


        $iterator = $s3->getIterator('ListObjects', array(
            'Bucket' => $selected_s3_bucket
        ));

        $found_files_remotely = array();

        foreach ($iterator as $object) {
            // echo $object['Key'] . "<br>";
            $found_files_remotely[] = $object['Key'];

        }

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


        $missing_locally = array_diff( $found_files_remotely, $found_files_locally );

        $missing_display = array();

        if( count( $missing_locally ) > 0 ){
            foreach( $missing_locally as $missing_file ){
                $missing_display[] = array(
                    'file' => $missing_file,
                    'location' => 'remote'
                );
            }
        }



        $missing_remotely = array_diff( $found_files_locally, $found_files_remotely );


        if( count( $missing_remotely ) > 0 ){
            foreach( $missing_remotely as $missing_file ){
                $missing_display[] = array(
                    'file' => $missing_file,
                    'location' => 'local'
                );
            }
        }

        ?>
        <div id="poststuff">
			<div id="post-body" class="metabox-holder columns-3">
				<div id="post-body-content">
					<div class="meta-box-sortables ui-sortable">
						<form method="post">
							<?php
							$this->entry_obj->prepare_items($missing_display);
							$this->entry_obj->display(); ?>
						</form>
					</div>
				</div>
			</div>
			<br class="clear">
		</div>
        <?php

		echo "<a href='".admin_url('upload.php?page=log-flume&sync=1')."' class='button button-primary'>Sync now</a><br><br>";


		if(isset($_GET['sync'])){

	        try {

	            $keyPrefix = '';
	            $options = array(
	                // 'params'      => array('ACL' => 'public-read'),
	                'concurrency' => 20,
	                'debug'       => true
	            );

				// Download missing files
                foreach($missing_locally as $file){

                    $result = $s3->getObject([
                        'Bucket' => $selected_s3_bucket,
                        'Key'    => $file,
                        'SaveAs' => $wp_upload_dir['basedir']."/".$file
                    ]);

                }

				// Upload missing files
                foreach($missing_remotely as $file){

                    $result = $s3->putObject(array(
                        'Bucket' => $selected_s3_bucket,
                        'Key'    => $file,
                        'SourceFile' => $wp_upload_dir['basedir']."/".$file
                    ));

                }



            } catch (Aws\S3\Exception\S3Exception $e) {
                echo "There was an error uploading the file.<br><br> Exception: $e";
            }
        }
		echo "</div>";

		?>
			<div class="wrap section">
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

    }


}

$log_flume = new DevelopmentSyncing;


class Media_List extends WP_List_Table {

	public function __construct() {

		parent::__construct( [
			'singular' => __( 'File', 'sp' ), //singular name of the listed records
			'plural'   => __( 'Files', 'sp' ), //plural name of the listed records
			'ajax'     => false //does this table support ajax?
		] );

	}


	public function no_items() {
		_e( 'No files found.', 'sp' );
	}


	public function column_default( $item, $column_name ) {
        switch( $column_name ) {
			case 'location':
                if( $item['location'] == "remote" ){
                    return "<span class='dashicons dashicons-cloud'></span>";
                };
                return "<span class='dashicons dashicons-admin-home'></span>";
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
            'file'    => 'Files',
            // 'user_handle'      => 'Username',
            // 'user_image'      => 'Profile Image',
            'location'      => 'Location'
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
        //ASTODO count the item array
		$total_items  = 1;

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		// $this->items = self::get_entries( $per_page, $current_page );
		$this->items = $items;
	}
}
