<?php
/*
 * Settings class for Workflow settings
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

/*
 * OW_Workflow_Settings Class
 *
 * @since 2.0
 */

class OW_Workflow_Settings {

   /**
    * @var string group name
    */
   protected $ow_workflow_group_name = 'ow-settings-workflow';

   /**
    * @var string activate workflow option name
    */
   protected $ow_activate_workflow = 'oasiswf_activate_workflow';

   /**
    * @var string default due days option name
    */
   protected $ow_default_due_days_option_name = 'oasiswf_default_due_days';
   
   /**
    * @var string step due days option name
    */
   protected $ow_step_due_date_setting_option_name = 'oasiswf_step_due_date_settings';

   /**
    * @var string show workflow on post types option name
    */
   protected $ow_show_wfsettings_on_post_types_option_name = 'oasiswf_show_wfsettings_on_post_types';

   /**
    * @var string priority setting option name
    */
   protected $ow_priority_setting_option_name = 'oasiswf_priority_setting';
   
   /**
    * @var string publish date setting option name
    */
   protected $ow_publish_date_setting_option_name = 'oasiswf_publish_date_setting';
   
   /**
    * @var string roles that can participate in the workflow setting option name
    */
   protected $ow_participating_roles_setting_option_name = 'oasiswf_participating_roles_setting';
   
   /**
    * @var string roles that can make workflow inbox as the landing page setting option name
    */
   protected $ow_login_redirect_setting_option_name = 'oasiswf_login_redirect_roles_setting';
   /**
    * @var string auto delete history setting option name
    */
   protected $ow_auto_delete_history_setting_option_name = 'oasiswf_auto_delete_history_setting';

   /**
    * Set things up.
    *
    * @since 2.0
    */
   public function __construct() {
      add_action( 'admin_init', array( $this, 'init_settings' ) );
   }

   // White list our options using the Settings API
   public function init_settings() {
      register_setting( $this->ow_workflow_group_name,
      		$this->ow_activate_workflow, array( $this, 'validate_activate_workflow_process' ) );
      register_setting( $this->ow_workflow_group_name,
      		$this->ow_default_due_days_option_name, array( $this, 'validate_default_due_days' ) );
      register_setting( $this->ow_workflow_group_name,
      		$this->ow_step_due_date_setting_option_name, array( $this, 'validate_step_due_date_setting' ) );
      register_setting( $this->ow_workflow_group_name,
      		$this->ow_show_wfsettings_on_post_types_option_name, array( $this, 'validate_specified_action_by_roles' ) );
      register_setting( $this->ow_workflow_group_name,
      		$this->ow_priority_setting_option_name, array( $this, 'validate_priority_setting' ) );
      register_setting( $this->ow_workflow_group_name,
              $this->ow_publish_date_setting_option_name,array( $this, 'validate_publish_date_setting' ) );
      register_setting( $this->ow_workflow_group_name,
      		$this->ow_participating_roles_setting_option_name, array( $this, 'validate_selected_participant_roles' ) );
      register_setting( $this->ow_workflow_group_name,
      		$this->ow_login_redirect_setting_option_name, array( $this, 'validate_selected_roles' ) );
      register_setting( $this->ow_workflow_group_name,
      		$this->ow_auto_delete_history_setting_option_name, array( $this, 'validate_auto_delete_history_setting' ) );
   }

   /**
    * sanitize user data
    * @param string $is_activated
    * @return string
    */
   public function validate_activate_workflow_process( $is_activated ) {
      return sanitize_text_field( $is_activated );
   }

   /**
    *
    * @param string or int $default_due_days
    * @return int
    */
   public function validate_default_due_days( $default_due_days ) {
      // If due days is not empty then do validate and sanitize user input
      $due_days = '';
      if ( ! empty( $default_due_days ) ) {
         if ( is_numeric( $default_due_days ) ) {
            $due_days = intval( sanitize_text_field( $default_due_days ) );
         } elseif ( ! is_numeric( $default_due_days ) ) {
            add_settings_error( $this->ow_workflow_group_name,
            		$this->ow_default_due_days_option_name,
            		__( 'Please enter a numeric value for default due date.', 'oasisworkflow' ),
            		'error' );
         } else {
            add_settings_error( $this->ow_workflow_group_name,
            		$this->ow_default_due_days_option_name,
            		__( 'Please enter the number of days for default due date.', 'oasisworkflow' ),
            		'error' );
         }
      }
      return $due_days;
   }
   
   /**
    * sanitize data
    * @param string $step_due_date_setting
    * @return string
    */
   public function validate_step_due_date_setting( $step_due_date_setting ) {
      return sanitize_text_field( $step_due_date_setting );
   }

   /**
    * do validate and sanitize selected roles
    * @param array $selected_roles
    * @return array
    */
   public function validate_specified_action_by_roles( $selected_roles ) {
      $roles = array();
      if ( count( $selected_roles ) > 0 ) {

         // Sanitize the value
         $roles = array_map( 'esc_attr', $selected_roles );
       
         foreach ( $selected_roles as $selected_role ) {
            array_push( $roles, $selected_role );
         }
      }
      return $roles;
   }

