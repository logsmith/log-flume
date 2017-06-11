<?php
/*
Plugin Name: Log Flume
Plugin URI: http://www.atomicsmash.co.uk
Description: Sync development media files to Amazon S3
Version: 0.0.15
Author: David Darke
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

		// AJAX endpoint
		add_action( "wp_ajax_log_flume_file_list", array($this, 'find_files_to_sync_ajax') );
		add_action( "wp_ajax_log_flume_transfer", array($this, 'log_flume_transfer_ajax_up') );

		add_action( "wp_ajax_nopriv_log_flume_file_list", array($this, 'my_must_login') );
		add_action( "wp_ajax_nopriv_log_flume_transfer", array($this, 'my_must_login') );

        $this->setup = true;

        if ( !defined('AWS_ACCESS_KEY_ID') || !defined('AWS_SECRET_ACCESS_KEY') || !defined('AWS_REGION') || AWS_ACCESS_KEY_ID == "" || AWS_SECRET_ACCESS_KEY == "" || AWS_REGION == "" ) {

            add_action( 'admin_notices', function(){
				echo "<div class='notice notice-error'><p>Please complete the setup of <a href='".admin_url('upload.php?page=log-flume')."'>Log Flume</a></p></div>";
			} );
            $this->setup = 'details';

        };

		if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
            $this->setup = 'autoload';
		}

        add_action("admin_init", array($this, 'display_theme_panel_fields' ));


    }


    function tabs_js() {
        wp_enqueue_script( 'log_flume_js', plugin_dir_url( __FILE__ ) . 'script.js', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_style( 'log_flume_css', plugin_dir_url( __FILE__ ) . 'styles.css' );
		wp_enqueue_style( 'log_flume_animate_css', "https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.5.2/animate.css" );
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

		// Add options for selecting a bucket
    	add_settings_section("section", "", null, "theme-options");
    	add_settings_field("logflume_s3_select_bucket", "Select bucket", array( $this, "display_s3_selection" ), "theme-options", "section");
        register_setting("section", "logflume_s3_select_bucket");

		// Add options for creating a bucket
		// add_settings_section("logflume_create_section", "", null, "logflume_create_options");
		// add_settings_field("logflume_s3_create_bucket", "Create bucket", array( $this, "display_s3_creation" ), "logflume_create_options", "logflume_create_section");
        // register_setting("logflume_create_section", "logflume_s3_select_bucket2");

    }

	function display_s3_selection(){

	    $s3 = new S3Client([
	        'version'     => 'latest',
	        'region'      => AWS_REGION,
	        'credentials' => [
	            'key'    => AWS_ACCESS_KEY_ID,
	            'secret' => AWS_SECRET_ACCESS_KEY,
	        ],
	    ]);

		$connected_to_S3 = true;

		try {
			$result = $s3->listBuckets(array());
		}

		//catch exception
		catch(Aws\S3\Exception\S3Exception $e) {
			$connected_to_S3 = false;
			// echo 'Message: ' .$e->getMessage();
		};


		if($connected_to_S3 == true){
		    //ASTODO this get_option is dupe
		    $selected = get_option('logflume_s3_select_bucket');

		    echo "<select name='logflume_s3_select_bucket' id='logflume_s3_select_bucket'>";

				echo "<option value='select'>Please Select</option>";
			    foreach ($result['Buckets'] as $bucket) {

			        if($bucket['Name'] == $selected){
			            echo "<option selected='selected' value='".$bucket['Name']."'>".$bucket['Name']."</option>";
			        }else{
			            echo "<option value='".$bucket['Name']."'>".$bucket['Name']."</option>";
			        }

			    }
		    echo "</select>";

		}else{

			echo "Error connecting to Amazon S3, please check your credentials.";

		}
	}

	function display_s3_creation(){

	    echo "<input type='text' name='logflume_s3_bucket_create' id='logflume_s3_bucket_create' />";

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

	function log_flume_transfer_ajax_up() {

		if ( !wp_verify_nonce( $_REQUEST['nonce'], "logflume_nonce")) {
			exit("No naughty business please");
		}

		$wp_upload_dir = wp_upload_dir();

		// $results['files'] = $_REQUEST['files'];


		// echo "<pre>";
		// print_r($results['files']);
		// echo "</pre>";
		//
		//
		//
		// die();

		// These need to be reduced
		$selected_s3_bucket = get_option('logflume_s3_select_bucket');

		$missing_files = $_REQUEST['files'];

		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => AWS_REGION,
			'credentials' => [
				'key'    => AWS_ACCESS_KEY_ID,
				'secret' => AWS_SECRET_ACCESS_KEY,
			],
		]);

		try {

			$keyPrefix = '';
			$options = array(
				// 'params'      => array('ACL' => 'public-read'),
				'concurrency' => 20,
				'debug'       => true
			);


			$synced_files = array();

			// Upload missing files
			foreach($missing_files as $file){

				$result = $s3->putObject(array(
					'Bucket' => $selected_s3_bucket,
					'Key'    => $file,
					'SourceFile' => $wp_upload_dir['basedir']."/".$file
				));

				$results['files'][] = $file;

			}

		} catch (Aws\S3\Exception\S3Exception $e) {
			echo "There was an error uploading the file.<br><br> Exception: $e";
		}

		$results['type'] = 'success';

		echo json_encode($results);

		// $result = json_encode($synced_files);
		// echo $result;


		// die();
		// if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		// 	$result = json_encode($synced_files);
		// 	echo $result;
		// }
		// else {
		// 	header("Location: ".$_SERVER["HTTP_REFERER"]);
		// }

		die();
	}

	function log_flume_transfer_ajax_down() {

		if ( !wp_verify_nonce( $_REQUEST['nonce'], "logflume_nonce")) {
			exit("No naughty business please");
		}

		$wp_upload_dir = wp_upload_dir();


		$results['type'] = 'success';
		$results['files'] = $_REQUEST['files'];

		echo json_encode($results);

		die();

		// These need to be reduced
		$selected_s3_bucket = get_option('logflume_s3_select_bucket');

		$missing_files = $this->find_files_to_sync();

		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => AWS_REGION,
			'credentials' => [
				'key'    => AWS_ACCESS_KEY_ID,
				'secret' => AWS_SECRET_ACCESS_KEY,
			],
		]);

		try {

			$keyPrefix = '';
			$options = array(
				// 'params'      => array('ACL' => 'public-read'),
				'concurrency' => 20,
				'debug'       => true
			);


			$synced_files = array();

			// Download missing files
			foreach($missing_files['missing_locally'] as $file){

				//Check to see if the missing $file is actually a folder
				$ext = pathinfo($file, PATHINFO_EXTENSION);

				//Check to see if the directory exists
				if (!file_exists(dirname($wp_upload_dir['basedir']."/".$file))) {
					mkdir(dirname($wp_upload_dir['basedir']."/".$file),0755, true);
				};

				//If the $file isn't a folder download it
				if($ext != ""){
					$result = $s3->getObject([
					   'Bucket' => $selected_s3_bucket,
					   'Key'    => $file,
					   'SaveAs' => $wp_upload_dir['basedir']."/".$file
					]);
				}
				$synced_files['files'][] = $file;

			}

		} catch (Aws\S3\Exception\S3Exception $e) {
			echo "There was an error uploading the file.<br><br> Exception: $e";
		}

		// $synced_files['type'] = 'success';


		// $result = json_encode($synced_files);
		// echo $result;


		// die();
		if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			$result = json_encode($synced_files);
			echo $result;
		}
		else {
			header("Location: ".$_SERVER["HTTP_REFERER"]);
		}

		die();
	}

	function my_must_login() {
	   echo "You must log in to sync media";
	   die();
	}


	function find_files_to_sync(){

		// These need to be reduced
		$selected_s3_bucket = get_option('logflume_s3_select_bucket');

		$ignore = array("DS_Store","htaccess");

		// Instantiate an Amazon S3 client.
		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => AWS_REGION,
			'credentials' => [
				'key'    => AWS_ACCESS_KEY_ID,
				'secret' => AWS_SECRET_ACCESS_KEY,
			],
		]);


		$iterator = $s3->getIterator('ListObjects', array(
			'Bucket' => $selected_s3_bucket
		));


		$found_files_remotely = array();

		if( count( $iterator ) > 0 ){
			foreach ($iterator as $object) {

				$found_files_remotely[] = $object['Key'];

			}
		}

		$wp_upload_dir = wp_upload_dir();

		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($wp_upload_dir['basedir'], RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
			RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
		);

		// $paths = array($wp_upload_dir['basedir']);
		$found_files_locally = array();

		foreach ($iter as $path => $dir) {
			// if ($dir->isDir()) {

			$filetype = pathinfo($dir);

			//This would be nicer to have this in the RecursiveIteratorIterator
			if (isset($filetype['extension']) && !in_array($filetype['extension'], $ignore)) {
				$found_files_locally[] = str_replace($wp_upload_dir['basedir'].'/','',$path);
				// echo $filetype['filename']." - ".str_replace($wp_upload_dir['basedir'].'/','',$path)."<br>";
				// echo $filetype['filename']."<br>";
			}

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

		// reset array keys
		$missing_locally = array_values($missing_locally);
		$missing_remotely = array_values($missing_remotely);


		$missing_files = array();
		$missing_files['missing_locally'] = $missing_locally;
		$missing_files['missing_remotely'] = $missing_remotely;
		$missing_files['display'] = $missing_display;

		return $missing_files;

	}

	function find_files_to_sync_ajax(){

		if ( !wp_verify_nonce( $_REQUEST['nonce'], "logflume_nonce")) {
			exit("No naughty business please");
		}

		$result['files'] = $this->find_files_to_sync();
		$result['type'] = 'success';

		$result = json_encode($result);

		echo $result;

		die();

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

        if( $this->setup === "details" ){
            echo "<h2>AWS setting missing :(</h2>";

            echo "Looks like you need to add these Constants to your config file:";

            echo "<pre>";
                echo "define('AWS_REGION','');\n";
                echo "define('AWS_ACCESS_KEY_ID','');\n";
                echo "define('AWS_SECRET_ACCESS_KEY','');";
            echo "</pre>";

            echo "Once these are in place, come back here to select your bucket.";

            return;
        };

        if( $this->setup === "autoload" ){
            echo "<h2>AWS classes missing :(</h2>";

            echo "It seems like the Log Flume can't find the AWS classes it needs to sync. This could be because:";

            echo "<ul><li> - Log Flume wasn't added via composer so it's dependencies weren't pulled.</li><li> - The autoload.php file generated by composer is not being required anywhere in your project.</li></ul>";

            return;
        };

		// These need to be reduced
		$selected_s3_bucket = get_option('logflume_s3_select_bucket');


		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => AWS_REGION,
			'credentials' => [
				'key'    => AWS_ACCESS_KEY_ID,
				'secret' => AWS_SECRET_ACCESS_KEY,
			],
		]);


        ?>
        <h2 class="nav-tab-wrapper log-flume-tabs">
			<?php
			// Check to see if bucket has been selected
			if($selected_s3_bucket != "" && $selected_s3_bucket != "select" ){
				?>
				<a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>/index.php?page=welcome-screen-about">Sync media</a>
				<a class="nav-tab" href="<?php echo admin_url() ?>/index.php?page=logflume">Select Bucket</a>
				<?php
			}else{
				?>
				<a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>/index.php?page=logflume">Select Bucket</a>
				<?php
			}
			?>
        </h2>
        <?php


		// Don't render any bucket options if a bucket isn't selected
		if( $selected_s3_bucket != "" && $selected_s3_bucket != "select" ){
			echo "<div class='wrap section log_flume_section log_flume_visible_section'>";



				try {


					$missing_display = $this->find_files_to_sync();

					// if(!isset($_GET['sync'])){

					if( count($missing_display) == 0 ){
						echo "<h2 style='margin-top:40px;'>All files are in sync with AWS.</h2>";
					};

			        ?>
			        <div id="poststuff">
						<div id="post-body" class="metabox-holder columns-3">
							<div id="post-body-content">
								<div class="meta-box-sortables ui-sortable">
									<form method="post">
										<?php
										$this->entry_obj->prepare_items($missing_display['display']);
										$this->entry_obj->display(); ?>
									</form>
								</div>
							</div>
						</div>
						<br class="clear">
					</div>
			        <?php


					if( count($missing_display) > 0 ){
						echo "<a href='".admin_url('admin-ajax.php')."' class='button button-primary logflume_sync_media_button'
						data-nonce='".wp_create_nonce("logflume_nonce")."'
						>Sync now</a>";
					}else{
						echo "<a href='".admin_url('upload.php?page=log-flume')."' class='button button-primary disabled'>Sync now - No files to sync</a>";
					}

					// } else {
					//
			        // }

				} catch (S3Exception $e) {

					echo "<h3>There was an issue with the connecting to AWS bucket. Make sure it's in the selected region (".AWS_REGION.")</h3>";
					// echo $e->getMessage() . "\n";

				}


			echo "</div>";
		};

		if( $selected_s3_bucket != "" && $selected_s3_bucket != "select" ){
			echo "<div class='wrap section log_flume_section'>";
		}else{
			echo "<div class='wrap section log_flume_section log_flume_visible_section'>";
		};

		?>
				<form method="post" action="options.php">
					<?php
						settings_fields("section");
						do_settings_sections("theme-options");
						submit_button('Select AWS bucket');


						// settings_fields("section");
						// do_settings_sections("logflume_create_options");
						// submit_button('Create AWS bucket');

					?>
				</form>
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
		_e( 'No files to sync 😎', 'sp' );
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
		$total_items  = count($items);

		$this->set_pagination_args([
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		]);

		$this->items = array_slice( $items, ( ($current_page - 1) * $per_page ), $per_page );

	}
}
