<?php
/*
  Plugin Name: Oasis Workflow Pro
  Plugin URI: https://www.oasisworkflow.com
  Description: Automate your WordPress Editorial Workflow with Oasis Workflow.
  Version: 5.6
  Author: Nugget Solutions Inc.
  Author URI: https://www.nuggetsolutions.com
  Text Domain: oasisworkflow
  ----------------------------------------------------------------------
  Copyright 2011-2018 Nugget Solutions Inc.
 */


define( 'OASISWF_VERSION', '5.6' );
define( 'OASISWF_DB_VERSION', '5.6' );
define( 'OASISWF_PATH', plugin_dir_path( __FILE__ ) ); //use for include files to other files
define( 'OASISWF_ROOT', dirname( __FILE__ ) );
define( 'OASISWF_FILE_PATH', OASISWF_ROOT . '/' . basename( __FILE__ ) );
define( 'OASISWF_URL', plugins_url( '/', __FILE__ ) );
define( 'OASISWF_SETTINGS_PAGE', esc_url( add_query_arg( 'page', 'ef-settings', get_admin_url( null, 'admin.php' ) ) ) );
define( 'OASISWF_STORE_URL', 'https://www.oasisworkflow.com' );
define( 'OASISWF_PRODUCT_NAME', 'Oasis Workflow Pro' );
define( 'OASISWF_EDIT_DATE_FORMAT', 'm-M d, Y' );
define( 'OASISWF_DATE_TIME_FORMAT', 'm-M d, Y @ H:i' );
define( 'OASIS_PER_PAGE', '50' );
load_plugin_textdomain( 'oasisworkflow', false, basename( dirname( __FILE__ ) ) . '/languages' );


/*
 * include utility classes
 */

if ( ! class_exists( 'OW_Utility' ) ) {
	include( OASISWF_PATH . 'includes/class-ow-utility.php' );
}
if ( ! class_exists( 'OW_Admin_Post' ) ) {
	include( OASISWF_PATH . 'includes/class-ow-admin-post.php' );
}
if ( ! class_exists( 'OW_Custom_Statuses' ) ) {
   include( OASISWF_PATH . 'includes/class-ow-custom-statuses.php' );
}
if ( ! class_exists( 'OW_Email_Settings_Helper' ) ) {
	include( OASISWF_PATH . 'includes/class-ow-email-settings-helper.php' );
}

/**
 * OW_Plugin_Init Class
 *
 * This class will set the plugin
 *
 * @since 2.0
 */

class OW_Plugin_Init {

	private $current_screen_pointers = array();

	/*
	 * Set things up.
	 *
	 * @since 2.0
	 */
	public function __construct() {

		//run on activation of plugin
		register_activation_hook( __FILE__, array( $this, 'oasis_workflow_activate' ) );

		//run on deactivation of plugin
		register_deactivation_hook( __FILE__, array( $this, 'oasis_workflow_deactivate' ) );

		//run on uninstall
		register_uninstall_hook( __FILE__, array( 'OW_Plugin_Init', 'oasis_workflow_uninstall' ) );
      
      add_action( 'admin_init', array( $this, 'validate_lite_version_exists' ) );

		// add custom interval for Auto Submit
		add_filter( 'cron_schedules', array( $this, 'custom_cron_interval' ) );
      
      // make workflow inbox as the landing page
      add_filter( 'login_redirect', array( $this, 'dashboard_redirect' ), 10, 3 );   

		// load the js and css files
		add_action( 'init', array( $this, 'load_css_and_js_files' ) );
      add_action( 'admin_head', array( $this, 'add_css' ) );

		// load the classes
		add_action( 'init', array( $this, 'load_all_classes' ) );

		// register custom post types
		add_action( 'init', array( $this, 'register_custom_post_types' ) );

		add_action( 'admin_menu', array( $this, 'register_menu_pages' ) );

		add_action( 'wpmu_new_blog', array( $this, 'run_on_add_blog' ), 10, 6 );
		add_action( 'delete_blog', array( $this, 'run_on_delete_blog' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'run_on_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'show_welcome_message_pointers' ) );
      add_action( 'wp_dashboard_setup', array( $this, 'add_workflow_tasks_summary_widget' ) );

	}

   /**
	 * Activate the plugin
	 *
	 * @since 2.0
	 */
	public function oasis_workflow_activate( $network_wide ) {
		global $wpdb;
		$this->run_on_activation();
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			// check if it is a network activation - if so, run the activation function for each blog id
			if ( $network_wide ) {
				// Get all blog ids
				$blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					$this->run_for_site();
					restore_current_blog();
				}
				return;
			}
		}

		// for non-network sites only
		$this->run_for_site();
	}

