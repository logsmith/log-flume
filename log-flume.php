<?php
/*
Plugin Name: Log Flume
Plugin URI: http://www.atomicsmash.co.uk
Description: Sync development media files to Amazon S3
Version: 0.1.0
Author: David Darke
Author URI: http://www.atomicsmash.co.uk
*/

if (!defined('ABSPATH'))exit; //Exit if accessed directly


use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class DevelopmentSyncing {

    function __construct() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {

            if($this->check_config_details_exist() == true){
                WP_CLI::add_command( 'logflume select-bucket', array($this ,'cli_log_flume_select_bucket') );
    			WP_CLI::add_command( 'logflume sync-media', array($this ,'cli_log_flume_transfer') );
    			// WP_CLI::add_command( 'logflume backup', array($this ,'backup_site') );
                WP_CLI::add_command( 'logflume setup', array($this ,'setup') );
                WP_CLI::add_command( 'logflume check-setup', array($this ,'check_setup') );
            }else{
                WP_CLI::add_command( 'logflume', array($this ,'setup') );
            }

        };

    }

	private function connect_to_s3(){

        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => LOG_FLUME_REGION,
            'credentials' => [
                'key'    => LOG_FLUME_ACCESS_KEY_ID,
                'secret' => LOG_FLUME_SECRET_ACCESS_KEY,
            ],
        ]);

        return $s3;
    }

	function check_config_details_exist(){
        if ( !defined('LOG_FLUME_ACCESS_KEY_ID') || !defined('LOG_FLUME_SECRET_ACCESS_KEY') || !defined('LOG_FLUME_REGION') || LOG_FLUME_ACCESS_KEY_ID == "" || LOG_FLUME_SECRET_ACCESS_KEY == "" || LOG_FLUME_REGION == "" ) {
            return false;
        }else{
            return true;
        }
    }

    function setup($args){

        if( $this->check_config_details_exist() == false ){

			echo WP_CLI::colorize( "%rS3 access details don't currently exist in your config files 😓!%n\n" );

            // View logflume Wiki
            // echo WP_CLI::colorize( "%rStep 1: %n");
            // echo WP_CLI::colorize( "%Y Visit https://github.com/logsmith/log-flume/wiki/Getting-AWS-credentials to learn how to create an IAM user.%n\n");

            // Add config details
            echo WP_CLI::colorize( "%YAdd these new config details to your wp-config file:%n\n");
            echo WP_CLI::colorize( "%Ydefine('LOG_FLUME_REGION','eu-west-2'); // eu-west-2 is London%n\n");
            echo WP_CLI::colorize( "%Ydefine('LOG_FLUME_ACCESS_KEY_ID','');%n\n");
            echo WP_CLI::colorize( "%Ydefine('LOG_FLUME_SECRET_ACCESS_KEY','');%n\n");
            echo WP_CLI::colorize( "%YOnce these are in place, re-run %n");
            echo WP_CLI::colorize( "%r'wp logflume setup'%n\n\n");

            echo WP_CLI::colorize( "%YIf you need help, visit https://github.com/logsmith/log-flume/wiki/Getting-AWS-credentials to learn how to create an IAM user.%n\n");

            return false;

		// }else{
        //     echo WP_CLI::colorize( "%YYes! config details exist! 🙂%n\n\n");
        }

        echo WP_CLI::colorize( "%YPlease provide a bucket name (url safe). Once given '-logflume' and '-backup' will be appended.%n\n");
        echo WP_CLI::colorize( "%YAn good name would the current sites URL: %n");

        $url = get_bloginfo('url');

        $disallowed = array('http://', 'https://');

        foreach($disallowed as $d) {
            if(strpos($url, $d) === 0) {
                $url = str_replace($d, '', $url);
            }
        }

        echo WP_CLI::colorize( "%Y'$url'\n");

        // Get bucket name
        $bucket_name = fgets( STDIN );

        // Remove new line created when pressing enter key
        $bucket_name = rtrim( $bucket_name, "\n" );

        echo WP_CLI::colorize( "%YWould you like to create the standard buckets?:%n\n");
        echo WP_CLI::colorize( "%r".$bucket_name."-logflume%n\n");
        echo WP_CLI::colorize( "%r".$bucket_name."-backup%n\n");

        WP_CLI::confirm( 'Would you like to create the standard logflume buckets?', $assoc_args = array('continue' => 'yes') );

        $s3 = $this->connect_to_s3();

        // If 'Y' create logflume bucket
        if( isset($assoc_args['continue']) ){
            // Create standard logflume bucket
            $this->create_bucket( $s3, $bucket_name, '.logflume' );
        }


        // If 'Y' create backup bucket
        // if( isset($assoc_args['continue']) ){
        //     // Create standard logflume bucket
        //     $this->create_bucket( $s3, $bucket_name );
        // }



        return;

    }

    private function create_bucket( $s3 = null, $bucket_name, $bucket_ext = ""){

        try {

            $result = $s3->createBucket([
                'Bucket' => $bucket_name.".logflume"
            ]);

        } catch (Aws\S3\Exception\S3Exception $e) {
            echo WP_CLI::colorize( "%rThere was a problem creating Log Flume buckets. The bucket might already exist 🤔%n\n");
        }


    }


    /**
     * Checks to see if the current S3 configs are currently working
     * This command will only appear in config details have been set
     * @return [type] [description]
     */
    function check_setup($args){


        // $result = $client->listBuckets([/* ... */]);
        // $promise = $client->listBucketsAsync([/* ... */]);

        $s3 = $this->connect_to_s3();

    	try {
            $result = $s3->listBuckets(array());
        }

    	// catch S3 exception
    	catch(Aws\S3\Exception\S3Exception $e) {
    		// $connected_to_S3 = false;

            echo WP_CLI::warning( "There was an error connecting to S3 😣 This was the error:\n" );

            echo $e->getAwsErrorCode()."\n";
            return false;
    	};

        return WP_CLI::success( "Connection to AWS successfull 😄");


    }

	function cli_log_flume_select_bucket($args){

		$connected_to_S3 = true;
		$selected_bucket_check = 0;
		$selected = get_option('logflume_s3_selected_bucket');

		if( $this->check_config_details_exist() == false ){
			return WP_CLI::error( "Config details missing" );
		}

		// Check to see if there user is trying to set a specific bucket
		if( isset( $args[0] )){
			$selected = $args[0];
			update_option('logflume_s3_selected_bucket',$selected,0);
			WP_CLI::success( "Selected bucket updated" );
		}


		// Test if bucket has not yet been selected
		if($selected == ""){
			echo WP_CLI::colorize( "%YNo bucket is currently selected. Run %n");
			echo WP_CLI::colorize( "%r'wp logflume select-bucket <bucket-name>'%n");
			echo WP_CLI::colorize( "%Y to select a bucket%n\n");
		}

		echo WP_CLI::colorize( "%YAvailable buckets:%n\n");

        $s3 = $this->connect_to_s3();


        //ASTODO this should be the built in config_check function
		try {
			$result = $s3->listBuckets(array());
		}

		//catch S3 exception
		catch(Aws\S3\Exception\S3Exception $e) {
			$connected_to_S3 = false;
			// echo 'Message: ' .$e->getMessage();
		};
        //ASTODO END replace

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


		if( $this->check_config_details_exist() == false ){
			return WP_CLI::error( "Config details missing" );
		}

		if($selected_s3_bucket == ""){
			return WP_CLI::error( "There is currently no S3 bucket selected, please run `wp logflume select-bucket`" );
		}

		echo WP_CLI::colorize( "%YStarting to sync files%n\n");

		$missing_files = $this->find_files_to_sync();


        //TODO This isn't needed!
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

    function backup_site() {

        // $this->backup_database();

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



    function backup_database(){

        // Check to see if the backup folder exists
        if (!file_exists("wp-content/uploads/backups/")) {
            mkdir("wp-content/uploads/backups/" ,0755);
            echo "The directory 'wp-content/uploads/backups/' was successfully created.\n";
        };

        // Create a backup with a file name 'latest-backup'
        $output = shell_exec('wp db export wp-content/uploads/backups/latest-backup.sql --allow-root');
        // Create a backup with a file name involving the datestamp
        $output = shell_exec('wp db export wp-content/uploads/backups/'. date('Y-m-d--h-i-s').'-backup.sql --allow-root');


    }


}

$log_flume = new DevelopmentSyncing;
