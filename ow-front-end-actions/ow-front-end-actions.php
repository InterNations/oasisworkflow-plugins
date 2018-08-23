<?php
/*
  Plugin Name: Oasis Workflow Front End Actions
  Plugin URI: http://www.oasisworkflow.com
  Description: Allow users to execute Oasis Workflow Actions from the front end of the website.
  Version: 1.4
  Author: Nugget Solutions Inc.
  Author URI: http://www.nuggetsolutions.com
  Text Domain: owfrontendactions
  ----------------------------------------------------------------------
  Copyright 2011-2016 Nugget Solutions Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

// Declare some global constants
define( 'OW_FE_ACTIONS_VERSION', '1.4' );
define( 'OW_FE_ACTIONS_DB_VERSION', '1.4' );
define( 'OW_FE_ACTIONS_ROOT', dirname( __FILE__ ) );
define( 'OW_FE_ACTIONS_URL', plugins_url( '/', __FILE__ ) );
define( 'OW_FE_ACTIONS_BASE_FILE', basename( dirname( __FILE__ ) ) . '/ow-front-end-actions.php' );
define( 'OW_FE_ACTIONS_PATH', plugin_dir_path( __FILE__ ) ); //use for include files to other files
define( 'OW_FE_ACTIONS_STORE_URL', 'https://www.oasisworkflow.com/' );
define( 'OW_FE_ACTIONS_PRODUCT_NAME', 'Oasis Workflow Front End Actions' );
define( 'OW_FE_ACTIONS_CURRENT_THEME', get_stylesheet_directory() );

/**
 * Main Front End Actions Class
 *
 * @class OW_Front_End_Actions_Init
 * @since 1.0
 */
class OW_Front_End_Actions_Init {

   private $current_screen_pointers = array();

   public function __construct() {
      // run on activation of plugin
      register_activation_hook( __FILE__, array( $this, 'ow_front_end_actions_activate' ) );

      // run on unistallation of plugin
      register_uninstall_hook( __FILE__, array( __CLASS__, 'ow_front_end_actions_uninstall' ) );
      
      // Load plugin text domain
      add_action( 'init', array( $this, 'load_ow_front_end_textdomain' ) );

      // load the classes
      add_action( 'init', array( $this, 'load_all_classes' ) );

      // enqueue css and scripts
      add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_and_css' ) );

      // Check Oasis Workflow Pro plugin exist before user access admin area
      add_action( 'admin_init', array( $this, 'validate_parent_plugin_exists' ) );

      add_action( 'wp_head', array( $this, 'inject_admin_ajax_js' ) );

      add_action( 'admin_enqueue_scripts', array( $this, 'show_welcome_message_pointers' ) );

      add_action( 'wpmu_new_blog', array( $this, 'run_on_add_blog' ), 10, 6 );
      add_action( 'delete_blog', array( $this, 'run_on_delete_blog' ), 10, 2 );
      add_action( 'admin_init', array( $this, 'run_on_upgrade' ) );
   }

   /**
    * Define ajaxurl for Front End
    * @since 1.0
    */
   public function inject_admin_ajax_js() {
      global $post;
      ?>
      <script type="text/javascript">
         var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
      </script>
      <?php
   }

