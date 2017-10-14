<?php
/*
Plugin Name: Log Flume
Plugin URI: http://www.atomicsmash.co.uk
Description: Sync development media files to Amazon S3
Version: 0.1.0
Author: David Darke
Author URI: http://www.atomicsmash.co.uk
*/

require('vendor/autoload.php');

if (!defined('ABSPATH'))exit; //Exit if accessed directly

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;


class DevelopmentSyncing {

    private $setup;

    function __construct() {

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


		// add_action("init", array($this, 'add_cli_commands' ));

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'logflume select-bucket', array($this ,'cli_log_flume_select_bucket') );
			WP_CLI::add_command( 'logflume sync-media', array($this ,'log_flume_transfer') );
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
			WP_CLI::success( "Secleted bucket updated" );
		};


		// Test if bucket has not yet been selected
		if($selected == ""){
			echo WP_CLI::colorize( "%YNo bucket is currently selected. Run %n");
			echo WP_CLI::colorize( "%r'wp logmsith select-bucket <bucket-name>'%n");
			echo WP_CLI::colorize( "%Y to select a bucket%n\n");
		};

		echo WP_CLI::colorize( "%YAvailable buckets:%n\n");


		//https://make.wordpress.org/cli/handbook/internal-api/wp-cli-colorize/
		// echo WP_CLI::colorize( "%bSuccess:%n");
		// echo WP_CLI::colorize( "%Y:%n");


		$s3 = new S3Client([
			'version'     => 'latest',
			'region'      => AWS_REGION,
			'credentials' => [
				'key'    => AWS_ACCESS_KEY_ID,
				'secret' => AWS_SECRET_ACCESS_KEY,
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

	function log_flume_transfer() {

		$selected_s3_bucket = get_option('logflume_s3_selected_bucket');


		if($selected_s3_bucket == ""){
			return WP_CLI::error( "There is currently no S3 bucket selected, please run `wp logflume select-bucket`" );
		}


		die();

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
		$selected_s3_bucket = get_option('logflume_s3_selected_bucket');

		$missing_files = $_REQUEST['files'];

		// echo "<pre>";
		// print_r($missing_files);
		// echo "</pre>";

		// die();

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
				// echo "<pre>";
				// print_r($file);
				// echo "</pre>";


			}
			// die();

		} catch (Aws\S3\Exception\S3Exception $e) {
			echo "There was an error uploading the file.<br><br> Exception: $e";
		}

		$results['type'] = 'success';

		echo json_encode($results);

		// $result = json_encode($synced_files);


		die();
	}



	function find_files_to_sync(){

		// These need to be reduced
		$selected_s3_bucket = get_option('logflume_s3_selected_bucket');

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


}

$log_flume = new DevelopmentSyncing;
