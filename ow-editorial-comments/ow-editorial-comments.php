<?php
/*
  Plugin Name: Oasis Workflow Editorial Comments
  Plugin URI: http://www.oasisworkflow.com
  Description: Contextual Editorial Comments for Oasis Workflow
  Version: 1.9
  Author: Nugget Solutions Inc.
  Author URI: http://www.nuggetsolutions.com
  Text Domain: oweditorialcomments
  ----------------------------------------------------------------------
  Copyright 2011-2017 Nugget Solutions Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

define( 'OW_EDITORIAL_COMMENTS_VERSION', '1.9' );
define( 'OW_EDITORIAL_COMMENTS_DB_VERSION', '1.9' );
define( 'OW_EDITORIAL_COMMENTS_ROOT', dirname( __FILE__ ) );
define( 'OW_EDITORIAL_COMMENTS_URL', plugins_url( '/', __FILE__ ) );
define( 'OW_EDITORIAL_COMMENTS_STORE_URL', 'https://www.oasisworkflow.com/' );
define( 'OW_EDITORIAL_COMMENTS_PRODUCT_NAME', 'Oasis Workflow Editorial Comments' );

// Set up localization
load_plugin_textdomain( 'oweditorialcomments', false, basename( dirname( __FILE__ ) ) . '/languages' );

/**
 * include utility classes
 * @since 1.0
 */
if ( ! class_exists( 'OW_Editorial_Comments_Utility' ) ) {
   include( 'includes/class-ow-utility.php' );
}

/**
 * Main Editorial Comments Class
 *
 * @class OW_Comments_Plugin_Init
 * Since 1.0
 */
class OW_Comments_Plugin_Init {

   private $current_screen_pointers = array();

   /**
    * Constructor of class
    */
   public function __construct() {
      $this->include_files();

      // run on activation of plugin
      register_activation_hook( __FILE__, array( $this, 'ow_editorial_comments_plugin_activation' ) );
      register_uninstall_hook( __FILE__, array( 'OW_Comments_Plugin_Init', 'ow_editorial_comments_plugin_uninstall' ) );
      
      add_action( 'admin_init', array( $this, 'validate_parent_plugin_exists' ) );

      // Register editorial comment button on TinyMCE Toolbar add
      add_action( 'admin_head', array( $this, 'add_editorial_comment_button_to_mce_editor' ) );

      // register main js file for post/page and workflow inbox and history page
      add_action( 'admin_print_scripts', array( $this, 'add_js_files' ) );

      add_action( 'admin_enqueue_scripts', array( $this, 'show_welcome_message_pointers' ) );

      /* add/delete new subsite */
      add_action( 'wpmu_new_blog', array( $this, 'run_on_add_blog' ), 10, 6 );
      add_action( 'delete_blog', array( $this, 'run_on_delete_blog' ), 10, 2 );
      
      // Enqueue css and js file in front end
      add_action( 'wp_enqueue_scripts', array( $this, 'add_front_end_files' ) );
      
   }

   /**
    * Include required core files used in admin
    * @since 1.0
    */
   public function include_files() {
      // if class is exist then this will not include anymore
      if ( ! class_exists( 'OW_Editorial_Comment' ) ) {
         include_once( 'includes/class-ow-editorial-comment.php' );
      }

      if ( ! class_exists( 'OW_Comments_Service' ) ) {
         include_once( 'includes/class-editorial-comments-service.php' );
      }

      if ( ! class_exists( 'OW_Comments_Widget' ) ) {
         include_once( 'includes/class-editorial-comments-widget.php' );
      }

      if ( ! class_exists( 'OW_Editorial_Comments_License_Settings' ) ) {
         include( 'includes/class-ow-editorial-comments-license-settings.php' );
      }
   }

   /**
    * Create table on activation of plugin
    * @since 1.0
    */
   public function ow_editorial_comments_plugin_activation( $networkwide ) {
      global $wpdb;
      
      $this->run_on_activation();
      
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
      if( ( ! is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) ) &&  ( ! is_plugin_active( 'oasis-workflow/oasiswf.php' ) ) ) {
         add_action( 'admin_notices', array( $this, 'show_oasis_workflow_pro_missing_notice' ) );
         add_action( 'network_admin_notices', array( $this, 'show_oasis_workflow_pro_missing_notice' ) );
         deactivate_plugins( $plugin );
         if ( isset( $_GET['activate'] ) ) {
            // Do not sanitize it because we are destroying the variables from URL
            unset( $_GET['activate'] );
         }
      }

