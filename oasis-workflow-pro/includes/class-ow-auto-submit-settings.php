<?php
/*
 * Settings class for Workflow Auto Submit Settings
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
 * Ow_Auto_submit_Settings Class
 *
 * @since 2.0
 */

class OW_Auto_Submit_Settings {

   /**
    * @var string default option name
    */
   protected $ow_auto_submit_settings_option_name = 'oasiswf_auto_submit_settings';

   /**
    * @var array Custom Cron Schedules
    */
   protected $custom_intervals = array();

   /**
    * Set things up.
    *
    * @since 2.0
    */
   public function __construct() {
      add_action( 'admin_init', array( $this, 'init_settings' ) );
      
      $this->custom_intervals = array(
        'minutes_15'  =>  array( 'interval' => 15 * 60, 'display' => __( '15 minutes', 'oasisworkflow' ) ),
        'minutes_30'  => array( 'interval' => 30 * 60, 'display' => __( '30 minutes', 'oasisworkflow' ) ),
        'minutes_45'  => array( 'interval' => 45 * 60, 'display' => __( '45 minutes', 'oasisworkflow' ) ),
        'hourly'      => array( 'interval' => 60 * 60, 'display' => __( '1 hour', 'oasisworkflow' ) ),
        'hours_4'     => array( 'interval' => 240 * 60, 'display' => __( '4 hours', 'oasisworkflow' ) ),
        'hours_8'     => array( 'interval' => 480 * 60, 'display' => __( '8 hours', 'oasisworkflow' ) )
        );

   }

   // White list our options using the Settings API
   public function init_settings() {
      register_setting( 'ow-settings-auto-submit', $this->ow_auto_submit_settings_option_name, array( $this, 'validate_auto_submit_settings' ) );
   }