   /**
    * sanitize data
    * @param string $review_process_setting
    * @return string
    */
   public function validate_review_process_setting( $review_process_setting ) {
      return sanitize_text_field( $review_process_setting );
   }

   /**
    * sanitize data
    * @param string $review_priority_setting
    * @return string
    */
   public function validate_priority_setting( $priority_setting ) {
      return sanitize_text_field( $priority_setting );
   }
   
   /**
    * sanitize data
    * @param string $publish_date_setting
    * @return string
    */
   public function validate_publish_date_setting( $publish_date_setting ) {
      return sanitize_text_field( $publish_date_setting );
   }
   
   /**
    * do validate and sanitize selected participants
    * @param array $selected_participants
    * @return array
    */
   public function validate_selected_participant_roles( $selected_participants ) {
      $participants = array();
      $participating_roles = OW_Utility::instance()->get_participating_roles();
      
      if ( count( $selected_participants ) > 0 ) {
         
          // Sanitize the value
         $selected_participants = array_map( 'esc_attr', $selected_participants );
         
         foreach ( $participating_roles as $role => $display_name ) {
            if ( is_array( $selected_participants ) && in_array( esc_attr( $role ), $selected_participants ) ) { // preselect specified role
              $participants[$role] = $display_name;
            } 
         }
      }
      return $participants;
   }
   
   /**
    * do validate and sanitize auto delete history settings
    * @param array $input
    * @return array
    */
   public function validate_auto_delete_history_setting( $input ) {
      $auto_delete_history_settings = array();
      $auto_delete_history_settings['enable'] = isset( $input['enable'] ) ? sanitize_text_field( $input['enable'] ) : '';
      $auto_delete_history_settings['period']  = sanitize_text_field( $input['period'] );
      return $auto_delete_history_settings;
   }
   
   /**
    * do validate and sanitize selected roles
    * @param array $selected_roles
    * @return array
    */
   public function validate_selected_roles( $roles ) {
      $selected_roles = array();
      $login_redirect_roles = OW_Utility::instance()->get_participating_roles();
      
      if ( count( $roles ) > 0 ) {
         
          // Sanitize the value
         if ( ! empty( $roles ) ) {
            $roles = array_map( 'esc_attr', $roles );
         }
         
         foreach ( $login_redirect_roles as $role => $display_name ) {
            if ( is_array( $roles ) && in_array( esc_attr( $role ), $roles ) ) { // preselect specified role
              $selected_roles[$role] = $display_name;
            } 
         }
      }
      return $selected_roles;
   }


