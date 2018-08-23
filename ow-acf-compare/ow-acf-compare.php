<?php
/*
  Plugin Name: Oasis Workflow ACF Compare
  Plugin URI: http://www.oasisworkflow.com
  Description: Compare Advanced Custom Fields between the original and revised article.
  Version: 1.3
  Author: Nugget Solutions Inc.
  Author URI: http://www.nuggetsolutions.com
  Text Domain: owacfcompare
  ----------------------------------------------------------------------
  Copyright 2011-2017 Nugget Solutions Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

define( 'OW_ACF_COMPARE_VERSION', '1.3' );
define( 'OW_ACF_COMPARE_ROOT', dirname( __FILE__ ) );
define( 'OW_ACF_COMPARE_URL', plugins_url( '/', __FILE__ ) );
define( 'OW_ACF_COMPARE_STORE_URL', 'https://www.oasisworkflow.com/' );
define( 'OW_ACF_COMPARE_PRODUCT_NAME', 'Oasis Workflow ACF Compare' );

/**
 * Main ACF Compare Class
 *
 * @class OW_ACF_Compare_Init
 * @since 1.0
 */
class OW_ACF_Compare_Init {

   private $current_screen_pointers = array();

   /**
    * Constructor of class
    */
   public function __construct() {
      $this->include_files();

      // run on activation of plugin
      register_activation_hook( __FILE__, array( $this, 'ow_acf_compare_plugin_activation' ) );
      register_uninstall_hook( __FILE__, array( __CLASS__, 'ow_acf_compare_plugin_uninstall' ) );
      
      // Load plugin text domain
      add_action( 'init', array( $this, 'load_ow_acf_compare_textdomain' ) );

      add_action( 'admin_init', array( $this, 'validate_parent_plugin_exists' ) );

      add_action( 'admin_enqueue_scripts', array( $this, 'show_welcome_message_pointers' ) );

      /* add/delete new subsite */
      add_action( 'wpmu_new_blog', array( $this, 'run_on_add_blog' ), 10, 6 );
      add_action( 'delete_blog', array( $this, 'run_on_delete_blog' ), 10, 2 );
   }

   /**
    * Include required core files used in admin
    * @since 1.0
    */
   public function include_files() {

      // utility class
      if ( ! class_exists( 'OW_ACF_Compare_Utility' ) ) {
         include_once( 'includes/class-ow-utility.php' );
      }

      // if class is exist then this will not include anymore
      if ( ! class_exists( 'OW_ACF_Compare_License_Settings' ) ) {
         include_once( 'includes/class-ow-acf-compare-license-settings.php' );
      }

      // service class
      if ( ! class_exists( 'OW_ACF_Comparator' ) ) {
         include_once( 'includes/class-ow-acf-comparator.php' );
      }

      // revision service class
      if ( ! class_exists( 'OW_ACF_Compare_Revision' ) ) {
         include_once( 'includes/class-ow-acf-compare-revision-service.php' );
      }
   }

   /**
    * Create table on activation of plugin
    * @since 1.0
    */
   public function ow_acf_compare_plugin_activation( $networkwide ) {
      global $wpdb;
      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         // check if it is a network activation - if so, run the activation function for each blog id
         if ( $networkwide ) {
            // Get all blog ids
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
            foreach ( $blog_ids as $blog_id ) {
               switch_to_blog( $blog_id );
               $this->create_tables();
               restore_current_blog();
            }
            return;
         }
      }

