<?php
/*
 * Step Info Content Popup
 *
 * @copyright   Copyright (c) 2016, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.1
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

global $wp_roles, $wpdb;
$process_info = "";
$step_info = "";
$step_db_id = "";
$step_gp_id = "";
$process_name = "";
if( isset( $_POST['step_gp_id'] ) && sanitize_text_field( $_POST["step_gp_id"] ) ) {
   $step_gp_id = sanitize_text_field( $_POST["step_gp_id"] );
}

if( isset( $_POST['process_name'] ) && sanitize_text_field( $_POST["process_name"] ) ) {
   $process_name = sanitize_text_field( $_POST["process_name"] );
}

if( isset( $_POST['step_db_id'] ) && sanitize_text_field( $_POST["step_db_id"] ) != "nodefine" ) {
   $step_db_id = sanitize_text_field( $_POST["step_db_id"] );
   $step_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_workflow_steps_table_name() . " WHERE ID = %d", $step_db_id ) );
   $step_info = json_decode( $step_row->step_info );
   $process_info = json_decode( $step_row->process_info );
}

$show_step_due_date = get_option( 'oasiswf_step_due_date_settings' );
$global_default_due_days = get_option( 'oasiswf_default_due_days' );

?>
<div id="step-setting">
   <div class="dialog-title"><strong><?php echo __( "Step Information", "oasisworkflow" ); ?></strong></div>
   <div id="step-setting-content" style="overflow:auto;" >
      <p class="step-name">
         <label><?php echo __( "Step Name :", "oasisworkflow" ) ?> </label><input type="text" id="step-name" name="step-name" />
      </p>

      <br class="clear">
      <div class="">
         <div style="margin-left:0px;">
            <label><?php echo __( "Assignee(s) :", "oasisworkflow" ); ?> </label>
         </div>
         <select name="show_available_actors[]" id="show_available_actors" class="ow-show-available-actors" multiple="multiple">
            <?php
            $task_assignee = '';
            $options = '';
            if( isset( $step_info->task_assignee ) ) {
               $task_assignee = $step_info->task_assignee;
            }

            // display options from other addons like "groups"
            apply_filters( 'owf_display_assignee_groups', $task_assignee );

            // display roles
				$options .= OW_Utility::instance()->get_roles_option_list( $step_info );

            // display all registered users
				$options .= OW_Utility::instance()->get_users_option_list( $step_info );

            echo $options;
            ?>
         </select>
      </div>
      <br class="clear">
      <div>
         <div style="margin-left:0px;">
            <label>
               <?php echo __( "Assign to all? : ", "oasisworkflow" ); ?>
               <a href="#" title="<?php echo __( 'Check this box to assign the task to all the users in this step and hide the assignee list during the sign off process.', "oasisworkflow" );
               ?>" class="tooltip">
                  <span title="">
                     <img src="<?php echo OASISWF_URL . '/img/help.png'; ?>" class="help-icon"/>
                  </span>
               </a>
            </label>
         </div>
         <span>
            <input type="checkbox" id="assign_to_all" value="1" <?php echo is_object( $step_info ) && isset( $step_info->assign_to_all ) ? checked( $step_info->assign_to_all, 1, false ) : 'checked'; ?> />
         </span>
         <br class="clear">
      </div>
      
      <?php if ( $show_step_due_date == 'yes' ) : 
               if ( isset( $step_info->step_due_days ) ) {
                  $step_due_days = $step_info->step_due_days;
               } else {
                  $step_due_days = $global_default_due_days;
               }
         ?>
      
      <div class="owf-text-info left full-width">
            <div class="left">
               <label><?php echo __( "Default", "oasisworkflow" ) . " <br> " .  __( "Due Date:", "oasisworkflow" ); ?>
                  <a href="#" title="<?php echo __( 'Specify a default due date for this step to be completed. This is an optional setting.', "oasisworkflow" ); ?>" class="tooltip">
                     <span title="">
                        <img src="<?php echo OASISWF_URL . '/img/help.png'; ?>" class="help-icon"/></span>
                  </a>
               </label>
            </div>
            <div class="left">
               <input type="text" id="step_due_days"
                      	name="step_due_days"
                      	size="4" class="step_due_days"
                      	value="<?php echo esc_attr( $step_due_days ); ?>"
                      	maxlength=2 />
               <span id="step-settings">&nbsp; <?php echo __( " day(s) after the post is submitted to the workflow.", "oasisworkflow" ); ?> </span>
            </div>
         </div>        
      <br class="clear">
      <?php endif; ?>
      
      <br class="clear">
      
      <div class="first_step">
         <div style="margin-left:0px;">
            <label><?php echo __( "Is first step? : ", "oasisworkflow" ); ?></label>
         </div>
         <span><input type="checkbox" id="first_step_check" /></span>
         <br class="clear">
      </div>
      <br class="clear">
        <div class="first-step-post-status owf-hidden">
            <fieldset class="first-step-fieldset">
               <legend><?php echo __( "On Submit to Workflow", "oasisworkflow" ); ?></legend>
               <label><?php echo __( 'Post Status: ', 'oasisworkflow' ); ?>
                   <a href="#" title="<?php echo __( 'Post Status after submit to workflow.', "oasisworkflow" );
                   ?>" class="tooltip">
                       <span title="">
                           <img src="<?php echo OASISWF_URL . 'img/help.png'; ?>" class="help-icon"/>
                       </span>
                   </a>
               </label>
               <select name="first_step_post_status" id="first_step_post_status">
                   <option value=""></option>
                   <?php $status_array = get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ); ?>
                   <?php foreach ( $status_array as $status_id => $status_object ) { ?>
                      <option value="<?php echo $status_id; ?>" <?php selected( $status_id, 'draft' ); ?>><?php echo $status_object->label; ?></option>
                   <?php } ?>
               </select>
               <?php apply_filters( 'owf_display_condition_group_list', $step_info, true, "first_step_condition_group" ); ?>
               <br class="clear">
            </fieldset>
        </div>
      
      <?php if ( $process_name == 'review' ) { ?>
       <br class="clear">
       <div class="first_step">
         <div style="margin-left:0px;">
            <label><?php echo __( "Review Settings: ", "oasisworkflow" ); ?></label>
         </div>
          <span>
                <?php
                $review_approval = 'everyone'; //default value
                if ( isset( $step_info->review_approval ) ) {
                   $review_approval = $step_info->review_approval;
                }
                ?>
                <input type="radio"
                       name="review_approval"
                       value="everyone"<?php echo $review_approval == 'everyone' ? 'checked="checked"' : ''; ?> />
                       <?php _e( 'Everyone should approve', 'oasisworkflow' ); ?>

                <br>
                <input type="radio"
                       name="review_approval"
                       value="more_than_50"<?php echo $review_approval == 'more_than_50' ? 'checked="checked"' : ''; ?> />
                       <?php _e( 'More than 50% should approve', 'oasisworkflow' ); ?>

                <br>
                <input type="radio"
                       name="review_approval"
                       value="anyone"<?php echo $review_approval == 'anyone' ? 'checked="checked"' : ''; ?> />
                       <?php _e( 'Anyone should approve', 'oasisworkflow' ); ?>
             </span>

         <br class="clear">
      </div> 
      <?php } ?>
      
      <?php apply_filters( 'owf_display_condition_group_list', $step_info, false, "condition_group" ); ?>

      <br class="clear">
      
      <h3 class="nav-tab-wrapper" id="step_email_content">
         <a class="nav-tab nav-tab-active" href="#assignment_email"><?php echo __( "Assignment Email", "oasisworkflow" ); ?></a>
         <a class="nav-tab" href="#reminder_email"><?php echo __( "Reminder Email", "oasisworkflow" ); ?></a>
      </h3>
      <form>
         <div id="assignment_email">
            <div>
               <h3><?php echo __( "Assignment Email", "oasisworkflow" ); ?></h3>
            </div>
            <div>
               <div class="place-holder"><label><?php echo __( "Placeholder : ", "oasisworkflow" ); ?></label></div>
               <div class="left">
                  <?php
                  $placeholders = get_site_option( "oasiswf_placeholders" );
                  $custom_placeholders = apply_filters( 'oasiswf_custom_placeholders', '' );
                  $placeholders = is_array( $custom_placeholders ) ? array_merge( $placeholders, $custom_placeholders ) : $placeholders;
                  ?>
                  <select id="assign-placeholder" style="width:150px;">
                     <option value=""><?php echo __( "--Select--", "oasisworkflow" ); ?></option>
                     <?php
                     //$placeholders = get_site_option( "oasiswf_placeholders" ) ;
                     if( $placeholders ) {
                        foreach ( $placeholders as $k => $v ) {
                           echo "<option value='$k'>{$v}</option>";
                        }
                     }
                     ?>
                  </select>
                  <input type="button" id="addPlaceholderAssignmentSubj" class="button-primary placeholder-add-bt" value="<?php echo __( "Add to subject", "oasisworkflow" ); ?>" style="margin-left:20px;" />
                  <input type="button" id="addPlaceholderAssignmentMsg" class="button-primary placeholder-add-bt" value="<?php echo __( "Add to message", "oasisworkflow" ); ?>" style="margin-left:20px;" />
               </div>
               <br class="clear">
            </div>
            <p>
               <label ><?php echo __( "Email subject : ", "oasisworkflow" ); ?></label>
               <?php
               $assignment_subject = "";
               $assignment_content = "";
               $reminder_subject = "";
               $reminder_content = "";
               if( is_object( $process_info ) ) {
                  $assignment_subject = $process_info->assign_subject;
                  // FIXED: Do not use nl2br() function because its adds br in place of \n
                  $assignment_content = $process_info->assign_content;
                  $reminder_subject = $process_info->reminder_subject;
                  $reminder_content = $process_info->reminder_content;
               }
               ?>
               <input type="text" id="assignment-email-subject" name="assignment-email-subject" value="<?php echo esc_attr( $assignment_subject ); ?>" />
            </p>
            <div class="email-message-area">
               <div class="left"><label><?php echo __( "Email message : ", "oasisworkflow" ); ?></label></div>
               <div class="left" id="assignment-email-content-div">
                  <textarea id="assignment-email-content" name="assignment-email-content" style="width:500px;height:200px;"><?php echo esc_textarea( $assignment_content ); ?></textarea>
               </div>
               <br class="clear">
            </div>
         </div>
         <div id="reminder_email" style="display: none;">
            <div>
               <h3><?php echo __( "Reminder Email", "oasisworkflow" ); ?></h3>
            </div>
            <div>
               <div class="place-holder"><label><?php echo __( "Placeholder : ", "oasisworkflow" ); ?></label></div>
               <div class="left">
                  <select id="reminder-placeholder" style="width:150px;">
                     <option value=""><?php echo __( "--Select--", "oasisworkflow" ); ?></option>
                     <?php
                     $placeholders = get_site_option( "oasiswf_placeholders" );
                     if( $placeholders ) {
                        foreach ( $placeholders as $k => $v ) {
                           echo "<option value='$k'>{$v}</option>";
                        }
                     }
                     ?>
                  </select>
                  <input type="button" id="addPlaceholderReminderSubj" class="button-primary placeholder-add-bt" value="<?php echo __( "Add to subject", "oasisworkflow" ); ?>" style="margin-left:20px;" />
                  <input type="button" id="addPlaceholderReminderMsg" class="button-primary placeholder-add-bt" value="<?php echo __( "Add to message", "oasisworkflow" ); ?>" style="margin-left:20px;" />

               </div>
               <br class="clear">
            </div>
            <p>
               <label><?php echo __( "Email subject : ", "oasisworkflow" ); ?></label>
               <input type="text" id="reminder-email-subject" name="reminder-email-subject" value="<?php echo esc_attr( $reminder_subject ); ?>" />
            </p>
            <div class="email-message-area">
               <div style="float:left;"><label><?php echo __( "Email message : ", "oasisworkflow" ); ?></label></div>
               <div style="float:left;">
                  <textarea id="reminder-email-content" name="reminder-email-content" style="width:500px;height:200px;"><?php echo esc_textarea( $reminder_content ); ?></textarea>
               </div>
               <br class="clear">
            </div>
         </div>
         <!--/div-->
      </form>
      <br class="clear">
      <input type="hidden" id="step_gpid-hi" value="<?php echo esc_attr( $step_gp_id ); ?>" />
      <input type="hidden" id="step_dbid-hi" value="<?php echo esc_attr( $step_db_id ); ?>" />
   </div>
   <div class="dialog-title" style="padding-bottom:0.5em"></div>
   <br class="clear">
   <p class="step-set">
      <input type="button" id="stepSave" class="button-primary" value="<?php echo __( "Save", "oasisworkflow" ); ?>"  />
      <span>&nbsp;</span>
      <a href="#" id="stepCancel" style="color:blue;margin-top:5px;"><?php echo __( "Cancel", "oasisworkflow" ); ?></a>
   </p>
   <br class="clear">
</div>