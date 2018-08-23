<?php

/*
 * Service class for the Workflow Process flow
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/*
 * OW_Process_Flow Class
 *
 * @since 2.0
 */

class OW_Process_Flow {

   /*
    * Set things up.
    *
    * @since 2.0
    */
   public function __construct() {
      add_action( 'wp_ajax_get_submit_step_details', array( $this, 'get_submit_step_details' ) );
      add_action( 'wp_ajax_validate_submit_to_workflow', array( $this, 'validate_submit_to_workflow' ) );

      add_action( 'wp_ajax_execute_sign_off_decision', array( $this, 'execute_sign_off_decision' ) );
      add_action( 'wp_ajax_get_sign_off_step_details', array( $this, 'get_sign_off_step_details' ) );
      add_action( 'wp_ajax_submit_post_to_step', array( $this, 'submit_post_to_step' ) );

      add_action( 'wp_ajax_check_for_claim_ajax', array( $this, 'check_for_claim_ajax' ) );
      add_action( 'wp_ajax_claim_process', array( $this, 'claim_process' ) );
      add_action( 'wp_ajax_reassign_process', array( $this, 'reassign_process' ) );

      add_action( 'wp_ajax_workflow_complete', array( $this, 'workflow_complete' ) );
      add_action( 'wp_ajax_workflow_cancel', array( $this, 'workflow_cancel' ) );

      add_action( 'wp_ajax_workflow_abort_comments', array( $this, 'workflow_abort_comments' ) );
      add_action( 'wp_ajax_workflow_abort', array( $this, 'workflow_abort' ) );
      add_action( 'wp_ajax_multi_workflow_abort', array( $this, 'multi_workflow_abort' ) );
      add_action( 'wp_ajax_get_post_publish_date_edit_format', array( $this, 'get_post_publish_date_edit_format' ) );

      add_action( 'wp_ajax_oasiswf_delete_post', array( $this, 'oasiswf_delete_post' ) );
      add_action( 'wp_trash_post', array( $this, 'when_post_trash_delete' ) );

      // Show posts/pages which current logged in user can access or see..
//      add_action( 'pre_get_posts', array( $this, 'show_only_accessible_posts' ) );

//      add_filter( 'owf_claim_process_pre', array( $this, 'pre_validate_claim' ), 10, 4 );

      // Trigger after user deleted
      add_action( 'deleted_user', array( $this, 'purge_user_assignments' ), 10, 1 );

      // Trigger on redirect post location
      add_action( 'redirect_post_location', array( $this, 'redirect_after_signoff' ) );
      add_action( 'owf_submit_to_workflow', array( $this, 'redirect_after_workflow_submit' ), 10, 2 );
      
   }
   
   /**
    * AJAX function - executed on step change during "submit to workflow".
    *
    * Given the selected step, it populates the step actors, teams (if any)
    * It also displays custom data (like pre publish checklist) (if any)
    */
   public function get_submit_step_details() {

      // nonce check
      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );

      // sanitize post_id
      $post_id = isset( $_POST["post_id"] ) ? intval( $_POST["post_id"] ) : null;

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      /* sanitize incoming data */
      $step_id = intval( $_POST["step_id"] );
      $history_id = isset( $_POST["history_id"] ) ? intval( $_POST["history_id"] ) : null;
      $wf_id = intval( $_POST["wf_id"] );

      // create an array of all the inputs
      $step_details_params = array (
         "step_id"    => $step_id,
         "post_id"    => $post_id,
         "history_id" => $history_id
      );

      $messages = "";

      // initialize the return array
      $step_details = array(
         "teams" => "",
         "users" => "",
         "process" => "",
         "assign_to_all" => 0,
         "custom_data" => "",
         "due_date" => ""
      );

      // if teams add-on is active, get all the available teams
      if ( get_option( 'oasiswf_team_enable' ) == 'yes' ) {
         $teams = apply_filters( 'get_teams_for_workflow', $wf_id );

         // hook to filter out teams
         $teams = apply_filters( 'owf_filter_teams', $teams, $post_id );

         if ( ! empty( $teams ) ) {
            $step_details["teams"] = $teams;
         }         
      }

      // call filter to display any custom data, like pre publish conditions
      $custom_data = "";
      $custom_data = apply_filters( 'owf_display_custom_data', $custom_data, $post_id, $step_id, $history_id );
      if ( ! empty ($custom_data ) ) {
         $step_details["custom_data"] = htmlentities( $custom_data );
      }

      // get step users
      $users_and_process_info = $this->get_users_in_step( $step_id, $post_id );

      if ( $users_and_process_info != null ) {
         $step_details["users"] = $users_and_process_info["users"];
         $step_details["process"] = $users_and_process_info["process"];
         $step_details["assign_to_all"] = $users_and_process_info["assign_to_all"];
      }
      
      $step_details[ "due_date" ] = $this->get_submit_workflow_due_date( $step_id );
      
      if ( empty( $step_details["users"] ) && empty( $step_details["teams"] ) ) {
         //something is wrong, we didn't get any step users
         $messages .= "<div id='message' class='error error-message-background '>";
         $messages .= '<p>' . __( 'No users found to assign the task.', 'oasisworkflow' ) . '</p>';
         $messages .= "</div>";
         wp_send_json_error( array( 'errorMessage' => $messages ) );
      }