      // for single site only
      $this->create_tables();
   }
   

   /**
    * Validate parent Plugin Oasis Workflow or Oasis Workflow Pro exist and activated
    * @access public
    * @since 1.0
    */
   public function validate_parent_plugin_exists() {
      $plugin = plugin_basename( __FILE__ );
      if( ( !is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) ) &&  ( ! is_plugin_active( 'oasis-workflow/oasiswf.php' ) ) ) {
         add_action( 'admin_notices', array( $this, 'show_oasis_workflow_pro_missing_notice' ) );
         add_action( 'network_admin_notices', array( $this, 'show_oasis_workflow_pro_missing_notice' ) );
         deactivate_plugins( $plugin );
         if ( isset( $_GET['activate'] ) ) {
            // Do not sanitize it because we are destroying the variables from URL
            unset( $_GET['activate'] );
         }
      }

     // check oasis workflow version
      // This plugin requires Oasis Workflow 2.2 or higher
      // With "Pro" version it needs Oasis Workflow Pro 3.4 or higher
      $pluginOptions = get_site_option( 'oasiswf_info' );
      if ( is_array( $pluginOptions ) && ! empty( $pluginOptions ) ) {
         if( ( is_plugin_active( 'oasis-workflow/oasiswf.php' ) && version_compare( $pluginOptions['version'], '2.2', '<' ) ) ||
            ( is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) && version_compare( $pluginOptions['version'], '3.4', '<' ) ) ) {
            add_action( 'admin_notices', array( $this, 'show_oasis_workflow_incompatible_notice' ) );
            add_action( 'network_admin_notices', array( $this, 'show_oasis_workflow_incompatible_notice' ) );
            deactivate_plugins( $plugin );
            if ( isset( $_GET['activate'] ) ) {
               // Do not sanitize it because we are destroying the variables from URL
               unset( $_GET['activate'] );
            }
         }
      }
   }

   /**
    * If Oasis Workflow or Oasis Workflow Pro plugin is not installed or activated
    * then throw the error
    *
    * @access public
    * @return mixed error_message, an array containing the error message
    *
    * @since 1.0 initial version
    */
   public function show_oasis_workflow_pro_missing_notice() {
      $plugin_error = OW_ACF_Compare_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow ACF Compare Add-on requires Oasis Workflow or Oasis Workflow Pro plugin to be installed and activated.'
              ) );
      echo $plugin_error;
   }

   /**
    * If the Oasis Workflow version is less than 2.2  or Oasis Workflow Pro is less than 3.4
    * then throw the incompatible notice
    * @access public
    * @return mixed error_message, an array containing the error message
    *
    * @since 1.0 initial version
    */
   public function show_oasis_workflow_incompatible_notice() {
      $plugin_error = OW_ACF_Compare_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow ACF Compare Add-on requires Oasis Workflow 2.2 or higher and with pro version it requires Oasis Workflow Pro 3.4 or higher.'
              ) );
      echo $plugin_error;
   }
   
  
   /**
    * Runs on uninstall
    *
    * Deactivate the licence, delete site specific data, delete database tables
    * Takes into account both a single site and multi-site installation
    *
    * @since 1.0 initial version
    */
   public static function ow_acf_compare_plugin_uninstall() {
      global $wpdb;

      self::run_on_uninstall();

      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         //Get all blog ids; foreach them and call the uninstall procedure on each of them
         $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

         //Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
         foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            // Deactivate the license
            self::deactivate_the_license();

            if ( $wpdb->query( "SHOW TABLES FROM " . $wpdb->dbname . " LIKE '" . $wpdb->prefix . "fc_%'" ) ) {
               self::delete_for_site();
            }
            restore_current_blog();
         }
         return;
      }
      self::deactivate_the_license();
      self::delete_for_site();
   }

   /**
    * TODO: Test this function isnt it translating the string?
    * Load ACF Compare textdomain before the UI appears
    *
    * @since 1.0
    */
   public function load_ow_acf_compare_textdomain() {
      load_plugin_textdomain( 'owacfcompare', false, basename( dirname( __FILE__ ) ) . '/languages' );
   }

   /**
    * Deactivate the license
    *
    * @since 1.0 initial version
    */
   public static function deactivate_the_license() {
      $license = trim( get_option( 'oasiswf_acf_compare_license_key' ) );

      // data to send in our API request
      $api_params = array(
          'edd_action' => 'deactivate_license',
          'license' => $license,
          'item_name' => urlencode( OW_ACF_COMPARE_PRODUCT_NAME ) // the name of our product in EDD
      );

      // Call the custom API.
      $response = wp_remote_post( OW_ACF_COMPARE_STORE_URL, array( 'timeout' => 15, 'body' => $api_params, 'sslverify' => false ) );

      if ( get_option( 'oasiswf_acf_compare_license_status' ) ) {
         delete_option( 'oasiswf_acf_compare_license_status' );
      }

      if ( get_option( 'oasiswf_acf_compare_license_key' ) ) {
         delete_option( 'oasiswf_acf_compare_license_key' );
      }
   }

   /**
    * Runs on uninstall
    *
    * It deletes site-wide data, like dismissed_wp_pointers,
    * It also deletes any wp_options which are not site specific
    *
    * @since 1.0 initial version
    */
   private static function run_on_uninstall() {
      if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) )
         exit();

      // delete the dismissed_wp_pointers entry for this plugin
      $blog_users = get_users( 'role=administrator' );
      foreach ( $blog_users as $user ) {
         $dismissed = explode( ',', (string) get_user_meta( $user->ID, 'dismissed_wp_pointers', true ) );
         if ( ( $key = array_search( "owf_acf_compare_install", $dismissed ) ) !== false ) {
            unset( $dismissed[$key] );
         }

         $updated_dismissed = implode( ",", $dismissed );
         update_user_meta( $user->ID, "dismissed_wp_pointers", $updated_dismissed );
      }
   }

   /**
    * Delete site specific data, like database tables, wp_options etc
    *
    * @since 1.0 initial version
    */
   private static function delete_for_site() {
      if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
         exit();
      }

      global $wpdb;
   }

   /**
    * Create site specific data when a new site is added to a multi-site setup
    *
    * @since 1.0 initial version
    */
   public function run_on_add_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
      global $wpdb;
      // TODO : check if plugin is active for the network before adding it to the site
      if ( is_plugin_active_for_network( basename( dirname( __FILE__ ) ) . '/ow-acf-compare.php' ) ) {

         switch_to_blog( $blog_id );
         $this->create_tables();
         restore_current_blog();
      }
   }

   /**
    * Delete site specific data when a site is removed from a multi-site setup
    *
    * @since 1.0 initial version
    */
   public function run_on_delete_blog( $blog_id, $drop ) {
      global $wpdb;

      switch_to_blog( $blog_id );

      self::deactivate_the_license();
      self::delete_for_site();

      restore_current_blog();
   }

   /**
    * Retrieves pointers for the current admin screen. Use the 'owf_admin_pointers' hook to add your own pointers.
    *
    * @return array Current screen pointers
    * @since 1.0
    */
   private function get_current_screen_pointers() {
      $pointers = '';

      $screen = get_current_screen();
      $screen_id = $screen->id;

      $welcome_title = __( "Welcome to Oasis Workflow ACF Compare", "owacfcompare" );
      $url = defined( 'OASISWF_URL' ) ? OASISWF_URL : '';
      $img_html = "<img src='" . $url . "img/small-arrow.gif" . "' style='border:0px;' />";
      $blurb_img_html = "<img src='" . OW_ACF_COMPARE_URL . "assets/img/comments-icon.png" . "' style='border:0px;' />";
      $welcome_message_1 = sprintf( __( "To get started with ACF Compare, activate the add-on by providing a valid license key on Workflows %s Settings, License tab.", "owacfcompare" ), $img_html );
      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         $default_pointers = array(
             'toplevel_page_oasiswf-inbox' => array(
                 'owf_acf_compare_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p>',
                     'position' => array( 'edge' => 'left', 'align' => 'center' ),
                 )
             )
         );
      } else {
         $default_pointers = array(
             'plugins' => array(
                 'owf_acf_compare_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p>',
                     'position' => array( 'edge' => 'left', 'align' => 'center' ),
                 )
             )
         );
      }

      if ( ! empty( $default_pointers[$screen_id] ) )
         $pointers = $default_pointers[$screen_id];

      return apply_filters( 'owf_admin_pointers', $pointers, $screen_id );
   }

   /**
    * Show the welcome message on plugin activation.
    *
    * @since 1.0
    */
   public function show_welcome_message_pointers() {
      // Don't run on WP < 3.3
      if ( get_bloginfo( 'version' ) < '3.3' ) {
         return;
      }

      // only show this message to the users who can activate plugins
      if ( ! current_user_can( 'activate_plugins' ) ) {
         return;
      }

      $pointers = $this->get_current_screen_pointers();

      // No pointers? Don't do anything
      if ( empty( $pointers ) || ! is_array( $pointers ) )
         return;

      // Get dismissed pointers.
      // Note : dismissed pointers are stored by WP in the "dismissed_wp_pointers" user meta.

      $dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
      $valid_pointers = array();

      // Check pointers and remove dismissed ones.
      foreach ( $pointers as $pointer_id => $pointer ) {
         // Sanity check
         if ( in_array( $pointer_id, $dismissed ) || empty( $pointer ) || empty( $pointer_id ) || empty( $pointer['target'] ) || empty( $pointer['content'] ) )
            continue;

         // Add the pointer to $valid_pointers array
         $valid_pointers[$pointer_id] = $pointer;
      }

      // No valid pointers? Stop here.
      if ( empty( $valid_pointers ) )
         return;

      // Set our class variable $current_screen_pointers
      $this->current_screen_pointers = $valid_pointers;

      // Add our javascript to handle pointers
      add_action( 'admin_print_footer_scripts', array( $this, 'display_pointers' ) );

      // Add pointers style and javascript to queue.
      wp_enqueue_style( 'wp-pointer' );
      wp_enqueue_script( 'wp-pointer' );
   }

   /**
    * Finally prints the javascript that'll make our pointers alive.
    *
    * @since 1.0
    */
   public function display_pointers() {
      if ( ! empty( $this->current_screen_pointers ) ):
         ?>
         <script type="text/javascript">// <![CDATA[
            jQuery( document ).ready( function ( $ ) {
               if ( typeof ( jQuery().pointer ) != 'undefined' ) {
         <?php foreach ( $this->current_screen_pointers as $pointer_id => $data ): ?>
                     $( '<?php echo $data['target'] ?>' ).pointer( {
                        content : '<?php echo addslashes( $data['content'] ) ?>',
                        position : {
                           edge : '<?php echo addslashes( $data['position']['edge'] ) ?>',
                           align : '<?php echo addslashes( $data['position']['align'] ) ?>'
                        },
                        close : function () {
                           $.post( ajaxurl, {
                              pointer : '<?php echo addslashes( $pointer_id ) ?>',
                              action : 'dismiss-wp-pointer'
                           } );
                        }
                     } ).pointer( 'open' );
         <?php endforeach ?>
               }
            } );
            // ]]></script>
         <?php
      endif;
   }

   /**
    * Set up the database tables for the plugin.
    * @access private
    * @global type $wpdb
    * @since 1.0
    */
   private function create_tables() {
      global $wpdb;

      // Disables showing of database errors
      $wpdb->hide_errors();

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

      $collate = '';
      if ( $wpdb->has_cap( 'collation' ) ) {
         if ( ! empty( $wpdb->charset ) ) {
            $collate .= "DEFAULT CHARACTER SET {$wpdb->charset}";
         }
         if ( ! empty( $wpdb->collate ) ) {
            $collate .= " COLLATE {$wpdb->collate}";
         }
      }

      // TODO: table creation goes here
   }

   /**
    * Plugin Update notifier
    */
   public function ow_acf_compare_plugin_updater() {

      // setup the updater
      if ( class_exists( 'OW_Plugin_Updater' ) ) {
         $edd_oasis_acf_compare_updater = new OW_Plugin_Updater( OW_ACF_COMPARE_STORE_URL, __FILE__, array(
               'version'   => OW_ACF_COMPARE_VERSION, // current version number
               'license'   => trim( get_option( 'oasiswf_acf_compare_license_key' ) ), // license key (used get_option above to retrieve from DB)
               'item_name' => OW_ACF_COMPARE_PRODUCT_NAME, // name of this plugin
               'author'    => 'Nugget Solutions Inc.' // author of this plugin
            )
         );
      }
   }

}

// Initialize ACF Compare Class
$ow_acf_compare_init = new OW_ACF_Compare_Init();
add_action( 'admin_init', array( $ow_acf_compare_init, 'ow_acf_compare_plugin_updater' ) );
?>