   /**
    * Create table on activation of plugin
    * @since 1.0
    */
   public function ow_front_end_actions_activate( $networkwide ) {
      global $wpdb;

      $this->run_on_activation();

      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         // check if it is a network activation - if so, run the activation function for each blog id
         if ( $networkwide ) {
            // Get all blog ids
            $blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
            foreach ( $blogids as $blog_id ) {
               switch_to_blog( $blog_id );
               $this->install_site_database();
               restore_current_blog();
            }
            return;
         }
      }
      $this->install_site_database();
   }

   /**
    * Load all the classes - as part of init action hook
    *
    * @since 1.0
    */
   public function load_all_classes() {

      // Utility class
      if ( ! class_exists( 'OW_Front_End_Actions_Utility' ) ) {
         include( OW_FE_ACTIONS_ROOT . '/includes/class-ow-fe-utilities.php');
      }

      // Load the widget class if editorial comment plugin is active
      if ( defined( 'OW_EDITORIAL_COMMENTS_ROOT' ) && ! class_exists( 'OW_Front_End_Actions_Widget' ) ) {
         include( OW_FE_ACTIONS_ROOT . '/includes/class-ow-fe-contextual-widget.php');
      }

      // Template class
      if ( ! class_exists( 'OW_Front_End_Inbox_Template' ) ) {
         include( OW_FE_ACTIONS_ROOT . '/includes/class-ow-fe-inbox-template.php');
      }

      // Inbox class
      if ( ! class_exists( 'OW_Front_End_Actions_Inbox' ) ) {
         include( OW_FE_ACTIONS_ROOT . '/includes/class-ow-fe-inbox.php');
      }

      // License class
      if ( ! class_exists( 'OW_Front_End_Actions_License_Settings' ) ) {
         include( 'includes/class-ow-fe-license-settings.php' );
      }

      // revision shortcode
      if ( ! class_exists( 'OW_Front_End_Actions_Make_Revision' ) ) {
         include( 'includes/class-ow-fe-make-revision.php' );
      }
      
      // submit to workflow shortcode
      if( !class_exists( 'OW_Front_End_Actions_Submit_To_Workflow' )) {
         include( 'includes/class-ow-fe-submit-to-workflow.php' );
      }
   }

   /**
    * Validate parent Plugin Oasis Workflow or Oasis Workflow Pro exist and activated
    * @access public
    * @since 1.0
    */
   public function validate_parent_plugin_exists() {
      $plugin = plugin_basename( __FILE__ );
      if ( ( !is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) ) && ( !is_plugin_active( 'oasis-workflow/oasiswf.php' ) ) ) {
         add_action( 'admin_notices', array( $this, 'show_oasis_workflow_missing_notice' ) );
         add_action( 'network_admin_notices', array( $this, 'show_oasis_workflow_missing_notice' ) );
         deactivate_plugins( $plugin );
         if ( isset( $_GET['activate'] ) ) :
            // Do not sanitize it because we are destroying the variables from URL
            unset( $_GET['activate'] );
         endif;
      }

      // check oasis workflow version
      // This plugin requires Oasis Workflow 2.1 or higher
      // With "Pro" version it needs Oasis Workflow Pro 3.3 or higher
      $pluginOptions = get_site_option( 'oasiswf_info' );
      if ( false !== $pluginOptions ) {
          if( ( is_plugin_active( 'oasis-workflow/oasiswf.php' ) && version_compare( $pluginOptions['version'], '2.1', '<' ) ) ||
             ( is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) && version_compare( $pluginOptions['version'], '3.3', '<' ) ) ) {
            add_action( 'admin_notices', array( $this, 'show_oasis_workflow_incompatible_notice' ) );
            add_action( 'network_admin_notices', array( $this, 'show_oasis_workflow_incompatible_notice' ) );
            deactivate_plugins( $plugin );
            if ( isset( $_GET['activate'] ) ) :
               // Do not sanitize it because we are destroying the variables from URL
               unset( $_GET['activate'] );
            endif;
         }
      }
   }

   /**
    * If Oasis Workflow or Oasis Workflow Pro plugin is not installed or activated then throw the error
    *
    * @access public
    * @return mixed error_message, an array containing the error message
    *
    * @since 1.0 initial version
    */
   public function show_oasis_workflow_missing_notice() {
      $plugin_error = OW_Front_End_Actions_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow Front End Actions Add-on requires Oasis Workflow or Oasis Workflow Pro plugin to be installed and activated.'
              ) );
      echo $plugin_error;
   }

   /**
    * If the Oasis Workflow version is less than 2.1  or Oasis Workflow Pro is less than 3.3
    * then throw the incompatible notice
    *
    * @access public
    * @return mixed error_message, an array containing the error message
    *
    * @since 1.0 initial version
    */
   public function show_oasis_workflow_incompatible_notice() {
      $plugin_error = OW_Front_End_Actions_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow Front End Actions Add-on requires requires Oasis Workflow 2.1 or higher and with pro version it requires Oasis Workflow pro 3.3 or higher.'
              ) );
      echo $plugin_error;
   }

   /**
    * Called on activation.
    * Creates the site_options (required for all the sites in a multi-site setup)
    * If the current version doesn't match the new version, runs the upgrade
    * Also created the cron schedules for - auto submit and reminder emails
    * @since 1.0
    */
   public function run_on_activation() {
      $pluginInfo = get_site_option( 'oasiswf_fe_actions_info' );
      if ( false === $pluginInfo ) {
         $oasiswf_fe_actions_info = array(
             'version' => OW_FE_ACTIONS_VERSION,
             'db_version' => OW_FE_ACTIONS_DB_VERSION
         );

         update_site_option( 'oasiswf_fe_actions_info', $oasiswf_fe_actions_info );
      } else if ( OW_FE_ACTIONS_VERSION != $pluginInfo['version'] ) {
         $this->run_on_upgrade();
      }
   }
   
   /**
    * Runs on uninstall
    *
    * Deactivate the licence, delete site specific data, delete database tables
    * Takes into account both a single site and multi-site installation
    *
    * @since 1.0 initial version
    */
   public static function ow_front_end_actions_uninstall() {
      global $wpdb;

      self::run_on_uninstall();

      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         //Get all blog ids; foreach them and call the uninstall procedure on each of them
         $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

         //Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
         foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );

            self::deactivate_the_license();

            if ( $wpdb->query( "SHOW TABLES FROM " . $wpdb->dbname . " LIKE '" . $wpdb->prefix . "fc_%'" ) ) {
               self::delete_for_site();
            }
            restore_current_blog();
         }
         return;
      }

      self::delete_for_site();
      self::deactivate_the_license();
   }

   /**
    * TODO: Test this function isnt it translating the string?
    * Load front end textdomain before the UI appears
    *
    * @since 1.0
    */
   public function load_ow_front_end_textdomain() {
      load_plugin_textdomain( 'owfrontendactions', false, basename( dirname( __FILE__ ) ) . '/languages' );
   }

   public function run_on_upgrade() {
      // TODO: place holder for future upgrades
      
      // update the version value
      $oasiswf_fe_actions_info = array(
             'version' => OW_FE_ACTIONS_VERSION,
             'db_version' => OW_FE_ACTIONS_DB_VERSION
         );
      update_site_option( 'oasiswf_fe_actions_info', $oasiswf_fe_actions_info );
   }

   public function install_site_database() {
      global $wpdb;
      if ( ! empty( $wpdb->charset ) )
         $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
      if ( ! empty( $wpdb->collate ) )
         $charset_collate .= " COLLATE {$wpdb->collate}";
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   }

   public static function deactivate_the_license() {
      // deactivate the license
      $license = trim( get_site_option( 'oasiswf_front_end_license_key' ) );

      // data to send in our API request
      $api_params = array(
          'edd_action' => 'deactivate_license',
          'license' => $license,
          'item_name' => urlencode( OW_FE_ACTIONS_PRODUCT_NAME ) // the name of our product in EDD
      );

      // Call the custom API.
      $response = wp_remote_post( OW_FE_ACTIONS_STORE_URL, array( 'timeout' => 15, 'body' => $api_params, 'sslverify' => false ) );

      delete_option( 'oasiswf_fe_actions_info' );
      if ( get_option( 'oasiswf_front_end_license_status' ) ) {
         delete_option( 'oasiswf_front_end_license_status' );
      }

      if ( get_option( 'oasiswf_front_end_license_key' ) ) {
         delete_option( 'oasiswf_front_end_license_key' );
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
   public static function run_on_uninstall() {
      if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) )
         exit();

      // delete the dismissed_wp_pointers entry for this plugin
      $blog_users = get_users( 'role=administrator' );
      foreach ( $blog_users as $user ) {
         $dismissed = explode( ',', (string) get_user_meta( $user->ID, 'dismissed_wp_pointers', true ) );
         if ( ( $key = array_search( "owf_front_end_actions_install", $dismissed ) ) !== false ) {
            unset( $dismissed[$key] );
         }

         $updated_dismissed = implode( ",", $dismissed );
         update_user_meta( $user->ID, "dismissed_wp_pointers", $updated_dismissed );
      }
   }

   /**
    * Delete site specific data created by this plugin
    *
    * @since 1.0
    */
   private static function delete_for_site() {
      if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
         exit();
      }
   }

   /**
    * Create site specific data when a new site is added to a multi-site setup
    *
    * @since 1.0 initial version
    */
   public function run_on_add_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
      global $wpdb;
      if ( is_plugin_active_for_network( basename( dirname( __FILE__ ) ) . '/ow-front-end-actions.php' ) ) {
         $old_blog = $wpdb->blogid;
         switch_to_blog( $blog_id );
         $this->install_site_database();
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
    * Enqueue scripts required for sign off and inbox actions
    *
    * @since 1.0 initial version
    */
   public function enqueue_scripts_and_css() {

      // enqueue script after ow-editorial-comment-js for show_popup function later use
      wp_register_script( 'ow-fe-actions-js', OW_FE_ACTIONS_URL . 'assets/js/ow-fe-actions.js', array( 'jquery' ), OW_FE_ACTIONS_VERSION );

      wp_register_script( 'owf-workflow-util', OASISWF_URL . 'js/pages/workflow-util.js', '', OASISWF_VERSION, true );


      wp_nonce_field( 'owf_inbox_ajax_nonce', 'owf_inbox_ajax_nonce' );
      wp_nonce_field( 'owf_claim_process_ajax_nonce', 'owf_claim_process_ajax_nonce' );

      wp_enqueue_script( 'jquery-ui-datepicker' );
      wp_enqueue_script( 'ow-fe-actions-js' );

      wp_enqueue_script( 'owf-workflow-util' );

      OW_Plugin_Init::enqueue_and_localize_inbox_script();

      $ow_process_flow = new OW_Process_Flow();
      $ow_process_flow->enqueue_and_localize_submit_step_script();

      wp_enqueue_style( 'owfes-jqueryui-css', 'http://code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css', false, '1.11.2', 'all' );

      wp_enqueue_style( 'ow-fe-actions-css', OW_FE_ACTIONS_URL . 'assets/css/ow-fe-actions.css', false, OW_FE_ACTIONS_VERSION, 'all' );

      OW_Plugin_Init::enqueue_and_localize_simple_modal_script();

      // Check whether parent plugin oasis workflow pro css exist
      if ( ! wp_style_is( 'owf-oasis-workflow-css' ) ) {
         wp_enqueue_style( 'owf-oasis-workflow-css', OASISWF_URL . 'css/pages/oasis-workflow.css', false, OW_FE_ACTIONS_VERSION, 'all' );
      }

      // if editorial comment plugin is active then and then enqueue the css and js file
      if ( defined( 'OW_EDITORIAL_COMMENTS_ROOT' ) ) {
         OW_Comments_Plugin_Init::include_contextual_comment_widget_scripts();
      }
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

      // Format : array( 'screen_id' => array( 'pointer_id' => array([options : target, content, position...]) ) );

      $welcome_title = __( "Welcome to Oasis Workflow Front End Actions", "owfrontendactions" );
      $oasiswf_url = defined( 'OASISWF_URL' ) ? OASISWF_URL : '';
      $img_html = "<img src='" . $oasiswf_url . "img/small-arrow.gif" . "' style='border:0px;' />";
      $welcome_message_1 = sprintf( __( "To get started with Front End Actions, activate the add-on by providing a valid license key on Workflows %s Settings, License tab.", "owfrontendactions" ), $img_html );
      $welcome_message_2 =  __( "Once the license is activated you will be able to use the shortcodes on any page.", "owfrontendactions" ) ;
      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         $default_pointers = array(
             'toplevel_page_oasiswf-inbox' => array(
                 'owf_front_end_actions_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p><p>' . $welcome_message_2 . '</p>',
                     'position' => array( 'edge' => 'left', 'align' => 'center' ),
                 )
             )
         );
      } else {
         $default_pointers = array(
             'plugins' => array(
                 'owf_front_end_actions_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p><p>' . $welcome_message_2 . '</p>',
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
    * Plugin Update notifier
    */
   public function ow_front_end_actions_plugin_updater() {

      // setup the updater
      if ( class_exists( 'OW_Plugin_Updater' ) ) {
         // setup the updater
         $edd_oasis_front_end_plugin_updater = new OW_Plugin_Updater( OW_FE_ACTIONS_STORE_URL, __FILE__, array(
               'version' => OW_FE_ACTIONS_VERSION, // current version number
               'license' => trim( get_option( 'oasiswf_front_end_license_key' ) ), // license key (used get_option above to retrieve from DB)
               'item_name' => OW_FE_ACTIONS_PRODUCT_NAME, // name of this plugin
               'author' => 'Nugget Solutions Inc.'  // author of this plugin
            )
         );
      }
   }

}

// Initialize the Front End Actions Init Class
$oasiswf_fe_actions_init = new OW_Front_End_Actions_Init();
add_action( 'admin_init', array( $oasiswf_fe_actions_init, 'ow_front_end_actions_plugin_updater' ) );
?>