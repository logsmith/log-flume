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


class DevelopmentSyncing {

    private $setup;

    function __construct() {

        add_action( 'load-upload.php', array($this, 'indexButton'));
        add_action( 'admin_menu', array($this, 'submenu') );

        $this->setup = true;

        if ( !defined('AWS_ACCESS_KEY_ID') || !defined('AWS_SECRET_ACCESS_KEY') || AWS_ACCESS_KEY_ID == "" || AWS_SECRET_ACCESS_KEY == "") {
            add_action( 'admin_notices', array($this, 'sample_admin_notice__success') );
            $this->setup = false;
        };

    }


    function sample_admin_notice__success() {

        echo "<div class='notice notice-error'><p>Please complete the setup of <a href='".admin_url('upload.php?page=log-flume')."'>Log Flume</a></p></div>";

    }

    static function getUrl() {
        return add_query_arg( array('page'=>'log-flume'), admin_url('upload.php') );
    }

    function submenu() {
        add_media_page( 'Sync Media to S3', 'Sync Media to S3', 'upload_files', 'log-flume', array($this, 'admin_page') );
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
    		</div>
    	<?php

        $ignore = array("DS_Store");

        echo "<a href='".admin_url('upload.php?page=log-flume&sync=1')."' class='button button-primary'>Sync now</a>";

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




            $result = $s3->listBuckets(array());


            foreach ($result['Buckets'] as $bucket) {

                echo "<pre>";
                print_r($bucket['Name']);
                echo "</pre>";

            }

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

                    $result = $s3->getObject([
                        'Bucket' => $selected_s3_bucket,
                        'Key'    => $file,
                        'SaveAs' => $wp_upload_dir['basedir']."/".$file
                    ]);


                }


                foreach($missing_remotely as $file){


                    echo $wp_upload_dir['basedir']."/".$file."<br>";
                    // $dest = 's3://';
                    // $manager = new \Aws\S3\Transfer($s3, $wp_upload_dir['basedir']."/".$file, $dest);
                    // $manager->transfer();

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
    }


}



$log_flume = new DevelopmentSyncing;






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

    // $result = $client->createBucket(array(
    //     'ACL' => 'string',
    //     // Bucket is required
    //     'Bucket' => 'string',
    //     'LocationConstraint' => 'string',
    //     'GrantFullControl' => 'string',
    //     'GrantRead' => 'string',
    //     'GrantReadACP' => 'string',
    //     'GrantWrite' => 'string',
    //     'GrantWriteACP' => 'string',
    // ));

    $new_bucket_name = 'logflume-tes2t';

    // $result = $s3->doesBucketExist( 'logflume-test', boolean $accept403 = true, array $options = array() )
    $does_bucket_exist = $s3->doesBucketExist( $new_bucket_name );


    if( $does_bucket_exist == false ){

        // Create a valid bucket and use a LocationConstraint
        $result = $s3->createBucket(array(
            'Bucket'             => $new_bucket_name,
            'LocationConstraint' => 'eu-west-2',
        ));

        // echo "<pre>";
        // print_r($result);
        // echo "</pre>";
        
        echo "<h3>Bucket created</h3>";

    }else{

        echo "<h3>Bucket already exists</h3>";

    };






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

	add_settings_section("section", "All Settings", null, "theme-options");

	add_settings_field("logflume_s3_bucket", "Select bucket", "display_s3_selection", "theme-options", "section");


    register_setting("section", "logflume_s3_bucket");


}

add_action("admin_init", "display_theme_panel_fields");
