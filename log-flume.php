<?php
/*
Plugin Name: Log Flume
Plugin URI: http://www.atomicsmash.co.uk
Description: Sync development media files to Amazon S3
Version: 1.1.0
Author: David Darke
Author URI: http://www.atomicsmash.co.uk
*/

if (!defined('ABSPATH'))exit; //Exit if accessed directly


use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * Sync media across development machines and backup websites
 */
class DevelopmentSyncing {

    function __construct() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {

            if( $this->check_config_details_exist() == true ){

                WP_CLI::add_command( 'logflume check_credentials', array( $this, 'check_credentials' ) );
                WP_CLI::add_command( 'logflume select_bucket', array( $this, 'select_bucket' ) );
                WP_CLI::add_command( 'logflume sync', array( $this, 'sync' ) );
                WP_CLI::add_command( 'logflume backup_wordpress', array( $this, 'backup_wordpress' ) );
                WP_CLI::add_command( 'logflume create_bucket', array( $this, 'create_bucket' ) );
                WP_CLI::add_command( 'logflume autodelete_sql', array( $this, 'add_lifecycle' ) );

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

	private function check_config_details_exist(){
        if ( !defined('LOG_FLUME_ACCESS_KEY_ID') || !defined('LOG_FLUME_SECRET_ACCESS_KEY') || !defined('LOG_FLUME_REGION') || LOG_FLUME_ACCESS_KEY_ID == "" || LOG_FLUME_SECRET_ACCESS_KEY == "" || LOG_FLUME_REGION == "" ) {

            echo WP_CLI::colorize( "%rS3 access details don't currently exist in your config files 😓!%n\n" );

            // Add config details
            echo WP_CLI::colorize( "%YAdd these new config details to your wp-config file:%n\n");
            echo WP_CLI::colorize( "%Ydefine('LOG_FLUME_REGION','eu-west-2'); // eu-west-2 is London%n\n");
            echo WP_CLI::colorize( "%Ydefine('LOG_FLUME_ACCESS_KEY_ID','');%n\n");
            echo WP_CLI::colorize( "%Ydefine('LOG_FLUME_SECRET_ACCESS_KEY','');%n\n");
            echo WP_CLI::colorize( "%YOnce these are in place, re-run %n");
            echo WP_CLI::colorize( "%r'wp logflume create_bucket'%n\n\n");

            echo WP_CLI::colorize( "%YIf you need help, visit https://github.com/logsmith/log-flume/wiki/Getting-AWS-credentials to learn how to create an IAM user.%n\n");

            return false;
        }else{
            return true;
        }
    }

    /**
     * Setup Log Flume bucket and create lifecycle policy.
     *
     * ## OPTIONS
     *
     * <bucket_name>
     * : Name of bucket to create
     *
     * ## EXAMPLES
     *
     *     $ wp option wordpress.dev 40
     *     Success: This is will setup a new bucket and add a lifecycle policy of
     *     40 days for the SQL folder.
     */
    function create_bucket( $args, $assoc_args ){

        // Get bucket name
        $bucket_name = $args[0];

        WP_CLI::confirm( 'Create bucket?', $assoc_args = array( 'continue' => 'yes' ) );

        // If 'Y' create logflume bucket
        if( isset( $assoc_args['continue'] )){
            $s3 = $this->connect_to_s3();
            // Create standard logflume bucket
            // $creation_success = $this->create_s3_bucket( $s3, $bucket_name, '.logflume' );

            $creation_success = true;

            try {
                $result = $s3->createBucket([
                    'Bucket' => $bucket_name . ".logflume"
                ]);
            } catch (Aws\S3\Exception\S3Exception $e) {
                echo WP_CLI::colorize( "%rThere was a problem creating Log Flume buckets. The bucket might already exist 🤔%n\n");
                $creation_success = false;
            }

        }

        if( $creation_success == true ){

            update_option( 'logflume_s3_selected_bucket', $bucket_name . '.logflume', 0 );
            echo WP_CLI::success( "Log Flume bucket created and selected 👌");

        }
    }


    /**
     * Checks to see if the current S3 configs are currently working
     * This command will only appear in config details have been set
     * @return [type] [description]
     */
    function check_credentials(){

        if( $this->check_config_details_exist() == false ){
			return WP_CLI::error( "Config details missing" );
		}

        $s3 = $this->connect_to_s3();

    	try {
            $result = $s3->listBuckets(array());
        } catch(Aws\S3\Exception\S3Exception $e) {
            echo WP_CLI::warning( "There was an error connecting to S3 😣\n\nThis was the error:\n" );
            echo $e->getAwsErrorCode()."\n";
            return false;
        };

        return WP_CLI::success( "Connection to S3 was successfull 😄");

    }

	function select_bucket( $args ){

		$connected_to_S3 = true;
		$selected_bucket_check = 0;
        //ASTODO This get_option could be a helper
		$selected_s3_bucket = get_option('logflume_s3_selected_bucket');


		if( $this->check_config_details_exist() == false ){
			return WP_CLI::error( "Config details missing" );
		}

		// Check to see if there user is trying to set a specific bucket
		if( isset( $args[0] )){
			$selected_s3_bucket = $args[0];
			update_option( 'logflume_s3_selected_bucket', $selected_s3_bucket, 0 );
			WP_CLI::success( "Selected bucket updated" );
		}

		// Test if bucket has not yet been selected
		if( $selected_s3_bucket == "" ){
			echo WP_CLI::colorize( "%YNo bucket is currently selected.%n\n");
			// echo WP_CLI::colorize( "%r'wp logflume create_bucket'%n");
			// echo WP_CLI::colorize( "%Y%n\n");
            // return false;
		}

		echo WP_CLI::colorize( "%YAvailable buckets:%n\n");

        $s3 = $this->connect_to_s3();


        //ASTODO this should be the built in config_check function
		try {
			$result = $s3->listBuckets( array() );
		}

		//catch S3 exception
		catch( Aws\S3\Exception\S3Exception $e ) {
			$connected_to_S3 = false;
			// echo 'Message: ' .$e->getMessage();
		};
        //ASTODO END replace

		if( $connected_to_S3 == true ){

			foreach ($result['Buckets'] as $bucket) {

				echo $bucket['Name'];

				if( $bucket['Name'] == $selected_s3_bucket ){
					$selected_bucket_check = 1;
					echo WP_CLI::colorize( "%r - currently selected%n");
				};

				echo "\n";
			}

		}else{
			return WP_CLI::error( "Error connecting to Amazon S3, please check your credentials." );
		}

		if( $selected_bucket_check == 0 && $selected_s3_bucket != "" ){
			return WP_CLI::error( "There is a selected bucket (". $selected_s3_bucket ."), but it doesn't seem to exits on S3?" );
		}

	}

    /**
     * Sync files to S3. Then export the db and upload
     *
     * ## EXAMPLES
     *
     *     $ wp backup
     *     Success: Sync the media and upload the db
     *
     */
    function backup_wordpress( $args, $assoc_args ) {

        // Sync media up to S3
        $this->sync([], ['direction' => 'up'] );

        // Backup DB
        $this->backup_database();

    }

    /**
     * Sync files to S3. You can also just sync in one direction, this is good for backups
     *
     * ## OPTIONS
     *
     * [--direction=<up-or-down>]
     * : Sync up to S3, or pull down from S3
     *
     * ## EXAMPLES
     *
     *     $ wp sync
     *     Success: Will sync all uploads to S3
     *
     */
	function sync( $args, $assoc_args ) {

        // Check to make sure direction is set and is valid
        if( ! isset( $assoc_args['direction'] ) ){
            $direction = 'both';
        }else{
            if( $assoc_args['direction'] == 'up' ){
                $direction = 'up';
            }else if( $assoc_args['direction'] == 'down' ){
                $direction = 'down';
            }else{
                WP_CLI::error( "Please only provide 'up' or 'down' as a direction" );
            }
        }

		$selected_s3_bucket = get_option('logflume_s3_selected_bucket');
		$wp_upload_dir = wp_upload_dir();

		// if( $this->check_config_details_exist() == false ){
		// 	return WP_CLI::error( "Config details missing" );
		// }

        if( $selected_s3_bucket == "" ){
            echo WP_CLI::colorize( "%YNo bucket is currently selected. Run %n");
            echo WP_CLI::colorize( "%r'wp logflume create_bucket'%n");
            echo WP_CLI::colorize( "%Y%n or ");
            echo WP_CLI::colorize( "%r'wp logflume select_bucket'%n");
            echo WP_CLI::colorize( "%Y%n\n");
            return false;
        }

		WP_CLI::log( WP_CLI::colorize( "%YStarting to sync files%n" ));

		$missing_files = $this->find_files_to_sync();

        //ASTODO This isn't needed!
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

			//ASTODO check count
			//ASTODO Don't sync sql-backup folder
			// Upload missing files
			foreach($missing_files['display'] as $file){

                if( $direction == 'both' || $direction == 'down' ){
    				if( $file['location'] == 'remote'){

    					//Check to see if the missing $file is actually a folder
    					$ext = pathinfo($file['file'], PATHINFO_EXTENSION);

    					//Check to see if the directory exists
    					if (!file_exists(dirname($wp_upload_dir['basedir']."/".$file['file']))) {
    						mkdir(dirname($wp_upload_dir['basedir']."/".$file['file']),0755, true);
    					};

    					if($ext != ""){
    						$result = $s3->getObject([
    						   'Bucket' => $selected_s3_bucket,
    						   'Key'    => $file['file'],
    						   'SaveAs' => $wp_upload_dir['basedir']."/".$file['file']
    						]);
    					}
    					$results['files'][] = $file['file'];

                        WP_CLI::log( WP_CLI::colorize( "%gSynced: ".$file['file'] . "%n%y - ⬇ downloaded from S3%n" ));
    				}
                }

                if( $direction == 'both' || $direction == 'up' ){
    				if( $file['location'] == 'local'){
    					$result = $s3->putObject(array(
    						'Bucket' => $selected_s3_bucket,
    						'Key'    => $file['file'],
    						'SourceFile' => $wp_upload_dir['basedir']."/".$file['file']
    					));
    					$results['files'][] = $file['file'];

                        WP_CLI::log( WP_CLI::colorize( "%gSynced: ".$file['file']."%n%y - ⬆ uploaded to S3%n" ));
    				}
                }

			}

		} catch (Aws\S3\Exception\S3Exception $e) {
			echo "There was an error uploading the file.<br><br> Exception: $e";
		}

		return WP_CLI::success( "Sync complete! 😎" );

	}

	private function find_files_to_sync(){

		// These need to be reduced
		$selected_s3_bucket = get_option('logflume_s3_selected_bucket');

		$ignore = array("DS_Store","htaccess");

		// Instantiate an Amazon S3 client.
        //ASTODO This isn't needed!
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

    /*
     * Backup a website database
     */
    private function backup_database(){

        $wp_upload_dir = wp_upload_dir();

        // Check to see if the backup folder exists
        if (!file_exists( $wp_upload_dir['basedir'] . "/logflume-backups/" )) {
            mkdir( $wp_upload_dir['basedir'] . "/logflume-backups/" ,0755 );
            echo WP_CLI::colorize( "%yThe directory 'wp-content/uploads/logflume-backups/' was successfully created.%n\n");
        };

        // generate a hash based on the date and a random number
        $hashed_filename = hash( 'ripemd160', date('ymd-h:i:s') . rand( 1, 99999 ) ) . ".sql";

        // Create a backup with a file name involving the datestamp and a rand number to make it harder to
        // guess the backup filenames and reduce the risk of being able to download backups
        $output = shell_exec( "wp db export " . $wp_upload_dir['basedir'] . "/logflume-backups/" . $hashed_filename . " --allow-root --path=".ABSPATH);

        $s3 = $this->connect_to_s3();

        //ASTODO centralise this get option, once it's centalised there will be a way of overriding it via config
        $selected_s3_bucket = get_option('logflume_s3_selected_bucket');

        //ASTODO check to see if backup actually worked
        if( $selected_s3_bucket != "" ){

            // Transfer the file to S3
            $success = false;

            try {

                $result = $s3->putObject(array(
                    'Bucket' => $selected_s3_bucket,
                    'Key'    => "sql-backups/".date('d-m-Y--h:i:s').".sql",
                    'SourceFile' =>  $wp_upload_dir['basedir'] . "/logflume-backups/" . $hashed_filename
                ));

                $success = true;

            } catch (Aws\S3\Exception\S3Exception $e) {
    			echo "There was an error uploading the backup database 😕";
    		}

            // If successfully transfered, delete local copy
            if( $success == true ){
                $output = shell_exec( "rm -rf  " . $wp_upload_dir['basedir'] . "/logflume-backups/" . $hashed_filename );
                return WP_CLI::success( "DB backup complete! 🎉" );
            }

        }
    }

    /**
     * Add life cycle policy to the SQL folder. This will help reduce file build up
     *
     * ## OPTIONS
     *
     * <number_of_days>
     * : Name of bucket to create
     *
     * ## EXAMPLES
     *
     *     $ wp add_lifecycle
     *     Success: Will sync all uploads to S3
     *
     */
    function add_lifecycle( $args, $assoc_args ){

        $selected_s3_bucket = get_option('logflume_s3_selected_bucket');

        if( $selected_s3_bucket == "" ){
            echo WP_CLI::colorize( "%YNo bucket is currently selected. Run %n");
            echo WP_CLI::colorize( "%r'wp logflume create_bucket'%n");
            echo WP_CLI::colorize( "%Y%n\n");
            return false;
        }

        // Get expirty time in number of days
        $backup_life = $args[0];

        $s3 = $this->connect_to_s3();

        // Setup lifecycle policy for DB backups
        $result = $s3->putBucketLifecycleConfiguration([
            'Bucket' => $selected_s3_bucket,
            'LifecycleConfiguration' => [
                'Rules' => [[
                    'Expiration' => [
                        // 'Date' => <integer || string || DateTime>,
                        'Days' => $backup_life,
                        // 'ExpiredObjectDeleteMarker' => true || false,
                    ],
                    'ID' => "SQL backups",
                    'Filter' => [
                        'Prefix' => 'sql-backups'
                    ],
                    'Status' => 'Enabled'
                ]]
            ]
        ]);

        echo WP_CLI::success( "Autodelete lifecycle added");

    }

}

$log_flume = new DevelopmentSyncing;
