<?php

/**
 * Oasis Workflow APIs
 *
 * @copyright   Copyright (c) 2017, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.9
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/**
 * OWAPI class 
 * @since 4.9
 */

class OW_API {
   
   /**
    * Submit post to workflow API
    * @since 4.9
    */
   public static function submit_to_workflow( $post_id, $workflow_data ) {
      // sanitize post_id
      $post_id        = intval( $post_id );
      $submitted_data = array_map( 'esc_attr', $workflow_data );
      $workflow_comments = isset( $submitted_data['workflow_comments'] ) && ! empty( $submitted_data['workflow_comments'] ) ? $submitted_data['workflow_comments'] : "";
      $team_id        = isset( $submitted_data['team_id'] ) && ! empty( $submitted_data['team_id'] ) ? $submitted_data['team_id'] : "";
      $due_date       = null;
      
      $ow_workflow_service = new OW_Workflow_Service();
      $ow_process_flow = new OW_Process_Flow();
      
      $is_post_revision = wp_is_post_revision( $post_id );
      if ( $is_post_revision !== FALSE ) {
         $post_id = $is_post_revision;
      }

      // capability check
      if ( ! OW_Utility::instance()->is_post_editable( $post_id ) ) {
         wp_die( __( 'You are not allowed to create/edit post.' ) );
      }
      
      $is_in_workflow = get_post_meta( $post_id, '_oasis_is_in_workflow', true );
      $is_post_revision = get_post_meta( $post_id, '_oasis_original', true );
     
      if ( ! empty( $is_in_workflow ) && $is_in_workflow == 1 && ( ! empty( $is_post_revision ) ) ) {
         return;
      }
      
      // submit the post to workflow
      $steps   = $ow_workflow_service->get_first_step_internal( $submitted_data['workflow_id'] );
      $step_id = $steps["first"][0][0];
         
      $users = $ow_process_flow->get_users_in_step( $step_id, $post_id );
      if ( ! isset( $users["users"] ) ) { // we didn't find any users for the step
         wp_die( __( 'No users found for this workflow. Please check for your workflow setup.', 'oasisworkflow' ) );
      }

      // looks like we found some assignees for this workflow
      $actors = "";
      foreach ( $users["users"] as $user ) {
         if ( $actors != "" ) {
            $actors .= "@";
         }
         $actors .= $user->ID;
      }
 
         
      // if teams add-on is active, validate if the team has the users
      if ( get_option( 'oasiswf_team_enable' ) == 'yes' &&  $team_id !== "" ) {
         $actors = apply_filters( 'owf_get_team_members', $team_id, $step_id, $post_id );
         if ( empty( $actors ) ) { // we didn't find any users for the step
             wp_die( __( 'No users found for this team. Please check for your team setup.', 'oasisworkflow' ) );
         }
      } 
 
      // get the due date
      $default_due_days = get_option( 'oasiswf_default_due_days' );
      if ( $default_due_days !== '' ) {
         $due_date = $ow_process_flow->get_submit_workflow_due_date( $step_id );
      }
      
      $workflow_submit_data                          = array();
      $workflow_submit_data['step_id']               = $step_id;
      $workflow_submit_data['actors']                = $actors;
      $workflow_submit_data['due_date']              = $due_date;
      $workflow_submit_data['comments']              = $workflow_comments;
      $workflow_submit_data['team_id']               = $team_id;
      $workflow_submit_data['pre_publish_checklist'] = "";
      $workflow_submit_data['publish_date']          = "";
      
      $ow_process_flow->submit_post_to_workflow_internal( $post_id, $workflow_submit_data );
   }
   
}
?>