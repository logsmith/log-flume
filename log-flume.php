<?php
/*
Plugin Name: Log Flume
Plugin URI: http://www.atomicsmash.co.uk
Description: Sync development media files to Amazon S3
Version: 0.1.0
Author: David Darke
Author URI: http://www.atomicsmash.co.uk
*/

// require('vendor/autoload.php');

if (!defined('ABSPATH'))exit; //Exit if accessed directly

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class DevelopmentSyncing {

    function __construct() {

        if ( !defined('LOG_FLUME_ACCESS_KEY_ID') || !defined('LOG_FLUME_SECRET_ACCESS_KEY') || !defined('LOG_FLUME_REGION') || LOG_FLUME_ACCESS_KEY_ID == "" || LOG_FLUME_SECRET_ACCESS_KEY == "" || LOG_FLUME_REGION == "" ) {

            add_action( 'admin_notices', function(){
				echo "<div class='notice notice-error'><p>Please complete the setup of <a href='".admin_url('upload.php?page=log-flume')."'>Log Flume</a></p></div>";
			} );

        };


		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'logflume select-bucket', array($this ,'cli_log_flume_select_bucket') );
			WP_CLI::add_command( 'logflume sync-media', array($this ,'cli_log_flume_transfer') );
        };

    }


	function cli_log_flume_select_bucket($args){

		$connected_to_S3 = true;
		$selected_bucket_check = 0;
		$selected = get_option('logflume_s3_selected_bucket');

		// Check to see if there user is trying to set a specific bucket
		if( isset( $args[0] )){
			$selected = $args[0];
			update_option('logflume_s3_selected_bucket',$selected,0);
			WP_CLI::success( "Selected bucket updated" );
		};


		// Test if bucket has not yet been selected
		if($selected == ""){
			echo WP_CLI::colorize( "%YNo bucket is currently selected. Run %n");
			echo WP_CLI::colorize( "%r'wp logmsith select-bucket <bucket-name>'%n");
			echo WP_CLI::colorize( "%Y to select a bucket%n\n");
		};

		echo WP_CLI::colorize( "%YAvailable buckets:%n\n");


		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => LOG_FLUME_REGION,
			'credentials' => [
				'key'    => LOG_FLUME_ACCESS_KEY_ID,
				'secret' => LOG_FLUME_SECRET_ACCESS_KEY,
			],
		]);


		try {
			$result = $s3->listBuckets(array());
		}

		//catch S3 exception
		catch(Aws\S3\Exception\S3Exception $e) {
			$connected_to_S3 = false;
			// echo 'Message: ' .$e->getMessage();
		};


		if($connected_to_S3 == true){

			foreach ($result['Buckets'] as $bucket) {

				echo $bucket['Name'];

				if($bucket['Name'] == $selected){
					$selected_bucket_check = 1;
					echo WP_CLI::colorize( "%r - currently selected%n");
				};

				echo "\n";
			}

		}else{
			return WP_CLI::error( "Error connecting to Amazon S3, please check your credentials." );
		}

		if($selected_bucket_check == 0 && $selected != ""){
			return WP_CLI::error( "There is a selected bucket (".$selected."), but it doesn't seem to exits on S3?" );
		}

	}

	function cli_log_flume_transfer() {

		$selected_s3_bucket = get_option('logflume_s3_selected_bucket');
		$wp_upload_dir = wp_upload_dir();

		echo WP_CLI::colorize( "%YStarting to sync files%n\n");

		//TODO Need to get a list of bucked and fact check the selected bucket exists.
		if($selected_s3_bucket == ""){
			return WP_CLI::error( "There is currently no S3 bucket selected, please run `wp logflume select-bucket`" );
		}

		$missing_files = $this->find_files_to_sync();

		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => LOG_FLUME_REGION,
			'credentials' => [
				'key'    => LOG_FLUME_ACCESS_KEY_ID,
				'secret' => LOG_FLUME_SECRET_ACCESS_KEY,
			],
		]);

		try {

			$keyPrefix = '';
			$options = array(
				// 'params'      => array('ACL' => 'public-read'),
				'concurrency' => 20,
				'debug'       => true
			);


			//TODO check count
			// Upload missing files
			foreach($missing_files['display'] as $file){

				// $results['files'] = $_REQUEST['files'];

				if( $file['location'] == 'remote'){

					// foreach($missing_files['missing_locally'] as $file){

					//Check to see if the missing $file is actually a folder
					$ext = pathinfo($file['file'], PATHINFO_EXTENSION);

					//Check to see if the directory exists
					if (!file_exists(dirname($wp_upload_dir['basedir']."/".$file['file']))) {
						mkdir(dirname($wp_upload_dir['basedir']."/".$file['file']),0755, true);
					};

					//If the $file isn't a folder download it
					if($ext != ""){
						$result = $s3->getObject([
						   'Bucket' => $selected_s3_bucket,
						   'Key'    => $file['file'],
						   'SaveAs' => $wp_upload_dir['basedir']."/".$file['file']
						]);
					}
					$results['files'][] = $file['file'];

					// }

				}

				if( $file['location'] == 'local'){

					$result = $s3->putObject(array(
						'Bucket' => $selected_s3_bucket,
						'Key'    => $file['file'],
						'SourceFile' => $wp_upload_dir['basedir']."/".$file['file']
					));

					$results['files'][] = $file['file'];

				}



				echo WP_CLI::colorize( "%gSynced: ".$file['file']."%n");

				if( $file['location'] == 'local' ){
					echo WP_CLI::colorize( "%y - ⬆ uploaded to S3%n\n");
				}else{
					echo WP_CLI::colorize( "%y - ⬇ downloaded from S3%n\n");
				}


			}

		} catch (Aws\S3\Exception\S3Exception $e) {
			echo "There was an error uploading the file.<br><br> Exception: $e";
		}

		return WP_CLI::success( "Sync complete! 😎" );

	}



	function find_files_to_sync(){

		// These need to be reduced
		$selected_s3_bucket = get_option('logflume_s3_selected_bucket');

		$ignore = array("DS_Store","htaccess");

		// Instantiate an Amazon S3 client.
		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => LOG_FLUME_REGION,
			'credentials' => [
				'key'    => LOG_FLUME_ACCESS_KEY_ID,
				'secret' => LOG_FLUME_SECRET_ACCESS_KEY,
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


}

$log_flume = new DevelopmentSyncing;
