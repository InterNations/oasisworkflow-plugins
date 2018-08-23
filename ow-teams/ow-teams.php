<?php

/*
  Plugin Name: Oasis Workflow Teams
  Plugin URI: http://www.oasisworkflow.com
  Description: Create and Manage Teams to work with Oasis Workflow.
  Version: 3.0
  Author: Nugget Solutions Inc.
  Author URI: http://www.nuggetsolutions.com
  Text Domain: owfteams
  ----------------------------------------------------------------------
  Copyright 2011-2018 Nugget Solutions Inc.
 */

// Declare some global constants
define( 'OWFTEAMS_VERSION', '3.0' );
define( 'OWFTEAMS_DB_VERSION', '3.0' );
define( 'OWFTEAMS_ROOT', dirname( __FILE__ ) );
define( 'OWFTEAMS_URL', plugins_url( '/', __FILE__ ) );
define( 'OWFTEAMS_PATH', plugin_dir_path( __FILE__ ) ); //use for include files to other files
define( 'OWFTEAMS_STORE_URL', 'https://www.oasisworkflow.com/' );
define( 'OWFTEAMS_PRODUCT_NAME', 'Oasis Workflow Teams' );
load_plugin_textdomain( 'owfteams', false, basename( dirname( __FILE__ ) ) . '/languages' );

/*
 * include utility classes
 */

if ( ! class_exists( 'OW_Teams_Utility' ) ) {
   include( OWFTEAMS_ROOT . '/includes/class-ow-utility.php' );
}

// Initialize plugin
class OW_Teams_Plugin_Init {
   
   private $current_screen_pointers = array();

   public function __construct() {
      $this->load_all_classes();

      register_activation_hook( __FILE__, array( $this, 'ow_teams_activate' ) );
      register_deactivation_hook( __FILE__, array( $this, 'ow_teams_deactivate' ) );
      register_uninstall_hook( __FILE__, array( 'OW_Teams_Plugin_Init', 'ow_teams_uninstall' ) );
      add_action( 'admin_init', array( $this, 'validate_parent_plugin_exists' ) );
     
      add_action( 'owf_add_submenu', array( $this, 'register_menu_pages' ) );
      add_action( 'admin_footer', array( $this, 'load_assets' ) );
      
      add_action( 'admin_enqueue_scripts', array( $this, 'show_welcome_message_pointers' ) );

      /* add/delete new subsite */
      add_action( 'wpmu_new_blog', array( $this, 'run_on_add_blog' ), 10, 6 );
      add_action( 'delete_blog', array( $this, 'run_on_delete_blog' ), 10, 2 );

      // run on upgrade
      add_action( 'admin_init', array( $this, 'run_on_upgrade' ) );
   }

   public function ow_teams_activate( $networkwide ) {
      global $wpdb;

      $this->run_on_activation();

      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         // check if it is a network activation - if so, run the activation function for each blog id
         if ( $networkwide ) {
            // Get all blog ids
            $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
            foreach ( $blog_ids as $blog_id ) {
               switch_to_blog( $blog_id );
               $this->install_site_database();
               $this->run_for_site();
               restore_current_blog();
            }
            return;
         }
      }