	/**
	 * deactivate the plugin
	 *
	 * @since 3.4
	 */
	public function oasis_workflow_deactivate( $network_wide ) {
		global $wpdb;

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			// check if it is a network activation - if so, run the activation function for each blog id
			if ( $network_wide ) {
				// Get all blog ids
				$blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
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
	 *  Runs on plugin uninstall.
	 *  a static class method or function can be used in an uninstall hook
	 *
	 *  @since 2.0
	 */

	public static function oasis_workflow_uninstall() {
		global $wpdb;
		OW_Plugin_Init::run_on_uninstall();
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			//Get all blog ids; foreach them and call the uninstall procedure on each of them
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

			//Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				if ( $wpdb->query( "SHOW TABLES FROM " . $wpdb->dbname . " LIKE '" . $wpdb->prefix . "fc_%'" ) ) {
					OW_Plugin_Init::clear_scheduled_hooks();
					OW_Plugin_Init::delete_for_site();
				}
				restore_current_blog();
			}
			return;
		}
		OW_Plugin_Init::clear_scheduled_hooks();
		OW_Plugin_Init::delete_for_site();
	}
   
   /**
    * Validate Oasis Workflow Free Version exist and activated
    * @access public
    * @since 5.2
    */
   public function validate_lite_version_exists() {
      $plugin = plugin_basename( __FILE__ );
      if( is_plugin_active( 'oasis-workflow/oasiswf.php' ) || ( file_exists( plugin_dir_path( __DIR__ ).'oasis-workflow/oasiswf.php' ) ) ) {
         add_action( 'admin_notices', array( $this, 'show_lite_version_incompatible_message' ) );
         add_action( 'network_admin_notices', array( $this, 'show_lite_version_incompatible_message' ) );
         deactivate_plugins( $plugin );
         if ( isset( $_GET['activate'] ) ) :
            unset( $_GET['activate'] );
         endif;
      }
   } 
   
   /**
    * If Oasis Workflow Free is installed or activated
    * then throw the error
    * @access public
    * @return mixed error_message, an array containing the error message
    * @since 5.2
    */
   public function show_lite_version_incompatible_message() {
      $plugin_error = OW_Utility::instance()->admin_notice( array(
          'type' => 'error',
          'message' => 'Please deactivate and uninstall the Oasis Workflow "free" version, before installing the "Pro" version.'
      ) );
      echo $plugin_error;
   }

   public function add_workflow_tasks_summary_widget() {
		wp_add_dashboard_widget( 'task_dashboard', __( 'Workflow Tasks At a Glance', 'oasisworkflow' ), array( $this, 'tasks_summary_dashboard_content' ) );
   }

   public function tasks_summary_dashboard_content() {
      include( OASISWF_PATH . "includes/pages/workflow-dashboard-widget.php" );
   }

	/**
	 * Create/Register menu items for the plugin.
	 *
	 * @since 2.0
	 */
	public function register_menu_pages() {
		$current_role = OW_Utility::instance()->get_current_user_role();

      // Lets check if filter is exist then trigger the user defined position
      if ( has_filter( 'ow_workflow_menu_position' ) ) {
         $position = apply_filters( 'ow_workflow_menu_position', '' );
      } else {
         $position = $this->get_menu_position( ".8" );
      }

		$ow_process_flow = new OW_Process_Flow();
		$inbox_count = $ow_process_flow->get_assigned_post_count();
		$count = ($inbox_count) ? '<span class="update-plugins count"><span class="plugin-count">' . $inbox_count . '</span></span>' : '';

		// top level menu for Workflows
		add_menu_page(
				__( 'Workflows', 'oasisworkflow' ),
				__( 'Workflows', 'oasisworkflow' ) . $count, $current_role,
				'oasiswf-inbox',
				array( $this, 'workflow_inbox_page_content' ),
				'',
				$position );

		// Inbox menu
		add_submenu_page( 'oasiswf-inbox',
				__( 'Inbox', 'oasisworkflow' ),
				__( 'Inbox', 'oasisworkflow' ) . $count, $current_role,
				'oasiswf-inbox',
				array( $this, 'workflow_inbox_page_content' ) );

		$workflow_terminology_options = get_option( 'oasiswf_custom_workflow_terminology' );
		$workflow_history_label = ! empty( $workflow_terminology_options['workflowHistoryText'] ) ? $workflow_terminology_options['workflowHistoryText'] : __( 'Workflow History' );

		// Workflow history menu - it can have a custom label, as defined in Settings -> Terminology
		if ( current_user_can( 'ow_view_workflow_history' ) ) {
			add_submenu_page( 'oasiswf-inbox',
					$workflow_history_label,
					$workflow_history_label,
					$current_role,
					'oasiswf-history',
					array( $this, 'workflow_history_page_content' ) );
		}

		// Reports
		if ( current_user_can( 'ow_view_reports' ) ) {
			add_submenu_page( 'oasiswf-inbox',
					__( 'Reports', 'oasisworkflow' ),
					__( 'Reports', 'oasisworkflow' ),
					$current_role,
					'oasiswf-reports',
					array( $this, 'workflow_reports_page_content' ) );
		}

		// Revision page - hidden from the menu, but called when "Compare Revision" is called
		add_submenu_page( 'oasiswf-revision',
				__( 'Revisions', 'oasisworkflow' ),
				__( 'Revisions', 'oasisworkflow' ),
				$current_role,
				'oasiswf-revision',
				array( $this, 'revision_compare_page_content' ) );

		// All Workflows - will display the workflow list
		if ( current_user_can( 'ow_create_workflow' ) || current_user_can( 'ow_edit_workflow' ) ) {
			add_submenu_page( 'oasiswf-inbox',
				__( 'All Workflows', 'oasisworkflow' ),
				__( 'All Workflows', 'oasisworkflow' ),
				'ow_create_workflow',
				'oasiswf-admin',
				array( $this, 'list_workflows_page_content' ) );
		}

		// Add New Workflow
		if ( current_user_can( 'ow_create_workflow' ) ) {
			add_submenu_page( 'oasiswf-inbox',
					__( 'Add New Workflow', 'oasisworkflow' ),
					__( 'Add New Workflow', 'oasisworkflow' ),
					'ow_create_workflow',
					'oasiswf-add',
					array( $this, 'create_workflow_page_content' ) );
		}

      if ( current_user_can( 'ow_create_workflow' ) ) {
			add_submenu_page( 'oasiswf-inbox',
					__( 'Custom Statuses', 'oasisworkflow' ),
					__( 'Custom Statuses', 'oasisworkflow' ),
					$current_role,
					'oasiswf-custom-statuses',
					array( $this, 'custom_statuses_page_content' ) );
		}

		if ( current_user_can( 'ow_export_import_workflow' ) ) {
			add_submenu_page( 'oasiswf-inbox',
				__( 'Tools', 'oasisworkflow' ),
				__( 'Tools', 'oasisworkflow' ),
				$current_role,
				'oasiswf-tools',
				array( $this, 'display_workflow_tools' ) );
		}
      
		// to add sub menus for add ons
		do_action( 'owf_add_submenu' );
      
      //show list of workflow add-ons
      add_submenu_page( 'oasiswf-inbox',
         __( 'Add-ons', 'oasisworkflow' ),
         __( 'Add-ons', 'oasisworkflow' ),
         'edit_theme_options',
         'oasiswf-addons',
         array( $this, 'addons_page_content' ) );
      
	}

   public function custom_statuses_page_content() {
      include( OASISWF_PATH . "includes/pages/ow-custom-statuses.php" );
   }
   
   public function display_workflow_tools() {
      include( OASISWF_PATH . "includes/pages/workflow-tools.php" );
   }
   
   public function addons_page_content(){
      include( OASISWF_PATH . "includes/pages/workflow-addons.php" );
   }

   public function load_css_and_js_files() {
		add_action( 'admin_print_styles', array( $this, 'add_css_files' ) );
		add_action( 'admin_print_scripts', array( $this, 'add_js_files' ) );
		add_action( 'admin_footer', array( $this, 'load_js_files_footer' ) );
	}

	/**
	 * Retrieves pointers for the current admin screen. Use the 'owf_admin_pointers' hook to add your own pointers.
	 *
	 * @return array Current screen pointers
	 * @since 3.2
	 */
	private function get_current_screen_pointers(){
		$pointers = '';

		$screen = get_current_screen();
		$screen_id = $screen->id;

		// Format : array( 'screen_id' => array( 'pointer_id' => array([options : target, content, position...]) ) );

		$welcome_title = __( "Welcome to Oasis Workflow", "oasisworkflow" );
		$img_html = "<img src='" . OASISWF_URL . "img/small-arrow.gif" . "' style='border:0px;' />";
		$welcome_message_1 = __( "To get started with Oasis Workflow follow the steps listed below.", "oasisworkflow" );
		$welcome_message_2 = sprintf( __( "1. Activate the plugin by providing a valid license key on Workflows %s Settings, License tab.", "oasisworkflow" ), $img_html );
		$welcome_message_3 = __( "2. Create a new workflow OR modify/use the \"Sample Workflow\" which comes with the plugin.", "oasisworkflow" );
		$welcome_message_4 = sprintf( __( "3. Activate the workflow process by going to Workflows %s Settings, Workflow tab.", "oasisworkflow" ), $img_html );
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$default_pointers = array(
				'toplevel_page_oasiswf-inbox' => array(
					'owf_install' => array(
						'target' => '#toplevel_page_oasiswf-inbox',
						'content' => '<h3>'. $welcome_title .'</h3> <p>'. $welcome_message_1 .'</p><p>'. $welcome_message_2 .'</p><p>'. $welcome_message_3 .'</p><p>'. $welcome_message_4 .'</p>',
						'position' => array( 'edge' => 'left', 'align' => 'center' ),
					)
				)
			);
		} else {
			$default_pointers = array(
					'plugins' => array(
							'owf_install' => array(
									'target' => '#toplevel_page_oasiswf-inbox',
									'content' => '<h3>'. $welcome_title .'</h3> <p>'. $welcome_message_1 .'</p><p>'. $welcome_message_2 .'</p><p>'. $welcome_message_3 .'</p><p>'. $welcome_message_4 .'</p>',
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
	 * @since 3.2
	 */
	public function show_welcome_message_pointers() {
		// Don't run on WP < 3.3
		if ( get_bloginfo( 'version' ) < '3.3' ){
			return;
		}

		// only show this message to the users who can activate plugins
		if ( ! current_user_can( 'activate_plugins' ) ){
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
			if ( in_array( $pointer_id, $dismissed ) || empty( $pointer )  || empty( $pointer_id ) || empty( $pointer['target'] ) || empty( $pointer['content'] ) )
				continue;

			// Add the pointer to $valid_pointers array
			$valid_pointers[$pointer_id] =  $pointer;
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
	 * @since 3.2
	 */
	public function display_pointers(){
		if ( ! empty( $this->current_screen_pointers ) ):
		?>
			<script type="text/javascript">// <![CDATA[
				 jQuery(document).ready(function($) {
					 if (typeof(jQuery().pointer) != 'undefined') {
							<?php foreach ( $this->current_screen_pointers as $pointer_id => $data): ?>
								 $('<?php echo $data['target'] ?>').pointer({
									  content: '<?php echo addslashes( $data['content'] ) ?>',
									  position: {
											edge: '<?php echo addslashes( $data['position']['edge'] ) ?>',
											align: '<?php echo addslashes( $data['position']['align'] ) ?>'
									  },
									  close: function() {
											$.post( ajaxurl, {
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
	 * If free version exists display an error asking the user to deactivate and delete the free version
	 * Deactivate the "Pro" version
	 *
	 */
	public function validate_free_version_exists() {
		$plugin = plugin_basename( __FILE__ );
		if ( is_plugin_active( 'oasis-workflow/oasiswf.php' ) ) {
			add_action( 'admin_notices', array( $this, 'deactivate_free_version_notice' )  );
			add_action( 'network_admin_notices', array( $this, 'deactivate_free_version_notice' ) );
			deactivate_plugins( $plugin );
			if( isset( $_GET['activate'] ) ) {
				// Do not sanitize it because we are destroying the variables from URL
				unset( $_GET['activate'] );
			}
			return;
		}
	}

	/**
	 * Admin notice for deactivating the "free" version
	 */
	public function deactivate_free_version_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo sprintf( __( 'In order to use the "Pro" version, you need to deactivate and delete the free/lite version of Oasis Workflow plugin on the %splugins page%s', 'oasis-workflow-pro' ),
					'<a href="' . wp_nonce_url( 'plugins.php?action=deactivate&amp;plugin=oasis-workflow%2Foasiswf.php&amp;plugin_status=all&amp;paged=1&amp;s=',
						'deactivate-plugin_oasis_workflow/oasiswf.php' ) . '">', '</a>' );
				?>
			</p>
		</div>
		<?php
	}


	/**
	 * New Workflow create action.
	 * This method is called when the menu item "Add New Workflow" is clicked.
	 *
	 * @since 2.0
	 */
	public function create_workflow_page_content() {
		include( OASISWF_PATH . "includes/pages/workflow-create.php" );
	}

	/**
	 * Workflows List Page action.
	 * This method is called when the menu item "All Workflows" is clicked.
	 *
	 * @since 2.0
	 */
	public function list_workflows_page_content() {

		$workflow_id = isset( $_GET['wf_id'] ) ? intval( sanitize_text_field( $_GET["wf_id"] ) ) : "";
		if ( ! empty ( $workflow_id ) ) {
			$this->create_workflow_page_content();
		} else {
			include( OASISWF_PATH . "includes/pages/workflow-list.php" );
		}
	}

	/**
	 * Inbox page action.
	 * This method is called when the menu item "Inbox" is clicked.
	 *
	 * @since 2.0
	 */
	public function workflow_inbox_page_content() {
		include( OASISWF_PATH . "includes/pages/workflow-inbox.php" );
	}

	/**
	 * Workflow History page action.
	 * This method is called when the menu item "History" is clicked
	 *
	 * @since 2.0
	 */
	public function workflow_history_page_content() {
		include( OASISWF_PATH . "includes/pages/workflow-history.php" );
		include( OASISWF_PATH . "includes/pages/subpages/delete-history.php" );
	}

	/**
	 * Workflow revision compare
	 * This method is called when "Revision Compare" is clicked.
	 *
	 * @since 2.0
	 */
	public function revision_compare_page_content() {
		include( OASISWF_PATH . "includes/pages/revision-compare.php" );
	}

	/**
	 * Reports page.
	 * This method is called when "Reports" is clicked.
	 */
	public function workflow_reports_page_content() {
		include( OASISWF_PATH . "includes/pages/workflow-reports.php" );
	}

	/**
	 * Custom cron interval - for auto submit.
	 * TODO: yet to be implemented.
	 *
	 * @since 3.0
	 */
	public function custom_cron_interval( $interval ) {

		$interval['minutes_15'] = array( 'interval' => 15 * 60, 'display' => __( '15 minutes', 'oasisworkflow' ) );
		$interval['minutes_30'] = array( 'interval' => 30 * 60, 'display' => __( '30 minutes', 'oasisworkflow' ) );
		$interval['minutes_45'] = array( 'interval' => 45 * 60, 'display' => __( '45 minutes', 'oasisworkflow' ) );
		$interval['hours_4']    = array( 'interval' => 240 * 60, 'display' => __( '4 hours', 'oasisworkflow' ) );
		$interval['hours_8']    = array( 'interval' => 480 * 60, 'display' => __( '8 hours', 'oasisworkflow' ) );

      // Filter to add the custom cron interval
		$interval = apply_filters( "owf_auto_submit_custom_interval", $interval );
      
		return $interval;
	}
   
   /**
	 * Redirect user to workflow inbox page if the user role is selected at 
    * workflow setting tab to make user redirect to inbox page else redirect to dashboard.
	 * @since 4.5
	 */
   public function dashboard_redirect( $url, $request, $user ) {
      if ( isset( $user->roles ) && ( ! empty( $user->roles ) ) ) {
         $login_redirect_roles = get_option( 'oasiswf_login_redirect_roles_setting' );
         /* If login redirect option is enabled
          * than redirect user to workflows inbox page
          */
         if ( is_array( $login_redirect_roles ) && array_key_exists( $user->roles[ 0 ], $login_redirect_roles ) ) {
            $login_url = admin_url() . 'admin.php?page=oasiswf-inbox';
            return $login_url;
         } else {
            // return to the requested url
            return $url;
         }
      } else {
         return $url;
      }
   }

   /**
	 * Load all the classes - as part of init action hook
	 *
	 * @since 2.0
	 */
	public function load_all_classes() {

		/*
		 * include model classes
		 */

		if ( ! class_exists( 'OW_Workflow_Step' ) ) {
			include( OASISWF_PATH . 'includes/models/class-ow-workflow-step.php' );
		}
      
      if ( ! class_exists( 'OW_Workflow' ) ) {
         include( OASISWF_PATH . 'includes/models/class-ow-workflow.php' );
      }      

		if ( ! class_exists( 'OW_Action_History' ) ) {
			include( OASISWF_PATH . 'includes/models/class-ow-action-history.php' );
		}

		if ( ! class_exists( 'OW_Review_History' ) ) {
			include( OASISWF_PATH . 'includes/models/class-ow-review-history.php' );
		}

		/*
		 * include service classes
		 */
		if ( ! class_exists( 'OW_Email' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-email.php' );
		}

		if ( ! class_exists( 'OW_Place_Holders' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-placeholders.php' );
		}

		if ( ! class_exists( 'OW_Workflow_Validator' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-workflow-validator.php' );
		}      
      
      if ( ! class_exists( 'OW_Workflow_Service' ) ) {
         include( OASISWF_PATH . 'includes/class-ow-workflow-service.php' );
      }

		if ( ! class_exists( 'OW_Process_Flow' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-process-flow.php' );
		}

		if ( ! class_exists( 'OW_History_Service' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-history-service.php' );
		}

		if ( ! class_exists( 'OW_Inbox_Service' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-inbox-service.php' );
		}

		if ( ! class_exists( 'OW_License_Service' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-license-service.php' );
		}

		if ( ! class_exists( 'OW_Revision_Service' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-revision-service.php' );
		}
      
      if ( ! class_exists( 'OW_Report_Service' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-report-service.php' );
		}
      
      if ( ! class_exists( 'OW_Auto_Submit_Service' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-auto-submit-service.php' );
		}
      
      if ( ! class_exists( 'OW_Duplicate_Service' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-duplicate-service.php' );
		}
     
		/*
		 * Settings classes
		 */
		if ( ! class_exists( 'OW_Settings_Base' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-settings-base.php' );
		}

		if ( ! class_exists( 'OW_License_Settings' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-license-settings.php' );
		}

		if ( ! class_exists( 'OW_Email_Settings' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-email-settings.php' );
		}

		if ( ! class_exists( 'OW_Workflow_Settings' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-workflow-settings.php' );
		}

		if ( ! class_exists( 'OW_Auto_Submit_Settings' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-auto-submit-settings.php' );
		}

		if ( ! class_exists( 'OW_Workflow_Terminology_Settings' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-workflow-terminology-settings.php' );
		}

		if ( ! class_exists( 'OW_Document_Revision_Settings' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-document-revision-settings.php' );
		}
      
      if ( ! class_exists( 'OW_Tools_Service' ) ) {
         include( OASISWF_PATH . 'includes/class-ow-tools-service.php' );
      }
      
      /*
		 * include API classes
		 */
      if ( ! class_exists( 'OWAPI' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-api.php' );
		}
	}

	/**
	 * Register custom post statuses - used by the revision process.
	 * And call the upgrade action.
	 *
	 * @since 2.0
	 */
	public function register_custom_post_types() {

		register_post_status( 'usedrev', array(
				'label' => __( 'Used Revision', 'oasisworkflow' ),
				'public' => false,
				'exclude_from_search' => true,
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => false
		) );

		register_post_status( 'currentrev', array(
				'label' => __( 'Current Revision', 'oasisworkflow' ),
				'public' => false,
				'exclude_from_search' => true,
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => false
		) );

		register_post_status( 'owf_scheduledrev', array(
				'label' => __( 'Scheduled Revision', 'oasisworkflow' ),
				'public' => true,
				'exclude_from_search' => true,
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => false
		) );

		$this->run_on_upgrade();
	}

	/**
	 * Put the "Workflows" main menu after the "Comments" menu
	 *
	 * @since 2.0
	 */
	private function get_menu_position( $decimal_loc ) {
		global $menu;

		$end_position = 0;
		$start_position = 0;

		foreach ( $menu as $k => $v ) {
			if ( $v[2] == "edit-comments.php" ) { // find position of Comments menu
				$start_position = $k;
			}
			if ( $v[2] == "themes.php" ) { // find position of Appearance menu
				$end_position = $k;
			}
			$menu_position[] = $k;
		}

		// place the Workflows menu in between Comments and Appearance menu
		for ( $i = $start_position; $i < $end_position; $i++ ) {
			// find a menu location which hasn't been used.
			if ( ! in_array( $i, $menu_position ) ) {
				$final_position = $i . $decimal_loc; // looks like we found one, so lets add the decimal position to it for uniqueness.
				return $final_position;
			}
		}
	}

	/**
	 * Run on deactivation
	 *
	 * Removes the custom capabilities added by the plugin
	 *
	 * @since 3.4
	 */
	private function run_on_deactivation() {
		/*
		 * Include the custom capability class
		 */
		if ( ! class_exists( 'OW_Custom_Capabilities' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-custom-capabilities.php' );
		}

		$ow_custom_capabilities = new OW_Custom_Capabilities();
		$ow_custom_capabilities->remove_capabilities();
	}

	/**
	 * Called on activation.
	 * Creates the site_options (required for all the sites in a multi-site setup)
	 * If the current version doesn't match the new version, runs the upgrade
	 * Also created the cron schedules for - auto submit and reminder emails
	 *
	 * @since 2.0
	 */
	private function run_on_activation() {
		$plugin_options = get_site_option( 'oasiswf_info' );
		if ( false === $plugin_options ) {
			$oasiswf_info = array(
					'version' => OASISWF_VERSION,
					'db_version' => OASISWF_DB_VERSION
			);

			$oasiswf_process_info = array(
					'assignment' => OASISWF_URL . 'img/assignment.gif',
					'review' => OASISWF_URL . 'img/review.gif',
					'publish' => OASISWF_URL . 'img/publish.gif'
			);

			$oasiswf_path_info = array(
					'success' => array( __( 'Success', 'oasisworkflow' ), 'blue' ),
					'failure' => array( __( 'Failure', 'oasisworkflow' ), 'red' )
			);

			$oasiswf_status = array(
					'assignment' => __( 'In Progress', 'oasisworkflow' ),
					'review'     => __( 'In Review', 'oasisworkflow' ),
					'publish'    => __( 'Ready to Publish', 'oasisworkflow' )
			);

			$oasiswf_placeholders = array(
					'%first_name%'          => __( 'first name', 'oasisworkflow' ),
					'%last_name%'           => __( 'last name', 'oasisworkflow' ),
					'%post_title%'          => __( 'post title', 'oasisworkflow' ),
					'%category%'            => __( 'category', 'oasisworkflow' ),
					'%last_modified_date%'  => __( 'last modified date', 'oasisworkflow' ),
               '%post_author%'         => __( 'post author', 'oasisworkflow' ),
               '%blog_name%'           => __( 'blog name', 'oasisworkflow' ),
               '%post_submitter%'      => __( 'post submitter', 'oasisworkflow' )
			);
         
         $oasiswf_email_placeholders = array(
					'{first_name}'          => __( 'First Name', 'oasisworkflow' ),
					'{last_name}'           => __( 'Last Name', 'oasisworkflow' ),
					'{post_title}'          => __( 'Post Title, this will be displayed as a link', 'oasisworkflow' ),
					'{category}'            => __( 'Category', 'oasisworkflow' ),
					'{last_modified_date}'  => __( 'Last Modified Date', 'oasisworkflow' ),
               '{publish_date}'        => __( 'Publish Date', 'oasisworkflow' ),
               '{post_author}'         => __( 'Post Author', 'oasisworkflow' ),
               '{blog_name}'           => __( 'Blog Name', 'oasisworkflow' ),
					'{current_user}'        => __( 'Current User', 'oasisworkflow' )
			);

			update_site_option( 'oasiswf_info', $oasiswf_info );
			update_site_option( 'oasiswf_process', $oasiswf_process_info );
			update_site_option( 'oasiswf_path', $oasiswf_path_info );
			update_site_option( 'oasiswf_status', $oasiswf_status );
			update_site_option( 'oasiswf_placeholders', $oasiswf_placeholders );
         update_site_option( 'oasiswf_email_placeholders', $oasiswf_email_placeholders );
		} elseif ( OASISWF_VERSION != $plugin_options['version'] ) {
			$this->run_on_upgrade();
		}

		if ( ! wp_next_scheduled('oasiswf_email_schedule') )
			wp_schedule_event(time(), 'daily', 'oasiswf_email_schedule');

		if ( ! wp_next_scheduled('oasiswf_auto_submit_schedule') )
			wp_schedule_event(time(), 'hourly', 'oasiswf_auto_submit_schedule');
      
      if ( ! wp_next_scheduled('oasiswf_email_digest_schedule') )
			wp_schedule_event(time(), 'hourly', 'oasiswf_email_digest_schedule');
      
      if ( ! wp_next_scheduled('oasiswf_auto_delete_history_schedule') )
			wp_schedule_event(time(), 'daily', 'oasiswf_auto_delete_history_schedule');
	}

	/**
	 * Called on activation.
	 * Creates the options and DB (required by per site)
	 *
	 *  @since 2.0
	 */
	private function run_for_site() {
      
      global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		// add ow_submit_to_workflow and ow_sign_off_step to all the existing roles.
		if ( ! function_exists('get_editable_roles') ) {
			require_once( ABSPATH . '/wp-admin/includes/user.php' );
		}

		/*
		 * Include the custom capability class
		 */
		if ( ! class_exists( 'OW_Custom_Capabilities' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-custom-capabilities.php' );
		}

		$ow_custom_capabilities = new OW_Custom_Capabilities();
		$ow_custom_capabilities->add_capabilities();

		$auto_submit_stati = array( 'draft' );

		$auto_submit_settings = array(
				'auto_submit_stati' => $auto_submit_stati,
				'auto_submit_due_days' => '1',
				'auto_submit_comment' => '',
				'auto_submit_post_count' => '10',
				'auto_submit_enable' => false,
				'search_post_title' => 'yes',
				'search_post_tags' => 'yes',
				'search_post_categories' => 'yes'
		);

      /*
		 * Add out of the box custom statuses
		 */
		if ( ! class_exists( 'OW_Custom_Statuses' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-custom-statuses.php' );
		}
      $ow_custom_statuses = new OW_Custom_Statuses();
      $ow_custom_statuses->register_custom_taxonomy();

      $custom_statuses = array(
          'Pitch' => __( 'New idea proposed.', 'oasisworkflow' ),
          'With Author' => __( 'An author has been assigned to the post.', 'oasisworkflow' ),
          'Ready to Publish' => __( 'The post is ready for publication.', 'oasisworkflow' )
      );

      foreach ( $custom_statuses as $custom_status => $desc ) {

         if ( term_exists( $custom_status ) ) {
            continue;
         }

         $args = array(
         	'description' => $desc,
         	'slug' => sanitize_title( $custom_status )
         );
         $response = wp_insert_term( $custom_status, 'post_status', $args );
      }

		$show_wfsettings_on_post_types = array( 'post', 'page' );

		// Copy children when Make Revision
		$copy_children_on_revision = "";

		//Priority Setting - initial setting is enabled
		$priority_setting = "enable_priority";

		//Get workflow participating roles
		$participating_roles = OW_Utility::instance()->get_participating_roles();
      
      // Auto delete history initial settings
      $auto_delete_history = array(
				'enable' => '',
				'period' => 'one-month-ago',				
		);
     
		// default roles for bulk actions
		$bulk_approval_roles = array( 'administrator' );

      // default message for make revision overlay
      $doc_revision_make_revision_overlay_message = __( 'You may not make changes to this published content. You must first make a revision and then submit your changes for approval.', 'oasisworkflow' );

		if ( ! get_option( 'oasiswf_show_wfsettings_on_post_types' ) ) {
			update_option( "oasiswf_show_wfsettings_on_post_types", $show_wfsettings_on_post_types );
		}

		if ( ! get_option( 'oasiswf_auto_submit_settings' ) ) {
			update_option( "oasiswf_auto_submit_settings", $auto_submit_settings );
		}

		// document revision site options
		if ( ! get_option( 'oasiswf_doc_revision_title_prefix' ) ) {
			update_option( "oasiswf_doc_revision_title_prefix", __( 'Copy of -', "oasisworkflow" ) );
		}

		if ( ! get_option( 'oasiswf_doc_revision_title_suffix' ) ) {
			update_option( "oasiswf_doc_revision_title_suffix", '' );
		}

		if ( ! get_option( 'oasiswf_copy_children_on_revision' ) ) {
			update_option( "oasiswf_copy_children_on_revision", $copy_children_on_revision );
		}
      
		if ( ! get_option( 'oasiswf_priority_setting' ) ) {
         update_option( "oasiswf_priority_setting", $priority_setting );
	   }
      
      // Step due date Setting
		if ( ! get_option( 'oasiswf_step_due_date_settings' ) ) {
			update_option( "oasiswf_step_due_date_settings", '' );
		}
      
      // Publish date Setting
		if ( ! get_option( 'oasiswf_publish_date_setting' ) ) {
			update_option( "oasiswf_publish_date_setting", '' );
		}
      
      if ( ! get_option( 'oasiswf_participating_roles_setting' ) ) {
			update_option( "oasiswf_participating_roles_setting", $participating_roles );
		}
      
      if ( ! get_option( 'oasiswf_login_redirect_roles_setting' ) ) {
			update_option( "oasiswf_login_redirect_roles_setting", '' );
		}
      
      if ( ! get_option( 'oasiswf_auto_delete_history_setting' ) ) {
			update_option( "oasiswf_auto_delete_history_setting", $auto_delete_history );
		}

		$enable_revision_process = "active";

		if ( ! get_option( 'oasiswf_activate_revision_process' ) ) {
			update_option( "oasiswf_activate_revision_process", $enable_revision_process );
		}

		$delete_revision_on_copy = "yes";

		if ( ! get_option( 'oasiswf_delete_revision_on_copy' ) ) {
			update_option( "oasiswf_delete_revision_on_copy", $delete_revision_on_copy );
		}

		$email_settings = array(
				'from_name' => '',
				'from_email_address' => '',
				'assignment_emails' => 'no',
				'reminder_emails' => 'no',
            'digest_emails' => ''
		);
		if ( ! get_option( 'oasiswf_email_settings' ) ) {
			update_option( "oasiswf_email_settings", $email_settings );
		}

		if ( ! get_option( 'oasiswf_roles_can_bulk_approval' ) ) {
			update_option( "oasiswf_roles_can_bulk_approval", $bulk_approval_roles );
		}

		if ( ! get_option( 'oasiswf_revise_post_make_revision_overlay' ) ) {
			update_option( 'oasiswf_revise_post_make_revision_overlay', $doc_revision_make_revision_overlay_message );
		}

		$this->install_admin_database();
		$this->install_site_database();
	}

	/**
	 * called on upgrade. checks the current version and applies the necessary upgrades from that version onwards
	 *
	 * @since 2.0
	 */
	public function run_on_upgrade() {
		$plugin_options = get_site_option( 'oasiswf_info' );

		if ( $plugin_options['version'] == "2.0.0" ) {
			$this->upgrade_database_202();
			$this->upgrade_database_21();
			$this->upgrade_database_22();
			$this->upgrade_database_23();
			$this->upgrade_database_26();
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.0.1" ) {
			$this->upgrade_database_202();
			$this->upgrade_database_21();
			$this->upgrade_database_22();
			$this->upgrade_database_23();
			$this->upgrade_database_26();
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.0.2" ) {
			$this->upgrade_database_21();
			$this->upgrade_database_22();
			$this->upgrade_database_23();
			$this->upgrade_database_26();
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.1" ) {
			$this->upgrade_database_22();
			$this->upgrade_database_23();
			$this->upgrade_database_26();
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.2" ) {
			$this->upgrade_database_23();
			$this->upgrade_database_26();
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.3" ) {
			$this->upgrade_database_26();
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.4" ) {
			$this->upgrade_database_26();
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.5" ) {
			$this->upgrade_database_26();
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.6" ) {
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "2.7" ) {
			$this->upgrade_database_30();
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "3.0" ) {
			$this->upgrade_database_31();
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "3.1" ) {
			$this->upgrade_database_32();
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "3.2" ) {
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "3.3" ) {
			$this->upgrade_database_34();
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "3.4" ) {
			$this->upgrade_database_35();
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "3.5" ) {
			$this->upgrade_database_36();
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "3.6" ) {
			$this->upgrade_database_37();
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif ( $plugin_options['version'] == "3.7" ) {
			$this->upgrade_database_38();
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif( $plugin_options['version'] == '3.8' ) {
			$this->upgrade_database_40();
			$this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif( $plugin_options['version'] == '3.9' ) {
         $this->upgrade_database_40();
         $this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
      } elseif( $plugin_options['version'] == '4.0' ) {
         $this->upgrade_database_41();
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
      } elseif( $plugin_options['version'] == '4.1' ) {
         $this->upgrade_database_42();
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
      } elseif( $plugin_options['version'] == '4.2' ) {
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
      } elseif( $plugin_options['version'] == '4.3' ) {
         $this->upgrade_database_44();
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
      } elseif( $plugin_options['version'] == '4.4' ) {
         $this->upgrade_database_45();
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
      } elseif( $plugin_options['version'] == '4.5' ) {
         $this->upgrade_database_46();
         $this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
      } elseif( $plugin_options['version'] == '4.6' ) {
			$this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif( $plugin_options['version'] == '4.7' ) {
			$this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif( $plugin_options['version'] == '4.8' ) {
			$this->upgrade_database_49();
         $this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif( $plugin_options['version'] == '4.9' ) {
			$this->upgrade_database_50();
         $this->upgrade_database_53();
		} elseif( $plugin_options['version'] == '5.0' ) {
        $this->upgrade_database_53();
      } elseif( $plugin_options['version'] == '5.1' ) {
			$this->upgrade_database_53();
		} elseif( $plugin_options['version'] == '5.2' ) {
			$this->upgrade_database_53();
		} elseif( $plugin_options['version'] == '5.3' ) {
			// nothing to upgrade
		} elseif( $plugin_options['version'] == '5.4' ) {
			// nothing to upgrade
		}


		// update the version value
		$oasiswf_info = array(
				'version' => OASISWF_VERSION,
				'db_version' => OASISWF_DB_VERSION
		);
		update_site_option( 'oasiswf_info', $oasiswf_info );
	}

	/**
	 * Called on uninstall - deletes site_options
	 *
	 * @since 2.0
	 */
	private static function run_on_uninstall() {
		if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) )
			exit();

		global $wpdb; //required global declaration of WP variable
		delete_site_option( 'oasiswf_info' );
		delete_site_option( 'oasiswf_process' );
		delete_site_option( 'oasiswf_path' );
		delete_site_option( 'oasiswf_status' );
		delete_site_option( 'oasiswf_placeholders' );

		// delete the dismissed_wp_pointers entry for this plugin
		$blog_users = get_users( 'role=administrator' );
		foreach ( $blog_users as $user ) {
			$dismissed = explode( ',', (string) get_user_meta( $user->ID, 'dismissed_wp_pointers', true ) );
			if ( ( $key = array_search( "owf_install", $dismissed ) ) !== false ) {
				unset( $dismissed[$key] );
			}

			$updated_dismissed = implode( ",", $dismissed );
			update_user_meta( $user->ID, "dismissed_wp_pointers", $updated_dismissed );
		}
	}

	/**
	 * Called on uninstall - deletes site specific options
	 *
	 * @since 2.0
	 */
	private static function delete_for_site() {
		global $wpdb;

		// deactivate the license
		$license = trim( get_option( 'oasiswf_license_key' ) );

		// data to send in our API request
		$api_params = array(
				'edd_action' => 'deactivate_license',
				'license' => $license,
				'item_name' => urlencode( OASISWF_PRODUCT_NAME ), // the name of our product in EDD
				'url' => home_url()
		);

		// Call the custom API.
		$response = wp_remote_post( OASISWF_STORE_URL, array( 'timeout' => 15, 'body' => $api_params ) );

		delete_option( 'oasiswf_license_status' );
		delete_option( 'oasiswf_license_key' );

		/*
		 * Include the custom capability class
		 */
		if ( ! class_exists( 'OW_Custom_Capabilities' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-custom-capabilities.php' );
		}

		$ow_custom_capabilities = new OW_Custom_Capabilities();
		$ow_custom_capabilities->remove_capabilities();

		delete_option( 'oasiswf_activate_workflow' );
		delete_option( 'oasiswf_doc_revision_title_prefix' );
		delete_option( 'oasiswf_doc_revision_title_suffix' );
		delete_option( 'oasiswf_default_due_days' );
		delete_option( 'oasiswf_reminder_days' );
		delete_option( 'oasiswf_show_wfsettings_on_post_types' );
		delete_option( 'oasiswf_reminder_days_after' );
		delete_option( 'oasiswf_auto_submit_settings' );

		delete_option( 'oasiswf_delete_revision_on_copy' );
		delete_option( 'oasiswf_copy_children_on_revision' );
		delete_option( 'oasiswf_activate_revision_process' );
		delete_option( 'oasiswf_revise_post_make_revision_overlay' );
      delete_option( 'oasiswf_preserve_revision_of_revised_article' );

		// delete all the post meta created by this plugin
		delete_post_meta_by_key( '_oasis_original' );
		delete_post_meta_by_key( '_oasis_is_in_workflow' );
		delete_post_meta_by_key( '_oasis_current_revision' );
		delete_post_meta_by_key( '_oasis_is_in_team' );
      delete_post_meta_by_key( '_oasis_task_priority' );

		delete_option( 'oasiswf_email_settings' );
		delete_option( 'oasiswf_roles_can_bulk_approval' );
		delete_option( 'oasiswf_hide_compare_button' );
		delete_option( 'oasiswf_custom_workflow_terminology' );
		delete_option( 'oasiswf_priority_setting' );
		delete_option( 'oasiswf_revise_post_roles' );
		delete_option( 'oasiswf_participating_roles_setting' );
		delete_option( 'oasiswf_publish_date_setting' );
      delete_option( 'oasiswf_step_due_date_settings' );
      delete_option( 'oasiswf_login_redirect_roles_setting' );
      delete_option( 'oasiswf_auto_delete_history_setting' );
      delete_option( 'oasiswf_post_publish_email_settings' );
      delete_option( 'oasiswf_revised_post_email_settings' );
      delete_option( 'oasiswf_unauthorized_update_email_settings' );
      delete_option( 'oasiswf_task_claim_email_settings' );
      delete_option( 'oasiswf_post_submit_email_settings' );
      delete_option( 'oasiswf_workflow_abort_email_settings' );


		$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name like 'workflow_%'" );

		$wpdb->query( "DROP TABLE IF EXISTS " . OW_Utility::instance()->get_emails_table_name() );
		$wpdb->query( "DROP TABLE IF EXISTS " . OW_Utility::instance()->get_action_history_table_name() );
		$wpdb->query( "DROP TABLE IF EXISTS " . OW_Utility::instance()->get_action_table_name() );
		$wpdb->query( "DROP TABLE IF EXISTS " . OW_Utility::instance()->get_workflow_steps_table_name());
		$wpdb->query( "DROP TABLE IF EXISTS " . OW_Utility::instance()->get_workflows_table_name());
	}

	/**
	 * Invoked when a new blog is added in a multi-site setup
	 *
	 * @since 2.0
	 */
	public function run_on_add_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		global $wpdb;
		if ( is_plugin_active_for_network( basename( dirname( __FILE__ ) ) . '/oasis-workflow-pro.php' ) ) {
			$old_blog = $wpdb->blogid;
			switch_to_blog( $blog_id );
			$this->run_for_site();
			restore_current_blog();
		}
	}

	/**
	 * Invoked with a blog is deleted in a multi-site setup
	 *
	 * @since 2.0
	 */
	public function run_on_delete_blog( $blog_id, $drop ) {
		global $wpdb;
		switch_to_blog( $blog_id );
		$this->delete_for_site();
		restore_current_blog();
	}

	/**
	 * 1.0.5 upgrade function
	 *
	 * @since 1.0.5
	 */
	private function upgrade_database_105() {
		global $wpdb;

		//fc_workflows table alter
		$table_name = OW_Utility::instance()->get_workflows_table_name();
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY start_date DATE" );
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY end_date DATE" );
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY wf_info longtext" );

		//fc_workflow_steps table alter
		$table_name = OW_Utility::instance()->get_workflow_steps_table_name();
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY create_datetime datetime" );
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY update_datetime datetime" );
	}

	/**
	 * 1.0.6 upgrade function
	 *
	 * @since 1.0.6
	 */
	private function upgrade_database_106() {
		global $wpdb;

		//fc_workflows table alter, add new column "wf_additional_info" at end
		$table_name = OW_Utility::instance()->get_workflows_table_name();
		$wpdb->query( "ALTER TABLE {$table_name} ADD wf_additional_info mediumtext" );
		$oasiswf_placeholders = array(
				'%first_name%' => __( 'first name', "oasisworkflow" ),
				'%last_name%' => __( 'last name', "oasisworkflow" ),
				'%post_title%' => __( 'post title', "oasisworkflow" ),
				'%category%' => __( 'category', "oasisworkflow" ),
				'%last_modified_date%' => __( 'last modified date', "oasisworkflow" )
		);
		$show_wfsettings_on_post_types = array( 'post', 'page' );
		$post_published_notification = "active";

		update_site_option( 'oasiswf_placeholders', $oasiswf_placeholders );
		update_site_option( "oasiswf_show_wfsettings_on_post_types", $show_wfsettings_on_post_types );
		update_site_option( "oasiswf_post_published_notification", $post_published_notification );
		// seed default values to new added field
		$additional_info = stripcslashes( 'a:2:{s:16:"wf_for_new_posts";i:1;s:20:"wf_for_revised_posts";i:1;}' );
		$wpdb->query( "UPDATE {$table_name} SET wf_additional_info = '" . $additional_info . "'" );
	}

	/**
	 * 1.0.8 upgrade function
	 *
	 * @since 1.0.8
	 */
	private function upgrade_database_108() {
		global $wpdb;

		// Copy children when Make Revision
		$copy_children_on_revision = "";

		// Review Setting - initial setting - everyone should approve
		$review_process_setting = "everyone";

		// default setting for roles who can view other user's inbox.
		$view_other_users_inbox_roles = array( "administrator" );

		// default setting for activating the revision process
		$enable_revision_process = "active";

		update_site_option( "oasiswf_view_other_users_inbox_roles", $view_other_users_inbox_roles );
		update_site_option( "oasiswf_copy_children_on_revision", $copy_children_on_revision );
		update_site_option( "oasiswf_review_process_setting", $review_process_setting );
		update_site_option( "oasiswf_activate_revision_process", $enable_revision_process );

		// look through each of the blogs and upgrade the DB
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			//Get all blog ids; foreach them and call the uninstall procedure on each of them
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

			//Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				if ( $wpdb->query( "SHOW TABLES FROM " . $wpdb->dbname . " LIKE '" . $wpdb->prefix . "fc_%'" ) ) {
					$this->upgrade_helper_108();
				}
				restore_current_blog();
			}
			return;
		}
		$this->upgrade_helper_108();
	}

	/**
	 * 1.0.8 upgrade helper function
	 *
	 * @since 1.0.8
	 */
	private function upgrade_helper_108() {
		global $wpdb;
		$action_table = OW_Utility::instance()->get_action_table_name();
		$action_history_table = OW_Utility::instance()->get_action_history_table_name();

		// get all the records for action_history_id, step_id and actor_id combination
		$multiple_records = $wpdb->get_results( "select t1.id AS id,	t1.action_history_id, concat(t1.action_history_id, '-', t1.step_id, '-', t1.actor_id) AS historyid_stepid_actorid, t1.reassign_actor_id AS next_actors FROM {$action_table} AS t1 ORDER BY t1.action_history_id, t1.actor_id" );

		// make historyid_stepid_actorid as key and get all the next assigned actors
		$multi_actors = array();
		$affected_action_history = array();
		foreach ( $multiple_records as $record ) {
			$multi_actors[][$record->historyid_stepid_actorid] = $record;
			if ( ! in_array( $record->action_history_id, $affected_action_history ) ) {
				$affected_action_history[] = $record->action_history_id;
			}
		}
		$ressigned_actors = array();
		foreach ( $multi_actors as $actor ) {
			foreach ( $actor as $id => $a ) {
				if ( array_key_exists( $id, $ressigned_actors ) ) {
					$ressigned_actors[$id] .= ',' . $a->next_actors;
				} else {
					$ressigned_actors[$id] = $a->next_actors;
				}
			}
		}
		foreach ( $ressigned_actors as $k => $v ) {
			$ressigned_actors[$k] = explode( ',', $v );
		}

		// we should now have all the next_assign_actors for a given history, step and actor combination
		// now add the new column to the database
		$new_col = $wpdb->query( "ALTER TABLE {$action_table} add next_assign_actors text after reassign_actor_id" );

		// lets assign the value to this new attribute
		foreach ( $ressigned_actors as $k => $v ) {
			$key = explode( '-', $k );
			$history_id = $key[0];
			$step_id = $key[1];
			$actor_id = $key[2];

			$result = $wpdb->update(
					$action_table, array(
							'next_assign_actors' => json_encode( $v )
					), array(
							'action_history_id' => $history_id,
							'step_id' => $step_id,
							'actor_id' => $actor_id
					)
			);
		}



		// delete duplicate records
		$remove_dup_records = $wpdb->query( "DELETE t1 FROM {$action_table} t1, {$action_table} t2
		WHERE t1.id > t2.id
		AND t1.action_history_id = t2.action_history_id
		AND t1.step_id = t2.step_id
		AND t1.actor_id = t2.actor_id" );

		// remove the old column
		$remove_old_col = $wpdb->query( "ALTER TABLE {$action_table} DROP reassign_actor_id" );
	}

	/**
	 * 1.0.10 upgrade function
	 *
	 * @since 1.0.10
	 */
	private function upgrade_database_1010() {
		global $wpdb;
		$table_name = OW_Utility::instance()->get_workflows_table_name();
		$wpdb->query( "ALTER TABLE {$table_name} CHANGE COLUMN auto_submit_keywords auto_submit_info mediumtext" );
	}

	/**
	 * 2.0.2 upgrade function
	 * @since 2.0.2
	 */
	private function upgrade_database_202() {
		$abort_workflow_roles = array( 'administrator' );
		update_site_option( "oasiswf_abort_workflow_roles", $abort_workflow_roles );
	}

	/**
	 * 2.1 upgrade function
	 * @since 2.1
	 */
	private function upgrade_database_21() {
		$delete_revision_on_copy = "yes";
		update_site_option( "oasiswf_delete_revision_on_copy", $delete_revision_on_copy );

		$email_settings = array(
				'from_name' => '',
				'from_email_address' => '',
				'assignment_emails' => 'yes',
				'reminder_emails' => 'no',
				'post_publish_emails' => 'yes'
		);
		update_site_option( "oasiswf_email_settings", $email_settings );

		if ( get_site_option( 'oasiswf_post_published_notification' ) ) {
			delete_site_option( 'oasiswf_post_published_notification' );
		}

		$auto_submit_settings = get_site_option( 'oasiswf_auto_submit_settings' );

		$auto_submit_settings = array(
				'auto_submit_stati' => $auto_submit_settings['auto_submit_stati'],
				'auto_submit_due_days' => $auto_submit_settings['auto_submit_due_days'],
				'auto_submit_comment' => $auto_submit_settings['auto_submit_comment'],
				'auto_submit_post_count' => $auto_submit_settings['auto_submit_post_count'],
				'auto_submit_enable' => $auto_submit_settings['auto_submit_enable'],
				'search_post_title' => 'yes',
				'search_post_tags' => 'no',
				'search_post_categories' => 'no'
		);

		update_site_option( "oasiswf_auto_submit_settings", $auto_submit_settings );

		// default setting for roles which can abort the workflows
		$abort_workflow_roles = array( "administrator" );
		update_site_option( "oasiswf_abort_workflow_roles", $abort_workflow_roles );
	}

	/**
	 * 2.2 upgrade function
	 *
	 * @since 2.2
	 */
	private function upgrade_database_22() {
		update_site_option( "oasiswf_hide_compare_button", "" );
	}

	/**
	 * 2.3 upgrade function
	 *
	 * @since 2.3
	 */
	private function upgrade_database_23() {
		$email_settings = get_site_option( 'oasiswf_email_settings' );
		$new_email_settings = array(
				'from_name' => $email_settings['from_name'],
				'from_email_address' => $email_settings['from_email_address'],
				'assignment_emails' => $email_settings['assignment_emails'],
				'reminder_emails' => $email_settings['reminder_emails'],
				'post_publish_emails' => $email_settings['post_publish_emails'],
				'unauthorized_post_update_emails' => 'yes'
		);
		update_site_option( "oasiswf_email_settings", $new_email_settings );
	}

	/**
	 * 2.6 upgrade function
	 *
	 * @since 2.6
	 */
	private function upgrade_database_26() {
		$bulk_approval_roles = array( 'administrator' );
		update_site_option( "oasiswf_roles_can_bulk_approval", $bulk_approval_roles );
	}

	/**
	 * 3.0 upgrade function
	 * move site options to options.
	 */
	private function upgrade_database_30()
	{
		global $wpdb;

		// look through each of the blogs and upgrade the DB
		if (function_exists('is_multisite') && is_multisite())
		{
			//Get all blog ids; foreach them and call the uninstall procedure on each of them
			$blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->base_prefix}blogs");

			//Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
			foreach ( $blog_ids as $blog_id )
			{
				switch_to_blog( $blog_id );
				if ( $wpdb->query( "SHOW TABLES FROM ".$wpdb->dbname." LIKE '".$wpdb->prefix."fc_%'" ) )
				{
					$this->upgrade_helper_30();
				}
				restore_current_blog();
			}

			// delete the site_options since we have added the same to respective site

			//license settings
			if ( get_site_option('oasiswf_license_key') ) {
				delete_site_option('oasiswf_license_key' );
			}

			if ( get_site_option('oasiswf_license_status') ) {
				delete_site_option('oasiswf_license_status' );
			}

			// workflow settings
			if ( get_site_option('oasiswf_activate_workflow') ) {
				delete_site_option('oasiswf_activate_workflow' );
			}

			if ( get_site_option('oasiswf_default_due_days') ) {
				delete_site_option('oasiswf_default_due_days' );
			}

			if ( get_site_option('oasiswf_skip_workflow_roles') ) {
				delete_site_option('oasiswf_skip_workflow_roles' );
			}

			if ( get_site_option('oasiswf_view_other_users_inbox_roles') ) {
				delete_site_option('oasiswf_view_other_users_inbox_roles' );
			}

			if ( get_site_option('oasiswf_abort_workflow_roles') ) {
				delete_site_option('oasiswf_abort_workflow_roles' );
			}

			if ( get_site_option('oasiswf_show_wfsettings_on_post_types') ) {
				delete_site_option('oasiswf_show_wfsettings_on_post_types' );
			}

			if ( get_site_option('oasiswf_review_process_setting') ) {
				delete_site_option('oasiswf_review_process_setting' );
			}

			if ( get_site_option('oasiswf_roles_can_bulk_approval') ) {
				delete_site_option('oasiswf_roles_can_bulk_approval' );
			}

			// Email settings
			if ( get_site_option('oasiswf_email_settings') ) {
				delete_site_option('oasiswf_email_settings' );
			}

			if ( get_site_option('oasiswf_reminder_days') ) {
				delete_site_option('oasiswf_reminder_days' );
			}

			if ( get_site_option('oasiswf_reminder_days_after') ) {
				delete_site_option('oasiswf_reminder_days_after' );
			}

			// auto submit settings
			if (get_site_option('oasiswf_auto_submit_settings')) {
				delete_site_option('oasiswf_auto_submit_settings' );
			}

			// document revision settings
			if ( get_site_option('oasiswf_doc_revision_title_prefix') ) {
				delete_site_option('oasiswf_doc_revision_title_prefix' );
			}

			if ( get_site_option('oasiswf_doc_revision_title_suffix') ) {
				delete_site_option('oasiswf_doc_revision_title_suffix' );
			}

			if ( get_site_option('oasiswf_copy_children_on_revision') ) {
				delete_site_option('oasiswf_copy_children_on_revision' );
			}

			if ( get_site_option('oasiswf_delete_revision_on_copy') ) {
				delete_site_option('oasiswf_delete_revision_on_copy' );
			}

			if ( get_site_option('oasiswf_activate_revision_process') ) {
				delete_site_option('oasiswf_activate_revision_process' );
			}

			if ( get_site_option('oasiswf_hide_compare_button') ) {
				delete_site_option('oasiswf_hide_compare_button' );
			}

			// workflow terminology settings
			if ( get_site_option('oasiswf_custom_workflow_terminology') ) {
				delete_site_option('oasiswf_custom_workflow_terminology' );
			}
		}

	}

	/**
	 * 3.0 upgrade helper
	 * Convert site options to options
	 *
	 * @since 3.0
	 */
	private function upgrade_helper_30()
	{
		global $wpdb;

		// add the wp_options to respective sites

		//license settings
		if ( get_site_option( 'oasiswf_license_key' ) ) {
			update_option( 'oasiswf_license_key', get_site_option( 'oasiswf_license_key' ) );
		}

		if ( get_site_option( 'oasiswf_license_status' ) ) {
			update_option( 'oasiswf_license_status', get_site_option( 'oasiswf_license_status' ) );
		}

		// workflow settings
		if ( get_site_option( 'oasiswf_activate_workflow' ) ) {
			update_option( 'oasiswf_activate_workflow', get_site_option( 'oasiswf_activate_workflow' ) );
		}

		if ( get_site_option( 'oasiswf_default_due_days' ) ) {
			update_option( 'oasiswf_default_due_days', get_site_option( 'oasiswf_default_due_days' ) );
		}

		if ( get_site_option( 'oasiswf_skip_workflow_roles' ) ) {
			update_option( 'oasiswf_skip_workflow_roles', get_site_option( 'oasiswf_skip_workflow_roles' ) );
		}

		if ( get_site_option( 'oasiswf_view_other_users_inbox_roles' ) ) {
			update_option( 'oasiswf_view_other_users_inbox_roles', get_site_option( 'oasiswf_view_other_users_inbox_roles' ) );
		}

		if ( get_site_option( 'oasiswf_abort_workflow_roles' ) ) {
			update_option( 'oasiswf_abort_workflow_roles', get_site_option( 'oasiswf_abort_workflow_roles' ) );
		}

		if ( get_site_option( 'oasiswf_show_wfsettings_on_post_types' ) ) {
			update_option( 'oasiswf_show_wfsettings_on_post_types', get_site_option( 'oasiswf_show_wfsettings_on_post_types' ) );
		}

		if ( get_site_option( 'oasiswf_review_process_setting' ) ) {
			update_option( 'oasiswf_review_process_setting', get_site_option( 'oasiswf_review_process_setting' ) );
		}

		if ( get_site_option( 'oasiswf_roles_can_bulk_approval' ) ) {
			update_option( 'oasiswf_roles_can_bulk_approval', get_site_option('oasiswf_roles_can_bulk_approval'));
		}

		// Email settings
		if ( get_site_option( 'oasiswf_email_settings' ) ) {
			update_option( 'oasiswf_email_settings', get_site_option( 'oasiswf_email_settings' ) );
		}

		if ( get_site_option( 'oasiswf_reminder_days' ) ) {
			update_option( 'oasiswf_reminder_days', get_site_option( 'oasiswf_reminder_days' ) );
		}

		if ( get_site_option( 'oasiswf_reminder_days_after' ) ) {
			update_option( 'oasiswf_reminder_days_after', get_site_option( 'oasiswf_reminder_days_after' ) );
		}

		// auto submit settings
		if ( get_site_option( 'oasiswf_auto_submit_settings' ) ) {
			update_option( 'oasiswf_auto_submit_settings', get_site_option( 'oasiswf_auto_submit_settings' ) );
		}

		// document revision settings
		if ( get_site_option( 'oasiswf_doc_revision_title_prefix' ) ) {
			update_option( 'oasiswf_doc_revision_title_prefix', get_site_option( 'oasiswf_doc_revision_title_prefix' ) );
		}

		if ( get_site_option( 'oasiswf_doc_revision_title_suffix' ) ) {
			update_option( 'oasiswf_doc_revision_title_suffix', get_site_option( 'oasiswf_doc_revision_title_suffix' ) );
		}

		if ( get_site_option( 'oasiswf_copy_children_on_revision' ) ) {
			update_option( 'oasiswf_copy_children_on_revision', get_site_option( 'oasiswf_copy_children_on_revision' ) );
		}

		if ( get_site_option( 'oasiswf_delete_revision_on_copy' ) ) {
			update_option( 'oasiswf_delete_revision_on_copy', get_site_option( 'oasiswf_delete_revision_on_copy' ) );
		}

		if ( get_site_option( 'oasiswf_activate_revision_process' ) ) {
			update_option( 'oasiswf_activate_revision_process', get_site_option( 'oasiswf_activate_revision_process' ) );
		}

		if ( get_site_option('oasiswf_hide_compare_button' ) ) {
			update_option( 'oasiswf_hide_compare_button', get_site_option( 'oasiswf_hide_compare_button' ) );
		}

		// workflow terminology settings
		if ( get_site_option( 'oasiswf_custom_workflow_terminology' ) ) {
			update_option( 'oasiswf_custom_workflow_terminology', get_site_option( 'oasiswf_custom_workflow_terminology' ) );
		}

		// create tables and data only if the workflow tables do not exist
		if( $wpdb->query( "SHOW TABLES FROM ".$wpdb->dbname." LIKE '".$wpdb->prefix."fc_workflow%'" ) ) {
			return;
		}

		// first create the tables and put the default data
		$this->install_admin_database();

		// delete the default data
		//fc_workflow_steps table
		$wpdb->query( "DELETE FROM " . OW_Utility::instance()->get_workflow_steps_table_name() );

		//fc_workflows table
		$wpdb->get_results( "DELETE FROM " . OW_Utility::instance()->get_workflows_table_name() );

		// now insert data from the original/main table into these new tables
		$sql = "INSERT INTO " . OW_Utility::instance()->get_workflows_table_name() . " SELECT * FROM " . $wpdb->base_prefix . "fc_workflows";
		$wpdb->get_results( $sql );

		$sql = "INSERT INTO " . OW_Utility::instance()->get_workflow_steps_table_name() . " SELECT * FROM " . $wpdb->base_prefix . "fc_workflow_steps";
		$wpdb->get_results( $sql );
	}

	/**
	 * 3.1 upgrade function
	 *
	 * @since 3.1
	 */
	private function upgrade_database_31() {
		$revise_post_roles = array( "administrator", "owfpostauthor", "editor", "author", "contributor" );
		update_option( "oasiswf_revise_post_roles", $revise_post_roles );
	}

	private function upgrade_database_32() {
		// update the dismissed pointer/message for existing plugin users.
		$blog_users = get_users( 'role=administrator' );
		foreach ( $blog_users as $user ) {
			$dismissed = (string) get_user_meta( $user->ID, 'dismissed_wp_pointers', true );
			$dismissed = $dismissed . "," . "owf_install";
			update_user_meta( $user->ID, "dismissed_wp_pointers", $dismissed );
		}
	}

	/**
	 * Upgrade function for upgrading to v3.4
	 * Calls upgrade_helper_34()
	 *
	 * @since 3.4
	 */
	private function upgrade_database_34() {
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
					$this->upgrade_helper_34();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_34();
	}

	/**
	 * Upgrade Helper for v3.4
	 *
	 * Add new option for make revision overlay message
	 * Remove options and replace it with custom capabilities
	 *
	 * @since 3.4
	 */
	private function upgrade_helper_34() {
		// add the new option for make revision overlay message
      $doc_revision_make_revision_overlay_message = __( 'You may not make changes to this published content. You must first make a revision and then submit your changes for approval.', 'oasisworkflow' );
      update_option( 'oasiswf_revise_post_make_revision_overlay', $doc_revision_make_revision_overlay_message );

      global $wp_roles;

      if ( class_exists('WP_Roles') ) {
      	if ( ! isset( $wp_roles ) ) {
      		$wp_roles = new WP_Roles();
      	}
      }

      // remove options and replace it with custom capabilities
      if ( get_site_option( 'oasiswf_skip_workflow_roles' ) ) {
      	$this->switch_from_option_to_capability( 'oasiswf_skip_workflow_roles', 'ow_skip_workflow', $wp_roles );
      	delete_option( 'oasiswf_skip_workflow_roles' );
      }

      if ( get_site_option( 'oasiswf_view_other_users_inbox_roles' ) ) {
      	$this->switch_from_option_to_capability( 'oasiswf_view_other_users_inbox_roles',
      			'ow_view_others_inbox', $wp_roles);
      	delete_option( 'oasiswf_view_other_users_inbox_roles' );
      }

      if ( get_site_option( 'oasiswf_abort_workflow_roles' ) ) {
      	$this->switch_from_option_to_capability( 'oasiswf_abort_workflow_roles',
      			'ow_abort_workflow', $wp_roles );
      	delete_option( 'oasiswf_abort_workflow_roles' );
      }

      if ( get_site_option( 'oasiswf_revise_post_roles' ) ) {
      	$this->switch_from_option_to_capability( 'oasiswf_revise_post_roles',
      			'ow_make_revision', $wp_roles );
      	delete_option( 'oasiswf_revise_post_roles' );
      }

      //other admin capabilities
      $wp_roles->add_cap( 'administrator', 'ow_create_workflow' );
      $wp_roles->add_cap( 'administrator', 'ow_edit_workflow' );
      $wp_roles->add_cap( 'administrator', 'ow_delete_workflow' );
      $wp_roles->add_cap( 'administrator', 'ow_view_reports' );
      $wp_roles->add_cap( 'administrator', 'ow_view_workflow_history' );
      $wp_roles->add_cap( 'administrator', 'ow_delete_workflow_history' );
      $wp_roles->add_cap( 'administrator', 'ow_download_workflow_history' );
      $wp_roles->add_cap( 'administrator', 'ow_view_others_inbox' );
      $wp_roles->add_cap( 'administrator', 'ow_make_revision' );
      $wp_roles->add_cap( 'administrator', 'ow_make_revision_others' );
      $wp_roles->add_cap( 'administrator', 'ow_abort_workflow' );
      $wp_roles->add_cap( 'administrator', 'ow_reassign_task' );
	}

	/**
	 * Upgrade function for upgrading to v3.5
	 * Calls upgrade_helper_35()
	 *
	 * @since 3.5
	 */
	private function upgrade_database_35() {
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
					$this->upgrade_helper_35();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_35();
	}

	/**
	 * Upgrade Helper for v3.5
	 *
	 * Add new custom capabilities
	 *
	 * @since 3.5
	 */
	private function upgrade_helper_35() {
		global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		$wp_roles->add_cap( 'administrator', 'ow_export_import_workflow' );
	}

	/**
	 * Upgrade function for upgrading to v3.6
	 * Calls upgrade_helper_36()
	 *
	 * @since 3.6
	 */
	private function upgrade_database_36() {
		global $wpdb;

		// look through each of the blogs and upgrade the DB
		if ( function_exists('is_multisite') && is_multisite())
		{
			//Get all blog ids; foreach them and call the uninstall procedure on each of them
			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );

			//Get all blog ids; foreach them and call the install procedure on each of them if the plugin table is found
			foreach ( $blog_ids as $blog_id )
			{
				switch_to_blog( $blog_id );
				if ( $wpdb->query( "SHOW TABLES FROM ".$wpdb->dbname." LIKE '".$wpdb->prefix."fc_%'" ) )
				{
					$this->upgrade_helper_36();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_36();
	}

	/**
	 * Upgrade Helper for v3.6
	 *
	 * Add new custom capabilities
	 *
	 * @since 3.6
	 */
	public function upgrade_helper_36() {
		global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}
		// add ow_submit_to_workflow and ow_sign_off_step to all the existing roles.
		if ( ! function_exists('get_editable_roles') ) {
			require_once( ABSPATH . '/wp-admin/includes/user.php' );
		}
		$editable_roles = get_editable_roles();
		foreach ( $editable_roles as $role => $details ) {
			if ( "subscriber" == esc_attr( $role ) ) { // no need to give this capabilities to subscriber
				continue;
			}
			$wp_roles->add_cap( esc_attr( $role ), 'ow_submit_to_workflow' );
			$wp_roles->add_cap( esc_attr( $role ), 'ow_sign_off_step' );
		}

		/*
		 * Add out of the box custom statuses as part of upgrade
		 */
		if ( ! class_exists( 'OW_Custom_Statuses' ) ) {
			include( OASISWF_PATH . 'includes/class-ow-custom-statuses.php' );
		}
		$ow_custom_statuses = new OW_Custom_Statuses();
		$ow_custom_statuses->register_custom_taxonomy();

		$custom_statuses = array(
          'Pitch' => 'New idea proposed.',
          'With Author' => 'An author has been assigned to the post.',
          'Ready to Publish' => 'The post is ready for publication.'
		);

		foreach ( $custom_statuses as $custom_status => $desc ) {

			if ( term_exists( $custom_status ) ) {
				continue;
			}

			$args = array(
				'slug' => sanitize_title( $custom_status ),
				'description' => $desc
			);
			$response = wp_insert_term( $custom_status, 'post_status', $args );
		}

	}

	/**
	 * Upgrade Database for v3.7
	 *
	 * Add new placeholder for "post author"
	 * Add priority settings, default it enabled
	 *
	 * @since 3.7
	 */
	private function upgrade_database_37() {
		global $wpdb;

		$oasiswf_placeholders = array(
				'%first_name%' => __( 'first name', "oasisworkflow" ),
				'%last_name%' => __( 'last name', "oasisworkflow" ),
				'%post_title%' => __( 'post title', "oasisworkflow" ),
				'%category%' => __( 'category', "oasisworkflow" ),
				'%last_modified_date%' => __( 'last modified date', "oasisworkflow" ),
				'%post_author%' => __( 'post author', 'oasisworkflow' )
		);

		update_site_option( 'oasiswf_placeholders', $oasiswf_placeholders );

		//Priority Setting - initial setting is enabled
		$priority_setting = "enable_priority";

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
					if ( ! get_option( 'oasiswf_priority_setting' ) ) {
						update_option( "oasiswf_priority_setting", $priority_setting );
					}
				}
				restore_current_blog();
			}
		}

		if ( ! get_option( 'oasiswf_priority_setting' ) ) {
			update_option( "oasiswf_priority_setting", $priority_setting );
		}
	}

	/**
	 * Upgrade Database for v3.8
	 *
	 * Replace current "assignee" with "task_assignee"
	 *
	 * @since 3.8
	 */
	private function upgrade_database_38() {
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
					$this->upgrade_helper_38();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_38();

	}

	/**
	 * Upgrade helper for v3.8 upgrade function
	 *
	 * Replace current "assignee" with "task_assignee"
	 *
	 * @since 3.8
	 */
	private function upgrade_helper_38() {
		global $wpdb;
      /**
       * 1. Get all the steps
       * 2. in loop, compare key and replace key "assignee" with "task_assignee"
       * 3. Since we were using only roles, all the current assignees will be roles.
       */
      $steps_table = OW_Utility::instance()->get_workflow_steps_table_name();
      $step_infos = $wpdb->get_results( "SELECT step_info, ID FROM $steps_table" );
      if ( $step_infos ) {
         foreach ( $step_infos as $step_info ) {
            $step_id = $step_info->ID;
            $step_info = json_decode( $step_info->step_info );
            foreach ( $step_info as $key => $info ) {
               if ( $key == 'assignee' ) {
                  $key = 'task_assignee';
                  $step_info->$key = new stdClass();
                  $roles = array();
                  foreach ($info as $role_slug => $role_name) {
                     $roles[] = $role_slug;
                  }
                  $step_info->$key->roles = $roles;
                  unset( $step_info->assignee );
                  $wpdb->query( $wpdb->prepare( "UPDATE $steps_table SET step_info = %s WHERE ID = %d", json_encode( $step_info ), $step_id ) );

               }
            }
         }
      }
	}

   private function upgrade_database_40() {
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
					$this->upgrade_helper_40();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_40();
   }

   private function upgrade_helper_40() {
      global $wpdb;
      /**
       * 1. Get all workflows
       * 2. update the first step's post status as draft
       * 3. migrate the post status from step info to connection info
       */
      $workflows_table = OW_Utility::instance()->get_workflows_table_name();
      $workflow_steps_table = OW_Utility::instance()->get_workflow_steps_table_name();
      $workflows = $wpdb->get_results( "SELECT * FROM $workflows_table" );
      if ( $workflows ) {

         foreach ( $workflows as $workflow ) {

         	// get the workflow_id
         	$workflow_id = $workflow->ID;

            // get wf_info from the workflow
            $wf_info = json_decode( $workflow->wf_info );

            /**
             * Set first step post status - we will set "Draft" as a first step post status
             * @since 4.0
             */
            $first_step = $wf_info->first_step[0];
            if ( $first_step && count( $wf_info->first_step ) == 1 ) {
            	$first_step_updated_info = array(
            		"step" => $first_step,
            		"post_status" => "draft"
            	);
               $wf_info->first_step[0] = $first_step_updated_info;
            }

            /**
             * loop through all the steps in wf_info and set it into stepInfo array for later user
             *    $stepInfo[step0] = array(
             *       'step_name' => 'review',
             *       'step'      => 'step0'
             *    );
             */
            $steps = $wf_info->steps;
            $stepInfo = array();
            foreach ( $steps as $step ) {
               $stepInfo[$step->fc_addid] = (object) array(
                  'step_name' => $step->fc_label,
                  'step' => $step->fc_addid
               );
            }

            /**
             * Migrate the post status from step info to connection info
				 * The "on success" of source step goes to the success connection
				 * The "on failure" of the source step goes to the failure connection
             */
            $connections = $wf_info->conns;
            foreach ( $connections as $connection ) {
               $source = $connection->sourceId;
               $stroke_style = $connection->connset->paintStyle->strokeStyle;

               $step_name = $wpdb->esc_like( '"step_name":"' . $stepInfo[$source]->step_name . '"' );
               // Add wildcards, since we are searching within step_info
               $step_name = '%' . $step_name . '%';

					$sql = "SELECT * FROM $workflow_steps_table WHERE workflow_id = %d AND step_info LIKE %s LIMIT 0, 1";
					$results = $wpdb->get_row( $wpdb->prepare( $sql, $workflow_id, $step_name ) );

					if ( $results ) {
						$step_info = json_decode( $results->step_info );
						$success = $step_info->status;
						$failure = $step_info->failure_status;
						switch( $stroke_style ) {
							case 'blue':
								$post_status = $success;
								break;
							case 'red':
								$post_status = $failure;
								break;
							default :
								$post_status = 'draft';
								break;
						}
						$connection->post_status = $post_status;
					}
				}

            $wf_info = wp_unslash( wp_json_encode( $wf_info ) );

            // Finally update the workflow info for given workflow id
            $wpdb->update( $workflows_table, array(
                'wf_info' => $wf_info
            ), array(
                'ID' => $workflow->ID
            ));

         }

         // now lets unset the status from step info
         $sql = "SELECT ID, step_info FROM $workflow_steps_table";
         $steps = $wpdb->get_results( $sql );
         foreach ( $steps as $step ) {
            $step_info = json_decode( $step->step_info );
            unset( $step_info->status, $step_info->failure_status );
            $step->step_info = wp_json_encode( $step_info );
            $wpdb->update( $workflow_steps_table, array(
                'step_info' => $step->step_info
            ), array(
                'ID' => $step->ID
            ));
         }
      }

   }

   /**
	 * Upgrade helper for v4.1 upgrade function
	 *
	 * Replace postmeta meta_key oasis_original,oasis_is_in_workflow,ow_task_priority,oasis_is_in_team,oasis_current_revision
    * to _oasis_original, _oasis_is_in_workflow, _oasis_task_priority, _oasis_is_in_team,_oasis_current_revision
	 *
	 * @since 4.1
	 */
	private function upgrade_database_41() {
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
					$this->upgrade_helper_41();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_41();

	}

	/**
	 * Upgrade helper for v4.1 upgrade function
	 *
	 * 1) Replace postmeta meta_key oasis_original,oasis_is_in_workflow,ow_task_priority,oasis_is_in_team,oasis_current_revision to _oasis_original, _oasis_is_in_workflow, _oasis_task_priority, _oasis_is_in_team,_oasis_current_revision.
	 * 2) add new setting review_approval to step_info in fc_wokflow_steps table.
	 * @since 4.1
	 */
	private function upgrade_helper_41() {
		global $wpdb;

      // update custom postmeta values
		// adding underscore will hide the custom post meta from the UI
      $meta_keys = array(
        'oasis_original' => '_oasis_original',
        'oasis_is_in_workflow' => '_oasis_is_in_workflow',
        'ow_task_priority' => '_oasis_task_priority',
        'oasis_is_in_team' => '_oasis_is_in_team',
        'oasis_current_revision' => '_oasis_current_revision'
      );

      foreach ( $meta_keys as $old_meta_key => $new_meta_key ) {
         $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s", $new_meta_key, $old_meta_key ) );
      }

		// get current review settings
		$review_setting = get_option( 'oasiswf_review_process_setting' );

      // add review_approval settings on existing review processes
		$workflow_steps_table = OW_Utility::instance()->get_workflow_steps_table_name();
		$sql = "SELECT ID, step_info FROM $workflow_steps_table";
		$steps = $wpdb->get_results( $sql );
		foreach ( $steps as $step ) {
			$step_info = json_decode( $step->step_info );
			if ( $step_info->process === 'review' ) {
				// add review approval and set it's value to
				$step_info->review_approval = $review_setting;
				$step->step_info = wp_json_encode( $step_info );
				$wpdb->update( $workflow_steps_table, array(
					'step_info' => $step->step_info
				), array(
					'ID' => $step->ID
				));
			}
		}

		// now delete the option, since we do not need it anymore
		delete_option('oasiswf_review_process_setting' );
   }
   
    /**
	 * Upgrade helper for v4.2 upgrade function
	 *
	 *
	 * @since 4.2
	 */
	private function upgrade_database_42() {
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
					$this->upgrade_helper_42();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_42();

	}   

   private function upgrade_helper_42() {
		global $wpdb;

		//fc_action_history table  - add history_meta field
		$table_name = OW_Utility::instance()->get_action_history_table_name();
		$wpdb->query( "ALTER TABLE {$table_name} ADD history_meta longtext" );

		//fc_action table  - add history_meta field
		$table_name = OW_Utility::instance()->get_action_table_name();
		$wpdb->query( "ALTER TABLE {$table_name} ADD history_meta longtext" );
	}
   
   /**
	 * Upgrade helper for v4.4 upgrade function
	 *
	 *
	 * @since 4.4
	 */
	private function upgrade_database_44() {
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
					$this->upgrade_helper_44();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_44();

	}   

   private function upgrade_helper_44() {
      global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		// add ow_submit_to_workflow and ow_sign_off_step to all the existing roles.
		if ( ! function_exists('get_editable_roles') ) {
			require_once( ABSPATH . '/wp-admin/includes/user.php' );
		}

		//Get workflow participating roles
		$participating_roles = OW_Utility::instance()->get_participating_roles();

      if ( ! get_option( 'oasiswf_participating_roles_setting' ) ) {
			update_option( "oasiswf_participating_roles_setting", $participating_roles );
		}

		// Publish date Setting
		if( ! get_option( 'oasiswf_publish_date_setting' ) ) {
			update_option( "oasiswf_publish_date_setting", '' );
		}
	}
   
   /**
	 * Upgrade helper for v4.5 upgrade function
	 *
	 *
	 * @since 4.5
	 */
	private function upgrade_database_45() {
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
					$this->upgrade_helper_45();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_45();

	}   

   private function upgrade_helper_45() {
      
      if ( ! get_option( 'oasiswf_login_redirect_roles_setting' ) ) {
			update_option( "oasiswf_login_redirect_roles_setting", '' );
		}
	}
   
   /**
	 * Upgrade helper for v4.6 upgrade function
	 * @since 4.6
	 */
	private function upgrade_database_46() {
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
					$this->upgrade_helper_46();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_46();

	}   

   private function upgrade_helper_46() {
      // Schedules a hook for email digest
      if ( ! wp_next_scheduled('oasiswf_email_digest_schedule') ) {
			wp_schedule_event( time(), 'hourly', 'oasiswf_email_digest_schedule' );
      }
      
      // Migrate email settings to new email settings
      $ow_email_settings_helper = new OW_Email_Settings_Helper();
      
      $email_settings   = get_site_option( 'oasiswf_email_settings' );
      
      $post_publish_settings = array (
          'is_active'         => $email_settings['post_publish_emails'],
          'user_roles'        => array('post_author'),
          'additional_users'  => '',
          'subject'           => $ow_email_settings_helper->get_post_publish_subject(),
          'content'           => $ow_email_settings_helper->get_post_publish_content()
      );
      
      $revised_post_publish_settings = array (
          'is_active'         => $email_settings['post_publish_emails'],
          'user_roles'        => array('post_author'),
          'additional_users'  => '',
          'subject'           => $ow_email_settings_helper->get_revised_post_publish_subject(),
          'content'           => $ow_email_settings_helper->get_revised_post_publish_content()
      );
      
      $unauthorized_update_settings = array (
          'is_active'         => $email_settings['unauthorized_post_update_emails'],
          'user_roles'        => array('current_task_assignees'),
          'additional_users'  => '',
          'subject'           => $ow_email_settings_helper->get_unauthorized_update_subject(),
          'content'           => $ow_email_settings_helper->get_unauthorized_update_content()
      );
      
      $tasked_claimed_settings = array (
          'is_active'         => $email_settings['assignment_emails'],
          'user_roles'        => array('current_task_assignees'),
          'additional_users'  => '',
          'subject'           => $ow_email_settings_helper->get_task_claimed_subject(),
          'content'           => $ow_email_settings_helper->get_task_claimed_content()
      );
      
      $post_submit_settings = array (
          'is_active'   => $email_settings['submit_to_workflow_email'],
          'user_roles'  => array('post_author'),
          'additional_users'       => '',
          'subject'     => $ow_email_settings_helper->get_post_submit_subject(),
          'content'     => $ow_email_settings_helper->get_post_submit_content()
      );
      
      $abort_workflow_settings = array (
          'is_active'         => $email_settings['abort_email_to_author'],
          'user_roles'        => array('post_author'),
          'additional_users'  => '',
          'subject'           => $ow_email_settings_helper->get_workflow_abort_subject(),
          'content'           => $ow_email_settings_helper->get_workflow_abort_content()
      );
      
      if ( ! get_option( 'oasiswf_post_publish_email_settings' ) ) {
			update_option( "oasiswf_post_publish_email_settings", $post_publish_settings );
		}
      if ( ! get_option( 'oasiswf_revised_post_email_settings' ) ) {
			update_option( "oasiswf_revised_post_email_settings", $revised_post_publish_settings );
		}
      if ( ! get_option( 'oasiswf_unauthorized_update_email_settings' ) ) {
			update_option( "oasiswf_unauthorized_update_email_settings", $unauthorized_update_settings );
		}
      if ( ! get_option( 'oasiswf_task_claim_email_settings' ) ) {
			update_option( "oasiswf_task_claim_email_settings", $tasked_claimed_settings );
		}
       if ( ! get_option( 'oasiswf_post_submit_email_settings' ) ) {
			update_option( "oasiswf_post_submit_email_settings", $post_submit_settings );
		}
      if ( ! get_option( 'oasiswf_workflow_abort_email_settings' ) ) {
			update_option( "oasiswf_workflow_abort_email_settings", $abort_workflow_settings );
		}
      
      // Add new option digest email in email settings
		$new_email_settings = array(
				'from_name' => $email_settings['from_name'],
				'from_email_address' => $email_settings['from_email_address'],
				'assignment_emails' => $email_settings['assignment_emails'],
				'reminder_emails' => $email_settings['reminder_emails'],
            'digest_emails' => ''
		);
		update_option( "oasiswf_email_settings", $new_email_settings );
      
      // Add new email placeholders
      $oasiswf_email_placeholders = array(
					'{first_name}' => __( 'First Name', "oasisworkflow" ),
					'{last_name}' => __( 'Last Name', "oasisworkflow" ),
					'{post_title}' => __( 'Post Title, this will be displayed as a link', "oasisworkflow" ),
					'{category}' => __( 'Category', "oasisworkflow" ),
					'{last_modified_date}' => __( 'Last Modified Date', "oasisworkflow" ),
               '{post_author}' => __( 'Post Author', 'oasisworkflow' ),
               '{blog_name}' => __( 'Blog Name', 'oasisworkflow' ),
					'{current_user}' => __( 'Current User', 'oasisworkflow' )
			);
      if ( ! get_site_option( 'oasiswf_email_placeholders' ) ) {
			update_site_option( "oasiswf_email_placeholders", $oasiswf_email_placeholders );
		}
      
      // Auto delete history 
      $auto_delete_history = array(
				'enable' => '',
				'period' => 'one-month-ago',				
		);
      if ( ! get_option( 'oasiswf_auto_delete_history_setting' ) ) {
			update_option( "oasiswf_auto_delete_history_setting", $auto_delete_history );
		}
      
      // Schedules a hook for auto delete history
      if ( ! wp_next_scheduled('oasiswf_auto_delete_history_schedule') ) {
			wp_schedule_event( time(), 'daily', 'oasiswf_auto_delete_history_schedule' );
      }
	}
   
  /**
	 * Upgrade helper for v4.9 upgrade function
	 * @since 4.9
	 */
	private function upgrade_database_49() {
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
					$this->upgrade_helper_49();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_49();

	}   
   
   private function upgrade_helper_49() {
      global $wpdb;
      // upgrade script for auto submit settings 
      $applicable_workflow    = array();
      $auto_submit_settings   = get_option( 'oasiswf_auto_submit_settings' );
      
      // Set the cron interval
      if ( wp_next_scheduled('oasiswf_auto_submit_schedule') ) {
         $schedule = wp_get_schedule( 'oasiswf_auto_submit_schedule' );
      } else {
         $schedule = "hourly";
      }
      $auto_submit_settings['auto_submit_interval'] = $schedule;
      
      // Set the applicable workflows
      $workflows = $wpdb->get_results( "SELECT * FROM " .
				OW_Utility::instance()->get_workflows_table_name() . " WHERE (end_date = '0000-00-00' OR end_date >= CURDATE())
				AND is_auto_submit = 1
				AND is_valid = 1
				ORDER BY ID" );
      
      foreach ( $workflows as $wf ) {
         $wf_id = $wf->ID;
         $auto_submit_info = unserialize( $wf->auto_submit_info );        
         $keyword_array = $auto_submit_info['keywords'];
         $auto_submit_keywords = implode( ',', $keyword_array );
         
         if ( ! empty( $auto_submit_keywords ) ) {
            $applicable_workflow[ $wf_id ] = $auto_submit_keywords;
         }
      }
      
      $auto_submit_settings['auto_submit_workflows'] = $applicable_workflow;
      update_option( "oasiswf_auto_submit_settings", $auto_submit_settings );
      
      // Add new placeholder %blog_name% and %publish_date%
      $oasiswf_placeholders = array(
         '%first_name%'         => __( 'first name', "oasisworkflow" ),
			'%last_name%'          => __( 'last name', "oasisworkflow" ),
			'%post_title%'         => __( 'post title', "oasisworkflow" ),
			'%category%'           => __( 'category', "oasisworkflow" ),
			'%last_modified_date%' => __( 'last modified date', "oasisworkflow" ),
			'%post_author%'        => __( 'post author', 'oasisworkflow' ),
         '%blog_name%'          => __( 'blog name', 'oasisworkflow' )
		);

		update_site_option( 'oasiswf_placeholders', $oasiswf_placeholders );
      
      // Add new email placeholders {publish_date}
      $oasiswf_email_placeholders = array(
			'{first_name}'          => __( 'First Name', "oasisworkflow" ),
			'{last_name}'           => __( 'Last Name', "oasisworkflow" ),
			'{post_title}'          => __( 'Post Title, this will be displayed as a link', "oasisworkflow" ),
			'{category}'            => __( 'Category', "oasisworkflow" ),
			'{last_modified_date}'  => __( 'Last Modified Date', "oasisworkflow" ),
         '{publish_date}'        => __( 'Publish Date', 'oasisworkflow' ),
         '{post_author}'         => __( 'Post Author', 'oasisworkflow' ),
         '{blog_name}'           => __( 'Blog Name', 'oasisworkflow' ),
			'{current_user}'        => __( 'Current User', 'oasisworkflow' )
			);
		update_site_option( "oasiswf_email_placeholders", $oasiswf_email_placeholders );
   }
   
   
    /**
	 * Upgrade helper for v5.0 upgrade function
	 * @since 5.0
	 */
	private function upgrade_database_50() {
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
					$this->upgrade_helper_50();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_50();

	}   
   
   private function upgrade_helper_50() {      
      // Add new placeholder %post_submitter%
      $oasiswf_placeholders = array(
         '%first_name%'         => __( 'first name', "oasisworkflow" ),
			'%last_name%'          => __( 'last name', "oasisworkflow" ),
			'%post_title%'         => __( 'post title', "oasisworkflow" ),
			'%category%'           => __( 'category', "oasisworkflow" ),
			'%last_modified_date%' => __( 'last modified date', "oasisworkflow" ),
			'%post_author%'        => __( 'post author', 'oasisworkflow' ),
         '%blog_name%'          => __( 'blog name', 'oasisworkflow' ),
         '%post_submitter%'     => __( 'post submitter', 'oasisworkflow' )
		);

		update_site_option( 'oasiswf_placeholders', $oasiswf_placeholders );      
   }
   
   /**
	 * Upgrade helper for v5.3 upgrade function
	 * @since 5.3
	 */
	private function upgrade_database_53() {
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
					$this->upgrade_helper_53();
				}
				restore_current_blog();
			}
		}

		$this->upgrade_helper_53();
	} 
   
   private function upgrade_helper_53() {      
      global $wp_roles;

      if ( class_exists('WP_Roles') ) {
         if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
         }
      }

      // Add admin capabilities
      $wp_roles->add_cap( 'administrator', 'ow_duplicate_post' );
   }
   
   /**
	 * Switch option name to a capability. Applicable for role-type options
	 *
	 * @param string $option_name
	 * @param string $capability
	 * @param WP_Roles wp_roles instance
	 *
	 * @since 3.4
	 */
	private function switch_from_option_to_capability( $option_name, $capability, $wp_roles) {
		$option_value = get_option( $option_name );
		if ( is_object( $wp_roles ) && is_array( $option_value ) ) {
			foreach ( $option_value as $role ) {
				if ( $role != "owfpostauthor" ) { //since this is not a real role
					$wp_roles->add_cap( $role, $capability );
				}
			}
		}
	}

	/**
	 * Create workflow tables and create the default workflow
	 *
	 * @since 2.0
	 */
	private function install_admin_database() {
		global $wpdb;
		if ( ! empty( $wpdb->charset ) )
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		if ( ! empty( $wpdb->collate ) )
			$charset_collate .= " COLLATE {$wpdb->collate}";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		//fc_workflows table
		$table_name = OW_Utility::instance()->get_workflows_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			ID int(11) NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			description mediumtext,
			wf_info longtext,
			version int(3) NOT NULL default 1,
			parent_id int(11) NOT NULL default 0,
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			is_auto_submit int(2) NOT NULL default 0,
			auto_submit_info mediumtext,
			is_valid int(2) NOT NULL default 0,
			create_datetime datetime DEFAULT NULL,
			update_datetime datetime DEFAULT NULL,
			wf_additional_info mediumtext DEFAULT NULL,
			PRIMARY KEY (ID)
			){$charset_collate};";
			dbDelta( $sql );
		}

		//fc_workflow_steps table
		$table_name = OW_Utility::instance()->get_workflow_steps_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			ID int(11) NOT NULL AUTO_INCREMENT,
			step_info text NOT NULL,
			process_info longtext NOT NULL,
			workflow_id int(11) NOT NULL,
			create_datetime datetime DEFAULT NULL,
			update_datetime datetime DEFAULT NULL,
			PRIMARY KEY (ID),
			KEY workflow_id (workflow_id)
			){$charset_collate};";
			dbDelta( $sql );
		}

		$this->install_admin_data();
	}

	/**
	 * Create workflow action tables
	 *
	 * @since 2.0
	 */
	private function install_site_database() {
		global $wpdb;
		if ( ! empty( $wpdb->charset ) )
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		if ( ! empty( $wpdb->collate ) )
			$charset_collate .= " COLLATE {$wpdb->collate}";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		//fc_emails table
		$table_name = OW_Utility::instance()->get_emails_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			// action - 1 indicates not send, 0 indicates email sent
			$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			ID int(11) NOT NULL AUTO_INCREMENT,
			subject mediumtext,
			message mediumtext,
			from_user int(11),
			to_user int(11),
			action int(2) DEFAULT 1,
			history_id int(11),
			send_date date DEFAULT NULL,
			create_datetime datetime DEFAULT NULL,
			PRIMARY KEY (ID)
			){$charset_collate};";
			dbDelta( $sql );
		}

		//fc_action_history table
		$table_name = OW_Utility::instance()->get_action_history_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			ID int(11) NOT NULL AUTO_INCREMENT,
			action_status varchar(20) NOT NULL,
			comment longtext NOT NULL,
			step_id int(11) NOT NULL,
			assign_actor_id int(11) NOT NULL,
			post_id int(11) NOT NULL,
			from_id int(11) NOT NULL,
			due_date date DEFAULT NULL,
			history_meta longtext DEFAULT NULL,
			reminder_date date DEFAULT NULL,
			reminder_date_after date DEFAULT NULL,
			create_datetime datetime NOT NULL,
			PRIMARY KEY (ID)
			){$charset_collate};";
			dbDelta( $sql );
		}

		//fc_action table
		$table_name = OW_Utility::instance()->get_action_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			ID int(11) NOT NULL AUTO_INCREMENT,
			review_status varchar(20) NOT NULL,
			actor_id int(11) NOT NULL,
			next_assign_actors text NOT NULL,
			step_id int(11) NOT NULL,
			comments mediumtext,
			due_date date DEFAULT NULL,
			action_history_id int(11) NOT NULL,
			history_meta longtext DEFAULT NULL,
			update_datetime datetime NOT NULL,
			PRIMARY KEY (ID)
			){$charset_collate};";
			dbDelta( $sql );
		}
	}

	/**
	 * Add default data - Create a default workflow
	 */
	private function install_admin_data() {
		global $wpdb;

		// insert into workflow table
		$table_name = OW_Utility::instance()->get_workflows_table_name();
		$row = $wpdb->get_row("SELECT max(ID) as maxid FROM $table_name");
		if ( is_numeric( $row->maxid ) ) { //data already exists, do not insert another row.
			return;
		}
		$workflow_id = '';
		$workflow_info = stripcslashes( '{"steps":{"step0":{"fc_addid":"step0","fc_label":"assignment","fc_dbid":"2","fc_process":"assignment","fc_position":["326px","568px"]},"step1":{"fc_addid":"step1","fc_label":"review","fc_dbid":"1","fc_process":"review","fc_position":["250px","358px"]},"step2":{"fc_addid":"step2","fc_label":"publish","fc_dbid":"3","fc_process":"publish","fc_position":["119px","622px"]}},"conns":{"0":{"sourceId":"step2","targetId":"step0","post_status":"draft","connset":{"connector":"StateMachine","paintStyle":{"lineWidth":3,"strokeStyle":"red"}}},"1":{"sourceId":"step1","targetId":"step0","post_status":"draft","connset":{"connector":"StateMachine","paintStyle":{"lineWidth":3,"strokeStyle":"red"}}},"2":{"sourceId":"step0","targetId":"step1","post_status":"pending","connset":{"connector":"StateMachine","paintStyle":{"lineWidth":3,"strokeStyle":"blue"}}},"3":{"sourceId":"step2","targetId":"step1","post_status":"pending","connset":{"connector":"StateMachine","paintStyle":{"lineWidth":3,"strokeStyle":"red"}}},"4":{"sourceId":"step1","targetId":"step2","post_status":"ready-to-publish","connset":{"connector":"StateMachine","paintStyle":{"lineWidth":3,"strokeStyle":"blue"}}}},"first_step":[{"step":"step1","post_status":"draft"}]}');
		$additional_info = stripcslashes( 'a:4:{s:16:"wf_for_new_posts";i:1;s:20:"wf_for_revised_posts";i:1;s:12:"wf_for_roles";a:0:{}s:17:"wf_for_post_types";a:0:{}}' );
		$data = array(
				'name' => 'Sample Workflow',
				'description' => 'sample workflow',
				'wf_info' => $workflow_info,
				'start_date' => date( "Y-m-d", current_time( 'timestamp' ) ),
				'end_date' => date( "Y-m-d", current_time( 'timestamp' ) + YEAR_IN_SECONDS ),
				'is_valid' => 1,
				'create_datetime' => current_time( 'mysql' ),
				'update_datetime' => current_time( 'mysql' ),
				'wf_additional_info' => $additional_info
		);
		$result = $wpdb->insert( $table_name, $data );
		if ( $result ) {
			$row = $wpdb->get_row( "SELECT max(ID) as maxid FROM $table_name" );
			if ( $row )
				$workflow_id = $row->maxid;
			else
				return false;
		}else {
			return false;
		}

		// insert steps
		$workflow_step_table = OW_Utility::instance()->get_workflow_steps_table_name();

		// step 1 - review
		$review_step_info = '{"process":"review","step_name":"review","assign_to_all":0,"task_assignee":{"roles":["editor"],"users":[],"groups":[]},"review_approval":"everyone"}';
		$review_process_info = '{"assign_subject":"","assign_content":"","reminder_subject":"","reminder_content":""}';
		$result = $wpdb->insert(
				$workflow_step_table, array(
						'step_info' => stripcslashes( $review_step_info ),
						'process_info' => stripcslashes( $review_process_info ),
						'create_datetime' => current_time( 'mysql' ),
						'update_datetime' => current_time( 'mysql' ),
						'workflow_id' => $workflow_id
				)
		);

		// step 2 - assignment
		$assignment_step_info = '{"process":"assignment","step_name":"assignment","assign_to_all":0,"task_assignee":{"roles":["author"],"users":[],"groups":[]}}';
		$assignment_process_info = '{"assign_subject":"","assign_content":"","reminder_subject":"","reminder_content":""}';
		$result = $wpdb->insert(
				$workflow_step_table, array(
						'step_info' => stripcslashes( $assignment_step_info ),
						'process_info' => stripcslashes( $assignment_process_info ),
						'create_datetime' => current_time( 'mysql' ),
						'update_datetime' => current_time( 'mysql' ),
						'workflow_id' => $workflow_id
				)
		);

		// step 3 - publish
		$publish_step_info = '{"process":"publish","step_name":"publish","assign_to_all":0,"task_assignee":{"roles":["administrator"],"users":[],"groups":[]}}';
		$publish_process_info = '{"assign_subject":"","assign_content":"","reminder_subject":"","reminder_content":""}';
		$result = $wpdb->insert(
				$workflow_step_table, array(
						'step_info' => stripcslashes( $publish_step_info ),
						'process_info' => stripcslashes( $publish_process_info ),
						'create_datetime' => current_time( 'mysql' ),
						'update_datetime' => current_time( 'mysql' ),
						'workflow_id' => $workflow_id
				)
		);
	}

	/**
	 * Called on uninstall OR blog delete to clear the scheduled hooks
	 *
	 * @since 2.0
	 */
	private static function clear_scheduled_hooks() {
		global $wpdb;
		/*
		 * Mail schedule remove
		 */
		wp_clear_scheduled_hook( 'oasiswf_email_schedule' );

		/*
		 * Auto Submit schedule remove
		*/
		wp_clear_scheduled_hook( 'oasiswf_auto_submit_schedule' );
      
      /*
		 * Email digest schedule remove
		*/
		 wp_clear_scheduled_hook( 'oasiswf_email_digest_schedule' );
       
      /*
		 * Auto delete history schedule remove
		 */
		 wp_clear_scheduled_hook( 'oasiswf_auto_delete_history_schedule' );

		/*
		 * Scheduled update of revised articles
		*/
		/*
		 * If you created a scheduled job using a hook and arguments
		 * you cannot delete it by supplying only the hook.
		 * Similarly if you created a set of scheduled jobs that share a
		 * hook but have different arguments you cannot delete them using
		 * only the hook name,
		 * you have to delete them all individually using the hook name and arguments.
		*/
		wp_clear_scheduled_hook( 'oasiswf_schedule_revision_update' );
		OW_Plugin_Init::clear_scheduled_revision_hooks();

	}

	/**
	 * to clear/delete all the scheduled revision update hooks.
	 *
	 * @since 2.0
	 */
	private static function clear_scheduled_revision_hooks() {
		$scheduled_posts = get_posts( array( 'post_type' => 'owf_scheduledrev' ) );

		foreach ( $scheduled_posts as $post ) {
			$args = array( $post->ID, "owf_scheduledrev" );
			$revision_update_schedule = wp_get_schedule( 'oasiswf_schedule_revision_update', $args );
			if ( $revision_update_schedule && ! empty( $revision_update_schedule ) ) {
				wp_clear_scheduled_hook( 'oasiswf_schedule_revision_update', $args );
			}
		}
	}

	/**
	 * enqueue CSS files
	 *
	 * @since 2.0
	 */
	public function add_css_files( $page ) {
		// ONLY load OWF scripts on OWF plugin pages
		if ( is_admin() && preg_match_all( '/page=ow-settings(.*)|page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
			wp_enqueue_style( 'owf-css', OASISWF_URL . 'css/pages/context-menu.css', false, OASISWF_VERSION, 'all' );
			wp_enqueue_style( 'owf-modal-css', OASISWF_URL . 'css/lib/modal/simple-modal.css', false, OASISWF_VERSION, 'all' );
			wp_enqueue_style( 'owf-calendar-css', OASISWF_URL . 'css/lib/calendar/datepicker.css', false, OASISWF_VERSION, 'all' );
			wp_enqueue_style( 'owf-oasis-workflow-css', OASISWF_URL . 'css/pages/oasis-workflow.css', false, OASISWF_VERSION, 'all' );

			/**
			 * enqueue status dropdown js
			 * @since 4.0
			 */
			wp_register_script( 'owf-post-statuses', OASISWF_URL . 'js/pages/ow-status-dropdown.js', array( 'jquery' ), OASISWF_VERSION );
			wp_enqueue_script( 'owf-post-statuses' );
		}
	}

   /**
	 * enqueue CSS files for dashboard
	 *
	 * @since 2.0
	 */
   public function add_css(){
      wp_enqueue_style( 'owf-css', OASISWF_URL . 'css/pages/workflow-dashboard-widget.css', false, OASISWF_VERSION, 'all' );
   }

	/**
	 * enqueue javascripts
	 *
	 * @since 2.0
	 */
	public function add_js_files() {
		$ow_process_flow = new OW_Process_Flow();

		// ONLY load OWF scripts on OWF plugin pages
		if ( is_admin() && preg_match_all( '/page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
			echo "<script type='text/javascript'>
   					var wf_structure_data = '' ;
   					var wfeditable = '' ;
   					var wfPluginUrl  = '" . OASISWF_URL . "' ;
                  var flag_compare_button = true;
   				</script>";
		}

		if ( is_admin() && isset( $_GET['page'] ) && ($_GET["page"] == "oasiswf-inbox" ||
				$_GET["page"] == "oasiswf-history" ) ) {
					OW_Plugin_Init::enqueue_and_localize_inbox_script();
				}
	}

	public static function enqueue_and_localize_inbox_script() {

		$ow_process_flow = new OW_Process_Flow();
		wp_enqueue_script( 'owf-workflow-inbox', OASISWF_URL . 'js/pages/workflow-inbox.js', array( 'jquery' ), OASISWF_VERSION );
		wp_enqueue_script( 'owf-workflow-history', OASISWF_URL . 'js/pages/workflow-history.js', array( 'jquery' ), OASISWF_VERSION );

		wp_localize_script( 'owf-workflow-inbox', 'owf_workflow_inbox_vars', array(
				'workflowTeamsAvailable' => $ow_process_flow->is_teams_available(),
				'dateFormat' => OW_Utility::instance()->owf_date_format_to_jquery_ui_format( get_option( 'date_format' ) ),
				'editDateFormat' => OW_Utility::instance()->owf_date_format_to_jquery_ui_format( OASISWF_EDIT_DATE_FORMAT ),
				'abortWorkflowConfirm' => __( 'Are you sure to abort the workflow?', 'oasisworkflow' )
		) );
	}

	/**
	 * load/enqueue javascripts as part of the footer
	 */
	public function load_js_files_footer() {
		// ONLY load OWF scripts on OWF plugin pages
		if ( is_admin() && preg_match_all( '/page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
			//wp_enqueue_script( 'thickbox' );
			wp_enqueue_script( 'jquery-ui-core' );
			wp_enqueue_script( 'jquery-ui-widget' );
			wp_enqueue_script( 'jquery-ui-mouse' );
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'jquery-json', OASISWF_URL . 'js/lib/jquery.json.js', '', '2.3', true );
			wp_enqueue_script( 'jquery-ui-draggable' );
			wp_enqueue_script( 'jquery-ui-droppable' );
		}

		if ( is_admin() && ( isset( $_GET['page'] ) && ($_GET["page"] == "oasiswf-admin" || $_GET["page"] == "oasiswf-add")) ) {
			wp_enqueue_style( 'select2-style', OASISWF_URL . 'css/lib/select2/select2.css', false, OASISWF_VERSION, 'all' );
			wp_enqueue_script( 'jsPlumb', OASISWF_URL . 'js/lib/jquery.jsPlumb-all-min.js', array( 'jquery-ui-core', 'jquery-ui-draggable', 'jquery-ui-droppable' ), '1.4.1', true );
			wp_enqueue_script( 'drag-drop-jsplumb', OASISWF_URL . 'js/pages/drag-drop-jsplumb.js', array( 'jsPlumb' ), OASISWF_VERSION, true );
			wp_enqueue_script( 'select2-js', OASISWF_URL . 'js/lib/select2/select2.min.js', array( 'jquery' ), OASISWF_VERSION, true );
			wp_localize_script( 'drag-drop-jsplumb', 'drag_drop_jsplumb_vars', array(
					'clearAllSteps' => __( 'Do you really want to clear all the steps?', 'oasisworkflow' ),
					'removeStep' => __( 'This step is already defined. Do you really want to remove this step?', 'oasisworkflow' ),
               'postStatusRequired' => __( 'Please select Post Status.', 'oasisworkflow' ),
					'pathBetween' => __( 'The path between', 'oasisworkflow' ),
					'stepAnd' => __( 'step and', 'oasisworkflow' ),
					'incorrect' => __( 'step is incorrect.', 'oasisworkflow' ),
               'stepHelp' => __( 'To edit/delete the step, right click on the step to access the step menu.', 'oasisworkflow' ),
         		'connectionHelp' => __( 'To connect to another step drag a line from the "dot" to the next step.', 'oasisworkflow' ),
					'postStatusLabel' => __( 'Post Status', 'oasisworkflow' )
			) );
		}

		if ( is_admin() && preg_match_all( '/page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
			wp_enqueue_script( 'owf-workflow-create', OASISWF_URL . 'js/pages/workflow-create.js', '', OASISWF_VERSION, true );
         wp_enqueue_script( 'owf-workflow-delete', OASISWF_URL . 'js/pages/workflow-delete.js', '', OASISWF_VERSION, true );

			wp_localize_script( 'owf-workflow-create', 'owf_workflow_create_vars', array(
					'alreadyExistWorkflow' => __( 'There is an existing workflow with the same name. Please choose another name.', 'oasisworkflow' ),
					'unsavedChanges' => __( 'You have unsaved changes.', 'oasisworkflow' ),
					'dateFormat' => OW_Utility::instance()->owf_date_format_to_jquery_ui_format( get_option( 'date_format' ) ),
					'editDateFormat' => OW_Utility::instance()->owf_date_format_to_jquery_ui_format( OASISWF_EDIT_DATE_FORMAT )
			) );
         wp_localize_script( 'owf-workflow-delete', 'owf_workflow_delete_vars', array(
					'workflow_delete_nonce' => wp_create_nonce( 'workflow_delete_nonce' )
			) );

			wp_enqueue_script( 'jquery-simplemodal', OASISWF_URL . 'js/lib/modal/jquery.simplemodal.js', '', '1.4.6', true );
			wp_enqueue_script( 'owf-workflow-util', OASISWF_URL . 'js/pages/workflow-util.js', '', OASISWF_VERSION, true );
			wp_localize_script( 'owf-workflow-util', 'owf_workflow_util_vars', array(
					'dueDateInPast' => __( 'Due date cannot be in the past.', 'oasisworkflow' )
			) );

			wp_enqueue_script( 'text-edit-whizzywig', OASISWF_URL . 'js/lib/textedit/whizzywig63.js', '', '63', true );
			wp_enqueue_script( 'owf-workflow-step-info', OASISWF_URL . 'js/pages/subpages/step-info.js', array( 'text-edit-whizzywig' ), OASISWF_VERSION, true );
			wp_enqueue_script( 'owf-workflow-step-info', OASISWF_URL . 'js/pages/subpages/step-info.js', '', OASISWF_VERSION, true );
			wp_localize_script( 'owf-workflow-step-info', 'owf_workflow_step_info_vars', array(
					'stepNameRequired' => __( 'Step name is required.', 'oasisworkflow' ),
					'stepNameAlreadyExists' => __( 'Step name already exists. Please use a different name.', 'oasisworkflow' ),
					'selectAssignees' => __( 'Please select assignee(s).', 'oasisworkflow' ),
					'selectPlaceholder' => __( 'Please select a placeholder.', 'oasisworkflow' ),
               'numericDueDate' => __( 'Please enter a numeric value for default due date.', 'oasisworkflow' ),
               'stepDueDate' => __( 'Please enter the number of days for default due date..', 'oasisworkflow' ),
			) );
		}

		if ( is_admin() && preg_match_all( '/edit\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
			include( OASISWF_PATH . "includes/pages/subpages/make-revision.php" );
			wp_enqueue_script( 'owf-workflow-util', OASISWF_URL . 'js/pages/workflow-util.js', '', OASISWF_VERSION, true );
			wp_enqueue_script( 'owf_make_revision', OASISWF_URL . 'js/pages/subpages/make-revision.js', array( 'jquery' ), OASISWF_VERSION, true );
         wp_enqueue_script( 'owf_duplicate_post', OASISWF_URL . 'js/pages/subpages/ow-duplicate-post.js', array( 'jquery' ), OASISWF_VERSION, true );
			wp_enqueue_style( 'owf-oasis-workflow-css', OASISWF_URL . 'css/pages/oasis-workflow.css', false, OASISWF_VERSION, 'all' );
			OW_Plugin_Init::enqueue_and_localize_simple_modal_script();
			$ow_process_flow = new OW_Process_Flow();
			$ow_process_flow->enqueue_and_localize_make_revision_script();

			/**
			 * enqueue status dropdown js
			 * @since 2.1
			 */
			wp_register_script( 'owf-post-statuses', OASISWF_URL . 'js/pages/ow-status-dropdown.js', array( 'jquery' ), OASISWF_VERSION );
			wp_enqueue_script( 'owf-post-statuses' );
		}

		if ( is_admin() && preg_match_all( '/page=ow-settings&tab=email_settings(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
			wp_enqueue_script( 'owf-email-settings', OASISWF_URL . 'js/pages/email-settings.js', array( 'jquery' ), OASISWF_VERSION	);
		}
      if ( is_admin() && preg_match_all( '/page=ow-settings&tab=auto_submit_settings(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
			wp_enqueue_script( 'owf-auto-submit-settings', OASISWF_URL . 'js/pages/subpages/auto-submit.js', array( 'jquery' ), OASISWF_VERSION );
		}
      
      if ( is_admin() && isset( $_GET['tab'] ) && $_GET["tab"] == "email_settings" ) {
         wp_enqueue_style( 'select2-style', OASISWF_URL . 'css/lib/select2/select2.css', false, OASISWF_VERSION, 'all' );
         wp_enqueue_script( 'select2-js', OASISWF_URL . 'js/lib/select2/select2.min.js', array( 'jquery' ), OASISWF_VERSION, true );
      }

	}

	/**
	 * Enqueue and Localize the simple modal script
	 *
	 * @since 3.3 initial version
	 */
	public static function enqueue_and_localize_simple_modal_script() {
		wp_enqueue_script( 'jquery-simplemodal', OASISWF_URL . 'js/lib/modal/jquery.simplemodal.js', '', '1.4.6', true );
		wp_enqueue_style( 'owf-modal-css', OASISWF_URL . 'css/lib/modal/simple-modal.css', false, OASISWF_VERSION, 'all' );
	}

	/**
	 * Plugin Update notifier
	 */
	public function ow_plugin_updater() {
		if ( ! class_exists( 'OW_Plugin_Updater' ) ) {
			include( OASISWF_PATH . "includes/class-ow-plugin-updater.php" );

			// setup the plugin updater
			$edd_updater = new OW_Plugin_Updater( OASISWF_STORE_URL, __FILE__, array(
					'version' => OASISWF_VERSION, // current version number
					'license' =>  trim( get_option( 'oasiswf_license_key' ) ), // license key (used get_option above to retrieve from DB)
					'item_name' => OASISWF_PRODUCT_NAME, // name of this plugin
					'author' => 'Nugget Solutions Inc.' // author of this plugin
				)
			);
		}
	}
}

// initialize the plugin
$ow_plugin_init = new OW_Plugin_Init();
add_action( 'admin_init', array( $ow_plugin_init, 'ow_plugin_updater' ) );
?>