      wp_send_json_success( $step_details );
   }

   /**
    *  AJAX function - Validate Submit to Workflow
    *
    *  In case of validation errors - display error messages on the "Submit to Workflow" popup
    */
   public function validate_submit_to_workflow() {

      // nonce check
      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );

      // sanitize incoming data
      $step_id = intval( $_POST['step_id'] );

      $form = $_POST['form'];
      parse_str( $form, $_POST );

      $post_tag_count = 0;
      if ( ! empty( $_POST['tax_input']['post_tag'] ) ) {
         $post_tag_count = count( explode( ',', sanitize_text_field( $_POST['tax_input']['post_tag'] ) ) );
      }
      // for some reason, there is an entry for "0" in the list, so lets minus that
      $post_category_count = ( isset( $_POST['post_category'] ) ) ? intval( count( $_POST['post_category'] ) ) - 1 : 0;

      $data = @array_map( 'esc_attr', $_POST );
      $post_id = $data['post_ID'];

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      $post_title = isset( $data["post_title"] ) ? $data["post_title"] : '' ;

      $post_excerpt = isset( $data['excerpt'] ) ? $data['excerpt'] : '';

      $post_content =  isset( $_POST['content'] ) ? $_POST['content'] : '';
      
      $user_provided_due_date = $data['hi_due_date'];
      
      // pre publish checklist
      $pre_publish_checklist = array();
      if ( ! empty ( $data['hi_custom_condition'] ) ) {
         $pre_publish_checklist = explode( ',', $data['hi_custom_condition'] );
      }

      // returns the post id of autosave post
      // ref :  https://wordpress.org/support/topic/need-to-invoke-autosave-programmatically-before-running-a-custom-action?replies=4#post-7853159
      // $saved_post_id = wp_create_post_autosave( $_POST );

      // create an array of all the inputs
      $submit_to_workflow_params = array (
         "step_id"               => $step_id,
         "step_decision"         => "complete",
         "history_id"            => "", //since the post is being submitted to a workflow, so no history_id exists
         "post_id"               => $post_id,
         "post_content"          => $post_content,
         "post_title"            => $post_title,
         "post_tag"              => $post_tag_count,
         "category"              => $post_category_count,
         "post_excerpt"          => $post_excerpt,
         "pre_publish_checklist" => $pre_publish_checklist
      );

      $validation_result = array();
      $messages = "";
      
      // Check if due date selected is past date if yes show error message
      $valid_due_date = $this->validate_due_date( $user_provided_due_date );
      if ( ! $valid_due_date ) {
         $due_date_error_message = __( 'Due date must be greater than the current date.', 'oasisworkflow' );
         array_push( $validation_result, $due_date_error_message );
      }
      
      // let the filter excute pre submit-to-workflow validations and return validation error messages, if any
      $validation_result = apply_filters( 'owf_submit_to_workflow_pre', $validation_result, $submit_to_workflow_params );

      if (  count( $validation_result ) > 0 ) {
         $messages .= "<div id='message' class='error error-message-background '>";
         $messages .= '<p>' . implode( "<br>", $validation_result ) . '</p>';
         $messages .= "</div>";
         wp_send_json_error( array( 'errorMessage' => $messages ) );
      }

      $post_status = "draft"; // default status, if nothing found
      // get post_status according to first step
      $ow_workflow_service = new OW_Workflow_Service();
      $step = $ow_workflow_service->get_step_by_id( $step_id );
      if( $step && $workflow = $ow_workflow_service->get_workflow_by_id( $step->workflow_id ) ) {
         $wf_info = json_decode( $workflow->wf_info );
         if( $wf_info->first_step && count( $wf_info->first_step ) == 1 ) {
            $first_step = $wf_info->first_step[0];
            if( is_object( $first_step ) && isset( $first_step->post_status ) &&
                ! empty( $first_step->post_status ) ) {
               $post_status = $first_step->post_status;
            }
         }
      }

      // No validation errors found, continue with submission to workflow
      wp_send_json_success( array( 'post_status' => $post_status ) );
   }


   /**
    * AJAX function - executed on decision select during "step sign off".
    *
    * Given the decision (approved or rejected), it populates the next steps in the workflow.
    * It also displays custom data (like pre publish checklist) (if any)
    */
   public function execute_sign_off_decision() {
      // nonce check
      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );

      // sanitize post_id
      $post_id = intval( $_POST['post_id'] );

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      /* sanitize incoming data */
      $step_id = isset( $_POST["step_id"] ) ? intval( $_POST["step_id"] ) : null;
      $history_id = intval( $_POST['history_id'] );

      $decision = sanitize_text_field( $_POST['decision'] ); //possible values - "success" and "failure"

      // initialize the return array
      $decision_details = array(
         "steps"            => "",
         "is_original_post" => true,
         "custom_data"      => ""
      );

      // get next steps
      // depending on the decision, get the next set of steps in the workflow
      $ow_history_service = new OW_History_Service();
      $ow_workflow_service = new OW_Workflow_Service();
      $action_history = $ow_history_service->get_action_history_by_id( $history_id );
      $steps = $ow_workflow_service->get_process_steps( $action_history->step_id );
      if ( empty ( $steps ) || ! array_key_exists( $decision, $steps ) ) { // no next steps found for the decision
         // if the decision was "success" - then this is the last step in the workflow
         if ( "success" == $decision ) {
            // check if this is the original post or a revision
            $original_post_id = get_post_meta( $action_history->post_id, '_oasis_original', true );
            if ( $original_post_id != null ) {
               $decision_details["is_original_post"] = false;
            }
         }
      } else { // assign the next steps depending on the decision
         $steps_array = array();
         foreach ( $steps[ $decision ] as $id => $value ) {
            array_push( $steps_array, array(
               "step_id" => $id,
               "step_name" => $value
            ) );
         }
         $decision_details["steps"] = $steps_array;
      }

      // call filter to display any custom data, like pre publish conditions
      $custom_data = "";
      if ( "success" == $decision ) {
         $custom_data = apply_filters( 'owf_display_custom_data', $custom_data, $post_id, $step_id, $history_id );
         if ( ! empty ( $custom_data ) ) {
            $decision_details["custom_data"] = htmlentities( $custom_data );
         }
      }

      wp_send_json_success( $decision_details );
   }

   /**
    * AJAX function - executed on step change during "Workflow Sign off".
    *
    * Given the selected step, it populates the step actors, teams (if any)
    */
   public function get_sign_off_step_details() {

      // nonce check
      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );

      // sanitize post_id
      $post_id = isset( $_POST["post_id"] ) ? intval( $_POST["post_id"] ) : null;

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      /* sanitize incoming data */
      $step_id = intval( $_POST["step_id"] );
      $history_id = isset( $_POST["history_id"] ) ? intval( $_POST["history_id"] ) : null;

      // create an array of all the inputs
      $step_details_params = array (
         "step_id"    => $step_id,
         "post_id"    => $post_id,
         "history_id" => $history_id
      );

      $messages = "";

      // initialize the return array
      $step_details = array(
         "users" => "",
         "process" => "",
         "assign_to_all" => 0,
         "team_id" => "",
         "team_name" => "",
         "due_date" => ""
      );

      // if team is assigned to the post, check if the team has members for this step
      $team_id = get_post_meta( $step_details_params["post_id"], '_oasis_is_in_team', true );
      if ( ! empty( $team_id ) ) {         
         $ow_teams_service = new OW_Teams_Service();
         $team      = $ow_teams_service->get_team_name_by_id( $team_id );
         $team_name = $team[0]->name;

         $step_details["team_id"]   = $team_id;
         $step_details["team_name"] = $team_name;
      }

      // get step users
      $users_and_process_info = $this->get_users_in_step( $step_id, $post_id );

      if ( $users_and_process_info != null ) {
         $step_details["users"] = $users_and_process_info["users"];
         $step_details["process"] = $users_and_process_info["process"];
         $step_details["assign_to_all"] = $users_and_process_info["assign_to_all"];
      }

      // get the due date for the step
      $step_details[ "due_date" ] = $this->get_sign_off_due_date( $post_id, $step_id );

      if ( empty( $step_details["users"] ) && empty( $step_details["team_id"] ) ) {
         //something is wrong, we didn't get any step users
         $messages .= "<div id='message' class='error error-message-background '>";
         $messages .= '<p>' . __( 'No users found to assign the task.', 'oasisworkflow' ) . '</p>';
         $messages .= "</div>";
         wp_send_json_error( array( 'errorMessage' => $messages ) );
      }

      wp_send_json_success( $step_details );
   }

   /**
    *
    * AJAX function - executes workflow sign off
    *
    * Validates the workflow signoff and then completes the sign off
    *
    */
   public function submit_post_to_step() {

      // nonce check
      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );

      // sanitize post_id
      $post_id = intval( $_POST['post_id'] );

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      /* sanitize incoming data */
      $step_id = intval( $_POST['step_id'] );
      $step_decision = sanitize_text_field( $_POST["step_decision"] );

      $priority = sanitize_text_field( $_POST["priority"] );

      // if empty, lets set the priority to default value of "normal".
      if( empty( $priority ) ) {
         $priority = '2normal';
      }

      $selected_actor_val = sanitize_text_field( $_POST["actors"] );
      OW_Utility::instance()->logger( "User Provided Actors:" . $selected_actor_val );
      
      $team = sanitize_text_field( $_POST["team"] );
      $team_id = null; 
      $is_team = false;
      // if teams add-on is active
      if ( $team === "true" ) { 
         $team_id = $selected_actor_val;        
         OW_Utility::instance()->logger( "Team ID:" . $team_id );
         $is_team = true;
      } else if ( $team !== "" ) {
         $team_id = $team;
         OW_Utility::instance()->logger( "Team ID:" . $team_id );
      }
      
      $actors = $this->get_workflow_actors( $post_id, $step_id, $selected_actor_val, $is_team );
      OW_Utility::instance()->logger( "Selected Actors:" . $actors );
      // hook to allow developers to add/remove users from the task assignment
      $actors = apply_filters( 'owf_get_actors', $actors, $step_id, $post_id );
      OW_Utility::instance()->logger( "Selected Actors After Filter:" . $actors );

      $task_user = get_current_user_id();
      // find out who is signing off the task; sometimes the admin can signoff on behalf of the actual user
      if ( isset( $_POST["task_user"] ) && $_POST["task_user"] != "" ) {
         $task_user = intval( sanitize_text_field( $_POST["task_user"] ) );
      }

      // sanitize_text_field remove line-breaks so do not sanitize it.
      $sign_off_comments = nl2br( $_POST["sign_off_comments"] );

      $due_date = "";
      if ( isset( $_POST["due_date"] ) && ! empty( $_POST["due_date"] ) ) {
         $due_date = sanitize_text_field( $_POST["due_date"] );
      }

      $custom_condition = sanitize_text_field( $_POST["custom_condition"] );
      $history_id = isset( $_POST["history_id"] ) ? intval( $_POST["history_id"] ) : null;

      // $_POST will get changed after the call to get_post_data, so get all the $_POST data before this call
      // get post data, either from the form or from the post_id
      $post_data = $this->get_post_data( $post_id );

      $pre_publish_checklist =  array();
      // pre publish checklist
      if ( ! empty ( $custom_condition ) ) {
         $pre_publish_checklist = explode( ',', $custom_condition );
      }

      // returns the post id of autosave post
      // ref :  https://wordpress.org/support/topic/need-to-invoke-autosave-programmatically-before-running-a-custom-action?replies=4#post-7853159
      // $saved_post_id = wp_create_post_autosave( $_POST );

      // create an array of all the inputs
      $sign_off_workflow_params = array (
         "post_id"               => $post_id,
         "step_id"               => $step_id,
         "history_id"            => $history_id,
         "step_decision"         => $step_decision,
         "post_priority"         => $priority,
         "task_user"             => $task_user,
         "actors"                => $actors,
         "due_date"              => $due_date,
         "comments"              => $sign_off_comments,
         "post_content"          => $post_data['post_contents'],
         "post_title"            => $post_data['post_title'],
         "post_tag"              => $post_data['post_tag_count'],
         "category"              => $post_data['post_category_count'],
         "post_excerpt"          => $post_data['post_excerpt'],
         "pre_publish_checklist" => $pre_publish_checklist,
         "current_page"          => $post_data['current_page']
      );
      
      $validation_result = array();

      $validation_result = $this->validate_workflow_sign_off( $post_id, $sign_off_workflow_params );

      // Check if due date selected is past date if yes than show error messages
      $valid_due_date = $this->validate_due_date( $due_date );
      if ( ! $valid_due_date ) {
          $due_date_error_message = __( 'Due date must be greater than the current date.', 'oasisworkflow' );
          array_push( $validation_result, $due_date_error_message );
      }

      if (  count( $validation_result ) > 0 ) {
         $messages = "<div id='message' class='error error-message-background '>";
         $messages .= '<p>' . implode( "<br>", $validation_result ) . '</p>';
         $messages .= "</div>";
         wp_send_json_error( array( 'errorMessage' => $messages ) );
      }

      // No validation errors found, continue with sign off process

      // update the post priority
      update_post_meta( $post_id, "_oasis_task_priority", $priority );

      $submit_post_to_step_results = array();

      $new_action_history_id = $this->submit_post_to_step_internal( $post_id, $sign_off_workflow_params );
      $submit_post_to_step_results["new_history_id"] = $new_action_history_id;

      $ow_history_service = new OW_History_Service();

      $history_details = $ow_history_service->get_action_history_by_id( $sign_off_workflow_params['history_id'] );

      // get the source step id from the old history info
      $source_step_id = $history_details->step_id;
      $target_step_id = $step_id;

      $new_post_status = $this->get_post_status_from_step_transition( $post_id, $source_step_id, $target_step_id );
      $submit_post_to_step_results["new_post_status"] = $new_post_status;

      wp_send_json_success( $submit_post_to_step_results );
   }

   /*
    * AJAX function - Check Claim
    */
   public function check_for_claim_ajax() {

      check_ajax_referer( 'owf_check_claim_nonce', 'security' );

      $action_history_id = intval( $_POST["history_id"] );

      // check if we need to show the claim button or not.
      if ( $this->check_for_claim( $action_history_id ) ) {
         wp_send_json_success();
      }

      wp_send_json_error();
   }

   /**
    * AJAX function - Claim process
    * Checks for claim, if true, adds a record in the history table for the claim action
    * deletes all the step emails which are not applicable anymore
    * send the actor who claimed the article about the assignment email and reminder emails (if any)
    * notify all other users in that step about the "task being claimed".
    *
    * @return int id of the newly added history object for the claim process
    *
    * @since 2.0
    */
   public function claim_process() {
      global $wpdb;

      check_ajax_referer( 'owf_claim_process_ajax_nonce', 'security' );
      
      $action_history_id = intval( $_POST["actionid"] );

      $action_history_table = OW_Utility::instance()->get_action_history_table_name();
      $ow_history_service = new OW_History_Service();
      $action_history = $ow_history_service->get_action_history_by_id( $action_history_id );
      $ow_email = new OW_Email( );
      $post_title = "";
      $new_history_id = "";
      $validation_result = array();
      $error_message = "";
      if ( $this->check_for_claim( $action_history_id ) ) { // First check if "claim" is applicable or not
         // let the filter execute pre claim process validations and return validation messages, if any
         $validation_result = apply_filters( 'owf_claim_process_pre', $validation_result, $action_history_id, $action_history->post_id, $action_history->assign_actor_id  );
         if ( count( $validation_result ) > 0 ) {
            $error_message = $this->construct_claim_error_message( $validation_result );
            wp_send_json_error( array( 'errorMessage' => $error_message ) );
         }
         
         $action_histories = $ow_history_service->get_action_history_by_status( "assignment", $action_history->post_id );
         foreach ( $action_histories as $action ) { // for all the history ids, only one will be "claimed", rest need to be "unclaimed" OR claim_cancelled.
            if ( $post_title == "" ) {
               $post_title = stripcslashes( get_post( $action->post_id )->post_title );
            }
            if ( $action_history_id == $action->ID ) { // this is a match, so claim
               // add claim action to history table
               $claim_history_data = (array) $action;
               unset( $claim_history_data["ID"] ); //unset the id, since we will get a new ID after insert
               $claim_history_data["action_status"] = "assignment";
               $claim_history_data["from_id"] = $action->ID;
               $claim_history_data["create_datetime"] = current_time( 'mysql' );
               if ( empty( $action->due_date ) ) {
                  unset( $claim_history_data["due_date"] );
               }
               if ( empty( $action->reminder_date ) ) {
                  unset( $claim_history_data["reminder_date"] );
               }
               if ( empty( $action->reminder_date_after ) ) {
                  unset( $claim_history_data["reminder_date_after"] );
               }
               $new_history_id = OW_Utility::instance()->insert_to_table( $action_history_table, $claim_history_data );

               $old_history_id = $action->ID;
               do_action( 'owf_claim_action', $action_histories, $new_history_id );

               // delete reminder emails, since the assignment is now claimed
               $ow_email->delete_step_email( $action->ID, $action->assign_actor_id );

               // add email reminders, if any
               $ow_email->generate_reminder_emails( $new_history_id );

               $data["action_status"] = "claimed";
            } else {
               $data["action_status"] = "claim_cancel";
               $post_id = $action->post_id;
               $ow_email->delete_step_email( $action->ID, $action->assign_actor_id );
            }
            $wpdb->update( $action_history_table, $data, array( "ID" => $action->ID ) );
         }
         // send email to other users, saying that the article has been removed from their inbox, since it was claimed by another user
         $ow_email->notify_users_on_task_claimed( $post_id );
      }
      $claim_data = array ( 'url'=> admin_url(), 'new_history_id' => $new_history_id );
      wp_send_json_success( $claim_data );
   }

   /**
    * AJAX function - Reassign process
    */
   public function reassign_process() {
      global $wpdb;

      // nonce check
      check_ajax_referer( 'owf_reassign_ajax_nonce', 'security' );

      // capability check
      if ( ! current_user_can( 'ow_reassign_task' ) ) {
         wp_die( __( 'You are not allowed to reassign tasks.' ) );
      }

      /* sanitize incoming data */
      $current_user = (sanitize_text_field( $_POST["task_user"] ) != "") ? intval( sanitize_text_field( $_POST["task_user"] ) ) : get_current_user_id();
      $action_history_id = intval( sanitize_text_field( $_POST["oasiswf"] ) );
      $reassign_users = array_map('sanitize_text_field', $_POST["reassign_id"] );
      $reassign_comments = ( sanitize_text_field( $_POST['reassign_comments'] ) != "" ) ? sanitize_text_field( $_POST['reassign_comments'] ) : "";


      $action_table = OW_Utility::instance()->get_action_table_name();
      $action_history_table = OW_Utility::instance()->get_action_history_table_name();

      $reassign_comments_json_array = array( array( "send_id" => $current_user,
                                                                  "comment" => stripcslashes( $reassign_comments ),
                                                                  "comment_timestamp" => current_time( "mysql" ) ) );

      $ow_email = new OW_Email( );
      $ow_history_service = new OW_History_Service();
      // get history details for all assignment, review and publish step
      $action = $ow_history_service->get_action_history_by_id( $action_history_id );
      $data = (array) $action;

      // insert record into history table regarding this action
      if ( $data["assign_actor_id"] != -1 ) { // assignment or publish step (reassigned)
         unset( $data["ID"] );
         if ( empty( $data['due_date'] ) || $data['due_date'] == '0000-00-00' ) {
            unset( $data['due_date'] );
         }
         if ( empty( $data['reminder_date'] ) || $data['reminder_date'] == '0000-00-00' ) {
            unset( $data['reminder_date'] );
         }
         if ( empty( $data['reminder_date_after'] ) || $data['reminder_date_after'] == '0000-00-00' ) {
            unset( $data['reminder_date_after'] );
         }
         $data["from_id"] = $action_history_id;
         $data["create_datetime"] = current_time( 'mysql' );
         if ( ! empty( $reassign_comments ) ) {
            $data['comment'] = json_encode( $reassign_comments_json_array );
         }

         foreach ( $reassign_users as $reassign_user_id ) {
            $data["assign_actor_id"] = $reassign_user_id;
            $new_history_id = OW_Utility::instance()->insert_to_table( $action_history_table, $data );
            if ( $new_history_id ) {
               $wpdb->update( $action_history_table, array( "action_status" => "reassigned" ),
                           array( "ID" => $action_history_id, "assign_actor_id" => $current_user ) );
               // action for editorial comments
               do_action( 'owf_save_workflow_reassign_action', $data["post_id"], $new_history_id );
               $ow_email->delete_step_email( $action_history_id, $current_user );
               $ow_email->send_step_email( $new_history_id, $reassign_user_id ); // send mail to the actor .
            }
         }
         wp_send_json_success();
      } else { // review step (reassigned)
         $delete_current_user_task = false;
         $reviews = $ow_history_service->get_review_action_by_status( "assignment", $action_history_id );
         // If the task is already assigned to the user
            foreach ( $reviews as $review ) {
               if ( in_array( $review->actor_id, $reassign_users ) ) {
                  // If comments exist than append the reassign comments to existing comments of the reassigned user
                  if ( ! empty( $review->comments ) ) {
                     $comments = json_decode( $review->comments, true );
                     array_push( $reassign_comments_json_array, $comments[0] );
                  }
                  $comment = json_decode( $data['comment'] );
                  if ( ! empty( $comment ) ) {
                     array_push( $reassign_comments_json_array, $comment[0] );
                  }
                  // Update comments for reassigned users
                  $wpdb->update( $action_table, array( 
                        "comments" => json_encode( $reassign_comments_json_array ),
                        "update_datetime" => current_time( "mysql" ) ),
                        array( "ID" => $review->ID ) );
                  $delete_current_user_task = true;
               }               
            }  
         if ( $delete_current_user_task ) {
            // Delete task from current user
            $wpdb->delete( $action_table, array(
                'actor_id' => $current_user,
                'action_history_id' => $action_history_id ) );
            $ow_email->delete_step_email( $action_history_id, $current_user );
            wp_send_json_success();
         } else {            
            $review = $ow_history_service->get_review_action_by_actor( $current_user, "assignment", $action_history_id );

            // if the reassign is in a review process, insert data into the fc_action table
            $review = (array) $review;
            $review_id = $review["ID"];
            unset( $review["ID"] );
            if ( empty( $review['due_date'] ) || $review['due_date'] == '0000-00-00' ) {
               unset( $review['due_date'] );
            }
            if ( empty( $review['comments'] ) ) {
               unset( $review['comments'] );
            }

            foreach ( $reassign_users as $reassign_user_id ) {
               $review["actor_id"] = $reassign_user_id;

               $new_review_history_id = OW_Utility::instance()->insert_to_table( $action_table, $review );
               if ( $new_review_history_id ) {
                  $wpdb->update( $action_table, array( "review_status" => "reassigned",
                                                       "comments" => json_encode( $reassign_comments_json_array ),
                                                       "update_datetime" => current_time( "mysql" ) ),
                                 array( "ID" => $review_id ) );

                  $ow_email->delete_step_email( $action_history_id, $current_user );
                  $ow_email->send_step_email( $action_history_id, $reassign_user_id ); // send mail to the actor .

               }
            }   
            wp_send_json_success();
         }   
      }

      wp_send_json_error();
   }

   /**
    * AJAX function - complete the workflow
    */
   public function workflow_complete() {
      // nonce check
      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );

      // sanitize post_id
      $post_id = intval( $_POST['post_id'] );

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      /* sanitize incoming data */
      $history_id = intval( $_POST['history_id'] );
      $publish_datetime = null;
      if( isset( $_POST["immediately"] ) && ! empty( $_POST["immediately"] ) ) { // even though hidden
         $publish_datetime = sanitize_text_field( $_POST["immediately"] );            
         $publish_immediately = false;
      } else {
         // looks like a case for immediate publish.
         $publish_immediately = true;
         $publish_datetime = get_the_date( 'Y-m-d H:i:s', $post_id );
      }

      $task_user = get_current_user_id();
      // find out who is signing off the task; sometimes the admin can signoff on behalf of the actual user
      if( isset( $_POST["task_user"] ) && $_POST["task_user"] != "" ) {
         $task_user = intval( sanitize_text_field( $_POST["task_user"] ) );
      }

      // where is action executed from - is this from Inbox page Or Post Edit page
      $parent_page = sanitize_text_field( $_POST["parent_page"] );

      $custom_condition = sanitize_text_field( $_POST["custom_condition"] );

      // $_POST will get changed after the call to get_post_data, so get all the $_POST data before this call
      // get post data, either from the form or from the post_id
      $post_data = $this->get_post_data( $post_id );

      $pre_publish_checklist = array();
      // pre publish checklist
      if ( ! empty ( $custom_condition ) ) {
         $pre_publish_checklist = explode( ',', $custom_condition );
      }

      // create an array of all the inputs
      $workflow_complete_params = array (
         "post_id"               => $post_id,
         "history_id"            => $history_id,
         "task_user"             => $task_user,
         "publish_datetime"      => $publish_datetime,
         "publish_immediately"   => $publish_immediately,
         "post_content"          => $post_data['post_contents'],
         "post_title"            => $post_data['post_title'],
         "post_tag"              => $post_data['post_tag_count'],
         "category"              => $post_data['post_category_count'],
         "post_excerpt"          => $post_data['post_excerpt'],
         "pre_publish_checklist" => $pre_publish_checklist,
         "current_page"          => $post_data['current_page']
      );
      
      $validation_result = $this->validate_workflow_complete( $post_id, $workflow_complete_params );

      if (  count( $validation_result ) > 0 ) {
         $messages = "<div id='message' class='error error-message-background '>";
         $messages .= '<p>' . implode( "<br>", $validation_result ) . '</p>';
         $messages .= "</div>";
         wp_send_json_error( array( 'errorMessage' => $messages ) );
      }

      // Sign off and complete the workflow
      $result_array = $this->change_workflow_status_to_complete_internal( $post_id, $workflow_complete_params );

      // when signing off from the inbox page, we do not have to worry about updating/saving the post
      // we simply take the post and complete the workflow.
      // if signing off from post edit page, we use the "save_action" via - workflow_submit_action()
      $original_post_id = get_post_meta( $post_id, '_oasis_original', true );
      if ( empty( $original_post_id ) && $parent_page == "inbox" ) { // we are dealing with original post
         $this->ow_update_post_status( $post_id, $result_array["new_post_status"] );
      } elseif ( ! empty( $original_post_id ) && $parent_page == "inbox" ) { // we are dealing with a revision post
         // hook for revision complete
         do_action("owf_revision_workflow_complete", $post_id );
      }

      do_action( 'owf_workflow_complete', $post_id, $result_array["new_action_history_id"] );

      $complete_workflow_results = array();

      $complete_workflow_results["new_post_status"] = $result_array["new_post_status"];

      wp_send_json_success( $complete_workflow_results );
   }

   /*
    * AJAX function - Cancel the workflow
    */
   public function workflow_cancel() {
      // nonce check
      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );

      // sanitize post_id
      $post_id = intval( sanitize_text_field( $_POST["post_id"] ) );
      
      $attachment = array();

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      /* sanitize incoming data */
      $user_id = get_current_user_id();
      $history_id = intval( sanitize_text_field( $_POST["history_id"] ) );
      $user_comments = sanitize_text_field( $_POST["comments"] );

      $comments[] = array( "send_id" => $user_id, "comment" => stripcslashes( $user_comments ) );
      $comments_json = json_encode( $comments );

      // cancel the workflow.
      $data = array(
         'action_status' => "cancelled",
         'comment' => $comments_json,
         'post_id' => $post_id,
         'from_id' => $history_id,
         'create_datetime' => current_time( 'mysql' )
      );
      $action_history_table = OW_Utility::instance()->get_action_history_table_name();
      $review_action_table = OW_Utility::instance()->get_action_table_name();
      $new_action_history_id = OW_Utility::instance()->insert_to_table( $action_history_table, $data );

      if ( isset( $_POST["hi_task_user"] ) && $_POST["hi_task_user"] != "" ) {
         $current_actor_id = intval( sanitize_text_field( $_POST["hi_task_user"] ) );
      } else {
         $current_actor_id = get_current_user_id();
      }

      $ow_email = new OW_Email( );
      $ow_history_service = new OW_History_Service();

      if ( $new_action_history_id ) {
         global $wpdb;
         // delete all the unsend emails for this workflow
         $ow_email->delete_step_email( $history_id, $current_actor_id );
         $result_history = $wpdb->update( $action_history_table, array( 'action_status' => 'processed' ),
                                                                  array( 'ID' => $history_id ) );

         $result_review_history = $wpdb->update( $review_action_table, array( 'review_status' => 'cancelled',
                                                                              'update_datetime' => current_time( 'mysql' ) ),
                                                                        array( 'action_history_id' => $history_id ) );

         // send email about workflow cancelled
         $post = get_post( $post_id );
         $post_author = get_userdata( $post->post_author );
         $title = "'{$post->post_title}' was cancelled from the workflow";
         $full_name = OW_Utility::instance()->get_user_name( $user_id );

         $msg = sprintf( __( '<div>Hello %1$s,</div><p>The post <a href="%2$s" title="%3$s">%3$s</a> has been cancelled from the workflow.</p>', 'oasisworkflow' ),
            $post_author->display_name,
            esc_url( get_permalink( $post_id ) ),
            $post->post_title );

         if ( ! empty ( $user_comments ) ) {
            $msg .= "<p><strong>" . __('Additionally,', "oasisworkflow") . "</strong> {$full_name} " . __('added the following comments', "oasisworkflow") . ":</p>" ;
            $msg .= "<p>" . nl2br( $user_comments ) . "</p>" ;
         }

         $msg .= __("<p>Thanks.</p>", "oasisworkflow");

         $message = '<html><head></head><body><div class="email_notification_body">' . $msg . '</div></body></html>';

         $ow_email = new OW_Email( );
         $ow_email->send_mail( $post->post_author, $title, $message );

         // clean up after workflow complete
         $this->cleanup_after_workflow_complete( $post_id );
         wp_send_json_success();
      }
   }
   
   /**
    * AJAX function - Display popup to enter the comments when doing abort from workflow
    * @since 5.4
    */
   public function workflow_abort_comments() {      
      // nonce check
      $nonce = 'owf_inbox_ajax_nonce';
      
      if ( isset( $_POST[ 'command' ] ) && sanitize_text_field( $_POST[ 'command' ] ) == 'exit_from_workflow' ) {
         $nonce = 'owf_exit_post_from_workflow_ajax_nonce';
      }
      check_ajax_referer( $nonce, 'security' );
      
      ob_start();
      include_once OASISWF_PATH . 'includes/pages/subpages/abort-workflow-comment.php';
      $result = ob_get_contents();
      ob_get_clean();
      wp_send_json_success( htmlentities( $result ) );
   }

   /**
    * AJAX function - Abort the workflow
    */
   public function workflow_abort() {
      global $wpdb;

      // nonce check
      $nonce = 'owf_inbox_ajax_nonce';

      if ( isset( $_POST['command'] ) && sanitize_text_field( $_POST['command'] ) == 'exit_from_workflow' ) {
         $nonce = 'owf_exit_post_from_workflow_ajax_nonce';
      }
      check_ajax_referer( $nonce, 'security' );

      // capability check
      if ( ! current_user_can( 'ow_abort_workflow' ) ) {
         wp_die( __( 'You are not allowed to abort the workflow.' ) );
      }

      /* sanitize incoming data */
      $history_id = intval( $_POST["history_id"] );
      $comments = sanitize_text_field( $_POST[ "comment" ] );

      $new_history_id = $this->abort_the_workflow( $history_id, $comments );
      if ( $new_history_id != null ) {
         wp_send_json_success();
      } else {
         wp_send_json_error();
      }
   }

   /**
    * AJAX function - multi-abort for workflow
    *
    * @since 2.0
    */
   public function multi_workflow_abort() {
      global $wpdb;

      // nonce check
      check_ajax_referer( 'owf_workflow_abort_nonce', 'security' );

      // capability check
      if ( ! current_user_can( 'ow_abort_workflow' ) ) {
         wp_die( __( 'You are not allowed to abort the workflow.' ) );
      }

      /* sanitize incoming data */
      $post_ids = (array) $_POST['post_ids'];
      $post_ids = array_map( 'intval', $post_ids );

      // loop through the history_ids and abort the workflow one by one.
      foreach ( $post_ids as $post_id ) {
         $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() .
                                                " WHERE post_id = %d AND action_status = 'assignment'", $post_id ) );

         $new_history_id = $this->abort_the_workflow( $row->ID );
      }

      wp_send_json_success();
   }

   /**
    * AJAX function - get publish date in edit format
    */
   public function get_post_publish_date_edit_format() {
      $post_id = isset( $_POST["post_id"] ) ? intval( sanitize_text_field( $_POST["post_id"] ) ) : null;

      // initialize the return array
      $publish_datetime_array = array(
         "publish_date" => "",
         "publish_hour" => "",
         "publish_min" => ""
      );

      if ( ! empty( $post_id ) ) {
         $publish_datetime = get_the_date( OASISWF_EDIT_DATE_FORMAT . " @ H:i", $post_id );
         $datetime_array = explode( "@", $publish_datetime );
         $time_array = explode( ":", $datetime_array[1] );
         $publish_datetime_array["publish_date"] = trim( $datetime_array[0] );
         $publish_datetime_array["publish_hour"] = trim( $time_array[0] );
         $publish_datetime_array["publish_min"] = trim( $time_array[1] );
         wp_send_json_success( $publish_datetime_array );
      } else {
         wp_send_json_error();
      }
   }

   /**
    * AJAX function - deletes the revision post
    */
   public function oasiswf_delete_post() {
      global $wpdb;
      check_ajax_referer( 'owf_make_revision_ajax_nonce', 'security' );
      $post_id = isset( $_POST['post_id'] ) ? $_POST['post_id'] : '';
      if ( $post_id ) {
         $status = wp_trash_post( $post_id ) ? 'success' : 'error';
      } else {
         $status = 'error';
      }
      echo $status;
      exit();
   }

   /**
    * Hook - wp_trash_post
    * When a post is trashed, delete all the action history related to the post
    *
    * @since 2.0
    */
   public function when_post_trash_delete( $post_id ) {
      global $wpdb;

      $post_id = intval( $post_id );

      $ow_history_service = new OW_History_Service();
      $histories = $ow_history_service->get_action_history_by_post( $post_id );
      if ( $histories ) {
         foreach ( $histories as $history ) {
            $wpdb->get_results( $wpdb->prepare( "DELETE FROM " . OW_Utility::instance()->get_action_table_name() . " WHERE action_history_id = %d", $history->ID ) );
            $wpdb->get_results( $wpdb->prepare( "DELETE FROM " . OW_Utility::instance()->get_emails_table_name() . " WHERE history_id = %d", $history->ID ) );

            // hook to delete any other workflow related data from add-ons
            do_action( 'owf_when_post_trash_delete', $post_id, $history->ID );
         }
         $wpdb->get_results( $wpdb->prepare( "DELETE FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE post_id = %d", $post_id ) );
      }

      // when we trash the post, and it happens to be a revision, then only remmove "_oasis_current_revision" from the original post
      $original_post = get_post_meta( $post_id, '_oasis_original', true );
      $this->cleanup_after_workflow_complete( $post_id );
      // delete the post meta on original post which is holding this post_id as current revision
      delete_post_meta( $original_post, '_oasis_current_revision', $post_id );
   }

   public function pre_validate_claim( $validation_result, $action_history_id, $post_id, $user_id ) {
      global $wpdb;

      // sanitize data
      $action_history_id = intval( $action_history_id );
      $post_id = intval( $post_id );
      $user_id = intval( $user_id );
      $assigned_task_count = 0;

      $assigned_tasks = $this->get_assigned_post( null, $user_id );

      foreach( $assigned_tasks as $assigned_task ) {
         if( ! $this->check_for_claim( $assigned_task->ID) ) { // if this task is already claimed
            $assigned_task_count++;
         }
      }

      if ( $assigned_task_count >= 1 ) {
         $error_messages[] = __( "You cannot claim additional tasks, since you already have more than 2 assignments.", 'oasisworkflow' );
         $validation_result = array_merge( $validation_result, $error_messages );
      }

      return $validation_result;
   }

   /**
    * Hook - deleted_user
    * When user deleted, check if the user has any workflow tasks. If so, then
    * 1. If the post has only one assignee (deleted user) then abort the workflow
    * 2. If the post has multiple assignee then delete the task for deleted user
    * @global type $wpdb
    * @param int $deleted_user_id
    * @since 3.8
    */
   public function purge_user_assignments( $deleted_user_id ) {
      global $wpdb;

      // get the current tasks for the deleted user
      $inbox_items = $this->get_assigned_post( null, $deleted_user_id ) ;
      $count_posts = count( $inbox_items );
      if ( $count_posts == 0 ) { //the deleted user doesn't seem to have any pending tasks
         return;
      }

      /*
       * Loop through each task and find if there are additional users assigned to the same task for the given post
       * If the deleted user is the only user who is assigned this task, then abort the workflow
       * If there are more users assigned to this task, delete the task assigned to the deleted user
       */
      foreach ( $inbox_items as $inbox_item ) {
         $post_id = $inbox_item->post_id;
         $step_id = $inbox_item->step_id;
         $is_multi_user_task = false;
         // get assigned tasks for the given post
         $step_users = $this->get_users_in_step( $step_id, $post_id ) ;
         if( $step_users && $step_users["users"] && ! empty( $step_users["users"] ) ) {
            foreach ( $step_users["users"] as $step_user ) {
               if ( $step_user->ID != $deleted_user_id ) { // only find tasks which are not assigned to the deleted user
                  $user_tasks = $this->get_assigned_post( $post_id, $step_user->ID ) ;
                  if( count( $user_tasks ) > 0 ) { // looks like there are more users who are assigned this task for the given post (review process  may be)
                     $is_multi_user_task = true;

                     // delete the task
                     $wpdb->delete( OW_Utility::instance()->get_action_table_name(), array(
                        'action_history_id' => $inbox_item->ID, // action_history_id
                        'actor_id'          => $deleted_user_id
                     ));

                     // delete records from the history table - this will generally be unclaimed tasks.
                     $wpdb->delete( OW_Utility::instance()->get_action_history_table_name(), array(
                        'ID'              => $inbox_item->ID, // action_history_id
                        'assign_actor_id' => $deleted_user_id
                     ));
                  }
               }
            }
         }

         if ( ! $is_multi_user_task ) {
            // looks like the deleted user is the only user who has the task assigned for this post
            $this->abort_the_workflow( $inbox_item->ID );
         }
      }
   }

   /**
    * Hook - save_post
    * Called after the step or workflow is completed
    */
   public function check_unauthorized_post_update( $post_id ) {
      global $wpdb;

      $post_id = intval( $post_id );
      $is_in_workflow = get_post_meta( $post_id, '_oasis_is_in_workflow', true );

      $ow_history_service = new OW_History_Service();

      // if in workflow and all assignments are completed then call cleanup_after_workflow_complete function
      if ( ! empty( $is_in_workflow ) && $is_in_workflow == 1 ) {
         $workflow_assignment_results = $wpdb->get_results( $wpdb->prepare( "SELECT A.*, B.review_status, B.actor_id, B.next_assign_actors, B.step_id as review_step_id, B.action_history_id, B.update_datetime FROM
							(SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE action_status = 'assignment') as A
							LEFT OUTER JOIN
							(SELECT * FROM " . OW_Utility::instance()->get_action_table_name() . " WHERE review_status = 'assignment') as B
							ON A.ID = B.action_history_id WHERE post_id = %d", $post_id ) );

         $ow_email = new OW_Email( );
         if ( count( $workflow_assignment_results ) > 0 ) {
            $can_update = 0;
            $create_datetime = new DateTime( $workflow_assignment_results[0]->create_datetime );
            $current_datetime = new DateTime( current_time( 'mysql' ) );
            $diff = $current_datetime->diff( $create_datetime );
            // essentially this method will be called after the user has signed off, so the best way to check if this was not part of sign off is to find the time elapsed
            // more than 2 minutes have passed
            $assignee_arr = array();

            if ( $diff->h > 0 || $diff->i > 1 ) {
               foreach ( $workflow_assignment_results as $assignment ) {
                  $history_details = $ow_history_service->get_action_history_by_id( $assignment->ID );
                  if ( $history_details->assign_actor_id == -1 ) { // review process
                     $action_details = $ow_history_service->get_review_action_by_history_id( $assignment->ID );
                     foreach ( $action_details as $action ) {
                        if ( ! in_array( $action->actor_id, $assignee_arr ) ) {
                           array_push( $assignee_arr, $action->actor_id );
                        }
                     }
                  } else { //assignment or publish process
                     if ( ! in_array( $history_details->assign_actor_id, $assignee_arr ) ) {
                        array_push( $assignee_arr, $history_details->assign_actor_id );
                     }
                  }
               }
               if ( in_array( get_current_user_id(), $assignee_arr ) ) {
                  $can_update = 1;
               }
               if ( $can_update == 0 ) {                  
                  $ow_email->notify_users_on_unauthorized_update( $post_id );
               }
            }
         }
      }
   }

   /**
    * Hook - redirect_post_location
    * Redirects the user to the inbox page after successful sign off
    * @param $url
    *
    * @return mixed
    */
   public function redirect_after_signoff( $url ) {
      if ( isset( $_POST['hi_oasiswf_redirect'] ) && $_POST['hi_oasiswf_redirect'] == 'step' ) {
         wp_redirect( admin_url( 'admin.php?page=oasiswf-inbox' ) );
         die();
      }
      return $url;
   }

   /**
    * get all the users who have at least one post in their inbox
    *
    * @return mixed user list
    *
    * @since 2.0
    */
   public function get_assigned_users() {
      global $wpdb;

      $sql = "SELECT distinct USERS.ID, USERS.display_name FROM
		(SELECT U1.ID, U1.display_name FROM {$wpdb->users} AS U1
		LEFT JOIN " . OW_Utility::instance()->get_action_history_table_name() . " AS AH ON U1.ID = AH.assign_actor_id
		WHERE AH.action_status = 'assignment'
		UNION
		SELECT U2.ID, U2.display_name FROM {$wpdb->users} AS U2
		LEFT JOIN " . OW_Utility::instance()->get_action_table_name() . " AS A ON U2.ID = A.actor_id
					WHERE A.review_status = 'assignment') USERS
					ORDER BY USERS.DISPLAY_NAME ";

      $result = $wpdb->get_results( $sql );
      return $result;
   }

   /**
    * Return task count grouped by priority
    *
    * @param $selected_user user_id of the user
    *
    * @return array|null|object
    */
   public function get_task_count_by_priority( $user_id ) {
      global $wpdb;

      $user_id = intval( $user_id );

      $sql = "SELECT postmeta.meta_value AS priority,
                COUNT(*) as priority_count
                FROM " . OW_Utility::instance()->get_action_history_table_name() . " action_history
                LEFT OUTER JOIN " . OW_Utility::instance()->get_action_table_name() . " action
                  ON action_history.ID = action.action_history_id AND action.review_status = 'assignment'
                JOIN {$wpdb->postmeta} AS postmeta
                  ON postmeta.post_id = action_history.post_id
                  AND postmeta.meta_key = '_oasis_task_priority'
                AND action_history.action_status = 'assignment'
                AND ( action_history.assign_actor_id = %d OR action.actor_id = %d)
                GROUP BY priority ORDER BY priority DESC";


      $result = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $user_id ) );
      return $result;
   }

   /**
    * Return task count grouped by Due dates
    *
    * @param $selected_user user_id of the user
    *
    * @return array|null|object
    */
   public function get_task_count_by_due_date( $user_id ) {
      global $wpdb;

      $user_id = intval( $user_id );

      $sql = "SELECT action_history.due_date as date,
              COUNT(*) as row_count
              FROM " . OW_Utility::instance()->get_action_history_table_name() . " action_history
              LEFT OUTER JOIN " . OW_Utility::instance()->get_action_table_name() . " action
                 ON action_history.ID = action.action_history_id
                 AND action.review_status = 'assignment'
              WHERE action_history.action_status = 'assignment'
              AND ( assign_actor_id = %d OR actor_id = %d)
              GROUP BY date";


      $result = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $user_id ) );
      return $result;
   }


   /*
    * get the assigned posts to a particular user
    *
    * @param int|null $post_id
    * @param int|null $user_id
    * @param mixed $return_format it could be rows or just a single row
    *
    * @since 2.0
    */
   // TODO : change the function name to get_assigned_tasks
   public function get_assigned_post( $post_id = null, $user_id = null, $return_format = "rows" ) {
      global $wpdb;

      if ( ! empty( $post_id ) ) {
         $post_id = intval( $post_id );
      }

      if ( ! empty( $user_id ) ) {
         $user_id = intval( $user_id );
      }

      // use white list approach to set order by clause
      $order_by = array(
          'post_title' => 'post_title',
          'post_type' => 'post_type',
          'post_author' => 'post_author',
          'due_date' => 'due_date',
          'priority' => 'priority'
      );

      $sort_order = array(
          'asc' => 'ASC',
          'desc' => 'DESC',
      );

      // default order by
      $order_by_column = " ORDER BY A.due_date, posts.post_title"; // default order by column
      // if user provided any order by and order input, use that
      if ( isset( $_GET['orderby'] ) && $_GET['orderby'] ) {
         // sanitize data
         $user_provided_order_by = sanitize_text_field( $_GET['orderby'] );
         $user_provided_order = sanitize_text_field( $_GET['order'] );
         if ( array_key_exists( $user_provided_order_by, $order_by ) ) {
            $order_by_column = " ORDER BY " . $order_by[$user_provided_order_by] . " " . $sort_order[$user_provided_order];
         }
      }

      // added a left outer join to priority, since it may or may not be present.

      $sql = "SELECT A.*, postmeta.meta_value AS priority, B.review_status, B.actor_id,
      			B.next_assign_actors, B.step_id as review_step_id, B.action_history_id, B.update_datetime,
      			posts.post_title, users.display_name as post_author, posts.post_type
      			FROM " . OW_Utility::instance()->get_action_history_table_name() . " A
      			LEFT OUTER JOIN  " . OW_Utility::instance()->get_action_table_name() . " B ON A.ID = B.action_history_id
      			AND B.review_status = 'assignment'
				   JOIN {$wpdb->posts} AS posts ON posts.ID = A.post_id
					LEFT OUTER JOIN {$wpdb->postmeta} AS postmeta ON postmeta.post_id = A.post_id
					AND postmeta.meta_key = '_oasis_task_priority'
					LEFT JOIN {$wpdb->base_prefix}users AS users ON users.ID = posts.post_author
					WHERE 1 = 1 AND A.action_status = 'assignment' ";

      // generate the where clause and get the results
      if ( $post_id ) {
         $where_clause = "AND (assign_actor_id = %d OR actor_id = %d) AND A.post_id = %d " . $order_by_column;
         if ( $return_format == "rows" ) {
            $result = $wpdb->get_results( $wpdb->prepare( $sql . $where_clause, $user_id, $user_id, $post_id ) );
         } else {
            $result = $wpdb->get_row( $wpdb->prepare( $sql . $where_clause, $user_id, $user_id, $post_id ) );
         }
      } elseif ( isset( $user_id ) ) {
         $where_clause = "AND assign_actor_id = %d OR actor_id = %d  " . $order_by_column;
         if ( $return_format == "rows" ) {
            $result = $wpdb->get_results( $wpdb->prepare( $sql . $where_clause, $user_id, $user_id ) );
         } else {
            $result = $wpdb->get_row( $wpdb->prepare( $sql . $where_clause, $user_id, $user_id ) );
         }
      } else {
         $where_clause = $order_by_column;
         if ( $return_format == "rows" ) {
            $result = $wpdb->get_results( $sql . $where_clause );
         } else {
            $result = $wpdb->get_row( $sql . $where_clause );
         }
      }

      return $result;
   }

   /**
    * For a given user, get all the assigned tasks
    *
    * @param $user_id
    *
    * @return $assigned_tasks array
    * @since 4.6
    *
    */
   public function get_assigned_task_count( $user_id ) {
      global $wpdb;

      $user_id = intval( $user_id );

      $assigned_tasks = $wpdb->get_var(
         $wpdb->prepare( "SELECT count(1) FROM " . OW_Utility::instance()->get_action_history_table_name() . " AH
                 LEFT OUTER JOIN " . OW_Utility::instance()->get_action_table_name() . " A
                 ON AH.ID = A.action_history_id
                 AND A.review_status = 'assignment'
                 JOIN {$wpdb->posts} AS posts ON posts.ID = AH.post_id
                 WHERE AH.action_status = 'assignment'
                 AND (AH.assign_actor_id = %d OR A.actor_id = %d)",
            $user_id, $user_id ) );

      return $assigned_tasks;
   }


   /*
    * Get count of assigned tasks
    *
    * @return int count of assigned tasks for the current user OR the passed in user.
    *
    * @since 2.0
    */
   public function get_assigned_post_count() {
      $selected_user = isset( $_GET['user'] ) ? intval( $_GET["user"] ) : get_current_user_id();

      $assigned_tasks = $this->get_assigned_task_count( $selected_user );

      return $assigned_tasks;
   }

   /**
    * Get all post_ids that are currently in a workflow.
    *
    * @return mixed array of post_ids
    *
    * @since 2.0 initial version
    */
   public function get_all_assigned_posts() {
      global $wpdb;

      // try to get it from cache
      $cache_key = md5( 'ow_assigned_post_count_' . get_current_user_id() );
      $post_id_array = wp_cache_get( $cache_key, 'ow-cache-workflows' );

      if ( $post_id_array === false ) {
         // anything which the action_status of "assignment" is currently in workflow and assigned.
         $sql = "SELECT DISTINCT(action_history.post_id) as post_id FROM
                        (SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE action_status = 'assignment') as action_history
                        LEFT OUTER JOIN
                        (SELECT * FROM " . OW_Utility::instance()->get_action_table_name() . " WHERE review_status = 'assignment') as review_history
                        ON action_history.ID = review_history.action_history_id order by action_history.due_date";

         // create a post_id array from the result set
         $assign_posts = $wpdb->get_results( $sql );
         if ( $assign_posts ) {
            foreach ( $assign_posts as $post ) {
               $post_id_array[] = $post->post_id;
            }
         }

         // non existent cache, so, lets add/set it to cache
         wp_cache_set( $cache_key, $post_id_array, 'ow-cache-workflows' );
      }

      return $post_id_array;
   }

   /**
    * Filter the Posts List to only show posts/pages the user has access to
    * 1. Workflow Assigned Posts
    * 2. Newly created Posts
    * 3. Published posts - which are not in any workflow.
    *
    * @param $query
    */
   public function show_only_accessible_posts( $query ) {
      global $wpdb, $current_screen;
      if ( is_admin() ) {
         
         // Show Accessible post by roles
         $current_user_id = get_current_user_id();
         $current_user_role = OW_Utility::instance()->get_user_role( $current_user_id );

         $roles = array();

         $user_roles = apply_filters( 'owf_show_only_accessible_posts', $roles );
         
         
         if ( in_array( $current_user_role, $user_roles ) ) {            

            /*
             * Get all the assigned posts
             * Union
             * Get all the posts which have "oasis_is_in_workflow" = 0, basically posts which are not in workflow
             * Union
             * Get all posts which are newly created, basically anything which do not have oasis_is_in_workflow metakey
             */

            $sql = "SELECT A.post_id as available_post_id FROM " . OW_Utility::instance()->get_action_history_table_name() . " A
               LEFT OUTER JOIN ". OW_Utility::instance()->get_action_table_name() . " B ON A.ID = B.action_history_id
                  AND B.review_status = 'assignment'
                  WHERE  A.action_status = 'assignment'
                  AND ( A.assign_actor_id = %d OR B.actor_id = %d)
                  UNION
                  SELECT posts.ID as available_post_id FROM {$wpdb->posts} posts
                  JOIN {$wpdb->postmeta} postmeta ON postmeta.post_id = posts.ID
                  AND postmeta.meta_key = '_oasis_is_in_workflow'
                  AND postmeta.meta_value = '0'
                  UNION
                  SELECT posts.ID as available_post_id FROM {$wpdb->posts} posts
                  WHERE posts.ID NOT IN ( SELECT post_id from {$wpdb->postmeta} postmeta
                  WHERE postmeta.meta_key = '_oasis_is_in_workflow' )";

            $results = $wpdb->get_results( $wpdb->prepare( $sql, get_current_user_id(), get_current_user_id() ) );

            $accessible_posts = array(0);
            if ( $results ) {
               foreach ( $results as $result ) {
                  array_push( $accessible_posts, $result->available_post_id );
               }
            }

            $query->set( 'post__in', $accessible_posts );
            
            // now modify the post count on a given screen with the above results.
            if (isset( $current_screen ) ) {
               add_filter( "views_$current_screen->id", array( $this, 'owf_adjust_post_count' ), 10, 1 );
            }
         }
      }
   }

   /**
    * Modify the post count of user can accessible the post
    *
    * @global object $wpdb
    * @global object $current_screen WP_Screen Object
    * @param array $views
    * @return array
    *
    * @since 4.1
    */
   public function owf_adjust_post_count( $views ) {
      global $wpdb, $current_screen;
      $current_post_type = $current_screen->post_type;

      // get post statuses from given $views array
      $post_statuses = array_keys( $views );

      // get all post statuses and its count for given post type
      $sql = "SELECT post_status, COUNT(*) post_count
                FROM $wpdb->posts
                WHERE post_status
                IN ( '".  implode( "','", $post_statuses )."' )
                AND post_type = '$current_post_type'
                GROUP BY post_status";

      $post_count_array = $wpdb->get_results( $sql, OBJECT_K );

      if ( $post_statuses ) {
         foreach ( $post_statuses as $post_status ) {
            if ( isset( $post_count_array[$post_status] ) ) {
               $post_count_array[ $post_status ]->post_count = 0;
            }
         }
      }

      // get accessible posts
      $sql = "SELECT A.post_id as available_post_id FROM " . OW_Utility::instance()->get_action_history_table_name() . " A
            LEFT OUTER JOIN ". OW_Utility::instance()->get_action_table_name() . " B ON A.ID = B.action_history_id
               AND B.review_status = 'assignment'
               WHERE  A.action_status = 'assignment'
               AND ( A.assign_actor_id = %d OR B.actor_id = %d)
               UNION
               SELECT posts.ID as available_post_id FROM {$wpdb->posts} posts
               JOIN {$wpdb->postmeta} postmeta ON postmeta.post_id = posts.ID
               AND postmeta.meta_key = '_oasis_is_in_workflow'
               AND postmeta.meta_value = '0'
               /* only include visible post statuses */
               AND posts.post_status IN ( '".  implode( "','", $post_statuses )."' )
               UNION
               SELECT posts.ID as available_post_id FROM {$wpdb->posts} posts
               WHERE posts.ID NOT IN ( SELECT post_id from {$wpdb->postmeta} postmeta
               WHERE postmeta.meta_key = '_oasis_is_in_workflow' )
               /* only include visible post statuses */
               AND posts.post_status IN ( '".  implode( "','", $post_statuses )."' )";

      $included_posts = $wpdb->get_results( $wpdb->prepare( $sql, get_current_user_id(), get_current_user_id() ) );

      $accessible_posts = array();
      if ( $included_posts ) {
         foreach ( $included_posts as $included_post ) {
            array_push( $accessible_posts, $included_post->available_post_id );
         }
      }

      $all_post_count = 0;

      // now lets check if $included_posts post ids exist in $post_count_array
      // if so, increment the count for the given post_status by 1
      // also keep incrementing the all_post_count by 1 for every post
      $updated_views = array();
      if ( $accessible_posts && $post_count_array) {
         foreach ( $accessible_posts as $post_id ) {
            $post_status = get_post_status( $post_id );
            if ( isset( $post_count_array[$post_status] ) ) {
               $post_count_array[$post_status]->post_count += 1;
               $updated_views[$post_status] = $post_count_array[$post_status]->post_count;
               $all_post_count += 1;
            }
         }
      }

      // if the $post_count_array shows the count as zero then remove it from the views
      // essentially, we do not want to show post_status(0) to the user.
      if ( $post_statuses ) {
         foreach ( $post_statuses as $post_status ) {
            if ( isset( $post_count_array[$post_status] ) ) {
               if ( $post_count_array[$post_status]->post_count < 1 && isset( $views[$post_status] ) ) {
                  unset( $views[$post_status] );
               }
            }
         }
      }

      // remove mine count from the views
      unset( $views["mine"] );
      $updated_views['all'] = $all_post_count;

      // step  3. now finally update the post count in $views array
      foreach ( $updated_views as $slug => $count ) {
         if ( isset( $views[ $slug ] ) ) {
            $views[ $slug ] = preg_replace( '/\(.+\)/U', '(' . $count . ')', $views[ $slug ] );
         }
      }

      return $views;
   }

   /**
    * Get the count for all the submitted articles.
    * @param string $post_type 
    * @return int $count
    * @since 4.8
    */
   public function get_submitted_article_count( $post_type = 'all', $team_filter ) {
      global $wpdb;
      
      // Sanitize incoming data
      $post_type = sanitize_text_field( $post_type );
      
      // get an array of all the assigned posts
      $assign_post_ids = $this->get_all_assigned_posts();
      $assign_post_ids = ( $assign_post_ids ) ? $assign_post_ids : array( -1 );
      $submitted_posts = null;
      
      // Filter Reports by team
      if ( $team_filter == -1 ) {
         $join = "";
         $where = "";
      } 
      if ( $team_filter == 0 ) {
         $join = "INNER JOIN $wpdb->postmeta AS postmeta ON postmeta.post_id = posts.ID AND postmeta.meta_key = '_oasis_is_in_team'";
      }
      if ( $team_filter !== 0 && $team_filter !== -1 ) {
         $join = "INNER JOIN $wpdb->postmeta AS postmeta ON postmeta.post_id = posts.ID AND postmeta.meta_key = '_oasis_is_in_team'";
         $where = "AND postmeta.meta_value = $team_filter";
      }

      // get post details
      if ( $post_type === "all" ) {
         $sql = "SELECT count(posts.ID) as post_id FROM " . $wpdb->posts .
                " as posts $join WHERE ID IN (" . implode( $assign_post_ids, "," ) . ") $where ";
         $submitted_posts = $wpdb->get_results( $sql );
      } else {
         $sql = "SELECT count(posts.ID) as post_id FROM " . $wpdb->posts .
                " as posts $join WHERE post_type = %s AND ID IN (" . implode( $assign_post_ids, "," ) . ") $where ";
         $submitted_posts = $wpdb->get_results( $wpdb->prepare( $sql, $post_type ) );
      }
      
       $count = $submitted_posts[0]->post_id;
       
      return $count;
   }

   /**
    * Get all the submitted articles.
    *
    * Get all the posts/pages/custom post types that are currently in a workflow.
    * It calls get_all_assigned_posts to get all assigned post_ids.
    * And then gets the details on those posts_ids.
    *
    * @param string $post_type specific post type otherwise "all"
    *
    * @return mixed array of posts
    *
    * @since 2.0 initial version
    */
   public function get_submitted_articles( $post_type = 'all', $team_filter, $page_number ) {
      global $wpdb;
      $offset = 0;
      $limit = OASIS_PER_PAGE;
      
      // Sanitize incoming data
      $post_type = sanitize_text_field( $post_type );       
      $page_number = intval( sanitize_text_field( $page_number ) );
      
      if ( $page_number !== 1 ) {
         $offset = $limit * ( $page_number - 1 );
      }
      
      // use white list approach to set order by clause
      $order_by = array(
          'post_title'  => 'post_title',
          'post_type'   => 'post_type',
          'post_author' => 'post_author',
          'post_date'   => 'post_date'
      );
      
      $sort_order = array(
          'asc'  => 'ASC',
          'desc' => 'DESC',
      );
      
      // default order by
      $order_by_column = " ORDER BY posts.post_title"; // default order by column
      // if user provided any order by and order input, use that
      if ( isset( $_GET['orderby'] ) && $_GET['orderby'] && $_GET['action'] == 'in-workflow' ) {
         // sanitize data
         $user_provided_order_by = sanitize_text_field( $_GET['orderby'] );
         $user_provided_order = sanitize_text_field( $_GET['order'] );
         if ( array_key_exists( $user_provided_order_by, $order_by ) ) {
            $order_by_column = " ORDER BY " . $order_by[$user_provided_order_by] . " " . $sort_order[$user_provided_order];
         }
      }

      // get an array of all the assigned posts
      $assign_post_ids = $this->get_all_assigned_posts();
      $assign_post_ids = ( $assign_post_ids ) ? $assign_post_ids : array( -1 );
      $submitted_posts = null;
     
      // Filter Reports by team
      if ( $team_filter == -1 ) {
         $join = "";
         $where = "";
      } 
      if ( $team_filter == 0 ) {
         $join = "INNER JOIN $wpdb->postmeta AS postmeta ON postmeta.post_id = posts.ID AND postmeta.meta_key = '_oasis_is_in_team'";
      }
      if ( $team_filter !== 0 && $team_filter !== -1 ) {
         $join = "INNER JOIN $wpdb->postmeta AS postmeta ON postmeta.post_id = posts.ID AND postmeta.meta_key = '_oasis_is_in_team'";
         $where = "AND postmeta.meta_value = $team_filter";
      }

      // get post details
      if ( $post_type === "all" ) {
         $sql = "SELECT posts.ID, posts.post_author, posts.post_title, posts.post_type, posts.post_date FROM " .
                $wpdb->posts . " as posts $join WHERE posts.ID IN (" . implode( $assign_post_ids, "," ) . ") $where ".
                $order_by_column . " LIMIT {$offset}, {$limit}";
         $submitted_posts = $wpdb->get_results( $sql );
      } else {
         $sql = "SELECT posts.ID, posts.post_author, posts.post_title, posts.post_type, posts.post_date FROM " .
                $wpdb->posts . " as posts $join WHERE posts.post_type = %s AND posts.ID IN (" . implode( $assign_post_ids, "," ) . ") $where".
                $order_by_column . " LIMIT {$offset}, {$limit}" ;
         $submitted_posts = $wpdb->get_results( $wpdb->prepare( $sql, $post_type ) );
      }

      return $submitted_posts;
   }
   
   /**
    * Get the count for all the unsubmitted articles.
    * @param string $post_type 
    * @return int $count
    * @since 4.8
    */
   public function get_unsubmitted_article_count( $post_type = 'all' ) {
      global $wpdb;
      $post_type = sanitize_text_field( $post_type );
      
      foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ) ) as $key => $status ) {
         if ( $status != 'publish' && $status != 'trash' ) { //not published
            $auto_submit_stati[$key] = "'" . esc_sql( $status ) . "'";
         }
      }
      $auto_submit_stati_list = join( ",", $auto_submit_stati );
      $unsubmitted_posts = null;

      // get all posts which are not published and are not in workflow
      if ( $post_type === "all" ) {
         $unsubmitted_posts = $wpdb->get_results( "SELECT COUNT( DISTINCT posts.ID) as post_id  FROM {$wpdb->prefix}posts posts
			WHERE posts.post_status in (" . $auto_submit_stati_list . ")
			AND
			(NOT EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta1 WHERE postmeta1.meta_key = '_oasis_is_in_workflow' and posts.ID = postmeta1.post_id) OR
			EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta2 WHERE postmeta2.meta_key = '_oasis_is_in_workflow' AND postmeta2.meta_value = '0' and posts.ID = postmeta2.post_id))" );
      } else {
         $sql = "SELECT COUNT( DISTINCT posts.ID) as post_id FROM {$wpdb->prefix}posts posts
			WHERE post_type = %s AND posts.post_status in (" . $auto_submit_stati_list . ")
			AND
			(NOT EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta1 WHERE postmeta1.meta_key = '_oasis_is_in_workflow' and posts.ID = postmeta1.post_id) OR
			EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta2 WHERE postmeta2.meta_key = '_oasis_is_in_workflow' AND postmeta2.meta_value = '0' and posts.ID = postmeta2.post_id))";

         $unsubmitted_posts = $wpdb->get_results( $wpdb->prepare( $sql, $post_type ) );
      }

      $count = $unsubmitted_posts[0]->post_id; 
 
      return $count;
   }

   /**
    * Get all the un-submitted articles.
    *
    * Get all the posts/pages/custom post types which are not published
    * and are not in any workflow.
    *
    * @param string $post_type specific post type otherwise "all"
    *
    * @return mixed array of posts
    * @since 2.0 initial version
    */

   public function get_unsubmitted_articles( $post_type = 'all', $page_number ) {
      global $wpdb;
      $offset = 0;
      $limit = OASIS_PER_PAGE;
      
      // Sanitize incoming data
      $post_type = sanitize_text_field( $post_type );       
      $page_number = intval( sanitize_text_field( $page_number ) );
      
      if ( $page_number !== 1 ) {
         $offset = $limit * ( $page_number - 1 );
      }
      
      // use white list approach to set order by clause
      $order_by = array(
          'post_title' => 'post_title',
          'post_type' => 'post_type',
          'post_author' => 'post_author',
          'post_date' => 'post_date'
      );

      $sort_order = array(
          'asc' => 'ASC',
          'desc' => 'DESC',
      );
      
      // default order by
      $order_by_column = " ORDER BY posts.post_title"; // default order by column
      // if user provided any order by and order input, use that
      if ( isset( $_GET['orderby'] ) && $_GET['orderby'] && $_GET['action'] !== 'in-workflow' ) {
         // sanitize data
         $user_provided_order_by = sanitize_text_field( $_GET['orderby'] );
         $user_provided_order = sanitize_text_field( $_GET['order'] );
         if ( array_key_exists( $user_provided_order_by, $order_by ) ) {
            $order_by_column = " ORDER BY " . $order_by[$user_provided_order_by] . " " . $sort_order[$user_provided_order];
         }
      }


      foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ) ) as $key => $status ) {
         if ( $status != 'publish' && $status != 'trash' ) { //not published
            $auto_submit_stati[$key] = "'" . esc_sql( $status ) . "'";
         }
      }
      $auto_submit_stati_list = join( ",", $auto_submit_stati );
      $unsubmitted_posts = null;

      // get all posts which are not published and are not in workflow
      if ( $post_type === "all" ) {
         $unsubmitted_posts = $wpdb->get_results( "SELECT distinct posts.ID, posts.post_author, posts.post_title, posts.post_type, posts.post_date FROM {$wpdb->prefix}posts posts
			WHERE posts.post_status in (" . $auto_submit_stati_list . ")
			AND
			(NOT EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta1 WHERE postmeta1.meta_key = '_oasis_is_in_workflow' and posts.ID = postmeta1.post_id) OR
			EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta2 WHERE postmeta2.meta_key = '_oasis_is_in_workflow' AND postmeta2.meta_value = '0' and posts.ID = postmeta2.post_id))".
                                                  $order_by_column . " LIMIT {$offset}, {$limit}" );
      } else {
         $sql = "SELECT distinct posts.ID, posts.post_author, posts.post_title, posts.post_type, posts.post_date FROM {$wpdb->prefix}posts posts
			WHERE post_type = %s AND posts.post_status in (" . $auto_submit_stati_list . ")
			AND
			(NOT EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta1 WHERE postmeta1.meta_key = '_oasis_is_in_workflow' and posts.ID = postmeta1.post_id) OR
			EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta2 WHERE postmeta2.meta_key = '_oasis_is_in_workflow' AND postmeta2.meta_value = '0' and posts.ID = postmeta2.post_id))".
                $order_by_column . " LIMIT {$offset}, {$limit}";

         $unsubmitted_posts = $wpdb->get_results( $wpdb->prepare( $sql, $post_type ) );
      }

      return $unsubmitted_posts;
   }

   /**
    * @deprecated
    *
    * get comments count
    *
    * @param int $action_history_id
    * @param boolean $is_inbox_comment - variable to show the comments blurb on the inbox
    * @param boolean|int $post_id - sign off comments for post_id
    *
    * @return int
    */
   public function get_comment_count( $action_history_id, $is_inbox_comment = FALSE, $post_id = FALSE ) {

      if ( ! empty( $action_history_id ) ) {
         $action_history_id = intval( $action_history_id );
      }

      if ( ! empty( $post_id ) ) {
         $post_id = intval( $post_id );
      }

      if ( ! empty( $is_inbox_comment ) ) {
         $is_inbox_comment = sanitize_text_field( $is_inbox_comment );
      }

      $i = 0;

      // in case of inbox comments, we need to count all the previous comments as well
      if ( $is_inbox_comment && $post_id > 0 ) {
         $action_history_ids = array();
         $review_step_action_history_ids = array();
         // get the comments from the assignment/publish steps
         $results = $this->get_assignment_comment_for_post( $post_id );
         if ( $results ) {
            foreach ( $results as $result ) {
               if( $result->action_status !== 'processed' && $result->assign_actor_id == -1 ) {
                  $review_step_action_history_ids[] = $result->ID;
               }
               $action_history_ids[] = $result->ID;
               if ( ! empty( $result->comment ) ) {
                  $comments = json_decode( $result->comment );
                  // Display comment count if comment index is not null
                  if ( ! empty( $comments[0]->comment ) ) {
                     $i = $i + count( $comments );
                  }
               }
            }
         }

         if ( ! empty( $review_step_action_history_ids ) ) {
            $results = $this->get_comments_for_review_steps( $review_step_action_history_ids );
            if ( $results ) {
               foreach ( $results as $result ) {
                  if ( ! empty( $result->comments ) ) {
                     $i++;
                  }
               }
            }
         }

         // hook to get contextual comments for the given post
         if ( has_filter( 'owf_get_contextual_comments_by_post_id' ) ) {
            $editorial_comments = apply_filters( 'owf_get_contextual_comments_by_post_id', $post_id );
            $i = $i + count( $editorial_comments );
         }

      } else { // non inbox page, could be history page
         $ow_history_service = new OW_History_Service();
         $action_history = $ow_history_service->get_action_history_by_id( $action_history_id );
         if ( $action_history ) {
            $comments = json_decode( $action_history->comment );
            if ( $comments ) {
               foreach ( $comments as $comment ) {
                  if ( $comment->comment ) {
                     $i++;
                  }
               }
            }

            // hook to get contextual comments for the given history
            if ( has_filter( 'owf_get_contextual_comments_by_history_id' ) ) {
               $editorial_comments = apply_filters( 'owf_get_contextual_comments_by_history_id', $action_history_id );
               $i = $i + count( $editorial_comments );
            }
         }
      }

      return $i;
   }

   /**
    * Get comments count by post_id
    *
    * @param $post_id post_id
    *
    * @return int
    */
   public function get_sign_off_comments_count_by_post_id( $post_id ) {
      $post_id = intval( $post_id );

      $i = 0;

      $action_history_ids = array();
      $review_step_action_history_ids = array();
      // get the comments from the assignment/publish steps
      $results = $this->get_assignment_comment_for_post( $post_id );
      if ( $results ) {
         foreach ( $results as $result ) {
            if( $result->action_status !== 'processed' && $result->assign_actor_id == -1 ) {
               $review_step_action_history_ids[] = $result->ID;
            }
            $action_history_ids[] = $result->ID;
            if ( ! empty( $result->comment ) ) {
               $comments = json_decode( $result->comment );
               // Display comment count if comment index is not null
               foreach( $comments as $comment ) {
                  if ( ! empty( $comment->comment ) ) {
                     $i++;
                  }

                  // hook to get contextual comments for the given post
                  // in this case, use the correct history_id
                  if ( has_filter( 'owf_get_contextual_comments_by_post_id' ) ) {
                     $editorial_comments = apply_filters( 'owf_get_contextual_comments_by_post_id', $result->ID, $post_id, $comment->send_id );
                     $i = $i + count( $editorial_comments );
                  }
               }
            }
         }

         if ( ! empty( $review_step_action_history_ids ) ) {
            $results = $this->get_comments_for_review_steps( $review_step_action_history_ids );
            if ( $results ) {
               foreach ( $results as $result ) {
                  if ( ! empty( $result->comments ) ) {
                     $comments = json_decode( $result->comments );
                     foreach( $comments as $comment ) {
                        if ( ! empty( $comment->comment ) ) {
                           $i++;
                        }

                        // hook to get contextual comments for the given post,
                        // in this case, the history_id will be zero
                        if ( has_filter( 'owf_get_contextual_comments_by_post_id' ) ) {
                           $editorial_comments = apply_filters( 'owf_get_contextual_comments_by_post_id', 0, $post_id, $comment->send_id );
                           $i = $i + count( $editorial_comments );
                        }
                     }
                  }
               }
            }
         }
      }

      return $i;
   }

   /**
    * get comments count by history_id
    *
    * @param int $action_history_id
    *
    * @return int
    */
   public function get_sign_off_comments_count_by_history_id( $action_history_id ) {

      $action_history_id = intval( $action_history_id );

      $i = 0;

      $ow_history_service = new OW_History_Service();
      $action_history = $ow_history_service->get_action_history_by_id( $action_history_id );
      if ( $action_history ) {
         $comments = json_decode( $action_history->comment );
         if ( $comments ) {
            foreach ( $comments as $comment ) {
               if ( $comment->comment ) {
                  $i++;
               }
            }
         }

         // hook to get contextual comments for the given history
         if ( has_filter( 'owf_get_contextual_comments_by_history_id' ) ) {
            $editorial_comments = apply_filters( 'owf_get_contextual_comments_by_history_id', $action_history_id );
            $i = $i + count( $editorial_comments );
         }
      }

      return $i;
   }

   /**
    * get the comments for assignment/publish steps
    *
    * @param int $post_id - post_id for which to get the sign off comments
    * @return mixed comments
    */
   public function get_assignment_comment_for_post( $post_id ) {
      global $wpdb;

      // sanitize the data
      $post_id = intval( $post_id );

      $table = OW_Utility::instance()->get_action_history_table_name();
      $sql = "SELECT ID, comment, action_status, assign_actor_id  FROM $table
			WHERE post_id = '%d'
			AND action_status NOT IN ('submitted', 'claimed', 'claim_cancel')
			GROUP BY from_id
      	ORDER BY ID DESC";

      return $wpdb->get_results( $wpdb->prepare( $sql, $post_id ) );
   }


   /**
    * Get sign off comments in case of review step
    *
    * @param array $review_step_action_history_ids
    *
    * @return array|null|object
    *
    * @since 4.0
    */
   public function get_comments_for_review_steps( $review_step_action_history_ids ) {
      global $wpdb;
      
      // sanitize the values
      $review_step_action_history_ids = array_map( 'intval', $review_step_action_history_ids );
      
      $table = OW_Utility::instance()->get_action_table_name();

      $imploded_action_history_ids = implode( ",", $review_step_action_history_ids );
      $action_history_condition = "action_history_id IN (" . $imploded_action_history_ids . ")";
      $sql = "SELECT *  FROM $table " .
		    " WHERE review_status IN ('complete', 'unable','reassigned') " .
		    " AND " . $action_history_condition;

      return $wpdb->get_results( $sql );
   }

   /**
    * If there are more than one user assigned to the same task as part of "assignment" or "publish" step
    * We need to show the "Claim" link.
    * @param int $action_history_id
    * @return boolean true, if claim is required, false if not.
    */
   public function check_for_claim( $action_history_id ) {
      global $wpdb;

      // sanitize the data
      $action_history_id = intval( $action_history_id );

      $ow_history_service = new OW_History_Service();
      $action_history = $ow_history_service->get_action_history_by_id( $action_history_id );
      $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() .
                      " WHERE action_status = 'assignment' AND post_id = %d", $action_history->post_id ) );

      if ( count( $rows ) > 1 ) { // more than one rows of assignment, return true
         return true;
      }
      return false; // looks like there is only one assignment task, so no "Claim" button needed.
   }



   /*
    * get all the posts in all the workflow
    *
    * @return mixed array of all the post with post_id and post_title
    *
    * @since 2.0
    */
   public function get_posts_in_all_workflow() {
      global $wpdb;
      $sql = "SELECT DISTINCT(A.post_id) as post_id , B.post_title as title
				  	FROM " . OW_Utility::instance()->get_action_history_table_name() . " AS A
					LEFT JOIN
					{$wpdb->posts} AS B
					ON  A.post_id = B.ID
					GROUP BY B.post_title";

      $result = $wpdb->get_results( $sql );
      return $result;
   }

   /*
    * from the given action history, return the sign off date
    * @param mixed $action_history_row - action history row
    * @return string sign off date
    *
    * @since 2.0
    */
   public function get_sign_off_date( $action_history_row ) {
      if ( $action_history_row->action_status == "complete" || $action_history_row->action_status == "submitted" ||
              $action_history_row->action_status == "aborted" || $action_history_row->action_status == "abort_no_action" ) {
         return isset( $action_history_row->create_datetime ) ? $action_history_row->create_datetime :  "";
      }

      if ( $action_history_row->action_status == "claim_cancel" ) {
         $ow_history_service = new OW_History_Service();

         $workflow_history_params = array (
            "post_id"               => $action_history_row->post_id,
            "step_id"               => $action_history_row->step_id,
            "from_history_id"       => $action_history_row->from_id,
            "action_status"         => "claimed"
         );

         $claimed_row = $ow_history_service->get_action_history_by_parameters( $workflow_history_params );
         return isset( $claimed_row->create_datetime ) ? $claimed_row->create_datetime  : "";
      }

      $ow_history_service = new OW_History_Service();
      $action = $ow_history_service->get_action_history_by_from_id( $action_history_row->ID );
      if ( $action ) {
         return isset( $action->create_datetime ) ? $action->create_datetime : "";
      }
   }

   /*
    * from the given action history, return the sign off status
    * @param mixed $action_history_row - action history row
    * @return string sign off status
    *
    * @since 2.0
    */
   public function get_sign_off_status( $action_history_row ) {
      if ( $action_history_row->action_status == "submitted" ) {
         return __( "Submitted", "oasisworkflow" );
      }
      if ( $action_history_row->action_status == "aborted" ) {
         return __( "Aborted", "oasisworkflow" );
      }   
      if ( $action_history_row->action_status == "abort_no_action" ) {
         return __( "No Action Taken", "oasisworkflow" );
      }   
      if ( $action_history_row->action_status == "claim_cancel" ) {
         return __( "Unclaimed", "oasisworkflow" );
      }   
      if ( $action_history_row->action_status == "claimed" ) {
         return __( "Claimed", "oasisworkflow" );
      }   
      if ( $action_history_row->action_status == "reassigned" ) {
         return __( "Reassigned", "oasisworkflow" );
      }   

      // from the next history record determine the status of the workflow
      $ow_history_service = new OW_History_Service();
      $next_history_record = $ow_history_service->get_action_history_by_from_id( $action_history_row->ID );
      if ( ! $next_history_record ) {
         return ""; // this is the latest step, so this step is not yet completed.
      }
      if ( $next_history_record->action_status == "complete" ) {
         return __( "Workflow completed", "oasisworkflow" );
      }
      if ( $next_history_record->action_status == "cancelled" ) {
         return __( "Cancelled", "oasisworkflow" );
      }   
      $step_info = json_decode( $action_history_row->step_info );
      $process = "";
      if ( ! empty( $step_info ) ) {
         $process = $step_info->process;
      }

      $workflow_service = new OW_Workflow_Service();
      $from_step = $action_history_row->step_id;
      $to_step = $next_history_record->step_id;
      $process_outcome = $workflow_service->get_process_outcome( $from_step, $to_step );

      if ( $process == "review" ) {
         if ( $process_outcome == "success" ) {
            return __( "Approved", "oasisworkflow" );
         }
         if ( $process_outcome == "failure" ) {
            return __( "Rejected", "oasisworkflow" );
         }   
      }
      
      if ( $process_outcome == "success" ) {
         return __( "Completed", "oasisworkflow" );
      }
      if ( $process_outcome == "failure" ) {
         return __( "Unable to Complete", "oasisworkflow" );
      }
   }

   /**
    * from the given review action history, return the sign off status
    * @param mixed $action_history_row - action history row
    * @return string sign off status
    *
    * @since 2.0
    */
   public function get_review_sign_off_status( $action_history_row, $review_row ) {
      if ( $review_row->review_status == "reassigned" ) {
         return __( "Reassigned", "oasisworkflow" );
      }
      $from_step = $action_history_row->step_id;
      $to_step = $review_row->step_id;
      if ( ! ($from_step && $to_step ) ) {
         return "";
      }
      $step_info = json_decode( $action_history_row->step_info );
      $process = "";
      if ( ! empty( $step_info ) ) {
         $process = $step_info->process;
      }

      $workflow_service = new OW_Workflow_Service();
      $process_outcome = $workflow_service->get_process_outcome( $from_step, $to_step );

      if ( $process == "review" ) {
         if ( $process_outcome == "success" ) {
            return __( "Approved", "oasisworkflow" );
         }
         if ( $process_outcome == "failure" ) {
            return __( "Rejected", "oasisworkflow" );
         }
      }
      if ( $process_outcome == "success" ) {
         return __( "Complete", "oasisworkflow" );
      }
      if ( $process_outcome == "failure" ) {
         return __( "Unable to Complete", "oasisworkflow" );
      }
   }

   /**
    * from the given action history, return the sign off status for the next history record
    * @param mixed $action_history_row - action history row
    * @return string sign off status
    *
    * @since 2.0
    */
   public function get_next_step_sign_off_status( $action_history_row ) {

      $ow_history_service = new OW_History_Service();
      $next_history_object = $ow_history_service->get_action_history_by_from_id( $action_history_row->ID );
      if ( ! $next_history_object ) {
         return "";
      } else {
         return $next_history_object->action_status;
      }
   }

   /**
    * for a given history row (sign off step), get the comments count
    *
    * @param mixed $action_history_row - action history row
    * @return int count of comments
    *
    * @since 2.0
    */
   public function get_sign_off_comment_count( $action_history_row ) {
      if ( $action_history_row->action_status == "claimed" ||
              $action_history_row->action_status == "claim_cancel" ||
              $action_history_row->action_status == "complete" || $action_history_row->action_status == "abort_no_action" ) {
         return "0";
      }
      $ow_history_service = new OW_History_Service();
      
      if ( $action_history_row->action_status == "aborted" ) {
         // Get comment count for the post aborted
         return $this->get_sign_off_comments_count_by_history_id( $action_history_row->ID );
      } else {      
         $next_history_object = $ow_history_service->get_action_history_by_from_id( $action_history_row->ID );
         if ( is_object( $next_history_object ) ) {
            return $this->get_sign_off_comments_count_by_history_id( $next_history_object->ID );
         } else {
            return 0; // no comments found
         }
      }
   }

   /**
    * for a given review history row (sign off step), get the comments count
    *
    * @param $review_row
    * @param $post_id
    * @param $user_id
    *
    * @return int
    *
    * @since 2.0
    */
   public function get_review_sign_off_comment_count( $review_row, $post_id, $user_id ) {
      $i = 0;

      if ( $review_row ) {
         $comments = json_decode( $review_row->comments );
         if ( $comments ) {
            foreach ( $comments as $comment ) {
               if ( isset( $comment->comment ) && ( ! empty( $comment->comment ) ) )
                  $i++;
            }
         }
         
         // hook to get contextual comments for the given history
         if ( has_filter( 'owf_get_contextual_comments_by_post_id' ) &&
              ( $review_row->review_status == 'complete' ||
                $review_row->review_status == 'unable' ||
                $review_row->review_status == 'reassigned' ) ) {
            $ow_history_service = new OW_History_Service();
            if ( ! empty( $review_row->action_history_id ) ) {
               $editorial_comments = array();
               $next_history_object = $ow_history_service->get_action_history_by_from_id( $review_row->action_history_id );
               if ( ! empty ($next_history_object ) ) {
                  $editorial_comments = apply_filters( 'owf_get_contextual_comments_by_post_id', $next_history_object->ID, $post_id, $user_id );
               }

               if ( empty ( $editorial_comments ) ) { // may be the step is not completely signed off yet, so pass the history_id as zero
                  $editorial_comments = apply_filters( 'owf_get_contextual_comments_by_post_id', 0, $post_id, $user_id );
               }
            }

            $i = $i + count( $editorial_comments );
         }
      }
      return $i;
   }
   
   /**
    * for a given history and review history row (sign off step), get the pre publish checklist count
    *
    * @param mixed $action_history_row - action history row
    * @return int count of selected conditions
    *
    * @since 4.2
    */
   public function get_checklist_count_by_history( $action_history_row ) {

      $history_meta = json_decode($action_history_row->history_meta) ;
      if ( isset( $history_meta->pre_publish_checklist ) ){
         $pre_publish_checklist = $history_meta->pre_publish_checklist;
         $checklist_questions = count ( explode( ',', $pre_publish_checklist->checklist_questions ) );
         return $checklist_questions;
      } else {
         return "0";
      }
   }

   /**
    * Get all comments related to specific  posts or users
    *
    * @param ID $action_id (could be review action id OR history action id)
    * @param string $page_action history or inbox or something else
    * @return mixed comments array
    * @since 2.0
    */
   public function get_sign_off_comments( $action_id, $page_action = "" ) {
      $action_id = intval( $action_id );

      $ow_history_service = new OW_History_Service();
      $action = $ow_history_service->get_action_history_by_id( $action_id );
      $comments = array();
      $content = "";

      if ( $action && $action->comment != "" ) {
         if ( $action->action_status != "claimed" &&
                 $action->action_status != "claim_cancel" ) {// no comments needed for claimed and claim cancel actions
            $comments = json_decode( $action->comment );
         }
      }

      if ( $action && $action->create_datetime != "" ) {
         $sign_off_date = $action->create_datetime;
      } else {
         $sign_off_date = "";
      }

      $review_rows = $ow_history_service->get_review_action_by_history_id( $action_id, "update_datetime" );
      if ( $review_rows ) {
         foreach ( $review_rows as $review_row ) {
            if ( $review_row->review_status == 'reassigned' ) {
               if ( ! empty( $review_row->comments ) ) {
                  $comments = json_decode( $review_row->comments ); // get the latest comment
                  break;
               }
            }
         }
      }

      if ( $page_action != "" && $page_action == "history" ) {
         $action = $ow_history_service->get_action_history_by_from_id( $action_id );
         if ( $action ) {
            if ( $action->comment != "" ) {
               if ( $action->action_status != "claimed" &&
                       $action->action_status != "claim_cancel" ) {// no comments needed for claimed and claim cancel actions
                  $comments = json_decode( $action->comment );
               }
            }
         }
      }

      if ( $page_action != "" && $page_action == "review" ) {
         $sign_off_date = "";
         $action = $ow_history_service->get_review_action_by_id( $action_id );
         if ( $action ) {
            $comments = json_decode( $action->comments );
            $sign_off_date = $action->update_datetime;
         }
      }

      foreach ( $comments as $key => $comment ) {
         if ( $comment->send_id == "System" ) {
            $lbl = "System";
         } else {
            $lbl = OW_Utility::instance()->get_user_name( $comment->send_id );
         }

         //return only comments exclude user and date
         if ( $key >= 0 ) {
            $content .= nl2br( $comment->comment );
         } else {
            $content .= nl2br( $comment->comment ) . "\t";
         }
      }
      return $content;
   }

   /**
    * Get a map of action history and related comments, action_history_id is the key while comments is the value.
    * comments can be an array too.
    */
   public function get_sign_off_comments_map( $post_id ) {
   	global $wpdb;
   	$post_id = intval( $post_id );

   	$comments_map = array();
   	$action_history_ids = array();
      $review_step_action_history_ids = array();
   	// get the comments for the post from the action_history
   	$results = $this->get_assignment_comment_for_post( $post_id );
   	if ( $results ) {
   		foreach ( $results as $result ) {
            if ( $result->action_status !== 'processed' && $result->assign_actor_id == -1 ) {
               $review_step_action_history_ids[] = $result->ID;
            }
   			$action_history_ids[] = $result->ID;
   			$comments = "";
   			if ( ! empty( $result->comment ) ) {
   				$comments = json_decode( $result->comment );
   			}

   			$comments_map[$result->ID] = $comments;
   		}
   	}
      
      if ( ! empty( $review_step_action_history_ids ) ) {
   		$results = $this->get_comments_for_review_steps( $review_step_action_history_ids );
   		if ( $results ) {
   			foreach ( $results as $result ) {
   				$comments = "";
   				if ( ! empty( $result->comments ) ) {
   					$comments = json_decode( $result->comments );
   				}

   				// add to the existing array
   				if ( array_key_exists( $result->action_history_id, $comments_map ) ) {
   					array_unshift( $comments_map[$result->action_history_id], $comments[0] );
   				}
   			}
   		}
   	}

   	return $comments_map;

   }

   /**
    * See whether it's a submit to workflow or make revision
    *
    * @since 2.0
    */
   public function workflow_submit_check( $selected_user ) {

      $page_var = isset( $_GET['page'] ) ? sanitize_text_field( $_GET["page"] ) : "";
      $selected_user = intval( sanitize_text_field( $selected_user ) );
      $post_id = "";
      if ( isset( $_GET['post'] ) ) {
         $post_id = intval( $_GET["post"] );
      }

      $ow_history_service = new OW_History_Service();

      //inbox
      $page_var = isset( $_GET['page'] ) ? sanitize_text_field( $_GET["page"] ) : "";
      if ( $page_var == 'oasiswf-inbox' ) {
         return "inbox";
      }

      // submit to workflow OR make revision
      if ( is_array( $post_id ) ) {//looks like the user is performing a bulk action, and hence we need not load the workflow javascripts
         return false;
      }
      $current_tasks = $ow_history_service->get_action_history_by_status( "assignment", $post_id );
      if ( ! empty( $post_id ) ) {
         $post_status = get_post_status( $post_id );
         if ( ($post_status == "publish" ||
                 $post_status == "future" ||
                 $post_status == "private") && count( $current_tasks ) == 0 ) {
            return "makerevision";
         }
      }

      if ( count( $current_tasks ) == 0 ) {
         return "submit";
      }

      // sign off
      if ( ! empty( $post_id ) && isset( $_GET['action'] ) && sanitize_text_field( $_GET["action"] ) == "edit" ) {

         $row = $this->get_assigned_post( $post_id, $selected_user, "row" );
         if ( $row ) {
            return $row->ID;
         }
      }

      return "not-assigned";
   }

   /**
    * Get users in step
    * @param int $step_id
    * @param int|null $post_id
    * @return mixed users and processes in the step
    */
   public function get_users_in_step( $step_id, $post_id = null ) {
      if ( $step_id == "nodefine" ) {
         return null;
      }

      $workflow_service = new OW_Workflow_Service();

      $users_and_process_info = null;
      $wf_info = $workflow_service->get_step_by_id( $step_id );
      if ( $wf_info ) {
         $step_info = json_decode( $wf_info->step_info );

         // lets check if task_assignee is set on step_info object
         $role_users = $users = $task_users = array();
         $temp_task_users = array(); // temporary array only used for finding unique users

         $task_assignee = '';
         if ( isset( $step_info->task_assignee ) && ! empty( $step_info->task_assignee ) ) {
            $task_assignee = $step_info->task_assignee;
         }

         if ( ! empty( $task_assignee ) ) {
            if ( isset( $task_assignee->roles ) && ! empty( $task_assignee->roles ) ) {
               $role_users = OW_Utility::instance()->get_step_users( $task_assignee->roles, $post_id, 'roles' );
            }

            // users
            if ( isset( $task_assignee->users ) && ! empty( $task_assignee->users ) ) {
               $users = OW_Utility::instance()->get_step_users( $task_assignee->users, $post_id, 'users' );
            }
         }

         $users = (object) array_merge( (array) $role_users, (array) $users );
         $args = array( $users, $task_assignee );

         do_action_ref_array( 'owf_get_group_users', array( &$args ) );

         $users = $args[0];

         // find unique users only, remove duplicates
         foreach ( $users as $task_user ) {
         	if ( ! array_key_exists ( $task_user->ID, $temp_task_users ) ) {
         		$temp_task_users[$task_user->ID] = $task_user; //temp_task_users is only used to compare key
         		$task_users[] = $task_user;
         	}
         }

         if ( $task_users ) {
            $users_and_process_info["users"] = $task_users;
            $users_and_process_info["process"] = $step_info->process;
            $users_and_process_info["assign_to_all"] = isset( $step_info->assign_to_all ) ? $step_info->assign_to_all : 0;
         }
      }

      // allow developers to filter users in the step
      $users_and_process_info = apply_filters( 'owf_get_users_in_step', $users_and_process_info,
         $post_id, $step_id );

      return $users_and_process_info;
   }

   /**
    * When the workflow progresses to the next step, we need to update the post status with the status set in the step
    *
    * @param int $post_id
    * @param int $from_step_id
    * @param int $new_action_history_id
    * @param string $current_page, page where this action is being executed
    * @param date $publish_datetime
    * @param string $immediately - do you want to publish the article immediately or at a set time.
    *
    * @return string $step_status
    */
   public function copy_step_status_to_post( $post_id, $from_step_id, $new_action_history_id, $current_page, $publish_datetime = null, $immediately = null ) {
      global $wpdb;

      $from_step_id = intval( $from_step_id );
      $post_id      = intval( $post_id );
      if ( ! empty( $publish_datetime ) ) {
         $publish_datetime = sanitize_text_field( $publish_datetime );
      }

      if ( ! empty( $immediately ) ) {
         $immediately = sanitize_text_field( $immediately );
      }

      // Derive new post status
      $ow_workflow_service = new OW_Workflow_Service();

      $ow_history_service = new OW_History_Service();
      $history_details = $ow_history_service->get_action_history_by_id( $new_action_history_id );
      // get the source and target step_ids
      $source_id = $from_step_id;
      $target_id = $history_details->step_id;

      // if the source and target step_ids are the same, we are most likely on the last step
      if ( $source_id == $target_id ) {

         $step = $ow_workflow_service->get_step_by_id( $target_id );
         $step_info = json_decode( $step->step_info );
         $process_type = $step_info->process;

         if ( $process_type == 'publish') { // if process type is publish, then set the step_status to "publish"
            $step_status = 'publish';
         } else {
            $step_status = get_post_status( $post_id );
            // TODO : handle other use cases when publish is NOT the last step, via "is Last Step?"
         }
      } else { // get the post_status from the connection info object.
         $step = $ow_workflow_service->get_step_by_id( $target_id );
         $workflow_id = $step->workflow_id;
         $workflow = $ow_workflow_service->get_workflow_by_id( $workflow_id );
         $connection = $ow_workflow_service->get_connection( $workflow, $source_id, $target_id );
         $step_status = $connection->post_status;
      }

      $previous_status = get_post_field('post_status', $post_id );

      if ( $publish_datetime ) { // user intends to publish or schedule the post
         $original_post_id = get_post_meta( $post_id, '_oasis_original', true );

         if ( empty( $original_post_id ) ) { // for new posts
            $step_status = "publish";
         } else  { // for revised post
            // we do not want to set publish status on revision, since it could trigger transition_post_status,
            // for publish, which could have unexpected results
            $step_status = "currentrev";
         }

         // double check if the publish datetime is in future
         if ( ( $step_status == "publish" || $step_status == "currentrev" )
               && ! $immediately ) {

            $time = strtotime( get_gmt_from_date( date( "Y-m-d H:i:s", strtotime( $publish_datetime ) ) ) . ' GMT');

            if ( $time > time() ) {
               if ( empty( $original_post_id ) ) { // for new posts
                  $step_status = "future";
               } else { // for revised posts
                  $step_status = "owf_scheduledrev";
               }
            }
         }

         $wpdb->update(
            $wpdb->posts,
            array(
               "post_date_gmt" => get_gmt_from_date( date( "Y-m-d H:i:s", strtotime( $publish_datetime ) ) ),
               "post_date" => date( "Y-m-d H:i:s", strtotime( $publish_datetime ) ),
               "post_status" => $step_status
            ),
            array( 'ID' => $post_id )
         );

         clean_post_cache( $post_id );
         $post = get_post( $post_id );

         wp_transition_post_status( $step_status, $previous_status, $post );

         if ( $current_page == "inbox" ) {

            /** This action is documented in wp-includes/post.php */
            // Calling this action, since quite a few plugins depend on this, like Jetpack etc.
            do_action( 'wp_insert_post', $post->ID, $post, true );
         }

      } else { // simply update the post status
         /**
          * The permalink was breaking when signing off the task. So, we are generating the post_name again,
          * so that it restores the permalink
          */

         $post_name = get_post_field('post_name', get_post( $post_id ) );
         if ( empty ( $post_name ) ) {
            $title = get_post_field('post_title', $post_id );
            $post_name = sanitize_title($title, $post_id);
         }

         $wpdb->update(
            $wpdb->posts,
            array(
               "post_status"  => $step_status,
               "post_name"   => $post_name
            ),
            array( 'ID' => $post_id )
         );

         clean_post_cache( $post_id );
         $post = get_post( $post_id );
         wp_transition_post_status( $step_status, $previous_status, $post );
      }

      return $step_status;
   }

   /**
    * this function will simply insert the data for the next step and update the previous action as "processed"
    */
   public function save_action( $data, $actors, $action_id = null ) {
      // reminder days BEFORE the due date
      $reminder_days = get_option( "oasiswf_reminder_days" );
      if ( $reminder_days && isset( $data["due_date"] ) ) {
         $data["reminder_date"] = OW_Utility::instance()->get_pre_next_date( $data["due_date"], "pre", $reminder_days );
      }

      // reminder days AFTER the due date
      $reminder_days_after = get_option( "oasiswf_reminder_days_after" );
      if ( $reminder_days_after && isset( $data["due_date"] ) ) {
         $data["reminder_date_after"] = OW_Utility::instance()->get_pre_next_date( $data["due_date"], "next", $reminder_days_after );
      }

      $ow_workflow_service = new OW_Workflow_Service();
      $action_history_table = OW_Utility::instance()->get_action_history_table_name();
      $action_table = OW_Utility::instance()->get_action_table_name();
      $wf_info = $ow_workflow_service->get_step_by_id( $data["step_id"] );
      if ( $wf_info ) {
         $step_info = json_decode( $wf_info->step_info );
      }

      $ow_email = new OW_Email( );

      if ( $step_info->process == "assignment" || $step_info->process == "publish" ) { //multiple actors are assigned in assignment/publish step
         if ( is_numeric( $actors ) ) {
            $arr[] = $actors;
         } else {
            $arr = explode( "@", $actors );
         }

         for ( $i = 0; $i < count( $arr ); $i ++ ) {
            $data["assign_actor_id"] = $arr[$i];
            $new_action_history_id = OW_Utility::instance()->insert_to_table( $action_history_table, $data );
            do_action( 'owf_save_workflow_signoff_action', $data["post_id"], $new_action_history_id );
         }
      } elseif ( $step_info->process == "review" ) {
         $data["assign_actor_id"] = -1;
         $new_action_history_id = OW_Utility::instance()->insert_to_table( $action_history_table, $data );
         do_action( 'owf_save_workflow_signoff_action', $data["post_id"], $new_action_history_id );

         $review_data = array(
             'review_status' => 'assignment',
             'action_history_id' => $new_action_history_id
         );

         if ( is_numeric( $actors ) ) {
            $arr[] = $actors;
         } else {
            $arr = explode( "@", $actors );
         }
         for ( $i = 0; $i < count( $arr ); $i ++ ) {
            if ( ! $arr[$i] )
               continue;
            $review_data["actor_id"] = $arr[$i];
            OW_Utility::instance()->insert_to_table( $action_table, $review_data );
         }
      }

      //some clean up, only if there is a previous history about the action
      if ( $action_id ) {
         global $wpdb;
         $wpdb->update( $action_history_table, array( "action_status" => "processed" ), array( "ID" => $action_id ) );
         // delete all the unsend emails for this workflow
         $ow_email->delete_step_email( $action_id );
      }

      return $new_action_history_id;
   }

   /**
    * Submit post to workflow - via POST action
    */
   public function submit_post_to_workflow() {

      // sanitize post_id
      $post_id = intval( $_POST["post_ID"] );

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      /* sanitize incoming data */
      $step_id = intval( $_POST["hi_step_id"] );

      $priority = sanitize_text_field( $_POST["hi_priority_select"] );
      if( empty( $priority ) ) {
         $priority = '2normal';
      }
    
      $selected_actor_val = sanitize_text_field( $_POST["hi_actor_ids"] );
      OW_Utility::instance()->logger( "User Provided Actors:" . $selected_actor_val );
      
      $team = sanitize_text_field( $_POST["hi_is_team"] );
      $team_id = null; 
      $is_team = false;
      // if teams add-on is active
      if ( $team === "true" ) { 
         $team_id = $selected_actor_val;
         $is_team = true;
      } else if ( $team !== "" ) {
         $team_id = $team;
      }

      $actors = $this->get_workflow_actors( $post_id, $step_id, $selected_actor_val, $is_team );
      OW_Utility::instance()->logger( "Selected Actors:" . $actors );
      // hook to allow developers to add/remove users from the task assignment
      $actors = apply_filters( 'owf_get_actors', $actors, $step_id, $post_id );
      OW_Utility::instance()->logger( "Selected Actors after filter:" . $actors );

      $due_date = sanitize_text_field( $_POST["hi_due_date"] );

      $custom_condition = sanitize_text_field( $_POST["hi_custom_condition"] );
      $pre_publish_checklist = array();
      // pre publish checklist
      if ( ! empty ( $custom_condition ) ) {
         $pre_publish_checklist = explode( ',', $custom_condition );
      }

      // sanitize_text_field remove line-breaks so do not sanitize it.
      $comments = nl2br( $_POST["hi_comment"] );

      $publish_date = sanitize_text_field( $_POST[ "hi_publish_datetime" ] );
      $user_provided_publish_date = isset( $publish_date ) ? $publish_date : "";

      // update priority on the post
      update_post_meta( $post_id, "_oasis_task_priority", $priority );

      $workflow_submit_data = array();
      $workflow_submit_data['step_id'] = $step_id;
      $workflow_submit_data['actors'] = $actors;
      $workflow_submit_data['due_date'] = $due_date;
      $workflow_submit_data['comments'] = $comments;
      $workflow_submit_data['team_id'] = $team_id;
      $workflow_submit_data['pre_publish_checklist'] = $pre_publish_checklist;
      $workflow_submit_data['publish_date'] = $user_provided_publish_date;


      $this->submit_post_to_workflow_internal( $post_id, $workflow_submit_data );
   }
   
   /*
    * Submit post to workflow - internal
    *
    * @param $post_id
    * @param $workflow_submit_data
    */
   public function submit_post_to_workflow_internal( $post_id, $workflow_submit_data ) {

      // sanitize post_id
      $post_id = intval( $post_id );

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }

      /* sanitize other incoming data */
      $step_id = intval( $workflow_submit_data['step_id'] );

      $due_date = "";
      if ( ! empty( $workflow_submit_data['due_date'] ) ) {
         $due_date = sanitize_text_field( $workflow_submit_data['due_date'] );
      }
      $actors = sanitize_text_field( $workflow_submit_data['actors'] );

      $team_id = "";
      if ( ! empty( $workflow_submit_data['team_id'] ) ) {
         $team_id = intval( $workflow_submit_data['team_id'] );
      }

      // get user submitted comments for submit to workflow
      $user_id = get_current_user_id();
      $comments[] = array(
         "send_id"           => $user_id,
         "comment"           => stripcslashes( $workflow_submit_data['comments'] ),
         "comment_timestamp" => current_time( "mysql" ) );
      $comments_json = json_encode( $comments );

      // Get history meta data
      $history_meta = array();
      $history_meta_json = null;
      $history_meta = apply_filters( 'owf_set_history_meta', $history_meta, $post_id, $workflow_submit_data );
      if ( count( $history_meta ) > 0 ) {
         $history_meta_json = json_encode( $history_meta );
      } else {
         $history_meta_json = null;
      }

      // create submit to workflow comments
      $post = get_post( $post_id );
      $user = OW_Utility::instance()->get_user_name( $user_id );
      $system_comments[] = array(
         "send_id"           => "System",
         "comment"           => "Post/Page was submitted to the workflow by " . $user,
         "comment_timestamp" => current_time( "mysql" ) );

      // create submit record
      $submit_data = array(
         'action_status'   => "submitted",
         'comment'         => json_encode( $system_comments ),
         'step_id'         => $step_id,
         'assign_actor_id' => $user_id,
         'post_id'         => $post_id,
         'from_id'         => '0',
         'create_datetime' => $post->post_date,
         'history_meta'    => $history_meta_json
      );
      $action_history_table = OW_Utility::instance()->get_action_history_table_name();
      $new_action_history_id = OW_Utility::instance()->insert_to_table( $action_history_table, $submit_data );  // insert record in history table for workflow submit

      // create assignment record
      $assignment_data = array(
         'action_status'   => "assignment",
         'comment'         => $comments_json,
         'step_id'         => $step_id,
         'post_id'         => $post_id,
         'from_id'         => $new_action_history_id,
         'create_datetime' => current_time( 'mysql' ),
         'history_meta'    => null
      );
      if ( ! empty( $due_date ) ) {
         $assignment_data["due_date"] = OW_Utility::instance()->format_date_for_db_wp_default( $due_date );
      }

      // call save_action to create assignments for the next step
      $new_action_history_id = $this->save_action( $assignment_data, $actors );

      if ( ! empty( $workflow_submit_data[ 'publish_date' ] ) ) {
         $user_provided_publish_date = sanitize_text_field( $workflow_submit_data[ 'publish_date' ] );
         $this->ow_update_post_publish_date( $post_id, $user_provided_publish_date );
      }

      // Lets update the post status when user do submit post to workflow first time
      $ow_workflow_service = new OW_Workflow_Service();
      $step = $ow_workflow_service->get_step_by_id( $step_id );
      if( $step && $workflow = $ow_workflow_service->get_workflow_by_id( $step->workflow_id ) ) {
         $wf_info = json_decode( $workflow->wf_info );
         if( $wf_info->first_step && count( $wf_info->first_step ) == 1 ) {
            $first_step = $wf_info->first_step[0];
            if( is_object( $first_step ) &&
                isset( $first_step->post_status ) &&
                ! empty( $first_step->post_status ) ) {
               $this->ow_update_post_status( $post_id, $first_step->post_status );
            }
         }
      }

      update_post_meta( $post_id, "_oasis_is_in_workflow", 1 ); // set the post meta to 1, specifying that the post is in a workflow.
      if ( ! empty( $team_id ) ) {
         update_post_meta( $post_id, "_oasis_is_in_team", $team_id ); // set the post meta to the team_id, specifying that the post is assigned to a specific team
      }

      // send task assignment notification
      $this->send_task_notification( $new_action_history_id, $actors );

      // hook to do something after submit to workflow
      do_action( 'owf_submit_to_workflow', $post_id, $new_action_history_id );
   }

   /**
    * Loop through the list of actors and send step email to them.
    *
    * @param $new_action_history_id
    * @param $actors
    */
   private function send_task_notification( $new_action_history_id, $actors ) {

      // sanitize new_action_history_id
      $new_action_history_id = intval( $new_action_history_id );

      $ow_email = new OW_Email( );

      if ( is_numeric( $actors ) ) {
         $arr[] = $actors;
      } else {
         $arr = explode( "@", $actors );
      }
      for ( $i = 0; $i < count( $arr ); $i ++ ) {
         if ( ! $arr[ $i ] ) {
            continue;
         }

         $ow_email->send_step_email( $new_action_history_id, $arr[ $i ] ); // send mail to the actor .
      }
   }

   /**
    * Get immediately publish drop down content
    */
   public function get_immediately_content( $post_id ) {
      global $wp_locale;
      $date = get_the_date( 'Y-n-d', $post_id );
      $date_array = explode( "-", $date );
      $time = get_the_time( 'G:i', $post_id );
      $time_array = explode( ":", $time );
      
      $published_date_time = $date." ".$time;
      $timestamp = strtotime( $published_date_time );

      // additional filter for allowing post publish date to be in the past.
      $owf_post_publish_in_past_filter = false;
      if ( has_filter( 'owf_publish_past' ) ) {
         $owf_post_publish_in_past_filter = apply_filters( 'owf_publish_past', $post_id );
      }
      
      // Get current date and time      
      $current_date = current_time( 'Y-n-d' );
      $current_time = current_time( 'G:i' );
      $current_date_time = $current_date." ".$current_time;
      $current_timestamp = strtotime( $current_date_time );

      /*
       * Set current date for publish date if post publish date 
       * is smaller than current date.
       */
      if( $current_timestamp > $timestamp && $owf_post_publish_in_past_filter == false ) {
         $date_array = explode( "-", $current_date );
         $time_array = explode( ":", $current_time );
      }

      echo "<select id='im-mon'>";
       for ( $i = 1; $i < 13; $i = $i +1 ) {
         $monthnum = zeroise($i, 2);
	      $monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
         if ( $date_array[1] * 1 == $i ) {
            echo "<option value={$i} selected>{$monthnum}-{$monthtext}</option>";
         } else {
            echo "<option value={$i}>{$monthnum}-{$monthtext}</option>";
         }   
      }
      echo "</select>";
      
      $im_day = esc_attr( $date_array[2] );
      $im_year = esc_attr( $date_array[0] );
      $im_hh = esc_attr( $time_array[0] );
      $im_mn = esc_attr( $time_array[1] );

      echo "<input type='text' id='im-day' value='{$im_day}' class='immediately margin' size='2' maxlength='2' autocomplete='off'>,
		<input type='text' id='im-year' value='{$im_year}' class='immediately im-year' size='4' maxlength='4' autocomplete='off'> @
		<input type='text' id='im-hh' value='{$im_hh}' class='immediately' size='2' maxlength='2' autocomplete='off'> :
		<input type='text' id='im-mn' value='{$im_mn}' class='immediately' size='2' maxlength='2' autocomplete='off'>";
   }

   /**
    * Check if the teams- add-on is available and activated.
    * @return mixed|string|void
    */
   public function is_teams_available() {
      if ( defined( 'OWFTEAMS_VERSION' ) && get_option( 'oasiswf_team_enable' ) == 'yes' ) {
         return get_option( 'oasiswf_team_enable' );
      } else {
         return "no";
      }
   }

   /**
    * localize submit workflow scripts
    */
   public function enqueue_and_localize_submit_workflow_script() {
      wp_nonce_field( 'owf_workflow_abort_nonce', 'owf_workflow_abort_nonce' );
      wp_nonce_field( 'owf_compare_revision_nonce', 'owf_compare_revision_nonce' );

		// enqueue js file if advanced custom fields plugin active
      $this->enqueue_acf_validator_script();
      wp_enqueue_script( 'owf_submit_workflow', OASISWF_URL . 'js/pages/subpages/submit-workflow.js', array( 'jquery' ), OASISWF_VERSION, true );

      $workflow_terminology_options = get_option( 'oasiswf_custom_workflow_terminology' );
      $submit_to_workflow_label = ! empty( $workflow_terminology_options['submitToWorkflowText'] ) ? $workflow_terminology_options['submitToWorkflowText'] : __( 'Submit to Workflow', 'oasisworkflow' );
      wp_localize_script( 'owf_submit_workflow', 'owf_submit_workflow_vars', array(
          'submitToWorkflowButton' => $submit_to_workflow_label,
          'allStepsNotDefined' => __( 'All steps are not defined.\n Please check the workflow.', 'oasisworkflow' ),
          'revisionPrepareMessage' => __( "Preparing the revision compare. If the page doesn't get redirected to the compare page in 10 seconds,", 'oasisworkflow' ),
          'clickHereText' => __( 'click here', 'oasisworkflow' ),
          'notValidWorkflow' => __( 'The selected workflow is not valid.\n Please check this workflow.', 'oasisworkflow' ),
          'noUsersDefined' => __( 'No users found to assign the task.', 'oasisworkflow' ),
          'multipleUsers' => __( 'You can select multiple users only for review step. Selected step is', 'oasisworkflow' ),
          'step' => __( 'step.', 'oasisworkflow' ),
          'selectWorkflow' => __( 'Please select a workflow.', 'oasisworkflow' ),
          'selectStep' => __( 'Please select a step.', 'oasisworkflow' ),
          'stepNotDefined' => __( 'This step is not defined.', 'oasisworkflow' ),
          'dueDateRequired' => __( 'Please enter a due date.', 'oasisworkflow' ),
          'noChecklistSelected' => __( 'You have not selected any pre publish checklist ', 'oasisworkflow' ),
          'selectAllChecklist' => __( 'Please select all pre publish checklist ', 'oasisworkflow' ),
          'noAssignedActors' => __( 'No assigned actor(s).', 'oasisworkflow' ),
          'drdb' => get_option( 'oasiswf_reminder_days' ),
          'drda' => get_option( 'oasiswf_reminder_days_after' ),
          'allowedPostTypes' => json_encode( get_option( 'oasiswf_show_wfsettings_on_post_types' ) ),
          'workflowTeamsAvailable' => $this->is_teams_available(),
          'noTeamSelected' => __( 'Please select a team.', 'oasisworkflow' ),
          'noRoleUsersInTeam' => __( 'No users found for the given Team and Workflow assignee(s). Please check the team.', 'oasisworkflow' ),
          'compareOriginal' => __( 'Compare With Original', 'oasisworkflow' ),
          'dateFormat' => OW_Utility::instance()->owf_date_format_to_jquery_ui_format( get_option( 'date_format' ) ),
          'editDateFormat' => OW_Utility::instance()->owf_date_format_to_jquery_ui_format( OASISWF_EDIT_DATE_FORMAT ),
          'hideCompareButton' => get_option( "oasiswf_hide_compare_button" ),
          'defaultDueDays' => get_option( 'oasiswf_default_due_days' ),
      	 'absoluteURL' => get_admin_url()
      ) );
      //}
   }

   /**
    * Localize submit step scripts
    */
   public function enqueue_and_localize_submit_step_script() {
      wp_nonce_field( 'owf_workflow_abort_nonce', 'owf_workflow_abort_nonce' );
      wp_nonce_field( 'owf_check_claim_nonce', 'owf_check_claim_nonce' );
      wp_nonce_field( 'owf_compare_revision_nonce', 'owf_compare_revision_nonce' );

		// enqueue js file if advanced custom fields plugin active
      $this->enqueue_acf_validator_script();

      wp_enqueue_script( 'owf_submit_step', OASISWF_URL . 'js/pages/subpages/submit-step.js', array( 'jquery' ), OASISWF_VERSION, true );

      $workflow_terminology_options = get_option( 'oasiswf_custom_workflow_terminology' );
      $sign_off_label = ! empty( $workflow_terminology_options['signOffText'] ) ? $workflow_terminology_options['signOffText'] : __( 'Sign Off', 'oasisworkflow' );
      wp_localize_script( 'owf_submit_step', 'owf_submit_step_vars', array(
          'revisionPrepareMessage' => __( "Preparing the revision compare. If the page doesn't get redirected to the compare page in 10 seconds,", 'oasisworkflow' ),
          'clickHereText' => __( 'click here', 'oasisworkflow' ),
          'signOffButton' => $sign_off_label,
          'claimButton' => __( 'Claim', 'oasisworkflow' ),
          'inboxButton' => __( 'Go to Workflow Inbox', 'oasisworkflow' ),
          'lastStepFailureMessage' => __( 'There are no further steps defined in the workflow.</br> Do you want to cancel the post/page from the workflow?', 'oasisworkflow' ),
          'lastStepSuccessMessage' => __( 'This is the last step in the workflow. Are you sure to complete the workflow?', 'oasisworkflow' ),
          'noUsersFound' => __( 'No users found to assign the task.', 'oasisworkflow' ),
          'decisionSelectMessage' => __( 'Please select an action.', 'oasisworkflow' ),
          'selectStep' => __( 'Please select a step.', 'oasisworkflow' ),
          'dueDateRequired' => __( 'Please enter a due date.', 'oasisworkflow' ),
          'noAssignedActors' => __( 'No assigned actor(s).', 'oasisworkflow' ),
          'multipleUsers' => __( 'You can select multiple users only for review step. Selected step is', 'oasisworkflow' ),
          'step' => __( 'step.', 'oasisworkflow' ),
          'drdb' => get_option( 'oasiswf_reminder_days' ),
          'drda' => get_option( 'oasiswf_reminder_days_after' ),
          'workflowTeamsAvailable' => $this->is_teams_available(),
          'noRoleUsersInTeam' => __( 'No users found for the assigned Team and Workflow assignee(s). Please contact the administrator.', 'oasisworkflow' ),
          'notInTeam' => __( 'No team assigned. Please contact the administrator.', 'oasisworkflow' ),
          'compareOriginal' => __( 'Compare With Original', 'oasisworkflow' ),
          'dateFormat' => OW_Utility::instance()->owf_date_format_to_jquery_ui_format( get_option( 'date_format' ) ),
          'editDateFormat' => OW_Utility::instance()->owf_date_format_to_jquery_ui_format( OASISWF_EDIT_DATE_FORMAT ),
          'hideCompareButton' => get_option( "oasiswf_hide_compare_button" ),
          'defaultDueDays' => get_option( 'oasiswf_default_due_days' ),
      	 'absoluteURL' => get_admin_url()
      ) );
   }

   /**
    * include/invoke ACF validation during the workflow submit and sign off process,
    * if ACF plugin is installed and active.
    *
    * @since 3.3
    */
   public function enqueue_acf_validator_script() {
      $acf_version = "";
      if ( ! function_exists( 'get_plugins' ) ) {
         require_once ABSPATH . 'wp-admin/includes/plugin.php';
      }
      $plugins = get_plugins();
      $active_plugins = get_option( 'active_plugins', array() );

      $acf_pro_path = "advanced-custom-fields-pro/acf.php";
      $acf_path = "advanced-custom-fields/acf.php";
      $isACFEnabled = "no";
     
      // Check ACF pro or free plugin and fetch the version
      foreach ( $plugins as $plugin_path => $plugin ) {
         if ( $plugin_path == $acf_pro_path && in_array( $acf_pro_path, $active_plugins ) ) {
            $acf_version = $plugin['Version'];
            $isACFEnabled = "yes";
            break;
         }  
         
         if ( $plugin_path == $acf_path && in_array( $acf_path, $active_plugins ) ) {             
            $acf_version = $plugin['Version'];
            $isACFEnabled = "yes";
            break;
         }
      }
      
      // Localize script for check if acf is enabled.
      wp_localize_script( 'owf-workflow-util', 'owf_workflow_util_vars', array(
         'isACFEnabled' => $isACFEnabled
		) );

      // Based on version enqueue required JS files

      if ( ! empty( $acf_version ) && $acf_version >= "5.7.0" ) { // applicable to pro and free version > 5.7.x of ACF
         wp_enqueue_script( 'owf_acf_validator',
            OASISWF_URL . 'js/pages/acf-pro-validator-new.js',
            array( 'jquery' ), OASISWF_VERSION, true );
      }

      if ( ! empty( $acf_version ) && $acf_version >= "5.0.0" && $acf_version <= "5.6.9"  ) { // applicable to pro and free version > 5.x of ACF
         wp_enqueue_script( 'owf_acf_validator',
            OASISWF_URL . 'js/pages/acf-pro-validator.js',
            array( 'jquery' ), OASISWF_VERSION, true );
      }
      
      if ( ! empty( $acf_version ) && $acf_version == "5.6.10"  ) { // applicable to pro and free version = 5.6.10 of ACF
         wp_enqueue_script( 'owf_acf_validator',
            OASISWF_URL . 'js/pages/acf-pro-validator.js',
            array( 'jquery' ), OASISWF_VERSION, true );
      }
      
      if ( ! empty( $acf_version ) && $acf_version < "5.0.0"  ) { // applicable for free version less than 5.x
         wp_enqueue_script( 'owf_acf_validator',
                 OASISWF_URL . 'js/pages/acf-validator.js',
                 array( 'jquery' ), OASISWF_VERSION, true );
      }
   }

   /*
    * localize make revision scripts
    */
   public function enqueue_and_localize_make_revision_script() {
      wp_enqueue_script( 'owf-workflow-util', OASISWF_URL . 'js/pages/workflow-util.js', '', OASISWF_VERSION, true );
      wp_enqueue_script( 'owf_make_revision', OASISWF_URL . 'js/pages/subpages/make-revision.js', array( 'jquery' ), OASISWF_VERSION, true );
      wp_nonce_field( 'owf_compare_revision_nonce', 'owf_compare_revision_nonce' );
      wp_nonce_field( 'owf_make_revision_ajax_nonce', 'owf_make_revision_ajax_nonce' );

      $workflow_terminology_options = get_option( 'oasiswf_custom_workflow_terminology' );
      $make_revision_label = ! empty( $workflow_terminology_options['makeRevisionText'] ) ? $workflow_terminology_options['makeRevisionText'] : __( 'Make Revision', 'oasisworkflow' );
      wp_localize_script( 'owf_make_revision', 'owf_make_revision_vars', array(
          'makeRevisionButton' => $make_revision_label,
          'allowedPostTypes' => json_encode( get_option( 'oasiswf_show_wfsettings_on_post_types' ) ),
          'enableDocumentRevisionProcess' => get_option( 'oasiswf_activate_revision_process' ),
          'enableWorkflowProcess' => get_option( "oasiswf_activate_workflow" )
      ) );
   }

   /**
    * enqueue and localize the workflow abort script
    *
    * @since 2.0
    */
   public function enqueue_and_localize_abort_script() {
      wp_nonce_field( 'owf_workflow_abort_nonce', 'owf_workflow_abort_nonce' );
      wp_nonce_field( 'owf_compare_revision_nonce', 'owf_compare_revision_nonce' );

      wp_enqueue_script( 'owf-abort-workflow', OASISWF_URL . 'js/pages/subpages/exit.js', '', OASISWF_VERSION, true );

      $workflow_terminology_options = get_option( 'oasiswf_custom_workflow_terminology' );
      $abort_workflow_label = ! empty( $workflow_terminology_options['abortWorkflowText'] ) ? $workflow_terminology_options['abortWorkflowText'] : __( 'Abort Workflow', 'oasisworkflow' );

      wp_localize_script( 'owf-abort-workflow', 'owf_abort_workflow_vars', array(
          'revisionPrepareMessage' => __( "Preparing the revision compare. If the page doesn't get redirected to the compare page in 10 seconds,", 'oasisworkflow' ),
          'clickHereText' => __( 'click here', 'oasisworkflow' ),
          'abortWorkflow' => $abort_workflow_label,
          'compareOriginal' => __( 'Compare With Original', 'oasisworkflow' ),
          'abortWorkflowConfirm' => __( 'Are you sure to terminate the workflow?', 'oasisworkflow' ),
          'hideCompareButton' => get_option( "oasiswf_hide_compare_button" ),
      	 'absoluteURL' => get_admin_url()
      ) );
   }
   
   /**
    * enqueue and localize the update published article
    * @since 5.1
    */
   public function enqueue_and_localize_update_publish_script() {
      wp_nonce_field( 'owf_update_published_nonce', 'owf_update_published_nonce' );
      wp_enqueue_script( 'owf-update-published', OASISWF_URL . 'js/pages/subpages/update-published.js', '', OASISWF_VERSION, true );
      
      wp_localize_script( 'owf-update-published', 'owf_update_published_vars', array(
          'updatePublishLinkText' => __( 'Update Published Article', 'oasisworkflow' )
      ) );
   }

   /**
    * Get Pre publish conditions selected by the user for the particular step
    *
    * If the history_type is "history" get it from the fc_action_history
    * If the history_type is "review_history" get it from the fc_action
    *
    * @param $post_id
    * @param $history_id
    * @param $history_type
    *
    * @return array array of selected checklist conditions
    *
    * @since 4.2
    */
   public function get_selected_checklist_conditions( $post_id, $history_id, $history_type ) {
      $checklist = array();
      $ow_history_service = new OW_History_Service();

      if ( $history_type == 'history' ){
         $history_info = $ow_history_service->get_action_history_by_id( $history_id );
      }
      else if ( $history_type == 'review_history' ) {
         $history_info = $ow_history_service->get_review_action_by_id( $history_id );
      }

      $history_meta = json_decode( $history_info->history_meta );
      if ( isset( $history_meta->pre_publish_checklist ) ) {
         $pre_publish_checklist = $history_meta->pre_publish_checklist;
         $checklist_question_id =  explode( ',', $pre_publish_checklist->checklist_questions );
         $condition_group_id = $pre_publish_checklist->condition_group_id;
      } 
      $ow_pre_publish_conditions = get_post_meta( $condition_group_id, 'ow_pre_publish_meta', true );
      foreach ( $ow_pre_publish_conditions as $value ) {
         if ( in_array( $value[ 'question_id' ], $checklist_question_id ) ) {
            array_push( $checklist, $value[ 'checklist_condition' ] );
         }        
      }
      return $checklist ;
   }

   /**
    * Update post status
    *
    * @param $post_id
    * @param $status
    *
    * @since 4.2
    */
   public function ow_update_post_status( $post_id, $status ) {
      // change the post status of the post
      global $wpdb;

      $post_id = intval( $post_id );
      $status = sanitize_text_field( $status );
      $previous_status = get_post_field('post_status', $post_id );
      
       /**
        * The permalink was breaking when submitting and signing off the task in workflow.
        * So, we are generating the post_name again,
        * so that it restores the permalink
        */
      $post_name = get_post_field('post_name', get_post( $post_id ) );
      if ( empty ( $post_name ) ) {
         $title = get_post_field('post_title', $post_id );
         $post_name = sanitize_title($title, $post_id);
      }

      $wpdb->update(
         $wpdb->posts,
         array(
            'post_status' => $status,
            'post_name'   => $post_name
         ),
         array( 'ID' => $post_id )
      );
      
      clean_post_cache( $post_id );
      $post = get_post( $post_id );
      wp_transition_post_status( $status, $previous_status, $post );
      do_action( 'wp_insert_post', $post->ID, $post, true );
   }


   /**
    * Update post publish date
    *
    * @param $post_id
    * @param $publish_date
    *
    * @since 4.2
    */
   public function ow_update_post_publish_date( $post_id, $publish_date ) {
      // change the post status of the post
      global $wpdb;

      $post_id = intval( $post_id );
      $publish_date = sanitize_text_field( $publish_date );
      $publish_date_mysql = OW_Utility::instance()->format_datetime_for_db_wp_default( $publish_date );
      $publish_date_gmt = get_gmt_from_date( $publish_date_mysql );

      $wpdb->update(
         $wpdb->posts,
         array(
            'post_date' => $publish_date_mysql,
            'post_date_gmt' => $publish_date_gmt
         ),
         array( 'ID' => $post_id )
      );
   }

   /**
    * 1) Override the default due date on first step of the workflow, if override is turned on.
    * 2) If the option to override the default due date is not set than return
    * the globally set due date.
    *
    * @param $step_id, ID of the first step in the workflow
    *
    * @return due date
    *
    * @since 4.5
    */
   public function get_submit_workflow_due_date( $step_id ) {
      
      $step_id = intval( $step_id );
      
      // fetch globally set due days
      $default_due_days = get_option( 'oasiswf_default_due_days' );
      $global_due_date = date_i18n( OASISWF_EDIT_DATE_FORMAT, current_time( 'timestamp' ) + DAY_IN_SECONDS * $default_due_days );
      
      $show_step_due_date = get_option( 'oasiswf_step_due_date_settings' );
      
      if ( $show_step_due_date === 'yes' ) {
         $ow_workflow_service = new OW_Workflow_Service();
         $step = $ow_workflow_service->get_step_by_id( $step_id );
         $step_info = json_decode( $step->step_info );
         // check step due days is set and not empty. If empty use global due date
         if ( isset( $step_info->step_due_days ) && ! empty( $step_info->step_due_days ) ) {
            $step_due_days = $step_info->step_due_days;
            $step_due_date = date_i18n( OASISWF_EDIT_DATE_FORMAT, current_time( 'timestamp' ) + DAY_IN_SECONDS * $step_due_days );
            return $step_due_date;
         } else {
            return $global_due_date;
         }
      } else {
         return $global_due_date;
      }
   }
   
   /**
    * 1) Override the default due date on each step of the workflow, if override is turned on.
    * 2) If the option to override the default due date is not set than return
    * the globally set due date.
    *
    * @param $post_id
    * @param $step_id
    *
    * @return due date
    *
    * @since 4.5
    */ 
   public function get_sign_off_due_date( $post_id, $step_id ) {
      
      $step_id = intval( $step_id );
      $post_id = intval( $post_id );
      
      // fetch globally set due days
      $default_due_days = get_option( 'oasiswf_default_due_days' );
      $global_due_date = date_i18n( OASISWF_EDIT_DATE_FORMAT, current_time( 'timestamp' ) + DAY_IN_SECONDS * $default_due_days );
      
      $show_step_due_date = get_option( 'oasiswf_step_due_date_settings' );
      
      if ( $show_step_due_date === 'yes' ) {
         $ow_workflow_service = new OW_Workflow_Service();
         $step = $ow_workflow_service->get_step_by_id( $step_id );
         $step_info = json_decode( $step->step_info );

         $ow_history_service = new OW_History_Service();
         $history_details = $ow_history_service->get_action_history_by_status( "submitted", $post_id );
         $submitted_datetime = strtotime( $history_details[0]->create_datetime );
         
         /* Sometime show default due date option is disable during submit to workflow but 
          * enabled after after it. So due date is empty. If empty than during sign-off return 
          * the globally set due date.
          */
         if ( empty( $submitted_datetime ) ) {
            return $global_due_date;
         }
         
         // check step due days is set and not empty. If empty use global due date
         if ( isset( $step_info->step_due_days ) && ! empty( $step_info->step_due_days ) ) {
            $step_due_days = $step_info->step_due_days;
            $step_due_date = date_i18n( OASISWF_EDIT_DATE_FORMAT, $submitted_datetime + DAY_IN_SECONDS * $step_due_days );
            return $step_due_date;
         } else {
            $step_due_date = date_i18n( OASISWF_EDIT_DATE_FORMAT, $submitted_datetime + DAY_IN_SECONDS * $default_due_days );
            return $step_due_date;
         }
      } else {
         return $global_due_date;
      }
   }

   /*
    * Validate Workflow Sign off
    *
    * In case of validation errors - display error messages on the "Sign off" popup
    *
    * @param $post_id
    * @param $sign_off_workflow_params
    * @param $validation_result
    *
    * @return mixed|void
    */
   private function validate_workflow_sign_off( $post_id, $sign_off_workflow_params ) {

      $validation_result = array();
      // if team is assigned to the post, then get the team details
      $team_id = get_post_meta( $post_id, '_oasis_is_in_team', true );
      if ( ! empty( $team_id ) ) {
         if ( ! $this->has_users_in_team( $sign_off_workflow_params["post_id"],
            $sign_off_workflow_params["step_id"], $team_id ) ) {
            $team_error_message = __( 'No users found for the assigned Team and Workflow role. Please contact the administrator.', 'oasisworkflow' );
            array_push( $validation_result, $team_error_message );
         }
      }

      // let the filter execute pre workflow sign off validations and return validation error messages, if any
      $validation_result = apply_filters( 'owf_sign_off_workflow_pre', $validation_result, $sign_off_workflow_params );

      return $validation_result;
   }

   /**
    * Validate due date
    * If empty due date, return true
    * due date should be greater than current date
    *
    * @param $due_date
    *
    * @return bool
    */
   private function validate_due_date( $due_date ) {

      if ( empty( $due_date ) ) {
         return true;
      }

      // get the various options which decide to hide/show the due date
      $default_due_days = '';
      if ( get_option( 'oasiswf_default_due_days' ) ) {
         $default_due_days = get_option('oasiswf_default_due_days');
      }

      $reminder_days = '';
      if ( get_option( 'oasiswf_reminder_days' ) ) {
         $reminder_days = get_option('oasiswf_reminder_days');
      }

      $reminder_days_after = '';
      if ( get_option( 'oasiswf_reminder_days_after' ) ) {
         $reminder_days_after = get_option('oasiswf_reminder_days_after');
      }

      // incoming formatted date: 08-Août 24, 2016
      // remove the textual month so that the date looks like: 08 24, 2016
      $start = '-';
      $end  = ' ';
      $replace_string = '';
      $formatted_date = preg_replace('#('.preg_quote($start).')(.*?)('.preg_quote($end).')#si', '$1'. $replace_string .'$3', $due_date);
      $formatted_date = str_replace( "-", "", $formatted_date );

      $due_date_object = DateTime::createFromFormat( 'm d, Y', $formatted_date );
      $due_date_timestamp = $due_date_object->getTimestamp();

      if ( ( $default_due_days !== '' ||
             $reminder_days !== '' ||
             $reminder_days_after !== '' ) &&
           $due_date != '' &&
           $due_date_timestamp < current_time( 'timestamp' ) ) {
         return false;
      }

      return true;
   }

   /*
    * Validate Workflow Complete
    *
    * In case of validation errors - display error messages on the "Sign off" popup
    *
    * @param $post_id
    * @param $sign_off_workflow_params
    * @param $validation_result
    *
    * @return mixed|void
    */
   private function validate_workflow_complete( $post_id, $sign_off_workflow_params ) {

      $validation_result = array();

      // let the filter execute pre workflow sign off validations and return validation error messages, if any
      $validation_result = apply_filters( 'owf_sign_off_workflow_pre', $validation_result, $sign_off_workflow_params );

      return $validation_result;
   }

   /*
    * If the post data can be extracted from the $_POST['form'], get it from there
    * Otherwise, simply get it from the post using get_post
    */
   private function get_post_data( $post_id ) {

      $post_data = array (
         "post_id"             => $post_id,
         "post_title"          => "",
         "post_excerpt"        => "",
         "post_contents"        => "",
         "post_category_count" => 0,
         "post_tag_count"      => 0
      );

      // if form attr is set then get its value from edit post else from inbox page
      if ( isset( $_POST['form'] ) && ! empty( $_POST['form'] ) ) {
         $form = $_POST['form'];
         parse_str( $form, $_POST );

         // for some reason, there is an entry for "0" in the list, so lets minus that
         if ( isset( $_POST['post_category'] ) ) {
            $post_data["post_category_count"] = intval( count( $_POST['post_category'] ) - 1 );
         }

         if ( isset( $_POST['tax_input']['post_tag'] ) && ! empty( $_POST['tax_input']['post_tag'] ) ) {
            $post_data["post_tag_count"] = count( explode( ',', sanitize_text_field( $_POST['tax_input']['post_tag'] ) ) );
         }

         $data = @array_map( 'esc_attr', $_POST );
         $post_data["post_title"] = isset( $data['post_title'] ) ? $data['post_title'] : '';
         $post_data["post_excerpt"] = isset( $data['excerpt'] ) ? $data['excerpt'] : '';
         $post_data["post_contents"] =  isset( $_POST['content'] ) ? $_POST['content'] : '';
         $post_data["current_page"] = "edit";

      } else {
         $data = (array) get_post( $post_id );
         $post_data["post_title"] = isset( $data['post_title'] ) ? $data['post_title'] : '';
         $post_data["post_contents"] = isset( $data['post_content'] ) ? $data['post_content'] : '' ;
         $post_data["post_excerpt"] = isset( $data['post_excerpt'] ) ? $data['post_excerpt'] : '';
         $post_data["post_tag_count"] = count( wp_get_post_tags( $post_id ) );
         $post_data["post_category_count"] = count( wp_get_post_categories( $post_id ) );
         $post_data["current_page"] = "inbox";
      }

      return $post_data;
   }

   /*
    * Check if the given team has members for the given step.
    *
    * @param $post_id
    * @param $step_id
    * @param $team_id
    *
    * @return bool
    */
   private function has_users_in_team( $post_id, $step_id, $team_id ) {

      $ow_workflow_service = new OW_Workflow_Service();
      $step = $ow_workflow_service->get_step_by_id( $step_id );
      $step_info = json_decode( $step->step_info );
      $assignee_roles = isset( $step_info->task_assignee->roles ) ? array_flip( $step_info->task_assignee->roles ) : null;

      if ( method_exists( 'OW_Teams_Service', 'get_team_members' ) ) {
         $ow_teams_service = new OW_Teams_Service();
         $actors = $ow_teams_service->get_team_members( $team_id, $assignee_roles, $post_id );
         if ( ! empty( $actors ) ) {
            return true;
         }
         return false;
      }

      return false;
   }

   /**
    * Javascript for hiding the ootb publish section
    * It also hides "Edit" on the post status, if the post is in a workflow.
    */
   private function ootb_publish_section_hide() {
      // if the post status is pending, WP hides the "Save"  button(meta-boxes.php - post_submit_meta_box())
      // we want to show the "Save" button no matter what the status is
      // also, we want to display the publish date/time, if the user has publish priveleges
      echo "<script type='text/javascript'>
					jQuery(document).ready(function() {
						jQuery('#publish, .misc-pub-section-last').hide();
						if(jQuery(\"#save-post\").length == 0) {
                     jQuery('#save-action').html('<input type=\"submit\" name=\"save\" id=\"save-post\" value=\"Save\" class=\"button\"><span class=\"spinner\"></span>');
						}
						jQuery('#post-status-display').parent().children('.edit-post-status').hide() ;
					});
				</script>";
   }

   /*
    * clean up the options after workflow is completed
    */
   public function cleanup_after_workflow_complete( $post_id ) {
      $post_id = intval( sanitize_text_field( $post_id ) );
      update_post_meta( $post_id, "_oasis_is_in_workflow", 0 ); // set the post meta to 0, specifying that the post is out of a workflow.
      delete_post_meta( $post_id, "_oasis_is_in_team" );
   }

   /*
    * Submit post to step - internal
    */
   private function submit_post_to_step_internal( $post_id, $workflow_signoff_data ) {
      global $wpdb;

      $ow_history_service = new OW_History_Service();
      $history_id = $workflow_signoff_data['history_id'];
      $step_id = $workflow_signoff_data['step_id'];
      $task_actor_id = $workflow_signoff_data['task_user'];
      $sign_off_comments = $workflow_signoff_data['comments'];
      $assigned_actors = $workflow_signoff_data['actors'];
      $step_decision = $workflow_signoff_data['step_decision'];

      $ow_email = new OW_Email();

      // get the history details from fc_action_history
      $history_details = $ow_history_service->get_action_history_by_id( $history_id );

      // comments added during signoff
      $comments[] = array( "send_id"           => $task_actor_id,
                           "comment"           => stripcslashes( $sign_off_comments ),
                           "comment_timestamp" => current_time( "mysql" ) );

      $team_id = get_post_meta( $history_details->post_id, '_oasis_is_in_team', true );

      $actors_info = array( $assigned_actors, $step_id, $history_details->post_id, $team_id );
      // by default we get the users assigned to the specified role
      // this action will allow to change the actor list
      do_action_ref_array( 'owf_get_actors', array( &$actors_info ) );
      $actors = $actors_info[0];

      // Get history meta data
      $history_meta = array();
      $history_meta_json = null;
      $history_meta = apply_filters( 'owf_set_history_meta', $history_meta, $post_id, $workflow_signoff_data );
      if ( count( $history_meta ) > 0 ) {
         $history_meta_json = json_encode( $history_meta );
      } else {
         $history_meta_json = null;
      }

      $new_action_history_id = "";

      if ( $history_details->assign_actor_id == -1 ) { // the current step is a review step, so review decision check is required
         // let's first save the review action
         // find the next assign actors
         if ( is_numeric( $actors ) ) {
            $next_assign_actors[] = $actors;
         } else {
            $arr = explode( "@", $actors );
            $next_assign_actors = $arr;
         }

         $review_data = array(
            "review_status"      => $step_decision,
            "next_assign_actors" => json_encode( $next_assign_actors ),
            "step_id"            => $step_id, // represents success/failure step id
            "comments"           => json_encode( $comments ),
            "history_meta"       => $history_meta_json,
            "update_datetime"    => current_time( 'mysql' )
         );

         if ( ! empty( $workflow_signoff_data['due_date'] ) ) {
            $review_data["due_date"] = OW_Utility::instance()->format_date_for_db_wp_default( $workflow_signoff_data['due_date'] );
         }

         $action_table = OW_Utility::instance()->get_action_table_name();
         $wpdb->update( $action_table, $review_data, array( "actor_id" => $task_actor_id, "action_history_id" => $history_id ) );

         // delete reminder email for this user, since the user completed his/her review
         $ow_email->delete_step_email( $history_id, $task_actor_id );

         // invoke the review step procedure to make a review decision
         $new_action_history_id = $this->review_step_procedure( $history_id, $history_details->step_id );

      } else { // the current step is either an assignment or publish step, so no review decision check required
         $data = array(
            'action_status'   => "assignment",
            'comment'         => json_encode( $comments ),
            'step_id'         => $step_id,
            'post_id'         => $post_id,
            'from_id'         => $history_id,
            "history_meta"    => $history_meta_json,
            'create_datetime' => current_time( 'mysql' )
         );
         if ( ! empty( $workflow_signoff_data['due_date'] ) ) {
            $data["due_date"] = OW_Utility::instance()->format_date_for_db_wp_default( $workflow_signoff_data['due_date'] );
         }

         // insert data from the next step
         $new_action_history_id = $this->save_action( $data, $actors, $history_id );

         //------post status change----------
         $this->copy_step_status_to_post( $post_id, $history_details->step_id, $new_action_history_id, $workflow_signoff_data['current_page'] );
      }
      
      if ( $new_action_history_id !== 0 ) {
         if ( $workflow_signoff_data['current_page'] == 'inbox') { ////signing off from inbox
            // send task assignment notification immediately, since the post is already updated
            $this->send_task_notification( $new_action_history_id, $actors );
         } else {
            // Set task assign meta, so that we will send email after saving the post
            $this->set_oasis_task_assignment_meta( $post_id, $new_action_history_id, $actors );
         }
      }

      do_action( 'owf_step_sign_off', $post_id, $new_action_history_id );

      return $new_action_history_id;

   }

   /*
    * Convenience function to get assignee user list, depending on the user selection.
    *
    * @param $post_id
    * @param $step_id
    * @param $selected_actor_val
    * @param $assign_to_all
    *
    * @return array|mixed|string|void
    */
   private function get_workflow_actors( $post_id, $step_id, $selected_actor_val, $is_team ) {
      // if teams add-on is active, validate if the team has the users
      if ( $is_team ) {
         $team_id = $selected_actor_val; // we store the team_id in $selected_actor_val
         $actors = apply_filters( 'owf_get_team_members', $team_id, $step_id, $post_id );
         return $actors;
      }

      $ow_workflow_service = new OW_Workflow_Service();
      $step = $ow_workflow_service->get_step_by_id( $step_id );
      $step_info = json_decode( $step->step_info );

      // all users in this step should be assigned the task.
      if ( 1 === $step_info->assign_to_all ) {
         $users_and_processes = $this->get_users_in_step( $step_id, $post_id );
         $users = $users_and_processes["users"];
         $actors = array();
         foreach ( $users as $user ) {
            $actors[] = $user->ID;
         }
         $actors = implode( "@", $actors );
         return $actors;
      } else {
         return $selected_actor_val; // these are the actual selected users
      }
   }

   /*
    * process for review step
    */
   private function review_step_procedure( $action_history_id, $step_id ) {
      global $wpdb;
      $review_setting = "";

      $action_history_id = intval( sanitize_text_field( $action_history_id ) );
      $step_id = intval( sanitize_text_field( $step_id ) );

      $ow_workflow_service = new OW_Workflow_Service();
      $ow_history_service = new OW_History_Service();

      // get the review action details from fc_action for the given history_id
      $total_reviews = $ow_history_service->get_review_action_by_history_id( $action_history_id );

      // get the review settings from the step info (all should approve, 50% should approve, one should approve)
      $workflow_step = $ow_workflow_service->get_step_by_id( $step_id );
      $step_info = json_decode( $workflow_step->step_info );
      if ( $step_info->process == 'review' ) { // this is simple double checking whether the step is review step.
         $review_setting = isset( $step_info->review_approval ) ? $step_info->review_approval : 'everyone';
      }

      // create a consolidated view of all the reviews, so far
      if ( $total_reviews ) {
         foreach ( $total_reviews as $review ) {
            $next_assign_actors = json_decode( $review->next_assign_actors );
            if ( empty( $next_assign_actors ) ) { // the action is still not completed by the user
               $r = array(
                  "re_actor_id" => $next_assign_actors,
                  "re_step_id" => $review->step_id,
                  "re_comment" => $review->comments,
                  "re_due_date" => $review->due_date
               );
               $review_data[$review->review_status][] = $r;
            } else { // action completed by user and we know the review results
               foreach ( $next_assign_actors as $actor ) :
                  $r = array(
                     "re_actor_id" => $actor,
                     "re_step_id" => $review->step_id,
                     "re_comment" => $review->comments,
                     "re_due_date" => $review->due_date
                  );
                  $review_data[$review->review_status][] = $r;
               endforeach;
            }
         }
      }

      $new_action_history_id = 0;
      switch ( $review_setting ) {
         case "everyone":
            $new_action_history_id = $this->review_step_everyone( $review_data, $action_history_id );
            break;
         case "anyone":
            $new_action_history_id  = $this->review_step_anyone( $review_data, $action_history_id );
            break;
         case "more_than_50":
            $new_action_history_id = $this->review_step_more_50( $review_data, $action_history_id );
            break;
      }

      return $new_action_history_id;
   }

   /*
    * everyone has to approve before the item moves to the next step
    */
   private function review_step_everyone( $review_data, $action_history_id ) {
      /*
       * If assignment (not yet completed) are found, return false; we cannot make any decision yet
       * If we find even one rejected review, complete the step as failed.
       * If all the reviews are approved, then move to the success step.
       */

      if ( isset( $review_data["assignment"] ) && $review_data["assignment"] ) {
         return 0; // there are users who haven't completed their review
      }

      if ( isset( $review_data["unable"] ) && $review_data["unable"] ) { // even if we see one rejected, we need to go to failure path.
         $new_action_history_id = $this->save_review_action( $review_data["unable"], $action_history_id, "unable" );
         return $new_action_history_id; // since we found our condition
      }

      if ( isset( $review_data["complete"] ) && $review_data["complete"] ) { // looks like we only have completed/approved reviews, lets complete this step.
         $new_action_history_id = $this->save_review_action( $review_data["complete"], $action_history_id, "complete" );
         return $new_action_history_id; // since we found our condition
      }
   }

   /*
    * anyone has to approve before the item moves to the next step
    */
   private function review_step_anyone( $review_data, $action_history_id ) {

      /*
       * First find any approved review, if found, complete the step as pass.
       * If no approved reviews are found, try to find a rejected review. If found, complete the step as failed.
       * Ignore if there are reviews, still in assignment (not yet completed)
       */

      if ( isset( $review_data["complete"] ) && $review_data["complete"] ) { // looks like at least one has approved, lets complete this step.
         $new_action_history_id = $this->save_review_action( $review_data["complete"], $action_history_id, "complete" );

         // change review status on remaining/not completed tasks as "no_action"
         if ( isset( $review_data["assignment"] ) && $review_data["assignment"] ) {
            $this->change_review_status_to_no_action( $review_data["assignment"], $action_history_id );
         }

         return $new_action_history_id; // since we found our condition
      }

      if ( isset( $review_data["unable"] ) && $review_data["unable"] ) { // looks like at least one has rejected, we need to go to failure path.
         $new_action_history_id = $this->save_review_action( $review_data["unable"], $action_history_id, "unable" );

         // change review status on remaining/not completed tasks as "no_action"
         if ( isset( $review_data["assignment"] ) && $review_data["assignment"] ) {
            $this->change_review_status_to_no_action( $review_data["assignment"], $action_history_id );
         }

         return $new_action_history_id; // since we found our condition
      }
   }

   /*
    * more than 50% should approve
    */
   private function review_step_more_50( $review_data, $action_history_id ) {
      $current_assigned_reviews = 0;
      $current_rejected_reviews = 0;
      $current_approved_reviews = 0;

      // get the review action details from fc_action for the given history_id
      $ow_history_service = new OW_History_Service();
      $total_reviews = $ow_history_service->get_review_action_by_history_id( $action_history_id );
      if ( $total_reviews ) {
         foreach ( $total_reviews as $review ) {
            if ( $review->review_status == 'complete' ) {
               $current_approved_reviews ++;
            }
            if ( $review->review_status == 'unable' ) {
               $current_rejected_reviews ++;
            }
            if ( $review->review_status == 'assignment' ) {
               $current_assigned_reviews ++;
            }
         }
      }

      $total_reviews = $current_assigned_reviews + $current_rejected_reviews + $current_approved_reviews;

      $need = floor( $total_reviews / 2 ) + 1; //more than 50%

      if ( $current_approved_reviews >= $need && isset( $review_data["complete"] ) && $review_data["complete"] ) { // looks like we have more than 50% approved, lets complete this step.
         $new_action_history_id = $this->save_review_action( $review_data["complete"], $action_history_id, "complete" );

         // change review status on remaining/not completed tasks as "no_action"
         if ( isset( $review_data["assignment"] ) && $review_data["assignment"] ) {
            $this->change_review_status_to_no_action( $review_data["assignment"], $action_history_id );
         }

         return $new_action_history_id; // since we found our condition
      }

      if ( $current_rejected_reviews >= $need && isset( $review_data["unable"] ) && $review_data["unable"] ) { // looks like we have more than 50% rejected, we need to go to failure path.
         $new_action_history_id = $this->save_review_action( $review_data["unable"], $action_history_id, "unable" );

         // change review status on remaining/not completed tasks as "no_action"
         if ( isset( $review_data["assignment"] ) && $review_data["assignment"] ) {
            $this->change_review_status_to_no_action( $review_data["assignment"], $action_history_id );
         }

         return $new_action_history_id; // since we found our condition
      }

      /*
       * in case, we have equal number of approved and rejected reviews (2 approved and 2 rejected),
       * and to make a decision we need more than 2 (more than 50%)
       * and we have no more assignments left,
       * we should take the failure path
       */
      if ( $current_rejected_reviews == $current_approved_reviews && ! isset( $review_data["assignment"] ) ) {
         $new_action_history_id = $this->save_review_action( $review_data["unable"], $action_history_id, "unable" );
         return $new_action_history_id; // since we found our condition
      }
   }

   /*
    * Change review action to no action for people whose tasks were skipped
    */
   private function change_review_status_to_no_action( $ddata, $action_history_id ) {
      global $wpdb;

      for ( $i = 0; $i < count( $ddata ); $i ++ ) {
         $review_data = array(
            "review_status" => "no_action",
            "update_datetime" => current_time( 'mysql' )
         );

         $action_table = OW_Utility::instance()->get_action_table_name();
         $wpdb->update( $action_table, $review_data, array( "review_status" => "assignment", "action_history_id" => $action_history_id ) );
      }
   }

   /*
    * Save the review action data
    */
   private function save_review_action( $ddata, $action_history_id, $result ) {
      $ow_history_service = new OW_History_Service();
      $action = $ow_history_service->get_action_history_by_id( $action_history_id );

      $review_data = array(
         'action_status' => "assignment",
         'post_id' => $action->post_id,
         'from_id' => $action->ID,
         'create_datetime' => current_time( 'mysql' )
      );

      $next_assign_actors = array();
      $all_comments = array();
      $due_date = '';
      for ( $i = 0; $i < count( $ddata ); $i ++ ) {
         if ( ! in_array( $ddata[$i]["re_actor_id"], $next_assign_actors ) ) { //only add unique actors to the array
            $next_assign_actors[] = $ddata[$i]["re_actor_id"];
         }

         // combine all commments into one set
         $temp_comment = json_decode( $ddata[$i]["re_comment"], true );
         foreach ( $temp_comment as $temp_key => $temp_value ) {
            $exists = 0;
            foreach ( $all_comments as $all_key => $all_value ) {
               if ( $all_value["send_id"] === $temp_value["send_id"] ) { // if the comment already exists, then skip it
                  $exists = 1;
               }
            }
            if ( $exists == 0 ) {
               $all_comments[] = $temp_value;
            }
         }
         // TODO: temp fix - it takes the last action assigned step
         $next_step_id = $ddata[$i]["re_step_id"];

         //-----get minimal due date--------
         $temp1_date = OW_Utility::instance()->get_date_int( $ddata[$i]["re_due_date"] );
         if ( ! empty( $due_date ) ) {
            $temp2_date = OW_Utility::instance()->get_date_int( $due_date );
            $due_date = ( $temp1_date < $temp2_date ) ? $ddata[$i]["re_due_date"] : $due_date;
         } else {
            $due_date = $ddata[$i]["re_due_date"];
         }
      }

      $next_actors = implode( "@", $next_assign_actors );
      $review_data["comment"] = json_encode( $all_comments );
      if ( ! empty( $due_date ) ) {
         $review_data["due_date"] = $due_date;
      }
      $review_data["step_id"] = $next_step_id;

      // we have all the data to generated the next set of tasks

      $new_action_history_id = $this->save_action( $review_data, $next_actors, $action->ID );

      //--------post status change---------------
      $this->copy_step_status_to_post( $action->post_id, $action->step_id, $new_action_history_id, "edit" );  

      return $new_action_history_id;
   }

   /*
    * Internal function to change the workflow status to complete
    */
   private function change_workflow_status_to_complete_internal( $post_id, $workflow_complete_params ) {
      global $wpdb;

      $ow_history_service = new OW_History_Service();
      $history = $ow_history_service->get_action_history_by_id( $workflow_complete_params["history_id"] );
      $currentTime = current_time( 'mysql' );

      // Get history meta data
      $history_meta = array();
      $history_meta_json = null;
      $history_meta = apply_filters( 'owf_set_history_meta', $history_meta, $post_id, $workflow_complete_params );
      if ( count( $history_meta ) > 0 ) {
         $history_meta_json = json_encode( $history_meta );
      } else {
         $history_meta_json = null;
      }

      $data = array(
         'action_status'   => "complete",
         'step_id'         => $history->step_id,
         'assign_actor_id' => get_current_user_id(),
         'post_id'         => $post_id,
         'from_id'         => $workflow_complete_params["history_id"],
         'comment'         => "",
         'create_datetime' => $currentTime
      );

      $action_history_table = OW_Utility::instance()->get_action_history_table_name();
      $action_table = OW_Utility::instance()->get_action_table_name();
      $new_action_history_id = OW_Utility::instance()->insert_to_table( $action_history_table, $data );

      // update action table, if review was the last step in the process
      $wpdb->update( $action_table, array( "review_status"     => "complete",
                                           "update_datetime"   => current_time( 'mysql' )
                                    ),
                                    array( "action_history_id" => $history->ID ) );

      $ow_email = new OW_Email( );

      if ( $new_action_history_id ) {
         global $wpdb;
         // delete all the unsend emails for this workflow
         $ow_email->delete_step_email( $workflow_complete_params["history_id"], $workflow_complete_params["task_user"] );

         // update the step as processed
         $result = $wpdb->update( $action_history_table,
                        array(
                              'history_meta'  => $history_meta_json,
                              'action_status' => 'processed'
                        ),
                        array( 'ID' => $workflow_complete_params["history_id"] ) );

         if ( $workflow_complete_params["publish_datetime"] != null
              && ! $workflow_complete_params["publish_immediately"]) {
            $new_post_status = $this->copy_step_status_to_post( $post_id, $history->step_id, $new_action_history_id,
               $workflow_complete_params['current_page'], $workflow_complete_params["publish_datetime"], false );
         } else {
            $new_post_status = $this->copy_step_status_to_post( $post_id, $history->step_id, $new_action_history_id,
               $workflow_complete_params['current_page'], current_time( 'mysql' ), true );
         }

      }
      $return_array = array (
         "new_action_history_id" => $new_action_history_id,
         "new_post_status"       => $new_post_status
      );
      return $return_array;
   }

   /*
    * Abort the task for given history id
    * @global type $wpdb
    * @param type $history_id
    * @since 3.8
    */
   private function abort_the_workflow( $history_id, $comments="", $print_id = true ) {
      global $wpdb;
      $history_id = (int) $history_id;

      $ow_history_service = new OW_History_Service();
      $action = $ow_history_service->get_action_history_by_id( $history_id );

      $action_history_table = OW_Utility::instance()->get_action_history_table_name();

      $comment[] = array(
         "send_id" => get_current_user_id(),
         "comment" => $comments,
         "comment_timestamp" => current_time( "mysql" )
      );
      $data = array(
         "action_status"   => "aborted",
         "post_id"         => $action->post_id,
         "comment"         => json_encode( $comment ),
         "from_id"         => $history_id,
         "step_id"         => $action->step_id, // since we do not have the step id information for this
         "assign_actor_id" => get_current_user_id(), // since we do not have anyone assigned anymore.
         'create_datetime' => current_time( 'mysql' )
      );
      $action_table = OW_Utility::instance()->get_action_table_name();
      $new_history_id = OW_Utility::instance()->insert_to_table( $action_history_table, $data );
      $ow_email = new OW_Email( );
      if ( $new_history_id ) {
         // find all the history records for the given post id which has the status = "assignment"
         $post_action_histories = $ow_history_service->get_action_history_by_status( "assignment", $action->post_id );
         foreach ( $post_action_histories as $post_action_history ) {
            // delete all the unsend emails for this workflow
            $ow_email->delete_step_email( $post_action_history->ID );
            // update the current assignments to abort_no_action
            $wpdb->update( $action_history_table, array( "action_status" => "abort_no_action", "create_datetime" => current_time( 'mysql' ) ), array( "ID" => $post_action_history->ID ) );
            // change the assignments in the action table to processed
            $wpdb->update( $action_table, array( "review_status" => "abort_no_action", "update_datetime" => current_time( 'mysql' ) ), array( "action_history_id" => $post_action_history->ID ) );
         }
         $this->cleanup_after_workflow_complete( $action->post_id );

         do_action( 'owf_workflow_abort', $action->post_id );
         
         return $new_history_id;
      }

      return null;
   }

   /*
    * Get the post status from the connnection info, given the source and target steps.
    *
    * @param $post_id
    * @param $source_step_id
    * @param $target_step_id
    *
    * @return false|string
    */
   private function get_post_status_from_step_transition( $post_id, $source_step_id, $target_step_id ) {

      $step_status = "";

      $ow_workflow_service = new OW_Workflow_Service();

      // if the source and target step_ids are the same, we are most likely on the last step
      if ( $source_step_id == $target_step_id ) {

         $step = $ow_workflow_service->get_step_by_id( $target_step_id );
         $step_info = json_decode( $step->step_info );
         $process_type = $step_info->process;

         if ( $process_type == 'publish' ) { // if process type is publish, then set the step_status to "publish"
            $step_status = 'publish';
         } else {
            $step_status = get_post_status( $post_id );
            // TODO : handle other use cases when publish is NOT the last step, via "is Last Step?"
         }
      } else { // get the post_status from the connection info object.
         $step = $ow_workflow_service->get_step_by_id( $target_step_id );
         $workflow_id = $step->workflow_id;
         $workflow = $ow_workflow_service->get_workflow_by_id( $workflow_id );
         $connection = $ow_workflow_service->get_connection( $workflow, $source_step_id, $target_step_id );
         $step_status = $connection->post_status;
      }

      return $step_status;
   }
   
   /*
    * Claim error message 
    * @return html and validation message
    * @since 4.6
    */
   private function construct_claim_error_message( $validation_result ) {
      $error_message  = '<div class="info-setting claim-error">';
      $error_message .= '<div class="dialog-title"><strong>'; 
      $error_message .= __( 'Claim Error', 'oasisworkflow' );
      $error_message .= '</strong></div>';
      $error_message .= '<p>' . implode( "<br>", $validation_result ) . '</p>';
      $error_message .= '</div>';
      return $error_message;
   }
   
   /**
    * Set Task Assignment Meta
    * @param int $post_id
    * @param int $new_action_history_id
    * @param String $actors
    * @since 5.1
    */
   private function set_oasis_task_assignment_meta( $post_id, $new_action_history_id, $actors ) {     
      // send task assignment notification
      $current_user_id = get_current_user_id();
      if( empty( $current_user_id ) ) {
         $current_user_id = -1;
      }
      
      $step_data = array(
          'action_history_id' => $new_action_history_id,
          'actors'            => $actors,
          'current_user_id'   => $current_user_id
      );
      
      add_post_meta( $post_id, "_oasis_task_assignment", $step_data );
   }

   /**
    * Hook - admin_footer
    * Setup the sign off popup and enqueue the related scripts
    *
    * @since 2.0
    *
    */
   public function step_signoff_popup_setup() {
      global $wpdb, $chkResult;
      $selected_user = isset( $_GET['user'] ) ? intval( sanitize_text_field( $_GET['user'] ) ) : get_current_user_id();

      $chkResult = $this->workflow_submit_check( $selected_user );

      $inbox_service = new OW_Inbox_Service();

      if ( get_option( "oasiswf_activate_workflow" ) == "active" &&
           is_admin() &&
           preg_match_all( '/page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {

         if ( $chkResult == "inbox" ) {
            $this->enqueue_and_localize_submit_step_script();

            $inbox_service->enqueue_and_localize_script();
         } elseif ( current_user_can( 'ow_submit_to_workflow' ) &&
                     $chkResult == "submit" &&
                     is_admin() &&
                     preg_match_all( '/page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {

            include( OASISWF_PATH . "includes/pages/subpages/submit-workflow.php" );
            $this->enqueue_and_localize_submit_workflow_script();
         } elseif ( $chkResult == "makerevision" &&
                     is_admin() &&
                     preg_match_all( '/page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
            include( OASISWF_PATH . "includes/pages/subpages/make-revision.php" );
            $this->enqueue_and_localize_make_revision_script();

         } elseif ( current_user_can( 'ow_sign_off_step' ) &&
                    ! current_user_can( 'manage_options' ) && //is not administrator
                    $chkResult == "not-assigned" && //post is not assigned to the user
                    is_admin() && // is admin panel
                    preg_match_all( '/page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {

            // let's hide the "Save" button, so that the user cannot update the post
            wp_enqueue_script( 'owf_restrict_edit', OASISWF_URL . 'js/pages/subpages/restrict-edit.js', array( 'jquery' ), OASISWF_VERSION, true );
         } else {
            if ( current_user_can( 'ow_sign_off_step' ) && is_numeric( $chkResult ) &&
                 is_admin() && preg_match_all( '/page=oasiswf(.*)|post-new\.(.*)|post\.(.*)/', $_SERVER['REQUEST_URI'], $matches ) ) {
               include( OASISWF_PATH . "includes/pages/subpages/submit-step.php" );
               $this->enqueue_and_localize_submit_step_script();

               $inbox_service->enqueue_and_localize_script();
            }
         }

         $post_type = get_post_type();
         $post_status = "";

         // do not hide the ootb publish section for skip_workflow_roles option, but hide it if the post is in the workflow
         $show_workflow_for_post_types = get_option( 'oasiswf_show_wfsettings_on_post_types' );
         $row = $aborted = null;
         if ( isset( $_GET['post'] ) && sanitize_text_field( $_GET["post"] ) && isset( $_GET['action'] ) && sanitize_text_field( $_GET["action"] ) == "edit" ) {
            $post_id = intval( sanitize_text_field( $_GET["post"] ) );
            $post_status = get_post_status( $post_id );
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE post_id = %d AND action_status = 'assignment'", $post_id ) );
            
            // If revision post and aborted from workflow
            if( get_post_meta( $post_id, '_oasis_original', true ) ) {
               $aborted = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE post_id = %d AND action_status = 'aborted'", $post_id ) );
            }
         }

         $hide_ootb_publish_section = array(
            "skip_workflow_roles" => true,
            "show_workflow_for_post_types" => true
         );

         // additional filter for deciding if the user can skip the workflow or not.
         // for example, based on tag or categories etc.
         $post_id = get_the_ID();
         $owf_skip_workflow_filter = false;
         if ( has_filter( 'owf_skip_workflow' ) ) {
            $owf_skip_workflow_filter = apply_filters( 'owf_skip_workflow', $post_id );
         }

         // check the filter value

         if ( current_user_can( 'ow_skip_workflow' ) || $owf_skip_workflow_filter == true ) { // do not hide the ootb publish section for skip_workflow_roles option
            $hide_ootb_publish_section["skip_workflow_roles"] = false;
         } else {
            $hide_ootb_publish_section["skip_workflow_roles"] = true;
         }

         if ( is_array( $show_workflow_for_post_types ) && in_array( $post_type, $show_workflow_for_post_types ) ) { // do not show ootb publish section for oasiswf_show_wfsettings_on_post_types
            $hide_ootb_publish_section['show_workflow_for_post_types'] = true;
         } else {
            $hide_ootb_publish_section['show_workflow_for_post_types'] = false;
         }

         if ( $post_status == "publish" || $post_status == "future" ) { // we are dealing with published posts
            $revision_process_status = get_option( "oasiswf_activate_revision_process" );
            if ( $revision_process_status == "active" ) { // if revision process is active, then run the above conditions
               if ( $hide_ootb_publish_section["skip_workflow_roles"] == 1 &&
                    $hide_ootb_publish_section['show_workflow_for_post_types'] == 1 ) {
                  $this->ootb_publish_section_hide();
               }
            }
         } else { // we are dealing with unpublished post
            if ( $hide_ootb_publish_section["skip_workflow_roles"] == 1 &&
                 $hide_ootb_publish_section['show_workflow_for_post_types'] == 1 ) {
               $this->ootb_publish_section_hide();
            }
         }


         // if the item is in the workflow, hide the OOTB publish section
         if ( $row ) {
            $this->ootb_publish_section_hide();
         }

         // Add nonce to the post page
         echo "<input type='hidden' name='owf_claim_process_ajax_nonce' id='owf_claim_process_ajax_nonce' value='" . wp_create_nonce( 'owf_claim_process_ajax_nonce' ) . "'/>";

         wp_nonce_field( 'owf_make_revision_ajax_nonce', 'owf_make_revision' );
         wp_nonce_field( 'owf_exit_post_from_workflow_ajax_nonce', 'owf_exit_post_from_workflow' );

         //--------generate abort workflow link---------

         if ( current_user_can( 'ow_abort_workflow' ) ) {
            if ( $row ) {
               echo "<script type='text/javascript'>var exit_wfid = $row->ID ;</script>";
               $this->enqueue_and_localize_abort_script();
            }
            
            // If revision post and aborted from workflow
            if( $aborted ) {
               echo "<script type='text/javascript'>var revision_post_id_for_update = $post_id ;</script>";
               $this->enqueue_and_localize_update_publish_script();
            }
         }
      }
   }

   /**
    * Hook - redirect_post_location
    * Call submit to workflow via POST action
    * Call workflow complete via POST action
    *
    * The idea is to first save the post with the latest changes and then take the action
    *
    * @param string $location
    * @param int $post_id
    * @return string
    */
   public function workflow_submit_action( $location, $post_id ) {
      if ( isset( $_POST["save_action"] ) && $_POST["save_action"] == "submit_post_to_workflow" ) {
         $this->submit_post_to_workflow();
      }

      if ( isset( $_POST["save_action"] ) && $_POST["save_action"] == "workflow_complete" ) {
         $original_post_id = get_post_meta( $post_id, '_oasis_original', true );
         if ( ! empty( $original_post_id ) ) { // we are dealing with a revision post
            // hook for revision complete
            do_action("owf_revision_workflow_complete", $post_id );
         }
      }

      return $location;
   }

   /**
    * Hook - get_edit_post_link
    * Add Oasis Workflow sign off buttons on the edit post link, if the item is in workflow
    *
    */
   public function oasis_edit_post_link( $url, $post_id, $context ) {
      global $wpdb;

      // lets check given post_id is in workflow
      $row_id = (int) $wpdb->get_var(
         $wpdb->prepare( "SELECT AH.ID FROM " . OW_Utility::instance()->get_action_history_table_name() ." AH
                              LEFT OUTER JOIN " . OW_Utility::instance()->get_action_table_name() ." A ON AH.ID = A.action_history_id
                              AND A.review_status = 'assignment'
                              WHERE AH.action_status = 'assignment'
                              AND (AH.assign_actor_id = %d OR A.actor_id = %d )
                              AND post_id = %d LIMIT 0, 1",
            get_current_user_id(), get_current_user_id(), $post_id ) );

      if ( $row_id && $row_id > 0 ) {
         $new_url = $url . '&oasiswf=' . $row_id;
      } else {
         $new_url = $url;
      }

      return $new_url;
   }

   /**
    * Redirect to the list page after submit to workflow
    *
    * @param $post_id
    * @param $new_action_history_id
    *
    * @since 4.5
    */
   public function redirect_after_workflow_submit( $post_id, $new_action_history_id ) {
      if ( ! isset( $_POST[ 'auto_submit_btn' ] ) && ! isset( $_POST[ 'ow_fe_submit_to_workflow' ] ) ) {
         // Filter to redirect user to a custom url
         // Redirect user to post/page list page
         $post_type = get_post_type( $post_id );
         if ( $post_type == 'post' ) {
            $link = admin_url() . "edit.php";
         } else {
            $link = admin_url() . "edit.php?post_type=" . $post_type;
         }
         $link = apply_filters( 'owf_redirect_after_workflow_submit', $link, $post_id );
         wp_redirect( $link );
         exit();
      }
   }
   
   /**
    * Loop through the list of actors and send step email to them.
    *
    * @param $post_id
    * @param $post_object
    */
   public function send_task_notification_after_save( $post_id, $post_object ) {
    
      // Sanitize incoming data
      $post_id    = intval( $post_id );
      $post_type  = $post_object->post_type;  
      
      // Return if this is an auto save
      if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
          return;
      }
      
      // Return if the function is called for saving revision.
      if ( $post_type == 'revision' || $post_type == 'auto-draft' ) {
          return;
      } 
     
      $ow_email = new OW_Email( );
     
      // Get current looged in user id
      $current_user_id = get_current_user_id();

      $task_assignment = get_post_meta( $post_id, '_oasis_task_assignment' );  
      
      foreach( $task_assignment as $assignment ) {

         OW_Utility::instance()->logger("Sending Task Assignment Email");
        
         $task_user_id            = $assignment['current_user_id'];
         $new_action_history_id   = $assignment['action_history_id'];
         $actors                  = $assignment['actors'];
        
         if( $task_user_id == $current_user_id ) {
           if ( is_numeric( $actors ) ) {
               $arr[] = $actors;
            } else {
               $arr = explode( "@", $actors );
            }
            for ( $i = 0; $i < count( $arr ); $i ++ ) {
               if ( ! $arr[ $i ] ) {
                  continue;
               }
                
               $ow_email->send_step_email( $new_action_history_id, $arr[ $i ] ); // send mail to the actor .
            }
         }
         delete_post_meta( $post_id , '_oasis_task_assignment', $assignment );
     }
   }
}

// construct an instance so that the actions get loaded
$ow_process_flow = new OW_Process_Flow();
add_action( 'admin_footer', array( $ow_process_flow, 'step_signoff_popup_setup' ) );
add_filter( 'redirect_post_location', array( $ow_process_flow, 'workflow_submit_action' ), 8, 2 );
add_filter( 'get_edit_post_link', array( $ow_process_flow, 'oasis_edit_post_link' ), 10, 3 );

// Trigger on post save
add_action( 'save_post', array( $ow_process_flow, 'check_unauthorized_post_update' ), 10, 1 );

add_action( 'edit_post', array( $ow_process_flow, 'send_task_notification_after_save' ), 15, 2 );
?>