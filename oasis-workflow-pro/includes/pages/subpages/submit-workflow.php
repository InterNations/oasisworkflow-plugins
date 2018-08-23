<?php
/*
 * Submit to Workflow popup
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

$ow_workflow_service = new OW_Workflow_Service();
$workflows = $ow_workflow_service->get_workflow_by_validity( 1 );
$default_due_days = get_option( 'oasiswf_default_due_days' );
$publish_date = date_i18n( OASISWF_EDIT_DATE_FORMAT, current_time( 'timestamp' ) );
$publish_time_array = explode( "-", current_time( "H-i" ) );
$reminder_days = get_option( 'oasiswf_reminder_days' );
$reminder_days_after = get_option( 'oasiswf_reminder_days_after' );
$publish_date_settings = get_option( 'oasiswf_publish_date_setting' );

if( $workflows ) {
   if( isset( $_GET['post'] ) && $_GET['post'] ) {
      $post_id = intval( sanitize_text_field( $_GET['post'] ) );
      $check_revision = get_post_meta( $post_id, '_oasis_original', true );
      // check if post is revision
      if( $check_revision != '' ) {
         $is_revision = true;
      } else {
         $is_revision = false;
      }
   } else {
      $is_revision = false;
   }
}

$workflow_terminology_options = get_option( 'oasiswf_custom_workflow_terminology' );
$assign_actors_label = !empty( $workflow_terminology_options['assignActorsText'] ) ? $workflow_terminology_options['assignActorsText'] : __( 'Assign Actor(s)', 'oasisworkflow' );
$due_date_label = !empty( $workflow_terminology_options['dueDateText'] ) ? $workflow_terminology_options['dueDateText'] : __( 'Due Date', 'oasisworkflow' );
$publish_date_label = !empty( $workflow_terminology_options['publishDateText'] ) ? $workflow_terminology_options['publishDateText'] : __( 'Publish Date', 'oasisworkflow' );
$priority_label = !empty( $workflow_terminology_options['taskPriorityText'] ) ? $workflow_terminology_options['taskPriorityText'] : __( 'Priority', 'oasisworkflow' );
?>
<div class="info-setting" id="new-workflow-submit-div">
   <div class="dialog-title"><strong><?php echo __( "Submit", "oasisworkflow" ); ?></strong></div>
   <div id="ow-step-messages" class="owf-hidden"></div>
   <div id="submit-workflow-contents">
      <div class="select-part">
         <label><?php echo __( "Workflow : ", "oasisworkflow" ); ?>
         </label>
         <select id="workflow-select" style="width:200px;">
            <?php
            $count = count( $workflows );
            $ary_sel = array();
            if( $workflows ) {
               $post_id = null;
               if( isset( $_GET['post'] ) && $_GET['post'] ) {
                  $post_id = intval( sanitize_text_field( $_GET['post'] ) );
               }
               foreach ( $workflows as $workflow ) {
                  if( $ow_workflow_service->is_workflow_applicable( $workflow->ID, $post_id ) ) {
                     $additional_info = unserialize( $workflow->wf_additional_info );
                     // Get all revised + universal workflows
                     if( $is_revision == true && $additional_info['wf_for_revised_posts'] ) {
                        $ary_sel[] = $workflow->ID;
                        if( $workflow->version == 1 )
                           echo "<option value={$workflow->ID}>" . $workflow->name . "</option>";
                        else
                           echo "<option value={$workflow->ID}>" . $workflow->name . " (" . $workflow->version . ")" . "</option>";
                     }
                     // Get all new + universal workflows
                     elseif( $is_revision == false && $additional_info['wf_for_new_posts'] ) {
                        $ary_sel[] = $workflow->ID;
                        if( $workflow->version == 1 )
                           echo "<option value={$workflow->ID}>" . $workflow->name . "</option>";
                        else
                           echo "<option value={$workflow->ID}>" . $workflow->name . " (" . $workflow->version . ")" . "</option>";
                     }
                  }
               }
            }
            ?>
         </select>
         <br class="clear">
      </div>
      <?php
      if( count( $ary_sel ) == 1 ) {
         echo <<<TRIGGER_EVENT
            <script>
               jQuery(document).ready(function() {
                  jQuery("#workflow-select option[value='$ary_sel[0]']").prop("selected", true);
               });
            </script>
TRIGGER_EVENT;
      } else {
         echo <<<ADD_BLANK_OPTION
            <script>
               jQuery(document).ready(function() {
                  jQuery('#workflow-select').prepend('<option selected="selected"></option>');
               });
            </script>
ADD_BLANK_OPTION;
      }
      ?>
      <div class="select-info">
         <label><?php echo __( "Step : ", "oasisworkflow" ); ?></label>
         <select id="step-select" name="step-select" style="width:150px;" real="step-loading-span" disabled="true"></select>
         <span id="step-loading-span"></span>
         <br class="clear">
      </div>

      <?php if( get_option( 'oasiswf_priority_setting' ) == 'enable_priority' ) { ?>
         <div class="select-info">
            <label><?php echo $priority_label . " :"; ?></label>
            <select id="priority-select" name="priority-select" style="width:150px;">
               <?php OW_Utility::instance()->get_priority_dropdown(); ?>
            </select>
            <span id="step-loading-span"></span>
            <br class="clear">
         </div>
      <?php } 
      // if team add on is active then show teams list 
      do_action( 'owf_assignee_list' );      
         ?>
         <div id="multiple-actors-div" class="select-info owf-hidden" style="height:140px;">
            <label><?php echo $assign_actors_label . " :"; ?></label>
            <div class="select-actors-div">
               <div class="select-actors-list">
                  <label><?php echo __( "Available", "oasisworkflow" ); ?></label>
                  <span class="assign-loading-span" style="float:right;">&nbsp;</span><br>
                  <p><select id="actors-list-select" name="actors-list-select" size=10 multiple="multiple"></select></p>
               </div>
               <div class="select-actors-div-point">
                  <a href="#" id="assignee-set-point"><img src="<?php echo OASISWF_URL . "img/role-set.png"; ?>"
                                                           style="border:0px;"/></a><br><br>
                  <a href="#" id="assignee-unset-point"><img src="<?php echo OASISWF_URL . "img/role-unset.png"; ?>"
                                                             style="border:0px;"/></a>
               </div>
               <div class="select-actors-list">
                  <label><?php echo __( "Assigned", "oasisworkflow" ); ?></label><br>
                  <p><select id="actors-set-select" name="actors-set-select" size=10 multiple="multiple"></select></p>
               </div>
            </div>
            <br class="clear">
         </div>
      
      <?php if( $default_due_days != '' || $reminder_days != '' || $reminder_days_after != '' ): ?>
         <div class="owf-text-info left full-width">
            <div class="left">
               <label><?php echo $due_date_label . " : "; ?>
                  <a href="#" title="<?php echo __( 'Specify a date for the assignment to be completed.', "oasisworkflow" ); ?>" class="tooltip">
                     <span title="">
                        <img src="<?php echo OASISWF_URL . '/img/help.png'; ?>" class="help-icon"/></span>
                  </a>
               </label>
            </div>
            <div class="left">
               <input class="date_input" name="due-date" id="due-date"  value=""/>
               <button class="date-clear" ><?php echo __( "clear", "oasisworkflow" ); ?></button>
            </div>
            <br class="clear">
         </div>
      <?php endif; ?>

      <!-- Added publish date box for user to choose future publish date. -->
      <?php if ( $publish_date_settings !== 'hide' ): ?>
         <div class="owf-text-info left full-width">
            <div class="left">
               <label><?php echo $publish_date_label . " : "; ?>
                  <a href="#" title="<?php echo __( 'Specify a desired publish date for the post.', "oasisworkflow" ); ?>" class="tooltip">
                     <span title="">
                        <img src="<?php echo OASISWF_URL . '/img/help.png'; ?>" class="help-icon"/></span>
                  </a>
               </label>
            </div>
            <div class="left">
               <input name="publish-date" id="publish-date" class="date_input" type="text" real="publish-date-loading-span" value="<?php echo esc_attr( $publish_date ); ?>">@
               <input type="text" name="publish-hour" id="publish-hour" class="date_input wf-time" placeholder="hour" maxlength="2" value="<?php echo esc_attr( $publish_time_array[0] ); ?>">:
               <input type="text" name="publish-min" id="publish-min" class="date_input wf-time" placeholder="min"  maxlength="2" value="<?php echo esc_attr( $publish_time_array[1] ); ?>">
               <button class="date-clear" ><?php echo __( "clear", "oasisworkflow" ); ?></button>
               <span class="publish-date-loading-span">&nbsp;</span>
            </div>
            <br class="clear">
         </div>
      <?php endif; ?>
      
      <div id="ow-step-custom-data" class="owf-hidden owf-text-info left full-width"></div>
     
      <div class="select-info owf-text-info left full-width" id="comments-div">
         <div class="left full-width">
            <label><?php echo __( "Comments : ", "oasisworkflow" ); ?></label>
            <textarea id="workflowComments" style="height:100px;width:400px;margin-top:10px;" ></textarea>
         </div>
         <br class="clear">
      </div>
   </div>
   <br class="clear">
   <div class="select-info left changed-data-set full-width">
      <input type="button" id="submitSave" class="button-primary" value="<?php echo __( "Submit", "oasisworkflow" ); ?>" />
      <span>&nbsp;</span>
      <a href="#" id="submitCancel"><?php echo __( "Cancel", "oasisworkflow" ); ?></a>
   </div>
   <br class="clear">
   <input type="hidden" name="owf_signoff_ajax_nonce" id="owf_signoff_ajax_nonce" value="<?php echo wp_create_nonce( 'owf_signoff_ajax_nonce' ); ?>" />
</div>