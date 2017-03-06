<?php
/*
Plugin Name: Logsmith - Log Flume
Plugin URI: http://www.atomicsmash.co.uk
Description: ---
Version: 0.0.1
Author: Atomic Smash
Author URI: http://www.atomicsmash.co.uk
*/
if (!defined('ABSPATH'))exit; //Exit if accessed directly

use Aws\S3\S3Client;
// use Aws\S3\Exception\S3Exception;

class DevelopmentSyncing {


    function __construct() {
        add_action('load-upload.php', array($this, 'indexButton'));
        add_action('admin_menu', array($this, 'submenu') );
    }


    static function getButtonLabel() {
        // change here the label of your custom upload button
        return 'Sync Media to S3';
    }

    static function getUrl() {
        return add_query_arg( array('page'=>'my-custom-upload'), admin_url('upload.php') );
    }

    function admin_page() {
        ?>
        <div class="wrap">
        <h2>Page</h2>
        </div>
        <?php




        // define('AWS_ACCESS_KEY_ID','');
        // define('AWS_SECRET_ACCESS_KEY','');


        // Instantiate an Amazon S3 client.


        // Instantiate an Amazon S3 client.
        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'eu-west-2',
            'credentials' => [
                'key'    => '',
                'secret' => '',
            ],
        ]);

        $result = $s3->listBuckets(array());
        foreach ($result['Buckets'] as $bucket) {

            echo "<pre>";
            print_r($bucket);
            echo "</pre>";



        }

        $iterator = $s3->getIterator('ListObjects', array(
            'Bucket' => 'atomicsmash-development'
        ));

        foreach ($iterator as $object) {
            echo $object['Key'] . "<br>";
        }

        echo "<hr>";







            $root = 'wp-content/uploads/2017/';

            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
            );

            $paths = array($root);
            foreach ($iter as $path => $dir) {
                // if ($dir->isDir()) {
                    //new to check against array of files to not sync
                    $paths[] = $path;
                // }
            }

            echo "<pre>";
            print_r($paths);
            echo "</pre>";




        try {
            // $s3->putObject([
            //     'Bucket' => 'atomicsmash-development',
            //     'Key'    => 'upload.sh',
            //     'Body'   => fopen('upload.sh', 'r'),
            //     // 'ACL'    => 'public-read',
            // ]);

            $keyPrefix = '';
            $options = array(
                // 'params'      => array('ACL' => 'public-read'),
                'concurrency' => 20,
                'debug'       => true
            );


            // $s3->uploadDirectory('wp-content/uploads/2017', 'atomicsmash-development',$keyPrefix,$options);
            // $s3->uploadDirectory('wp-content/uploads/2017', 'atomicsmash-development');

            // http://docs.aws.amazon.com/aws-sdk-php/v3/guide/service/s3-transfer.html
            $source = 'wp-content/uploads/2017/';

            $dest = 's3://atomicsmash-development/foo';
            $manager = new \Aws\S3\Transfer($s3, $source, $dest);
            $manager->transfer();

        // readDir()

            // $uploadList = array_diff($localFiles, $s3Files); // returns green.jpg




        } catch (Aws\S3\Exception\S3Exception $e) {
            echo "There was an error uploading the file.\n $e";
        }






    }


    function submenu() {
        add_media_page( self::getButtonLabel(), self::getButtonLabel(), 'upload_files', 'my-custom-upload', array($this, 'admin_page') );
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
            $mybutton = sprintf($format, esc_url(self::getUrl()), esc_html(self::getButtonLabel()) );
            $safe_text .= $mybutton;
        }
        return $safe_text;
    }

}




$ui = new DevelopmentSyncing;