   /**
    * Validate and sanitize all user input data
    *
    * @param array $input
    * @return array
    * @since 2.0
    */
   public function validate_auto_submit_settings( array $input ) {
      $auto_submit_settings = array(); // Initialize the option array
      $workflow_array = array();

      // Trigger - one time action for testing auto submit
      if ( isset( $_POST['auto_submit_btn'] ) ) {
         $ow_auto_submit_service = new OW_Auto_Submit_Service();
         $submitted_posts_count = $ow_auto_submit_service->auto_submit_articles( TRUE );

         add_settings_error(
            'ow-settings-auto-submit',
            'auto_submit_trigger_one_time',
            __( 'Auto submit triggered successfully. ' . $submitted_posts_count . ' posts/page submitted.', 'oasisworkflow' ),
            'updated'
         );
      }

      $auto_submit_stati = array();

      // verify if post status is selected
      if ( isset( $input["auto_submit_stati"] ) && is_array( $input["auto_submit_stati"] ) ) {

         $selected_options = $input["auto_submit_stati"];
         // sanitize the values
         $selected_options = array_map( 'esc_attr', $selected_options );
         foreach ( $selected_options as $selected_option ) {
            array_push( $auto_submit_stati, $selected_option );
         }
      } else {
         add_settings_error(
            'ow-settings-auto-submit',
            'auto_submit_stati',
            __( 'Please select atleast one Post/Page status.', 'oasisworkflow' ),
            'error'
         );

      }
      $auto_submit_settings['auto_submit_stati'] = $auto_submit_stati;

      // If due date is not empty then do validate and sanitize user input
      $due_days = '';
      if ( ! empty( $input["auto_submit_due_days"] ) ) {
         if ( is_numeric( $input["auto_submit_due_days"] ) ) {
            $due_days = intval( sanitize_text_field( $input["auto_submit_due_days"] ) );
         } else if ( ! is_numeric( $input["auto_submit_due_days"] ) ) {
            add_settings_error(
               'ow-settings-auto-submit',
               'auto_submit_due_days',
               __( 'Please enter a numeric value for due date.', 'oasisworkflow' ),
               'error'
            );
         } else {
            add_settings_error(
               'ow-settings-auto-submit',
               'auto_submit_due_days',
               __( 'Please enter a value for due date.', 'oasisworkflow' ),
               'error'
            );
         }
      }
      $auto_submit_settings['auto_submit_due_days'] = $due_days;

      $auto_submit_settings['auto_submit_comment'] = stripcslashes( sanitize_text_field( $input["auto_submit_comment"] ) );

      // If post count is not empty then do validate and sanitize user input
      $post_count = '';
      if ( ! empty( $input["auto_submit_post_count"] ) ) {
         if ( is_numeric( $input["auto_submit_post_count"] ) ) {
            $post_count = intval( sanitize_text_field( $input["auto_submit_post_count"] ) );
         } else if ( ! is_numeric( $input["auto_submit_post_count"] ) ) {
            add_settings_error(
               'ow-settings-auto-submit',
               'auto_submit_post_count',
               __( 'Please enter a numeric value for post count.', 'oasisworkflow' ),
               'error'
            );
         } else {
            add_settings_error(
               'ow-settings-auto-submit',
               'auto_submit_post_count',
               __( 'Please enter the number of posts/pages to be processed at one time.', 'oasisworkflow' ),
               'error'
            );
         }
      }
      $auto_submit_settings['auto_submit_post_count'] = $post_count;

      $auto_submit_settings['auto_submit_enable'] = isset( $input["auto_submit_enable"] ) ? sanitize_text_field( $input["auto_submit_enable"] ) : '';
      $auto_submit_settings['search_post_title'] = isset( $input["search_post_title"] ) ? sanitize_text_field( $input["search_post_title"] ) : '';
      $auto_submit_settings['search_post_tags'] = isset( $input["search_post_tags"] ) ? sanitize_text_field( $input["search_post_tags"] ) : '';
      $auto_submit_settings['search_post_categories'] = isset( $input["search_post_categories"] ) ? sanitize_text_field( $input["search_post_categories"] ) : '';
      
      // Schedule the cron
      $auto_submit_settings['auto_submit_interval'] = isset( $input["auto_submit_interval"] ) ? sanitize_text_field( $input["auto_submit_interval"] ) : "";
       
      // Update cron if scheduled cron is different than existing cron set 
      $auto_submit_interval = $auto_submit_settings['auto_submit_interval'];
      $schedule = wp_get_schedule( 'oasiswf_auto_submit_schedule' );
      
      if ( ! empty( $auto_submit_interval ) && $auto_submit_interval !== $schedule ) {
         $interval = $auto_submit_interval;
         $timestamp = wp_next_scheduled( 'oasiswf_auto_submit_schedule' );
         wp_unschedule_event( $timestamp, 'oasiswf_auto_submit_schedule' );        
         wp_schedule_event( time(), $interval, 'oasiswf_auto_submit_schedule' );
      }

      // Auto submit associated workflows
      $workflow_array = array();
      $error = false;
      $workflow_count = 0;

      if ( array_key_exists("workflow_id", $input ) ) {
         $workflows = array_map( 'esc_attr', $input['workflow_id'] );

         // the first element in the array is always empty, so remove that element.
         // it's the one which is used to clone, when the user hits the plus sign, to assign workflows.
         array_splice( $workflows, 0, 1 );
         $workflow_count = count( $workflows );
      }

      if ( array_key_exists("keywords", $input ) ) {
         $keywords = array_map( 'esc_attr', $input['keywords'] );

         // the first element in the array is always empty, so remove that element.
         // it's the one which is used to clone, when the user hits the plus sign, to assign workflows.
         array_splice( $keywords, 0, 1 );
      }


      // if more than one workflow is selected, then keywords are required
      if ( $workflow_count > 1 ) {
         foreach ( $workflows as $key => $value ) {
            if ( empty( $keywords[ $key ] ) ) {
                $error = true;
            }
         }
      }
      
      if ( $error ) {
         add_settings_error(
            'ow-settings-auto-submit',
            'auto_submit_workflows',
            __( 'Please provide keywords for the workflows to participate in auto submit process.', 'oasisworkflow' ),
            'error'
         );
      }
      
      if ( ! empty( $workflows ) && $workflow_count > 0 ) {
         for ( $index = 0; $index < $workflow_count; $index ++ ) {
            // workflow_id as key
            // keywords as value
            $workflow_array[$workflows[ $index ]] = $keywords[ $index ];
         }
      }

      if ( empty ( $workflow_array ) ) { //looks like the option is being updated from outside the settings page, eg, via update_option()
         // try to get it directly from the 'auto_submit_workflows'
         if ( array_key_exists("auto_submit_workflows", $input ) ) {
            $workflow_array = array_map( 'esc_attr', $input['auto_submit_workflows'] );
         }
      }

      if ( ! empty ( $workflow_array ) ){
         $auto_submit_settings['auto_submit_workflows'] = $workflow_array;
      }

      return $auto_submit_settings;
   }