      // for single site only
      $this->install_site_database();
      $this->run_for_site();
   }
   
   public function ow_teams_deactivate( $network_wide ) {
      global $wpdb;

      if ( function_exists('is_multisite') && is_multisite() ) {
         // check if it is a network activation - if so, run the deactivation function for each blog id
         if ( $network_wide ) {
            // Get all blog ids
            $blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
            foreach ($blogids as $blog_id) {
               switch_to_blog($blog_id);
               $this->run_on_deactivation();
               restore_current_blog();
            }
            return;
         }
      }

      // for non-network sites only
      $this->run_on_deactivation();
   }

   /**
    * Validate parent Plugin Oasis Workflow or Oasis Workflow Pro exist and activated
    * @access public
    * @since 2.3
    */

   public function validate_parent_plugin_exists() {
      $pluginOptions = get_site_option( 'oasiswf_info' );
      $plugin = plugin_basename( __FILE__ );
      if( ( !is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) ) &&  ( ! is_plugin_active( 'oasis-workflow/oasiswf.php' ) ) ) {
         add_action( 'admin_notices', array( $this, 'show_oasis_workflow_pro_missing_notice' ) );
         add_action( 'network_admin_notices', array( $this, 'show_oasis_workflow_pro_missing_notice' ) );
         deactivate_plugins( $plugin );
         if ( isset( $_GET['activate'] ) ) :
            unset( $_GET['activate'] );
         endif;
      }

     // check oasis workflow version
      // This plugin requires Oasis Workflow 2.2 or higher
      // With "Pro" version it needs Oasis Workflow Pro 1.0.8 or higher
      if ( is_array( $pluginOptions ) && ! empty( $pluginOptions ) ) {
         if ( ( is_plugin_active( 'oasis-workflow/oasiswf.php' ) && version_compare( $pluginOptions[ 'version' ], '2.2', '<' ) ) || ( is_plugin_active( 'oasis-workflow-pro/oasis-workflow-pro.php' ) && version_compare( $pluginOptions[ 'version' ], '1.0.8', '<' ) ) ) {
            add_action( 'admin_notices', array( $this, 'show_oasis_workflow_pro_incompatible_notice' ) );
            add_action( 'network_admin_notices', array( $this, 'show_oasis_workflow_pro_incompatible_notice' ) );
            deactivate_plugins( $plugin );
            if ( isset( $_GET['activate'] ) ) :
               unset( $_GET['activate'] );
            endif;
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
    * @since 2.3 initial version
    *
    */

   public function show_oasis_workflow_pro_missing_notice() {
      $plugin_error = OW_Teams_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow Teams Add-on requires Oasis Workflow or Oasis Workflow Pro plugin to be installed and activated.'
      ) );
      echo $plugin_error;
   }

   /**
    * If the Oasis Workflow Pro version is less than 1.0.8 or Oasis Workflow version is less than 2.2
    * then throw the incompatible notice
    *
    * @access public
    * @return mixed error_message, an array containing the error message
    *
    * @since 2.3 initial version
    */

   public function show_oasis_workflow_pro_incompatible_notice() {
      $plugin_error = OW_Teams_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Oasis Workflow Teams Add-on requires requires Oasis Workflow 2.2 or higher and with pro version it requires Oasis Workflow Pro 1.0.8 or higher.'
      ) );
      echo $plugin_error;
   }

   public function run_on_activation() {
      $pluginInfo = get_site_option( 'oasiswf_team_info' );
      if ( false === $pluginInfo ) {
         $oasiswf_team_info = array(
             'version' => OWFTEAMS_VERSION,
             'db_version' => OWFTEAMS_DB_VERSION
         );

         update_site_option( 'oasiswf_team_info', $oasiswf_team_info );
      } else if ( OWFTEAMS_VERSION != $pluginInfo['version'] ) {
         $this->run_on_upgrade();
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

      $welcome_title = __( "Welcome to Oasis Workflow Teams", "owfteams" );
      $url = defined( 'OASISWF_URL' ) ? OASISWF_URL : '';
      $img_html = "<img src='" . $url . "img/small-arrow.gif" . "' style='border:0px;' />";
      $welcome_message_1 = sprintf( __( "1. Activate the add-on by providing a valid license key on Workflows %s Settings, License tab.", "owfteams" ), $img_html );
      $welcome_message_2 = __( "2. Create teams and start using teams in workflows.", "owfteams" );
      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         $default_pointers = array(
             'toplevel_page_oasiswf-inbox' => array(
                 'owf_teams_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p><p>' . $welcome_message_2 . '</p>',
                     'position' => array( 'edge' => 'left', 'align' => 'center' ),
                 )
             )
         );
      } else {
         $default_pointers = array(
             'plugins' => array(
                 'owf_teams_install' => array(
                     'target' => '#toplevel_page_oasiswf-inbox',
                     'content' => '<h3>' . $welcome_title . '</h3> <p>' . $welcome_message_1 . '</p><p>' . $welcome_message_2 . '</p>',
                     'position' => array( 'edge' => 'left', 'align' => 'center' ),
                 )
             )
         );
      }

      if ( !empty( $default_pointers[ $screen_id ] ) )
         $pointers = $default_pointers[ $screen_id ];

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
      if ( !current_user_can( 'activate_plugins' ) ) {
         return;
      }

      $pointers = $this->get_current_screen_pointers();

      // No pointers? Don't do anything
      if ( empty( $pointers ) || !is_array( $pointers ) )
         return;

      // Get dismissed pointers.
      // Note : dismissed pointers are stored by WP in the "dismissed_wp_pointers" user meta.

      $dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
      $valid_pointers = array();

      // Check pointers and remove dismissed ones.
      foreach ( $pointers as $pointer_id => $pointer ) {
         // Sanity check
         if ( in_array( $pointer_id, $dismissed ) || empty( $pointer ) || empty( $pointer_id ) || empty( $pointer[ 'target' ] ) || empty( $pointer[ 'content' ] ) )
            continue;

         // Add the pointer to $valid_pointers array
         $valid_pointers[ $pointer_id ] = $pointer;
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

   public static function ow_teams_uninstall() {
      global $wpdb;

      OW_Teams_Plugin_Init::run_on_uninstall();

      if ( function_exists( 'is_multisite' ) && is_multisite() ) {
         //Get all blog ids; foreach them and call the uninstall procedure on each of them
         $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

         //Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
         foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            if ( $wpdb->query( "SHOW TABLES FROM " . $wpdb->dbname . " LIKE '" . $wpdb->prefix . "fc_%'" ) ) {
            	OW_Teams_Plugin_Init::deaactivate_the_license();
               OW_Teams_Plugin_Init::delete_for_site();
            }
            restore_current_blog();
         }
         return;
      }

      OW_Teams_Plugin_Init::deaactivate_the_license();
      OW_Teams_Plugin_Init::delete_for_site();
   }

   /**
    * Called on uninstall - deletes site_options
    *
    * @since 2.5
    */
   private static function run_on_uninstall() {
      if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) )
         exit();

      global $wpdb; //required global declaration of WP variable
      delete_site_option( 'oasiswf_team_info' );

      // delete the dismissed_wp_pointers entry for this plugin
      $blog_users = get_users( 'role=administrator' );
      foreach ( $blog_users as $user ) {
         $dismissed = explode( ',', (string) get_user_meta( $user->ID, 'dismissed_wp_pointers', true ) );
         if( ( $key = array_search( "owf_teams_install", $dismissed ) ) !== false ) {
            unset( $dismissed[$key] );
         }

         $updated_dismissed = implode( ",", $dismissed );
         update_user_meta( $user->ID, "dismissed_wp_pointers", $updated_dismissed );
      }
   }

   public function run_on_upgrade() {
      global $wpdb;
      $pluginOptions = get_site_option( 'oasiswf_team_info' );
      if ( $pluginOptions['version'] == "1.0.0" ) {
         $this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "1.1" ) {
         $this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "1.2" ) {
         $this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "1.3" ) {
      	$this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "1.4" ) {
      	$this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "2.1" ) {
      	$this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "2.2" ) {
      	$this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "2.3" ) {
      	$this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "2.4" ) {
      	$this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "2.5" ) {
      	$this->upgrade_database_27();
         $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "2.6" ) {
      	 $this->upgrade_database_27();
          $this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "2.7" ) {
      	$this->upgrade_database_28();
         $this->upgrade_database_29();
      }
      if ( $pluginOptions['version'] == "2.8" ) {
         $this->upgrade_database_29();
      }
      
      // update the version value
      $oasiswf_team_info = array(
             'version' => OWFTEAMS_VERSION,
             'db_version' => OWFTEAMS_DB_VERSION
         );
      update_site_option( 'oasiswf_team_info', $oasiswf_team_info );
   }
   
   /**
    * Upgrade database for v2.7 upgrade function
    *
    * @since 2.7
    */
   private function upgrade_database_27() {
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
               $this->upgrade_helper_27();
            }
            restore_current_blog();
         }
      }

      $this->upgrade_helper_27();
   }
   
   /**
    * Helper function  for v2.7 upgrade 
    */
   private function upgrade_helper_27() {
      global $wpdb;

		$teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
		$wpdb->query( "ALTER TABLE {$teams_table} ADD associated_workflows longtext AFTER description" );      
      
      // Set default array value for added associated_workflows column
      $teams = $wpdb->get_results( "SELECT A.id AS team_id, A.associated_workflows
			FROM {$teams_table} AS A " );
      $associate_workflow = array("-1");
      if( $teams ) {
         foreach( $teams as $team ) {
             $wpdb->update( $teams_table, array(
					'associated_workflows' => json_encode( $associate_workflow )
				), array(
					'ID' => $team->team_id
				));
         }
      }
   }
   
   /**
    * Upgrade database for v2.8 upgrade function
    *
    * @since 2.8
    */
   private function upgrade_database_28() {
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
               $this->upgrade_helper_28();
            }
            restore_current_blog();
         }
      }

      $this->upgrade_helper_28();
   }
   
   /**
    * Helper function  for v2.8 upgrade 
    */
   private function upgrade_helper_28() {
      global $wp_roles;

      if ( class_exists('WP_Roles') ) {
      	if ( ! isset( $wp_roles ) ) {
      		$wp_roles = new WP_Roles();
      	}
      }
      
      // Add admin capabilities
      $wp_roles->add_cap( 'administrator', 'ow_create_teams' );
      $wp_roles->add_cap( 'administrator', 'ow_edit_teams' );
      $wp_roles->add_cap( 'administrator', 'ow_delete_teams' );
      
   }

   /**
    * Upgrade database for v2.9 upgrade function
    *
    * @since 2.9
    */
   private function upgrade_database_29() {
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
               $this->upgrade_helper_29();
            }
            restore_current_blog();
         }
      }

      $this->upgrade_helper_29();
   }

   /**
    * Helper function  for v2.9 upgrade
    */
   private function upgrade_helper_29() {
      global $wp_roles;

      if ( class_exists('WP_Roles') ) {
         if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
         }
      }

      // Add admin capabilities
      $wp_roles->add_cap( 'administrator', 'ow_view_teams' );
   }

   public function install_site_database() {
      global $wpdb;
      if ( ! empty( $wpdb->charset ) )
         $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
      if ( ! empty( $wpdb->collate ) )
         $charset_collate .= " COLLATE {$wpdb->collate}";
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      //fc_teams table
      $table_name = OW_Teams_Utility::instance()->get_teams_table_name();
      if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
         // action - 1 indicates not send, 0 indicates email sent
         $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			    ID int(11) NOT NULL AUTO_INCREMENT,
			    name varchar(30) NOT NULL,
			    description mediumtext,
             associated_workflows longtext DEFAULT NULL,
			    create_datetime  datetime DEFAULT NULL,
			    update_datetime  datetime DEFAULT NULL,
			    PRIMARY KEY (ID)
	    		){$charset_collate};";
         dbDelta( $sql );
      }
      //fc_team_members table
      $table_name = OW_Teams_Utility::instance()->get_teams_members_table_name();
      if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
         $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			    ID int(11) NOT NULL AUTO_INCREMENT,
			    team_id int(11) NOT NULL,
			    user_id int(11) NOT NULL,
			    role_name varchar(176) NOT NULL,
			    create_datetime datetime NOT NULL,
			    PRIMARY KEY (ID)
	    		){$charset_collate};";
         dbDelta( $sql );
      }

      if ( ! get_option( 'oasiswf_team_enable' ) ) { //if the option exists, then do not initialize to null
         $oasiswf_team_enable = '';
         update_option( 'oasiswf_team_enable', $oasiswf_team_enable );
      }
   }
   
   private function run_for_site() {
      /*
		 * Include the teams custom capability class
		 */
		if ( ! class_exists( 'OW_Team_Custom_Capabilities' ) ) {
			include( OWFTEAMS_PATH . 'includes/class-ow-teams-custom-capabilities.php' );
		}

		$ow_team_custom_capabilities = new OW_Team_Custom_Capabilities();
		$ow_team_custom_capabilities->add_team_capabilities();
   }
   
   public function run_on_deactivation() {
      $oasiswf_team_enable = '';
      update_option( 'oasiswf_team_enable', $oasiswf_team_enable );
      
      /*
		 * Include the teams custom capability class
		 */
		if ( ! class_exists( 'OW_Team_Custom_Capabilities' ) ) {
			include( OWFTEAMS_PATH . 'includes/class-ow-teams-custom-capabilities.php' );
		}

		$ow_team_custom_capabilities = new OW_Team_Custom_Capabilities();
		$ow_team_custom_capabilities->remove_team_capabilities();
   }

   public static function deaactivate_the_license() {
      // deactivate the license
      $license = trim( get_option( 'oasiswf_teams_license_key' ) );

      // data to send in our API request
      $api_params = array(
          'edd_action' => 'deactivate_license',
          'license' => $license,
          'item_name' => urlencode( OWFTEAMS_PRODUCT_NAME ) // the name of our product in EDD
      );

      // Call the custom API.
      $response = wp_remote_post( OWFTEAMS_STORE_URL, array( 'timeout' => 15, 'body' => $api_params, 'sslverify' => false ) );

      if ( get_option( 'oasiswf_teams_license_status' ) ) {
         delete_option( 'oasiswf_teams_license_status' );
      }

      if ( get_option( 'oasiswf_teams_license_key' ) ) {
         delete_option( 'oasiswf_teams_license_key' );
      }
   }

   public static function delete_for_site() {
      if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) )
         exit();


      global $wpdb;
      delete_option( 'oasiswf_team_enable' );

      $wpdb->query( 'DROP TABLE IF EXISTS ' . OW_Teams_Utility::instance()->get_teams_members_table_name() );
      $wpdb->query( 'DROP TABLE IF EXISTS ' . OW_Teams_Utility::instance()->get_teams_table_name() );
      
      
      /*
		 * Include the teams custom capability class
		 */
		if ( ! class_exists( 'OW_Team_Custom_Capabilities' ) ) {
			include( OWFTEAMS_PATH . 'includes/class-ow-teams-custom-capabilities.php' );
		}

		$ow_team_custom_capabilities = new OW_Team_Custom_Capabilities();
		$ow_team_custom_capabilities->remove_team_capabilities();
      
   }

   public function run_on_add_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
      global $wpdb;
      if ( is_plugin_active_for_network( basename( dirname( __FILE__ ) ) . '/ow-teams.php' ) ) {
         $old_blog = $wpdb->blogid;
         switch_to_blog( $blog_id );
         $this->install_site_database();
         restore_current_blog();
      }
   }

   public function run_on_delete_blog( $blog_id, $drop ) {
      global $wpdb;
      switch_to_blog( $blog_id );
      OW_Teams_Plugin_Init::deaactivate_the_license();
      OW_Teams_Plugin_Init::delete_for_site();
      restore_current_blog();
   }

   /*
    * Load all the classes - as part of init action hook
    *
    * @since 2.0
    */

   public function load_all_classes() {

      if ( ! class_exists( 'OW_Teams_Service' ) ) {
         include( OWFTEAMS_ROOT . '/includes/class-ow-teams-service.php' );
      }

      if ( ! class_exists( 'OW_Teams_License_Settings' ) ) {
         include( OWFTEAMS_ROOT . '/includes/class-ow-teams-license-settings.php' );
      }

      if ( ! class_exists( 'OW_Team_Settings' ) ) {
         include( OWFTEAMS_ROOT . '/includes/class-ow-teams-settings.php' );
      }
   }

   /*
    * Create menu items for the plugin.
    *
    * @since 2.0
    */

   public function register_menu_pages() {
      
      $current_role = OW_Teams_Utility::instance()->get_current_user_role();
      
      $ow_teams_service = new OW_Teams_Service();

      if ( current_user_can( 'ow_create_teams' ) || current_user_can( 'ow_edit_teams' ) || current_user_can( 'ow_view_teams' ) ) {
         add_submenu_page( 'oasiswf-inbox', __( 'All Teams', 'owfteams' ), __( 'All Teams', 'owfteams' ), $current_role, 'oasiswf-teams', array( $ow_teams_service, 'list_teams' ) );
      }
       
      if ( current_user_can( 'ow_create_teams' ) ) {
         add_submenu_page( 'oasiswf-inbox', __( 'Add New Team', 'owfteams' ), __( 'Add New Team', 'owfteams' ), $current_role, 'add-new-team', array( $ow_teams_service, 'add_or_edit_team' ) );
      }

      if ( current_user_can( 'ow_edit_teams' ) || current_user_can( 'ow_view_teams' ) ) {
         add_submenu_page( 'edit-team', __( 'Edit Team', 'owfteams' ), __( 'Edit Team', 'owfteams' ), $current_role, 'edit-team', array( $ow_teams_service, 'add_or_edit_team' ) );
      }
      
      add_submenu_page( 'view-team', __( 'View Team', 'owfteams' ), __( 'View Team', 'owfteams' ), $current_role, 'view-team', array( $ow_teams_service, 'view_team_associates' ) );
      
   }

   public function load_assets() {
      if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'oasiswf-teams' ||
                                       $_GET['page'] == 'add-new-team' ||
                                       $_GET['page'] == 'edit-team' ||
                                       $_GET['page'] == 'view-team' ||
                                       $_GET['page'] == 'oasiswf-teams-settings' ) ) {

         // Check whether parent plugin oasis workflow pro css exist
         if ( ! wp_style_is( 'owf-oasis-workflow-css' ) ) {
            wp_enqueue_style( 'owf-oasis-workflow-css', OASISWF_URL . 'css/pages/oasis-workflow.css', false, OASISWF_VERSION, 'all' );
         }

         wp_enqueue_style( 'owf-team-style', OWFTEAMS_URL . 'assets/css/oasiswf-teams.css', false, OWFTEAMS_VERSION, 'all' );

         wp_enqueue_script( 'owf-teams-js', OWFTEAMS_URL . 'assets/js/oasiswf-teams.js', array( 'jquery' ), OWFTEAMS_VERSION, true );

         wp_localize_script( 'owf-teams-js', 'owf_teams_js_vars', array(
             'teamInUse' => __( 'The team cannot be deleted, since it\'s currently being used in a workflow.', 'owfteams' )
         ) );
      }

      if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'add-new-team' || $_GET['page'] == 'edit-team' ) ) {
         wp_enqueue_style( 'select2-style', OWFTEAMS_URL . 'assets/css/select2/select2.css', false, OWFTEAMS_VERSION, 'all' );

         wp_enqueue_script( 'select2-js', OWFTEAMS_URL . 'assets/js/select2/select2.min.js', array( 'jquery' ), OWFTEAMS_VERSION, true );
         wp_enqueue_script( 'owf-teams-select2-js', OWFTEAMS_URL . 'assets/js/oasiswf-teams-select2.js', array( 'jquery', 'select2-js' ), OWFTEAMS_VERSION, true );
      }
      
      if ( is_admin() && preg_match_all( '/post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
         wp_enqueue_script( 'owf-teams-js', OWFTEAMS_URL . 'assets/js/oasiswf-teams.js', array( 'jquery' ), OWFTEAMS_VERSION, true );
      }
      if ( isset( $_GET['page'] ) && $_GET['page'] == 'oasiswf-inbox' ) {
          wp_enqueue_script( 'owf-teams-js', OWFTEAMS_URL . 'assets/js/oasiswf-teams.js', array( 'jquery' ), OWFTEAMS_VERSION, true );
      }
   }

   /**
    * Plugin Update notifier
    */
   public function ow_teams_plugin_updater() {

      // setup the updater
      if ( class_exists( 'OW_Plugin_Updater' ) ) {
         // setup the updater
         $edd_oasis_teams_plugin_updater = new OW_Plugin_Updater( OWFTEAMS_STORE_URL, __FILE__, array(
               'version' => OWFTEAMS_VERSION, // current version number
               'license' => trim( get_option( 'oasiswf_teams_license_key' ) ), // license key (used get_option above to retrieve from DB)
               'item_name' => OWFTEAMS_PRODUCT_NAME, // name of this plugin
               'author' => 'Nugget Solutions Inc.'  // author of this plugin
            )
         );
      }
   }

}

// initialize the plugin
$ow_teams_plugin_init = new OW_Teams_Plugin_Init();
add_action( 'admin_init', array( $ow_teams_plugin_init, 'ow_teams_plugin_updater' ) );
?>