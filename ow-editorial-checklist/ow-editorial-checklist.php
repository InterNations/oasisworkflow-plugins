<?php
/*
  Plugin Name: Oasis Workflow Editorial Checklist
  Plugin URI: https://www.oasisworkflow.com
  Description: Automates content checklist before it moves to the next step in the workflow.
  Version: 1.6
  Author: Nugget Solutions Inc.
  Author URI: http://www.nuggetsolutions.com
  Text Domain: oweditorialchecklist
  ----------------------------------------------------------------------
  Copyright 2013-2016 Nugget Solutions Inc.
 */

if( !defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

define( 'OW_EDITORIAL_CHECKLIST_VERSION', '1.6' );
define( 'OW_EDITORIAL_CHECKLIST_DB_VERSION', '1.6' );
define( 'OW_EDITORIAL_CHECKLIST_ROOT', dirname( __FILE__ ) );
define( 'OW_EDITORIAL_CHECKLIST_PATH', plugin_dir_path( __FILE__ ) ); //use for include files to other files
define( 'OW_EDITORIAL_CHECKLIST_URL', plugins_url( '/', __FILE__ ) );
define( 'OW_EDITORIAL_CHECKLIST_STORE_URL', 'https://www.oasisworkflow.com/' );
define( 'OW_EDITORIAL_CHECKLIST_PRODUCT_NAME', 'Oasis Workflow Editorial Checklist' );

/**
 * Main Editorial Checklist Plugin Class
 *
 * @class OW_Editorial_Checklist_Init
 * Since 1.0
 */
class OW_Editorial_Checklist_Init {

   private $current_screen_pointers = array();

   /**
    * Constructor of class
    */
   public function __construct() {
      $this->include_files();

      // run on activation of plugin
      register_activation_hook( __FILE__, array( $this, 'ow_editorial_checklist_plugin_activation' ) );
      register_uninstall_hook( __FILE__, array( __CLASS__, 'ow_editorial_checklist_plugin_uninstall' ) );
      
      // Load plugin text domain
      add_action( 'init', array( $this, 'load_ow_editorial_checklist_textdomain' ) );

      add_action( 'admin_init', array( $this, 'validate_parent_plugin_exists' ) );

      // Register custom post type for conditional checker
      add_action( 'init', array( $this, 'register_conditional_checker_post_type' ) );

      // add sub-page to workflow menu
      add_action( 'owf_add_submenu', array( $this, 'register_conditional_checker_menu_page' ), 10 );

      // register main js file for post/page and workflow inbox and history page
      add_action( 'admin_footer', array( $this, 'load_assets' ) );

      add_action( 'admin_enqueue_scripts', array( $this, 'show_welcome_message_pointers' ) );

      /* add/delete new subsite */
      add_action( 'wpmu_new_blog', array( $this, 'run_on_add_blog' ), 10, 6 );
      add_action( 'delete_blog', array( $this, 'run_on_delete_blog' ), 10, 2 );
      add_action( 'admin_init', array( $this, 'run_on_upgrade' ) );
   }

   /**
    * Include required core files used in admin
    * @since 1.0
    */
   public function include_files() {
   	// Utility Class
   	if( !class_exists( 'OW_Editorial_Checklist_Utility' ) ) {
   		include_once( 'includes/class-ow-utility.php' );
   	}

      if( !class_exists( 'OW_Editorial_Checklist_Meta_Boxes' ) ) {
         include_once('includes/class-ow-editorial-checklist-meta-boxes.php');
      }

      if( !class_exists( 'OW_Context_Attribute_Meta_Box' ) ) {
         include_once('includes/class-ow-context-attribute-meta-box.php');
      }

      if( !class_exists( 'OW_Containing_Attribute_Meta_Box' ) ) {
         include_once('includes/class-ow-containing-attribute-meta-box.php');
      }
      
      if( !class_exists( 'OW_Pre_Publish_Meta_Box' ) ) {
         include_once('includes/class-ow-pre-publish-meta-box.php');
      }

      if( !class_exists( 'OW_Editorial_Checklist_Service' ) ) {
         include_once('includes/class-ow-editorial-checklist-service.php');
      }

      if( !class_exists( 'OW_Editorial_Checklist_License_Settings' ) ) {
         include_once( 'includes/class-ow-editorial-checklist-license-settings.php' );
      }

      if( !class_exists( 'OW_Editorial_Checklist_Settings' ) ) {
         include_once( 'includes/class-ow-editorial-checklist-settings.php' );
      }

   }

   /**
    * Create table on activation of plugin
    * @since 1.0
    */
   public function ow_editorial_checklist_plugin_activation( $networkwide ) {
      global $wpdb;

      $this->run_on_activation();

      if( function_exists( 'is_multisite' ) && is_multisite() ) {
         // check if it is a network activation - if so, run the activation function for each blog id
         if( $networkwide ) {
            // Get all blog ids
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
            foreach ( $blog_ids as $blog_id ) {
               switch_to_blog( $blog_id );
               $this->run_for_site();
               restore_current_blog();
            }
            return;
         }
      }

      // for single site only
      $this->run_for_site();
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
         if( isset( $_GET['activate'] ) ) {
            // Do not sanitize it because we are destroying the variables from URL
            unset( $_GET['activate'] );
         }
      }

      // check oasis workflow version
      // This plugin requires Oasis Workflow 2.2 or higher
      // With "Pro" version it needs Oasis Workflow Pro 3.6 or higher
      $pluginOptions = get_site_option( 'oasiswf_info' );
      if ( is_array( $pluginOptions ) && ! empty( $pluginOptions ) ) {
         if( ( is_plugin_active( 'oasis-workflow/oasiswf.php' ) && version_compare( $pluginOptions['version'], '2.2', '<' ) ) ||
             ( is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) && version_compare( $pluginOptions['version'], '3.6', '<' ) ) ) {
            add_action( 'admin_notices', array( $this, 'show_oasis_workflow_incompatible_notice' ) );
            add_action( 'network_admin_notices', array( $this, 'show_oasis_workflow_incompatible_notice' ) );
            deactivate_plugins( $plugin );
            if( isset( $_GET['activate'] ) ) {
               // Do not sanitize it because we are destroying the variables from URL
               unset( $_GET['activate'] );
            }
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
   public function show_oasis_workflow_pro_missing_notice() {
      $plugin_error = OW_Editorial_Checklist_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow Editorial Checklist Add-on requires Oasis Workflow or Oasis Workflow Pro plugin to be installed and activated.'
              ) );
      echo $plugin_error;
   }

   /**
    * If the Oasis Workflow version is less than 2.2  or Oasis Workflow Pro is less than 3.6
    * then throw the incompatible notice
    *
    * @access public
    * @return mixed error_message, an array containing the error message
    *
    * @since 1.0 initial version
    */
   public function show_oasis_workflow_incompatible_notice() {
      $plugin_error = OW_Editorial_Checklist_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow Editorial Checklist Add-on requires requires Oasis Workflow 2.2 or higher and with pro version it requires Oasis Workflow pro 3.6 or higher.'
              ) );
      echo $plugin_error;
   }

   public function run_on_activation() {
      $pluginInfo = get_site_option( 'oasiswf_editorial_checklist_info' );
      if ( false === $pluginInfo ) {
         $oasiswf_editorial_checklist_info = array(
            'version' => OW_EDITORIAL_CHECKLIST_VERSION,
            'db_version' => OW_EDITORIAL_CHECKLIST_DB_VERSION
         );

         update_site_option( 'oasiswf_editorial_checklist_info', $oasiswf_editorial_checklist_info );
      } else if ( OW_EDITORIAL_CHECKLIST_VERSION != $pluginInfo['version'] ) {
         $this->run_on_upgrade();
      }
   }

   /**
    * Called on activation.
    * Creates the options and DB (required by per site)
    *
    *  @since 2.0
    */
   private function run_for_site() {
      // set default option to "prevent signing off" if the checklist is not met
      $default_setting_value = 'prevent_signing_off';
      if ( ! get_option( 'oasiswf_checklist_action' ) ) {
         update_option( 'oasiswf_checklist_action', $default_setting_value );
      }

      $this->create_tables();
   }
   
   /**
    * Runs on uninstall
    *
    * Deactivate the licence, delete site specific data, delete database tables
    * Takes into account both a single site and multi-site installation
    *
    * @since 1.0 initial version
    */
   public static function ow_editorial_checklist_plugin_uninstall() {
      global $wpdb;

      self::run_on_uninstall();

      if( function_exists( 'is_multisite' ) && is_multisite() ) {
         //Get all blog ids; foreach them and call the uninstall procedure on each of them
         $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

         //Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
         foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            // Deactivate the license
            self::deactivate_the_license();

            if( $wpdb->query( "SHOW TABLES FROM " . $wpdb->dbname . " LIKE '" . $wpdb->prefix . "fc_%'" ) ) {
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
    * Load editorial checklist textdomain before the UI appears
    *
    * @since 1.0
    */
   public function load_ow_editorial_checklist_textdomain() {
      load_plugin_textdomain( 'oweditorialchecklist', false, basename( dirname( __FILE__ ) ) . '/languages' );
   }

   /**
    * Deactivate the license
    *
    * @since 1.0 initial version
    */
   public static function deactivate_the_license() {
      $license = trim( get_option( 'oasiswf_editorial_checklist_license_key' ) );

      // data to send in our API request
      $api_params = array(
          'edd_action' => 'deactivate_license',
          'license' => $license,
          'item_name' => urlencode( OW_EDITORIAL_CHECKLIST_PRODUCT_NAME ) // the name of our product in EDD
      );

      // Call the custom API.
      $response = wp_remote_post( OW_EDITORIAL_CHECKLIST_STORE_URL, array( 'timeout' => 15, 'body' => $api_params, 'sslverify' => false ) );

      if( get_option( 'oasiswf_editorial_checklist_license_status' ) ) {
         delete_option( 'oasiswf_editorial_checklist_license_status' );
      }

      if( get_option( 'oasiswf_editorial_checklist_license_key' ) ) {
         delete_option( 'oasiswf_editorial_checklist_license_key' );
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
      if( !defined( 'ABSPATH' ) && !defined( 'WP_UNINSTALL_PLUGIN' ) )
         exit();

      delete_site_option( 'oasiswf_editorial_checklist_info' );
      delete_site_option( 'oasiswf_checklist_upgrade_14' );

      // delete the dismissed_wp_pointers entry for this plugin
      $blog_users = get_users( 'role=administrator' );
      foreach ( $blog_users as $user ) {
         $dismissed = explode( ',', (string) get_user_meta( $user->ID, 'dismissed_wp_pointers', true ) );
         if( ( $key = array_search( "owf_editorial_checklist_install", $dismissed ) ) !== false ) {
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
      if( !defined( 'ABSPATH' ) && !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
         exit();
      }

      global $wpdb;

      delete_option( 'oasiswf_checklist_action' );
      
      // delete all conditions (stored as post meta) for each condition group
      delete_post_meta_by_key( 'ow_context_attribute_meta' );
      delete_post_meta_by_key( 'ow_containing_attribute_meta' );
      delete_post_meta_by_key( 'ow_pre_publish_meta' );
      
      // delete condition group from all steps
      $editorial_checklist_service = new OW_Editorial_Checklist_Service();
      $condition_groups = $editorial_checklist_service->get_all_condition_groups();
      if( $condition_groups ) {
         foreach ( $condition_groups as $condition_group ) {
            $editorial_checklist_service->delete_condition_group( $condition_group->ID);
         }
      }

      // delete all condition groups
      $args = array(
          'numberposts' => -1,
          'post_type' => 'ow-condition-group'
      );
      $condition_groups = get_posts( $args );
      if ( is_array( $condition_groups ) ) {
         foreach ( $condition_groups as $condition_group ) {
            wp_delete_post( $condition_group->ID, true );
         }
      }

   }

   /**
    * Create site specific data when a new site is added to a multi-site setup
    *
    * @since 1.0 initial version
    */
   public function run_on_add_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
      global $wpdb;
      // TODO : check if plugin is active for the network before adding it to the site
      if( is_plugin_active_for_network( basename( dirname( __FILE__ ) ) . '/ow-editorial-checklist.php' ) ) {

         switch_to_blog( $blog_id );
         $this->run_for_site();
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

      // Format : array( 'screen_id' => array( 'pointer_id' => array([options : target, content, position...]) ) );

      $welcome_title = __( "Welcome to Oasis Workflow Editorial Checklist", "oweditorialchecklist" );
      $url = defined( 'OASISWF_URL' ) ? OASISWF_URL : '';
      $img_html = "<img src='" . $url . "img/small-arrow.gif" . "' style='border:0px;' />";
      $welcome_message_1 = sprintf( __( "To get started with Editorial Checklist, activate the add-on by providing a valid license key on Workflows %s Settings, License tab.", "oweditorialchecklist" ), $img_html );
      $welcome_message_2 = __( 'Create condition groups for your editorial checklist.', 'oweditorialchecklist' );
      $welcome_message_3 = __( 'Assign the condition groups to any step(s) in the workflow.', 'oweditorialchecklist' );

      if( function_exists( 'is_multisite' ) && is_multisite() ) {
         $default_pointers = array(
             'toplevel_page_oasiswf-inbox' => array(
                 'owf_editorial_checklist_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p><p>' . $welcome_message_2 . '</p><p>' . $welcome_message_3 . '</p>',
                     'position' => array( 'edge' => 'left', 'align' => 'center' ),
                 )
             )
         );
      } else {
         $default_pointers = array(
             'plugins' => array(
                 'owf_editorial_checklist_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p><p>' . $welcome_message_2 . '</p><p>' . $welcome_message_3 . '</p>',
                     'position' => array( 'edge' => 'left', 'align' => 'center' ),
                 )
             )
         );
      }

      if( !empty( $default_pointers[$screen_id] ) )
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
      if( get_bloginfo( 'version' ) < '3.3' ) {
         return;
      }

      // only show this message to the users who can activate plugins
      if( !current_user_can( 'activate_plugins' ) ) {
         return;
      }

      $pointers = $this->get_current_screen_pointers();

      // No pointers? Don't do anything
      if( empty( $pointers ) || !is_array( $pointers ) )
         return;

      // Get dismissed pointers.
      // Note : dismissed pointers are stored by WP in the "dismissed_wp_pointers" user meta.

      $dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
      $valid_pointers = array();

      // Check pointers and remove dismissed ones.
      foreach ( $pointers as $pointer_id => $pointer ) {
         // Sanity check
         if( in_array( $pointer_id, $dismissed ) || empty( $pointer ) || empty( $pointer_id ) || empty( $pointer['target'] ) || empty( $pointer['content'] ) )
            continue;

         // Add the pointer to $valid_pointers array
         $valid_pointers[$pointer_id] = $pointer;
      }

      // No valid pointers? Stop here.
      if( empty( $valid_pointers ) )
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
      if( !empty( $this->current_screen_pointers ) ):
         ?>
         <script type="text/javascript">// <![CDATA[
            jQuery(document).ready(function ($) {
               if (typeof (jQuery().pointer) != 'undefined') {
         <?php foreach ( $this->current_screen_pointers as $pointer_id => $data ): ?>
                     $('<?php echo $data['target'] ?>').pointer({
                        content: '<?php echo addslashes( $data['content'] ) ?>',
                        position: {
                           edge: '<?php echo addslashes( $data['position']['edge'] ) ?>',
                           align: '<?php echo addslashes( $data['position']['align'] ) ?>'
                        },
                        close: function () {
                           $.post(ajaxurl, {
                              pointer: '<?php echo addslashes( $pointer_id ) ?>',
                              action: 'dismiss-wp-pointer'
                           });
                        }
                     }).pointer('open');
         <?php endforeach ?>
               }
            });
            // ]]></script>
         <?php
      endif;
   }

   /**
    * Checks if the license is active
    *
    * @return boolean, if license is active return TRUE or else FALSE
    *
    * @since 1.0 initial version
    */
   public function is_licence_active() {
      $license_status = get_option( 'oasiswf_editorial_checklist_license_status' );
      return ($license_status != 'valid') ? FALSE : TRUE;
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
      if( $wpdb->has_cap( 'collation' ) ) {
         if( !empty( $wpdb->charset ) ) {
            $collate .= "DEFAULT CHARACTER SET {$wpdb->charset}";
         }
         if( !empty( $wpdb->collate ) ) {
            $collate .= " COLLATE {$wpdb->collate}";
         }
      }

      // TODO: table creation goes here
   }

   /**
    * Enqueue any js scripts or css files required by the plugin
    *
    * @since 1.0 initial creation
    */
   public function load_assets() {
      if ( ( isset( $_GET[ 'post_type' ] ) && ($_GET[ 'post_type' ] == 'ow-condition-group' ) ) || ( isset( $_GET[ 'action' ] ) && ($_GET[ 'action' ] == 'edit' ) ) || ( isset( $_GET[ 'page' ] ) && ( $_GET[ 'page' ] == 'oasiswf-admin' ) ) ) {
         wp_enqueue_style( 'ow-editorial-checklist-css',
            OW_EDITORIAL_CHECKLIST_URL . 'assets/css/ow-editorial-checklist-main.css',
            OW_EDITORIAL_CHECKLIST_VERSION,
            true );

         wp_enqueue_script( 'ow-editorial-checklist-js',
            OW_EDITORIAL_CHECKLIST_URL . 'assets/js/ow-editorial-checklist-main.js',
            array( 'jquery' ),
            OW_EDITORIAL_CHECKLIST_VERSION,
            true );

         wp_localize_script( 'ow-editorial-checklist-js', 'ow_editorial_checklist_js_vars', array(
            'owf_editorial_checklist_nonce' => wp_create_nonce( 'owf_editorial_checklist_nonce' )
         ) );
      }
      
      if( isset( $_GET[ 'page' ] ) && ( $_GET[ 'page' ] == 'oasiswf-history' ) ) {
          wp_enqueue_script( 'ow-pre-publish-condition-js', OW_EDITORIAL_CHECKLIST_URL . 'assets/js/ow-pre-publish-condition.js', array( 'jquery' ), OW_EDITORIAL_CHECKLIST_VERSION, true );
      }
   }

   public function register_conditional_checker_post_type() {
      // Create Condition Group post type
      $labels = array(
          'name' => __( 'Condition Group', 'oweditorialchecklist' ),
          'singular_name' => __( 'Condition Group', 'oweditorialchecklist' ),
          'add_new' => __( 'Add New', 'oweditorialchecklist' ),
          'add_new_item' => __( 'Add New Condition Group', 'oweditorialchecklist' ),
          'edit_item' => __( 'Edit Condition Group', 'oweditorialchecklist' ),
          'new_item' => __( 'New Condition Group', 'oweditorialchecklist' ),
          'view_item' => __( 'View Condition Group', 'oweditorialchecklist' ),
          'search_items' => __( 'Search Condition Group', 'oweditorialchecklist' ),
          'not_found' => __( 'No Condition Groups found', 'oweditorialchecklist' ),
          'not_found_in_trash' => __( 'No Condition Groups found in Trash', 'oweditorialchecklist' ),
      );

      $post_type = 'ow-condition-group';

      register_post_type( $post_type, array(
          'labels' => $labels,
          'public' => false,
          'show_ui' => true,
          '_builtin' => false,
          'capability_type' => 'page',
          'hierarchical' => false,
          'rewrite' => false,
          'query_var' => "oweditorialchecklist",
          'supports' => array(
          		'title',
          ),
          'show_in_menu' => false,
      ) );

      // add custom columns to the Condition Group Listing page
      add_filter( "manage_{$post_type}_posts_columns", array( $this, 'display_column_headings' ) );
      add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'display_column_contents' ), 10, 2 );
   }

   public function register_conditional_checker_menu_page() {
      $current_role = OW_Utility::instance()->get_current_user_role();
      if ( current_user_can( 'ow_create_workflow' ) || current_user_can( 'ow_edit_workflow' ) ) {
	      // Condition Group Page
	      add_submenu_page( 'oasiswf-inbox', __( 'Condition Groups', 'oweditorialchecklist' ),
	      		__( 'Condition Groups', 'oweditorialchecklist' ),
	      		$current_role, 'edit.php?post_type=ow-condition-group' );
      }
   }

   /**
    * Display custom column title on ow-condition-group listing page
    * @param type $columns
    * @return array $columns update column list
    * @since 1.0
    */
   public function display_column_headings( $columns ) {
      $date = $columns['date'];
      unset($columns['date']);
      // add condition count column
      $columns['condition_count'] = __( 'Conditions', 'oweditorialchecklist' );
      $columns['date'] = $date;
      return $columns;
   }

   /**
    * Set default value of editorial checklist in setting page
    *
    * @since 1.0
    * @since 1.1 added default settings
    */
   public function run_on_upgrade() {

      $pluginOptions = get_site_option( 'oasiswf_editorial_checklist_info' );

      if ( $pluginOptions['version'] == "1.2" ) {
         $this->upgrade_database_13();
         $this->upgrade_database_14();
         $this->upgrade_database_15();
         $this->upgrade_database_16();         
      }

      if ( $pluginOptions['version'] == "1.3" ) {
         $this->upgrade_database_14();
         $this->upgrade_database_15();
         $this->upgrade_database_16();
      }

      if ( $pluginOptions['version'] == "1.4" ) {
         $this->upgrade_database_15();
         $this->upgrade_database_16();
      }
      
      if ( $pluginOptions['version'] == "1.5" ) {
         $this->upgrade_database_16();
      }
      
      // update the version value
		$oasiswf_editorial_checklist_info = array(
            'version' => OW_EDITORIAL_CHECKLIST_VERSION,
            'db_version' => OW_EDITORIAL_CHECKLIST_DB_VERSION
         );
      update_site_option( 'oasiswf_editorial_checklist_info', $oasiswf_editorial_checklist_info );
   }

   private function upgrade_database_13() {
      // set default option to "prevent signing off" if the checklist is not met
      $default_setting_value = 'prevent_signing_off';
      if ( ! get_option( 'oasiswf_checklist_action' ) ) {
         update_option( 'oasiswf_checklist_action', $default_setting_value );
      }
   }

   /**
    * Upgrade database for v1.4 upgrade function
    *
    *
    * @since 1.4
    */
   private function upgrade_database_14() {
      global $wpdb;

      // look through each of the blogs and upgrade the DB
      if ( function_exists('is_multisite') && is_multisite() )
      {
         //Get all blog ids; foreach them and call the uninstall procedure on each of them
         $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

         //Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
         foreach ( $blog_ids as $blog_id )
         {
            switch_to_blog( $blog_id );
            if ( $wpdb->query( "SHOW TABLES FROM ".$wpdb->dbname." LIKE '".$wpdb->prefix."fc_%'" ) )
            {
               $this->upgrade_helper_14();
            }
            restore_current_blog();
         }
      }

      $this->upgrade_helper_14();

   }

   /**
    * Helper function for v1.4 upgrade
    */
   private function upgrade_helper_14() {
      global $wpdb;
      $results = array();
      $get_checklist_conditions = $wpdb->get_results( "SELECT meta_id, meta_value
                                                        FROM {$wpdb->postmeta}
                                                        WHERE meta_key IN('ow_context_attribute_meta','ow_containing_attribute_meta')" );

      foreach ( $get_checklist_conditions as $checklist_conditions ) {
         if( ! empty( $checklist_conditions->meta_value ) ){
            $meta_id = $checklist_conditions->meta_id; 
            $each_conditions = unserialize( $checklist_conditions->meta_value );
            
               foreach( $each_conditions as $conditions ){
                  $conditions[ 'required' ] = 'yes';
                  $results[] = $conditions;
                  $serial_condition = stripcslashes( serialize( $results ) );
                  $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = '" . $serial_condition ."' WHERE meta_id = '". $meta_id ."'" );
               }
               
            unset($results);   
         }
      }
   }

   /**
    * Upgrade database for v1.5 upgrade function
    *
    *
    * @since 1.5
    */
   private function upgrade_database_15() {
      global $wpdb;

      // remove the temporary option created for v1.4 upgrade
      if ( ! get_option( 'oasiswf_checklist_upgrade_14' ) ) {
         delete_site_option( 'oasiswf_checklist_upgrade_14' );
      }
   }
   
   /**
    * Upgrade database for v1.6 upgrade function
    *
    * @since 1.6
    */
   private function upgrade_database_16() {
      global $wpdb;

      // look through each of the blogs and upgrade the DB
      if ( function_exists('is_multisite') && is_multisite() )
      {
         //Get all blog ids; foreach them and call the uninstall procedure on each of them
         $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

         //Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
         foreach ( $blog_ids as $blog_id )
         {
            switch_to_blog( $blog_id );
            if ( $wpdb->query( "SHOW TABLES FROM ".$wpdb->dbname." LIKE '".$wpdb->prefix."fc_%'" ) )
            {
               $this->upgrade_helper_16();
            }
            restore_current_blog();
         }
      }

      $this->upgrade_helper_16();

   }
   
   /**
    * Helper function  for v1.6 upgrade 
    */
   private function upgrade_helper_16() {
      global $wpdb;
      $context = array();
      $get_context_attribute_meta = $wpdb->get_results( "SELECT meta_id, meta_value
                                                        FROM {$wpdb->postmeta}
                                                        WHERE meta_key IN('ow_context_attribute_meta')" );

      foreach ( $get_context_attribute_meta as $context_attribute ) {
         if ( ! empty( $context_attribute->meta_value ) ) {
            $meta_id = $context_attribute->meta_id; 
            $each_context_attribute = unserialize( $context_attribute->meta_value );
                  foreach( $each_context_attribute as $attribute ) {
                  $results = array_slice( $attribute, 0, 3, true ) +
                             array( "count_type" => "words" ) +
                             array_slice( $attribute, 3, count( $attribute ) - 1, true ) ;
                  $context[] = $results;
                  $serial_condition = stripcslashes( serialize( $context ) );
                  $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = '" . $serial_condition ."' WHERE meta_id = '". $meta_id ."'" );
                  }         
            unset( $context );  
         }
      }
   }

   /**
    * Display custom column contents on ow-condition-group listing page
    * @param string $column_name
    * @param int $post_id
    * @return custom column contents
    * @since 1.0
    */
   public function display_column_contents( $column_name, $post_id ) {
      if( $column_name == 'condition_count' ) {
         $post_id = (int) $post_id;
         $ow_context_attribute_meta = get_post_meta( $post_id, 'ow_context_attribute_meta', true );
         $ow_containing_attribute_meta = get_post_meta( $post_id, 'ow_containing_attribute_meta', true );
         $ow_pre_publish_meta = get_post_meta( $post_id, 'ow_pre_publish_meta', true );

         $context_attribute_meta_count = 0;
         if ( ! empty ( $ow_context_attribute_meta ) ) {
            $context_attribute_meta_count = count( $ow_context_attribute_meta );
         }

         $containing_attribute_meta_count = 0;
         if ( ! empty ( $ow_containing_attribute_meta ) ) {
            $containing_attribute_meta_count = count( $ow_containing_attribute_meta );
         }

         $pre_publish_meta_count = 0;
         if ( ! empty ( $ow_pre_publish_meta ) ) {
            $pre_publish_meta_count = count( $ow_pre_publish_meta );
         }

         echo $context_attribute_meta_count + $containing_attribute_meta_count + $pre_publish_meta_count ;
      }
   }

   /**
    * Plugin Update notifier
    */
   public function ow_editorial_checklist_plugin_updater() {

      // setup the updater
      if ( class_exists( 'OW_Plugin_Updater' ) ) {
         $edd_oasis_editorial_checklist_updater = new OW_Plugin_Updater( OW_EDITORIAL_CHECKLIST_STORE_URL, __FILE__, array(
               'version'   => OW_EDITORIAL_CHECKLIST_VERSION, // current version number
               'license'   => trim( get_option( 'oasiswf_editorial_checklist_license_key' ) ), // license key (used get_option above to retrieve from DB)
               'item_name' => OW_EDITORIAL_CHECKLIST_PRODUCT_NAME, // name of this plugin
               'author'    => 'Nugget Solutions Inc.' // author of this plugin
            )
         );
      }
   }
}

// initialize the plugin
$ow_editorial_checklist_init = new OW_Editorial_Checklist_Init();
add_action( 'admin_init', array( $ow_editorial_checklist_init, 'ow_editorial_checklist_plugin_updater' ) );

?>