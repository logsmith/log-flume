<?php
/*
Plugin Name: Logsmith - Log Flume
Plugin URI: http://www.atomicsmash.co.uk
Description: Sync development media files
Version: 0.0.1
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


    function __construct() {
        add_action( 'load-upload.php', array($this, 'indexButton'));
        add_action( 'admin_menu', array($this, 'submenu') );
        add_action( 'admin_init', array($this, 'wporg_settings_init') );

    }


    static function getButtonLabel() {
        // change here the label of your custom upload button
        return 'Sync Media to S3';
    }

    static function getUrl() {
        return add_query_arg( array('page'=>'my-custom-upload'), admin_url('upload.php') );
    }

    function wporg_settings_init() {
        // register a new setting for "wporg" page
        register_setting( 'wporg', 'wporg_options' );

        // register a new section in the "wporg" page
        add_settings_section(
            'wporg_section_developers',
            __( 'The Matrix has you.', 'wporg' ),
            'wporg_section_developers_cb',
            'wporg'
        );

        // register a new field in the "wporg_section_developers" section, inside the "wporg" page
        add_settings_field(
            'wporg_field_pill', // as of WP 4.6 this value is used only internally
            // use $args' label_for to populate the id inside the callback
            __( 'Pill', 'wporg' ),
            'wporg_field_pill_cb',
            'wporg',
            'wporg_section_developers',
            [
                'label_for' => 'wporg_field_pill',
                'class' => 'wporg_row',
                'wporg_custom_data' => 'custom',
            ]
        );
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

    function admin_page() {

        $ignore = array("DS_Store");

        ?>
        <div class="wrap">
        <h2>Sync media library to S3</h2>
        </div>
        <?php

        // check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
        return;
        }

        // add error/update messages

        // check if the user have submitted the settings
        // wordpress will add the "settings-updated" $_GET parameter to the url
        if ( isset( $_GET['settings-updated'] ) ) {
        // add settings saved message with the class of "updated"
        add_settings_error( 'wporg_messages', 'wporg_message', __( 'Settings Saved', 'wporg' ), 'updated' );
        }

        // show error/update messages
        settings_errors( 'wporg_messages' );
        ?>
        <div class="wrap">
        <form action="options.php" method="post">
        <?php
        // output security fields for the registered setting "wporg"
        settings_fields( 'wporg' );
        // output setting sections and their fields
        // (sections are registered for "wporg", each field is registered to a specific section)
        do_settings_sections( 'wporg' );
        // output save settings button
        submit_button( 'Save Settings' );
        ?>
        </form>
        </div>
        <?php


        if(isset($_GET['sync'])){

            // define('AWS_ACCESS_KEY_ID','');
            // define('AWS_SECRET_ACCESS_KEY','');


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

            // echo "<pre>";
            // print_r($result);
            // echo "</pre>";



            foreach ($result['Buckets'] as $bucket) {

                // echo "<pre>";
                // print_r($bucket);
                // echo "</pre>";

            }

            echo "<hr>";
            echo "<h3>S3 Files</h3>";



            $iterator = $s3->getIterator('ListObjects', array(
                'Bucket' => AWS_BUCKET
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
                // $s3->putObject([
                //     'Bucket' => AWS_BUCKET,
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


                // $s3->uploadDirectory('wp-content/uploads/2017', AWS_BUCKET,$keyPrefix,$options);
                // $s3->uploadDirectory('wp-content/uploads/2017', AWS_BUCKET);

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
                        'Bucket' => AWS_BUCKET,
                        'Key'    => $file,
                        'SaveAs' => $wp_upload_dir['basedir']."/".$file
                    ]);


                }


                foreach($missing_remotely as $file){


                    echo $wp_upload_dir['basedir']."/".$file."<br>";
                    // $dest = 's3://';
                    // $manager = new \Aws\S3\Transfer($s3, $wp_upload_dir['basedir']."/".$file, $dest);
                    // $manager->transfer();

                    // $s3->putObject([
                    //     'Bucket' => AWS_BUCKET,
                    //     'Key'    => $wp_upload_dir['basedir']."/".$file,
                    //     'Body'   => fopen('upload.sh', 'r'),
                    //     // 'ACL'    => 'public-read',
                    // ]);
                    $result = $s3->putObject(array(
                        'Bucket' => AWS_BUCKET,
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





/**
 * @internal never define functions inside callbacks.
 * these functions could be run multiple times; this would result in a fatal error.
 */

/**
 * custom option and settings
 */

/**
 * register our wporg_settings_init to the admin_init action hook
 */
// add_action( 'admin_init', 'wporg_settings_init' );

/**
 * custom option and settings:
 * callback functions
 */

// developers section cb

// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function wporg_section_developers_cb( $args ) {
 ?>
 <p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Follow the white rabbit.', 'wporg' ); ?></p>
 <?php
}

// pill field cb

// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the "label_for" key value is used for the "for" attribute of the <label>.
// the "class" key value is used for the "class" attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function wporg_field_pill_cb( $args ) {
    // get the value of the setting we've registered with register_setting()
    $options = get_option( 'wporg_options' );
    // output the field
    ?>
    <select id="<?php echo esc_attr( $args['label_for'] ); ?>"
    data-custom="<?php echo esc_attr( $args['wporg_custom_data'] ); ?>"
    name="wporg_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
    >
    <option value="red" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'red', false ) ) : ( '' ); ?>>
    <?php esc_html_e( 'red pill', 'wporg' ); ?>
    </option>
    <option value="blue" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'blue', false ) ) : ( '' ); ?>>
    <?php esc_html_e( 'blue pill', 'wporg' ); ?>
    </option>
    </select>
    <p class="description">
    <?php esc_html_e( 'You take the blue pill and the story ends. You wake in your bed and you believe whatever you want to believe.', 'wporg' ); ?>
    </p>
    <p class="description">
    <?php esc_html_e( 'You take the red pill and you stay in Wonderland and I show you how deep the rabbit-hole goes.', 'wporg' ); ?>
    </p>
    <?php
    }