   /**
    * generate the page
    *
    * @since 2.0
    */
   public function add_settings_page() {
      $is_activated_workflow = get_option( $this->ow_activate_workflow );
      $default_due_days = get_option( $this->ow_default_due_days_option_name );
      $step_due_date_setting = get_option( $this->ow_step_due_date_setting_option_name );
      $show_wfsettings_on_post_types = get_option( $this->ow_show_wfsettings_on_post_types_option_name );
      $priority_setting = get_option( $this->ow_priority_setting_option_name );    
      $publish_date_setting = get_option( $this->ow_publish_date_setting_option_name );
      $participants = get_option( $this->ow_participating_roles_setting_option_name );
      $login_redirect_roles = get_option( $this->ow_login_redirect_setting_option_name );
      $auto_delete_history = get_option( $this->ow_auto_delete_history_setting_option_name );
      ?>
      <form id="wf_settings_form" method="post" action="options.php">
          <?php
          settings_fields( $this->ow_workflow_group_name ); // adds nonce for current settings page
          ?>
          <div id="workflow-setting">
              <div id="settingstuff">
                  <div class="select-info">
                      <label class="settings-title"><input type="checkbox"
                      	name="<?php echo $this->ow_activate_workflow; ?>"
                      	value="active" <?php echo ( $is_activated_workflow == 'active' ) ? 'checked' : ''; ?> />
                      	<?php echo __( "Activate Workflow process ?", "oasisworkflow" ); ?>
                      </label>
							 <br />
							 <span class="description">
							 	<?php echo __( "(After you are done setting up your editorial workflow, make it available for use by activating the workflow process.)", "oasisworkflow" );?>
							 </span>
                  </div>

                  <div class="select-info">
                      <label class="settings-title">
                          <input type="checkbox" id="chk_default_due_days" <?php echo ( $default_due_days ) ? 'checked' : ''; ?> />
                          <?php echo __( "Set Default Due Date as CURRENT DATE + ", "oasisworkflow" ); ?>
                      </label>
                      <input type="text" id="default_due_days"
                      	name="<?php echo $this->ow_default_due_days_option_name; ?>"
                      	size="4" class="default_due_days"
                      	value="<?php echo esc_attr( $default_due_days ); ?>"
                      	maxlength=2 />
                      <label class="settings-title"><?php echo __( "day(s).", "oasisworkflow" ); ?></label>
                      <br class="clearfix">
                      <label class="indented-label">
                         <input type="checkbox" id="chk_step_due_days" <?php echo ( $default_due_days ) ? '' : 'disabled'; ?>
                                 name="<?php echo $this->ow_step_due_date_setting_option_name; ?>"
                                 value="yes"
                                    <?php checked( $step_due_date_setting, 'yes' ); ?>/>
                                 <?php _e( 'Allow to override the Default Due Date on each step of the workflow.', 'oasisworkflow' ); ?>
                      </label>
                  </div>

                 <?php
                       echo <<<TRIGGER_EVENT
                        <script>
                           jQuery(document).ready(function() {
                              jQuery(function(){
                                 jQuery("#chk_default_due_days").click(function(){
                                     if(!jQuery(this).is(":checked")){ //checks if the checkbox/this is selected or not
                                       jQuery("#default_due_days").val(""); //empty the input value
                                       jQuery('#chk_step_due_days').prop('checked', false); 
                                       jQuery('#chk_step_due_days').prop('disabled', true);
                                     } else {
                                       jQuery('#chk_step_due_days').prop('disabled', false);
                                     }
                                 });
                              });
                           });
                        </script>
TRIGGER_EVENT;
                  ?>
                 
                  <div class="select-info">
                      <div class="list-section-heading">
                          <label>
                          		<?php echo __( "Show Workflow options for the following post/page types:", "oasisworkflow" ) ?>
                          </label>
                      </div>
                      <?php OW_Utility::instance()->owf_checkbox_post_types_multi(
                      		$this->ow_show_wfsettings_on_post_types_option_name . '[]',
                      		$show_wfsettings_on_post_types ); ?>
                  </div>
                 
                 <div class="select-info">
                      <div class="list-section-heading">
                          <label>
                          		<?php echo __( "Roles that can participate in the workflow:", "oasisworkflow" ) ?>
                          </label>
                      </div>
                      <?php OW_Utility::instance()->owf_checkbox_roles_multi(
                      		$this->ow_participating_roles_setting_option_name . '[]',
                      		$participants ); ?>
                  </div>
                 
                 <div class="select-info">
                      <div class="list-section-heading">
                          <label>
                          		<?php echo __( "Make Workflow Inbox as the dashboard for the following roles:", "oasisworkflow" ) ?>
                          </label>
                      </div>
                      <?php OW_Utility::instance()->owf_checkbox_roles_multi(
                      		$this->ow_login_redirect_setting_option_name . '[]',
                      		$login_redirect_roles ); ?>
                  </div>

		  				<div class="select-info">
                      <label class="settings-title">
                          <input type="checkbox" id="enable_priority"
                          		name="<?php echo $this->ow_priority_setting_option_name; ?>"
                          		value="enable_priority" <?php checked( $priority_setting, 'enable_priority'); ?>/>
                          		<?php _e( 'Enable workflow task priority.', 'oasisworkflow' ); ?>
                      </label>
							 <br />
							 <span class="description">
							 	<?php echo __( "(Allows user to specify priority when signing off the task.)", "oasisworkflow" );?>
							 </span>
                  </div>
                 
                 <div class="select-info">
                      <label class="settings-title">
                          <input type="checkbox" id="hide_publish_date"
                                 name="<?php echo $this->ow_publish_date_setting_option_name; ?>"
                                 value="hide"
                                    <?php checked( $publish_date_setting, 'hide' ); ?>/>
                                 <?php _e( 'Hide Publish Date field on "Submit to Workflow".', 'oasisworkflow' ); ?>
                      </label>
                  </div>
                 
                  <div class="select-info">
                     <?php $check = ( $auto_delete_history['enable'] == "yes" ) ? ' checked="checked" ' : ''; ?>
                     <input type="checkbox" name="<?php echo $this->ow_auto_delete_history_setting_option_name ?>[enable]"
                            value="yes" <?php echo $check; ?>/>
                     <label class="settings-title">
                        <?php echo __( "Delete Workflow History for posts/pages which were last updated :", "oasisworkflow" ); ?>
                     </label>
                     <select name="<?php echo $this->ow_auto_delete_history_setting_option_name ?>[period]">
                        <?php OW_Utility::instance()->get_purge_history_period_dropdown( $auto_delete_history['period']); ?>
                     </select>
                     <br />
							 <span class="description">
							 	<?php echo __( "(A cron job will run once a day to purge the workflow history for completed workflows.)", "oasisworkflow" );?>
							 </span>
                  </div>

                  <div class="select-info full-width">
                      <div id="owf_settings_button_bar">
                          <input type="submit" id="settingSave"
                                 class="button button-primary button-large"
                                 value="<?php echo __( "Save", "oasisworkflow" ); ?>" />
                      </div>
                  </div>
              </div>
          </div>
      </form>
      <?php
   }

}

$ow_workflow_settings = new OW_Workflow_Settings();
?>