   /**
    * Display setting page
    * @access public
    */
   public function add_settings_page() {                       
      $auto_submit_settings = get_option( $this->ow_auto_submit_settings_option_name );
      $ow_workflow_service = new OW_Workflow_Service();
      $this->custom_intervals = apply_filters( "owf_auto_submit_custom_interval", $this->custom_intervals );
      $workflows = $ow_workflow_service->get_workflow_list( "active" );
      ?>
      <form id="wf_settings_form" method="post" action="options.php">
         <?php
         settings_fields( 'ow-settings-auto-submit' ); // adds nonce for current settings page
         ?>
         <div id="workflow-setting">
            <div id="auto-submit-setting">
               <div id="settingstuff">
                  <ol>
                     <li>
                        <div class="select-info">
                           <label class="settings-title"><input type="checkbox" name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[auto_submit_enable]" value="active" <?php echo $auto_submit_settings['auto_submit_enable'] == 'active' ? 'checked' : ''; ?> />&nbsp;&nbsp;<?php echo __( "Enable Auto Submit?", "oasisworkflow" ); ?>
                           </label>
                        </div>
                     </li>
                     <li>
                        <div class="select-info">
                           <div class="list-section-heading">
                              <label><?php echo __( "Post/Page status(es) to be selected for auto submit:", "oasisworkflow" ) ?></label>
                           </div>
                           <select name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[auto_submit_stati][]" size="6" multiple="multiple">
                              <?php OW_Utility::instance()->owf_dropdown_post_status_multi( $auto_submit_settings['auto_submit_stati'] ); ?>
                           </select>
                        </div>
                     </li>
                     <li>
                        <div class="select-info">
                           <label class="settings-title">
                              <?php echo __( "Include the following in keyword search:", "oasisworkflow" ); ?>
                           </label>
                        </div>
                        <div class="select-info margin-override">
                           <input type="checkbox" name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[search_post_title]" value="yes"  <?php echo $auto_submit_settings['search_post_title'] == 'yes' ? 'checked' : ''; ?>/>&nbsp;&nbsp;
                           <label class="settings-title"><?php echo __( "Title", "oasisworkflow" ); ?> </label>
                        </div>
                        <div class="select-info margin-override">
                           <input type="checkbox" name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[search_post_tags]" value="yes"  <?php echo $auto_submit_settings['search_post_tags'] == 'yes' ? 'checked' : ''; ?>/>&nbsp;&nbsp;
                           <label class="settings-title"><?php echo __( "Tags", "oasisworkflow" ); ?> </label>
                        </div>
                        <div class="select-info margin-override">
                           <input type="checkbox" name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[search_post_categories]" value="yes"  <?php echo $auto_submit_settings['search_post_categories'] == 'yes' ? 'checked' : ''; ?>/>&nbsp;&nbsp;
                           <label class="settings-title"><?php echo __( "Categories", "oasisworkflow" ); ?> </label>
                        </div>
                     </li>
                     <li>
                        <div class="select-info">
                           <label class="settings-title">
                              <?php echo __( "Set Due date as CURRENT DATE + ", "oasisworkflow" ); ?>
                           </label>
                           <input type="text" name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[auto_submit_due_days]" size="4" class="auto_submit_due_days" value="<?php echo esc_attr( $auto_submit_settings['auto_submit_due_days'] ); ?>" maxlength=2 />
                           <label class="settings-title"><?php echo __( "day(s).", "oasisworkflow" ); ?></label>
                        </div>
                     </li>
                     <li>
                        <div class="select-info">
                           <div class="list-section-heading">
                              <label>
                                 <?php echo __( "Auto submit comments:", "oasisworkflow" ); ?>
                              </label>
                           </div>
                           <textarea name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[auto_submit_comment]" size="4" class="auto_submit_comment"
                                     cols="80" rows="5"><?php echo esc_textarea( $auto_submit_settings['auto_submit_comment'] ); ?></textarea>
                        </div>
                     </li>
                     <li>
                        <div class="select-info">
                           <label class="settings-title">
                              <?php echo __( "Process ", "oasisworkflow" ); ?>
                           </label>
                           <input type="text" name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[auto_submit_post_count]" size="8" class="auto_submit_post_count" value="<?php echo esc_attr( $auto_submit_settings['auto_submit_post_count'] ); ?>" maxlength=4 />
                           <label class="settings-title"><?php echo __( "posts/pages at one time.", "oasisworkflow" ); ?></label>
                           <br/>
                           <span class="description"><?php echo __( "(Limit the number of posts/pages to be processed at one time for optimum server performance.)", "oasisworkflow" ); ?></span>
                        </div>
                     </li>
                     <li>
                        <div class="select-info">
                           <div class="list-section-heading">
                              <label class="settings-title"><?php _e( "Run Auto Submit Engine every:", "oasisworkflow" ) ?></label>
                              <select name="<?php echo $this->ow_auto_submit_settings_option_name; ?>[auto_submit_interval]">
                                 <option value=""><?php _e( "Please Select", "oasisworkflow" ); ?></option>
                                    <?php $auto_submit_interval = empty( $auto_submit_settings['auto_submit_interval'] ) ? 'hourly' : $auto_submit_settings['auto_submit_interval']; ?>
                                    <?php if ( $this->custom_intervals ) : ?>
                                       <?php foreach ( $this->custom_intervals as $interval => $value ) : ?>
                                          <?php $is_default = $auto_submit_interval == $interval ? 'selected' : ''; ?>
                                                <option value="<?php echo $interval; ?>" <?php echo $is_default; ?>><?php echo $value["display"]; ?></option>
                                       <?php endforeach; ?>
                                    <?php endif; ?>
                              </select>
                              <br/>
                              <span class="description"><?php echo __( "(How often do you wish to run the auto submit process?)", "oasisworkflow" ); ?></span>
                           </div>
                        </div>
                     </li>
                     <li>
                        <div class="select-info">
                           <?php $class = ""; ?>
                           <label class="settings-title">
                              <?php echo __( "Applicable Workflows for auto submit:", "oasisworkflow" ); ?>
                           </label>
                           <br/>
                           <span class="description"><?php echo __( "(Provide keywords(comma separated) for the workflows to participate in auto submit process.)", "oasisworkflow" ); ?></span>
                           <br class="clearfix">
                           <ul class="auto-submit-label">
                              <li><?php echo __('Assigned Workflows', 'oasisworkflow' ); ?></li>
                              <li><?php echo __('Keywords', 'oasisworkflow' ); ?></li>
                           </ul>
                           <div class="auto-submit-workflow">   
                              <div class="owf-hidden">
                                 <select name="<?php echo $this->ow_auto_submit_settings_option_name;?>[workflow_id][]">
                                    <?php OW_Utility::instance()->workflows_dropdown( $workflows ); ?>
                                 </select>
                                 <textarea name="<?php echo $this->ow_auto_submit_settings_option_name;?>[keywords][]" rows="1" cols="50" placeholder="<?php echo __('Keywords for Auto Submit(comma separated)', 'oasisworkflow' ); ?>"></textarea>
                                 <span class="icon-remove remove-workflow">
                                    <img src="<?php echo OASISWF_URL ?>/img/trash.png" title="remove" />
                                 </span>
                              </div>
                              <?php
                              if ( isset( $auto_submit_settings['auto_submit_workflows'] ) && ( ! empty( $auto_submit_settings['auto_submit_workflows'] ) ) ) {
                                 $class = "owf-hidden";
                                 $workflow_list = $auto_submit_settings['auto_submit_workflows'];
                                 foreach ( $workflow_list as $wf_id => $keywords ) {
                              ?>
                                 <div class="owf-workflows">
                                    <select name="<?php echo $this->ow_auto_submit_settings_option_name;?>[workflow_id][]">
                                       <?php OW_Utility::instance()->workflows_dropdown( $workflows, $wf_id ); ?>
                                    </select>
                                    <textarea name="<?php echo $this->ow_auto_submit_settings_option_name;?>[keywords][]" rows="1" cols="50" placeholder="<?php echo __('Keywords for Auto Submit(comma separated)', 'oasisworkflow' ); ?>"><?php echo esc_attr( $keywords ); ?></textarea>
                                    <span class="icon-remove remove-workflow">
                                       <img src="<?php echo OASISWF_URL ?>/img/trash.png" title="remove" />
                                    </span>
                                 </div>
                              <?php
                                 }
                              } ?> 
                              <div class="no-applicable-workflow <?php echo $class ?>">
                               <?php echo __('No applicable workflows found. Click the "+ Assign Workflow" button to add workflows.', 'oasisworkflow' ); ?>
                              </div>
                              <div class="owf-as-button">
                              <input type="button" name="add-workflow" id="add-workflow" value="<?php _e( '+ Assign Workflow', 'oasisworkflow' ); ?>"
                                     class="button button-primary add-workflow" />
                              </div>
                           </div>
                        </div>
                     </li>
                  </ol>

                  <div class="select-info full-width">
                     <div id="owf_settings_button_bar">
                        <input type = "submit" id = "settingSave"
                               class = "button button-primary button-large"
                               value="<?php echo __("Save", "oasisworkflow"); ?>" />

                        <input type = "submit" id = "auto_submit_btn"
                               class = "button button-secondary button-large" name = "auto_submit_btn"
                               value="<?php echo __("Trigger Auto Submit - Just One Time", "oasisworkflow"); ?>" />
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </form>
      <?php
   }

}

$ow_auto_submit_settings = new OW_Auto_submit_Settings();
?>