      // check oasis workflow version
      // This plugin requires Oasis Workflow 2.1 or higher
      // With "Pro" version it needs Oasis Workflow Pro 3.6 or higher
      $pluginOptions = get_site_option( 'oasiswf_info' );
      if ( is_array( $pluginOptions ) && ! empty( $pluginOptions ) ) {    
        if( ( is_plugin_active( 'oasis-workflow/oasiswf.php' ) && version_compare( $pluginOptions['version'], '2.1', '<' ) ) ||
            ( is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) && version_compare( $pluginOptions['version'], '3.6', '<' ) ) ) {
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
   
   public function run_on_activation() {
      $pluginInfo = get_site_option( 'oasiswf_editorial_comments_info' );
      if ( false === $pluginInfo ) {
         $oasiswf_editorial_comments_info = array(
            'version' => OW_EDITORIAL_COMMENTS_VERSION,
            'db_version' => OW_EDITORIAL_COMMENTS_DB_VERSION
         );

         update_site_option( 'oasiswf_editorial_comments_info', $oasiswf_editorial_comments_info );
      } else if ( OW_EDITORIAL_COMMENTS_VERSION != $pluginInfo['version'] ) {
         $this->run_on_upgrade();
      }
   }

      
   public static function ow_editorial_comments_plugin_uninstall() {
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

   public static function deactivate_the_license() {
      $license = trim( get_option( 'oasiswf_editorial_comments_license_key' ) );

      // data to send in our API request
      $api_params = array(
          'edd_action' => 'deactivate_license',
          'license' => $license,
          'item_name' => urlencode( OW_EDITORIAL_COMMENTS_PRODUCT_NAME ) // the name of our product in EDD
      );

      // Call the custom API.
      $response = wp_remote_post( OW_EDITORIAL_COMMENTS_STORE_URL, array( 'timeout' => 15, 'body' => $api_params, 'sslverify' => false ) );

      if ( get_option( 'oasiswf_editorial_comments_license_status' ) ) {
         delete_option( 'oasiswf_editorial_comments_license_status' );
      }

      if ( get_option( 'oasiswf_editorial_comments_license_key' ) ) {
         delete_option( 'oasiswf_editorial_comments_license_key' );
      }
   }

   public static function run_on_uninstall() {
      if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) )
         exit();

      // delete the dismissed_wp_pointers entry for this plugin
      $blog_users = get_users( 'role=administrator' );
      foreach ( $blog_users as $user ) {
         $dismissed = explode( ',', (string) get_user_meta( $user->ID, 'dismissed_wp_pointers', true ) );
         if ( ( $key = array_search( "owf_editorial_comments_install", $dismissed ) ) !== false ) {
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

      global $wpdb;
      $wpdb->query( "DROP TABLE IF EXISTS " . OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name() );
   }

   /**
    * Create table when user create new site over multisite
    * @since 1.0
    */
   public function run_on_add_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
      global $wpdb;
      if ( is_plugin_active_for_network( basename( dirname( __FILE__ ) ) . '/ow-editorial-comments.php' ) ) {
         $old_blog = $wpdb->blogid;
         switch_to_blog( $blog_id );
         $this->create_tables();
         restore_current_blog();
      }
   }

   /**
    * Delete selected site over multisite
    * @since 1.0
    */
   public function run_on_delete_blog( $blog_id, $drop ) {
      global $wpdb;

      switch_to_blog( $blog_id );

      self::deactivate_the_license();
      self::delete_for_site();

      restore_current_blog();
   }

   /**
    * If Oasis Workflow or Oasis Workflow Pro plugin is not installed or activated then throw the error
    * @access public
    * @return type error message
    * @since 1.0
    */
   public function show_oasis_workflow_pro_missing_notice() {
      $plugin_error = OW_Editorial_Comments_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow Editorial Comments Add-on requires Oasis Workflow or Oasis Workflow Pro plugin to be installed and activated.'
              ) );
      echo $plugin_error;
   }

   /**
    * If the Oasis Workflow version is less than 2.1  or Oasis Workflow Pro is less than 3.6
    * then throw the incompatible notice
    * @access public
    * @return type error message
    * @since 1.0
    */
   public function show_oasis_workflow_incompatible_notice() {
      $plugin_error = OW_Editorial_Comments_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow Editorial Comments Add-on requires Oasis Workflow 2.1 or higher and with pro version it requires Oasis Workflow pro 3.6 or higher.'
              ) );
      echo $plugin_error;
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

      $welcome_title = __( "Welcome to Oasis Workflow Editorial Comments", "oweditorialcomments" );
      $url = defined( 'OASISWF_URL' ) ? OASISWF_URL : '';
      $img_html = "<img src='" . $url . "img/small-arrow.gif" . "' style='border:0px;' />";
      $blurb_img_html = "<img src='" . OW_EDITORIAL_COMMENTS_URL . "assets/img/comments-icon.png" . "' style='border:0px;' />";
      $welcome_message_1 = sprintf( __( "To get started with Editorial Comments, activate the add-on by providing a valid license key on Workflows %s Settings, License tab.", "oweditorialcomments" ), $img_html );
      $welcome_message_2 = sprintf( __( "Once the license is activated you will see a blurb button %s in the Post Editor's TinyMCE toolbar.", "oweditorialcomments" ), $blurb_img_html );
      $welcome_message_3 = __( "Select a text in the post content area, click the blurb icon and start adding contextual comments.", "oweditorialcomments" );
      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         $default_pointers = array(
             'toplevel_page_oasiswf-inbox' => array(
                 'owf_editorial_comments_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p><p>' . $welcome_message_2 . '</p><p>' . $welcome_message_3 . '</p>',
                     'position' => array( 'edge' => 'left', 'align' => 'center' ),
                 )
             )
         );
      } else {
         $default_pointers = array(
             'plugins' => array(
                 'owf_editorial_comments_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p><p>' . $welcome_message_2 . '</p><p>' . $welcome_message_3 . '</p>',
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

   public function is_licence_active() {
      $license_status = get_option( 'oasiswf_editorial_comments_license_status' );
      return ($license_status != 'valid') ? FALSE : TRUE;
   }

   /**
    * Set up the database tables which the plugin needs to function.
    * @access private
    * Tables:
    *    fc_comments - Table for storing the contextual comment and history id
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

      $table = OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name();
      $query = "CREATE TABLE IF NOT EXISTS $table (
                  ID bigint(20) NOT NULL AUTO_INCREMENT,
                  workflow_history_id bigint(20) NOT NULL,
                  comments longtext,
                  post_id bigint(20) NOT NULL,
                  user_id bigint(20) NOT NULL,
                  PRIMARY KEY (ID)
                  ) $collate";
      dbDelta( $query );
   }
   
   /**
    * Add upgrade scripts
    *
    * @since 1.5
    */
   public function run_on_upgrade() {

      $pluginOptions = get_site_option( 'oasiswf_editorial_comments_info' );
      // upgrade statements
      
      // update the version value
      $oasiswf_editorial_comments_info = array(
            'version' => OW_EDITORIAL_COMMENTS_VERSION,
            'db_version' => OW_EDITORIAL_COMMENTS_DB_VERSION
         );
      update_site_option( 'oasiswf_editorial_comments_info', $oasiswf_editorial_comments_info );
   }

   /**
    * Create comment button on toolbar for tinyMCE Editor
    * @access public
    * @global type $typenow
    * @return type button
    */
   public function add_editorial_comment_button_to_mce_editor() {
      global $typenow;

      // Check if user has permission
      if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
         return;
      }

      // show comment button only on selected post/page/custom-post-types
      $show_workflow_for_post_types = get_option( 'oasiswf_show_wfsettings_on_post_types' );
      if ( ! in_array( $typenow, $show_workflow_for_post_types ) ) {
         return;
      }

      // Check if WYSIWYG is enabled
      if ( 'true' == get_user_option( 'rich_editing' ) ) {
         // register editorial comment tinymce js
         add_filter( 'mce_external_plugins', array( $this, 'ow_editorial_comment_tinymce_plugin' ) );

         // Check if the license is activated or not
         $is_activated = $this->is_licence_active();
         if ( $is_activated ) {
            // Create the editorial comment button
            add_filter( 'mce_buttons', array( $this, 'register_editorial_comment_button' ) );
         }

         // Include required script where WYSIWYG is enabled
         self::include_contextual_comment_widget_scripts();
      }
   }

   public function add_js_files() {
      if ( is_admin() && isset( $_GET['page'] ) && ($_GET["page"] == "oasiswf-inbox" ||
              $_GET["page"] == "oasiswf-history" ) ) {
              	self::include_contextual_comment_widget_scripts();
      }      
   }

   public static function include_contextual_comment_widget_scripts() {
      wp_enqueue_script( 'ow-editorial-comment-js', OW_EDITORIAL_COMMENTS_URL . 'assets/js/editorial-comments-widget.js', array( 'jquery' ), OW_EDITORIAL_COMMENTS_VERSION, true );
      wp_enqueue_style( 'editorial-comment-style', OW_EDITORIAL_COMMENTS_URL . 'assets/css/editorial-comments-style.css', OW_EDITORIAL_COMMENTS_VERSION, true );

      // Localize the tinyMCE popup text
      wp_localize_script( 'ow-editorial-comment-js', 'ow_editorial_comment_tinyMCE_popup_vars', array(
          'labelContextualText' => __( 'Contextual Text', 'oweditorialcomments' ),
          'labelContextualComment' => __( 'Contextual Comment', 'oweditorialcomments' ),
          'labelEditorialCommentOn' => __( 'Editorial Comment On', 'oweditorialcomments' )
      ) );
   }
   
   public function add_front_end_files() {
      if ( is_preview() && ( isset( $_GET['oasiswf'] ) ) ) {         
         wp_enqueue_style( 'contextual-comment-view', OW_EDITORIAL_COMMENTS_URL . 'assets/css/contextual-comments-view.css', OW_EDITORIAL_COMMENTS_VERSION, true );
         wp_enqueue_script( 'ow-contextual-comment-js', OW_EDITORIAL_COMMENTS_URL . 'assets/js/contextual-comment-view.js', array( 'jquery' ), OW_EDITORIAL_COMMENTS_VERSION, true );
      }
   }

   /**
    * JS file for comment plugin
    * @access public
    * @param array $plugin_array
    * @return array
    * @since 1.0
    */
   public function ow_editorial_comment_tinymce_plugin( $plugin_array ) {
      // Create nonce for TinyMCE ajax request
      wp_nonce_field( 'owf_tinymce_ajax_nonce', 'owf_tinymce_ajax_nonce' );

      $plugin_array['owEditorialCommentPlugin'] = OW_EDITORIAL_COMMENTS_URL . 'assets/js/mce-editor-plugin.js';
      return $plugin_array;
   }

   /**
    * Push the editorial comment button into default editor's buttons array
    * @param array $buttons
    * @return type array
    * @since 1.0
    */
   public function register_editorial_comment_button( $buttons ) {
      array_push( $buttons, 'owEditorialCommentPlugin' );
      return $buttons;
   }

   /**
    * Plugin Update notifier
    */
   public function ow_editorial_comments_plugin_updater() {

      // setup the updater
      if ( class_exists( 'OW_Plugin_Updater' ) ) {
         $edd_oasis_editorial_comments_updater = new OW_Plugin_Updater( OW_EDITORIAL_COMMENTS_STORE_URL, __FILE__, array(
               'version'   => OW_EDITORIAL_COMMENTS_VERSION, // current version number
               'license'   => trim( get_option( 'oasiswf_editorial_comments_license_key' ) ), // license key (used get_option above to retrieve from DB)
               'item_name' => OW_EDITORIAL_COMMENTS_PRODUCT_NAME, // name of this plugin
               'author'    => 'Nugget Solutions Inc.' // author of this plugin
            )
         );
      }
   }

}

// Initialize the Editorial Comment Class
$ow_editorial_comments_init = new OW_Comments_Plugin_Init();
add_action( 'admin_init', array( $ow_editorial_comments_init, 'ow_editorial_comments_plugin_updater' ) );
?>