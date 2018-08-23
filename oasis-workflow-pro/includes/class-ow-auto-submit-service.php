<?php

/*
 * Service class for the Auto Submit
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.2
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/*
 * OW_Auto_Submit_Service Class
 *
 * @since 4.2
 */

class OW_Auto_Submit_Service {

   /*
    * Set things up.
    *
    * @since 4.2
    */
   public function __construct() {
      // auto submit articles
      add_action( 'oasiswf_auto_submit_schedule', array( $this, 'auto_submit_articles' ) );
      add_action( 'owf_workflow_delete', array( $this, 'delete_auto_submit_associated_workflows' ), 10, 1 );
   }

   /**
    * Hook - oasiswf_auto_submit_schedule
    * Auto submit articles to the workflow - invoked via cron
    *
    * Checks for the auto submit settings, looks up the workflows allowed for auto submit
    * Submits any unsubmitted articles into the workflow
    *
    * @return int count of submitted items
    *
    * @since 2.0
    * @since 3.3 ignore "enable auto submit" if the request comes from "trigger auto submit once"
    */
   public function auto_submit_articles( $ignore_enable_auto_submit = false ) {

      $auto_submit_settings = get_option( 'oasiswf_auto_submit_settings' );

      /**
       * Do not check whether enable auto-submit is on/off when the request comes from
       * Trigger auto-submit just once
       * Set the administrator as user for the duration of the cron job.
       */
      if ( ! $ignore_enable_auto_submit ) {
         // if auto submit is activated then proceed
         if ( $auto_submit_settings['auto_submit_enable'] == "active" ) {
            $this->set_cron_job_user();
         } else {
            // nothing to submit, since auto submit is not enabled
            return 0;
         }
      }

      // if at least one status is specified then proceed
      $auto_submit_stati = $auto_submit_settings['auto_submit_stati'];
      if ( count( $auto_submit_stati ) == 0 ) {
         //nothing to submit, since no post statuses specified.
         return 0;
      }
      $ow_process_flow      = new OW_Process_Flow();
      $workflows            = array();
      $applicable_workflows = isset( $auto_submit_settings['auto_submit_workflows'] ) ? $auto_submit_settings['auto_submit_workflows'] : "";
      
      if ( ! empty( $applicable_workflows ) ) {
         $workflows = $this->build_auto_submit_parameters( $applicable_workflows );
      }
      
      $auto_submit_post_count = ( $auto_submit_settings['auto_submit_post_count'] != null ) ? $auto_submit_settings['auto_submit_post_count'] : "5";
      $auto_submit_due_days   = ( $auto_submit_settings['auto_submit_due_days'] != null ) ? $auto_submit_settings['auto_submit_due_days'] : "1";
      $auto_submit_comments   = $auto_submit_settings['auto_submit_comment'];

      // get all posts which satisfy the criteria
      $unsubmitted_posts = $this->get_unsubmitted_posts( $auto_submit_stati, $auto_submit_post_count );
      OW_Utility::instance()->logger( "Number of unsubmitted posts/pages:" . count( $unsubmitted_posts ) );

      if ( count( $unsubmitted_posts ) <= 0 ) {
         OW_Utility::instance()->logger( "No unsubmitted posts/pages found, so exiting. " );
         return 0;
      }

      $submitted_posts_count = 0;

      OW_Utility::instance()->logger( "current site:" . $GLOBALS['blog_id'] );
      
      foreach ( $workflows as $wf ) {
         OW_Utility::instance()->logger( "current workflow: " . $wf['workflow_name'] );   
         $revised_post     = $wf['revised_post'];
         $new_post         = $wf['new_post'];
         $keywords         = trim( $wf['keywords'] );
         $first_step_id    = $wf['first_step_id'];
         $post_status      = $wf['post_status'];
         $applicable_team  = $wf['applicable_team'];
         $assignee_roles   = $wf['assignee_roles'];
         $applicable_post_type = $wf['applicable_post_types'];
         
         if ( count( $workflows ) > 1 && empty( $keywords ) ) { // no keywords defined and there are more than one workflow for auto submit.
            OW_Utility::instance()->logger( "No keywords found, but multiple workflows are assigned, so simply continue" );
            continue;
         }

         $auto_submit_keywords = array();
         if ( ! empty ( $keywords ) ) {
            $keyword_array = explode( ",", $keywords );
            // trim all the keywords
            $auto_submit_keywords = array_map( 'trim', $keyword_array );
         }

         OW_Utility::instance()->logger( "keywords array: " );
         OW_Utility::instance()->logger( $auto_submit_keywords );

         foreach ( $unsubmitted_posts as $i => $row ) {

            $post_id = $row->ID;
            $post_type = get_post_type( $post_id );
            
            /**
             * if multiple workflows and no matching keyword found, then continue
             * if only one workflow, then do not check for this condition
             */
            if ( ! empty( $auto_submit_keywords ) && $this->has_no_matching_keyword( $row, $auto_submit_keywords ) ) {
               continue;
            }

            /**
             * If revised post is set and new post is not set then check post meta oasis_original exist or not
             * if not exist then its a new post/page
             */
            if ( $new_post == 0 && $revised_post == 1 && ! get_post_meta( $post_id, '_oasis_original', true ) ) {
               continue;
            }

            /**
             * If new post is set but not revised post then check post meta oasis_original exist or not
             * If exist then its revised post/page
             */
            if ( $revised_post == 0 && $new_post == 1 && get_post_meta( $post_id, '_oasis_original', true ) ) {
               continue;
            }
            
            /**
             * If post type not available in applicable post type than don't send for auto submit
             */
            if ( ! in_array( $post_type, $applicable_post_type ) ) {
               continue;
            }

            // submit the post to workflow
           
            if ( get_option( 'oasiswf_team_enable' ) == 'yes' && ( ! empty( $applicable_team ) ) ) {              
               $ow_teams_service = new OW_Teams_Service();               
               $actors = $ow_teams_service->get_team_members( $applicable_team, $assignee_roles, $post_id );
               $actors = implode( "@", $actors );
               OW_Utility::instance()->logger( "final applicable team:" . $applicable_team );
               OW_Utility::instance()->logger( "users for auto submit from the team:" . $actors );
            } else {
               $users = $ow_process_flow->get_users_in_step( $first_step_id, $post_id );
               if ( ! isset( $users["users"] ) ) { // we didn't find any users for the step
                  OW_Utility::instance()->logger( "We didn't find any users for this step, skipping this post:" . $post_id );
                  continue;
               }
               $actors = "";
               foreach ( $users["users"] as $user ) {
                  if ( $actors != "" ) {
                     $actors .= "@";
                  }
                  $actors .= $user->ID;
               }
               OW_Utility::instance()->logger( "users for auto submit:" . $actors );
            }

            $due_date = OW_Utility::instance()->get_pre_next_date( date( "m/d/Y" ), "next", $auto_submit_due_days );
            if ( $actors != "" ) {
               $workflow_submit_data                          = array();
               $workflow_submit_data['step_id']               = $first_step_id;
               $workflow_submit_data['actors']                = $actors;
               $workflow_submit_data['due_date']              = OW_Utility::instance()->format_date_for_display_and_edit( $due_date );
               $workflow_submit_data['comments']              = $auto_submit_comments;
               $workflow_submit_data['team_id']               = $applicable_team;
               $workflow_submit_data['pre_publish_checklist'] = "";                 
               $ow_process_flow->submit_post_to_workflow_internal( $post_id, $workflow_submit_data );
               // Lets update the post status when after submit post to workflow     
               if ( $first_step_id ) {
                  wp_update_post( array(
                     'ID'          => $post_id,
                     'post_status' => $post_status
                  ) );
               }

               OW_Utility::instance()->logger( $post_id . " successfully submitted." );

               // increment the count of successfully submitted posts
               $submitted_posts_count ++;

               // remove the post from the list of unsubmitted posts
               unset( $unsubmitted_posts[ $i ] );
            }
         }
      }

      return $submitted_posts_count;
   }
   
   /**
    * Hook - owf_workflow_delete
    * Delete auto submit associated workflows if workflow is deleted
    * @param int $wf_id workflow id
    * @since 4.9
    */
   public function delete_auto_submit_associated_workflows( $deleted_wf_id ) {

      // sanitize incoming data
      $deleted_wf_id = intval( $deleted_wf_id );

      // find auto submit associated workflows
      $auto_submit_settings = get_option( 'oasiswf_auto_submit_settings' );  
      $associated_workflows = isset( $auto_submit_settings['auto_submit_workflows'] ) ? $auto_submit_settings['auto_submit_workflows'] : "";

      if ( ! empty( $associated_workflows ) ) {
         foreach ( $associated_workflows as $associated_wf_id => $keywords ) {
            if ( $associated_wf_id == $deleted_wf_id ) { // if found unset it from $associated_workflows
               unset( $associated_workflows[ $associated_wf_id ] );
            }
         }

         // set the updated associated workflows
         $auto_submit_settings['auto_submit_workflows'] = $associated_workflows;
         update_option( 'oasiswf_auto_submit_settings', $auto_submit_settings );
      }
   }
   
   /**
    * Set the administrator as user for the duration of the cron job.
    * @since 4.4
    */
   private function set_cron_job_user() {
      // If we're not doing cron, bail out.
      if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) {
         return;
      }
      $args       = array( 'role' => 'Administrator', 'number' => 1 );
      $oasis_user = get_users( $args );
      if ( in_array( 'administrator', $oasis_user[0]->roles ) ) {
         // Set the user for the duration of the cron job.
         wp_set_current_user( $oasis_user[0]->ID );
      }
   }

   /**
    * Fetch the required auto submit parameters
    * @param array $applicable_workflows
    * @return array $data
    * @since 4.9
    */ 
   private function build_auto_submit_parameters( $applicable_workflows ) {
      
      $ow_workflow_service  = new OW_Workflow_Service();   
      $data                 = array();
      $workflow_ids         = array();
      $keywords             = array();
      $applicable_team      = null;
      
      /*
       * Fetch the applicable workflow ids and keyword.
       * Set it into array
       */
      foreach ( $applicable_workflows as $wf_id => $keywords ) {
         $workflow_ids[] =  $wf_id;
      }
      
      // get multiple workflow by passing $workflow_ids array
      $all_workflows = $ow_workflow_service->get_multiple_workflows_by_id( $workflow_ids );
      
      /*
       * For each applicable workflow get required parameters
       */
      foreach ( $all_workflows as $workflow ) {
         $wf_id  = $workflow->ID;
         $workflow_name = $workflow->name;
         $workflow_applicable_to = @unserialize( $workflow->wf_additional_info );    
         $is_wf_applicable_to_revised_post = '';
         $is_wf_applicable_to_new_post = '';
         if ( is_array( $workflow_applicable_to ) && ! empty( $workflow_applicable_to ) ) {
            $is_wf_applicable_to_revised_post = $workflow_applicable_to['wf_for_revised_posts'];
            $is_wf_applicable_to_new_post = $workflow_applicable_to['wf_for_new_posts'];
         }
         
         // get workflow applicable post types
         $wf_applicable_post_types = $workflow_applicable_to['wf_for_post_types'];
         
         // If empty applicable post types than consider all post types
         if( empty( $wf_applicable_post_types ) ) {
            $all_post_types = OW_Utility::instance()->owf_get_post_types();
            foreach( $all_post_types as $post_type ) {
               $wf_applicable_post_types[] = $post_type['name'];
            }
         }
         
         // get first step post status
         $wf_info = json_decode( $workflow->wf_info );
         if ( $wf_info->first_step && count( $wf_info->first_step ) == 1 ) {
            $first_step = $wf_info->first_step[0];
            if ( is_object( $first_step ) &&
                 isset( $first_step->post_status ) &&
                 ! empty( $first_step->post_status )
               ) {
                  $post_status = $first_step->post_status;
            }
         }
         
         // Get step details
         $steps   = $ow_workflow_service->get_first_step_internal( $wf_id );
         $first_step_id = $steps["first"][0][0];
         $step    = $ow_workflow_service->get_step_by_id( $first_step_id );
         
         // Get applicable teams
         if ( get_option( 'oasiswf_team_enable' ) == 'yes' ) {
            $ow_teams_service = new OW_Teams_Service();
            $teams = $ow_teams_service->get_teams_for_workflow( $wf_id );
            // if multiple teams are associated,then get the first team from the $teams results
            if ( $teams ) {
               $applicable_team = $teams[0]->ID;
            }
         }
         
         $step_info = json_decode( $step->step_info );
         $assignee_roles = isset( $step_info->task_assignee->roles ) ? array_flip( $step_info->task_assignee->roles ) : null;

         // Map the parameters into array
         $data[] = array(
            'workflow_id'           => $wf_id,
            'workflow_name'         => $workflow_name,
            'keywords'              => $applicable_workflows[ $wf_id ],
            'revised_post'          => $is_wf_applicable_to_revised_post,
            'new_post'              => $is_wf_applicable_to_new_post,
            'post_status'           => $post_status,
            'first_step_id'         => $first_step_id,
            'assignee_roles'        => $assignee_roles,
            'applicable_team'       => $applicable_team,
            'applicable_post_types' => $wf_applicable_post_types
         );
      }  
      return $data;
   }

   /**
    * Function - to match the keywords with auto submit assaigned workflow keywords 
    * @param object $post
    * @param array $auto_submit_keywords
    * @return boolean
    */
   public function has_no_matching_keyword( $post, $auto_submit_keywords ) {
      $auto_submit_settings = get_option( 'oasiswf_auto_submit_settings' );

      // Fetch keywords like from custom taxonomy to match with auto submit keywords
      $extra_keywords = apply_filters( "owf_auto_submit_extra_keywords", $post );
      
      if ( ( $auto_submit_settings['search_post_title'] == 'yes' &&
             OW_Utility::instance()->str_array_pos( $post->post_title, $auto_submit_keywords ) ) ||
           ( $auto_submit_settings['search_post_tags'] == 'yes' &&
             OW_Utility::instance()->is_post_tag_in_array( $post->ID, $auto_submit_keywords ) ) ||
           ( $auto_submit_settings['search_post_categories'] == 'yes' &&
             OW_Utility::instance()->is_post_category_in_array( $post->ID, $auto_submit_keywords ) ) ||
           ( ! empty( $extra_keywords ) && $this->is_extra_keywords_in_array( $extra_keywords, $auto_submit_keywords ) )  
      ) {
         return false;
      }

      return true;
   }
   
   /**
    * Check the extra keywords available in ayto submit keywords array.
    * @param array $extra_keywords
    * @param array $keyword_array
    * @return boolean
    * @since 5.3
    */
   public function is_extra_keywords_in_array( $extra_keywords, $keyword_array ) {
      $search_array = array_map( 'strtolower', $keyword_array );
      foreach ( $extra_keywords as $keywords ) {
         if ( in_array( strtolower( $keywords ), $search_array ) ) {
            return true;
         }
      }
      return false;
   }

   /*
    * Get all unsubmitted posts
    *
    * @param $post_status_list
    * @param $post_count
    *
    * @return array|null|object
    */
   private function get_unsubmitted_posts( $post_status_list, $post_count ) {
      global $wpdb;

      $post_count = intval( sanitize_text_field( $post_count ) );

      // create placeholder for post status
      $string_place_holders          = array_fill( 0, count( $post_status_list ), '%s' );
      $place_holders_for_post_status = implode( ", ", $string_place_holders );

      $all_unsubmitted_posts        = array();
      $post_type_condition          = "";
      $show_workflow_for_post_types = get_option( 'oasiswf_show_wfsettings_on_post_types' );
      if ( is_array( $show_workflow_for_post_types ) ) {
         $show_workflow_for_post_types_list = "'" . implode( "', '", $show_workflow_for_post_types ) . "'";
         $post_type_condition               = " posts.post_type in (" . $show_workflow_for_post_types_list . ") AND ";
      }

      $sql = "SELECT distinct posts.ID, posts.post_title FROM {$wpdb->prefix}posts posts 
         WHERE posts.post_status in (" . $place_holders_for_post_status . ")
	   	AND " . $post_type_condition . "( NOT EXISTS ( SELECT * from {$wpdb->prefix}postmeta postmeta1 WHERE postmeta1.meta_key = '_oasis_is_in_workflow' and posts.ID = postmeta1.post_id ) OR
		   	EXISTS (SELECT * from {$wpdb->prefix}postmeta postmeta2 WHERE postmeta2.meta_key = '_oasis_is_in_workflow' AND postmeta2.meta_value = '0' and posts.ID = postmeta2.post_id))
		   	order by post_modified_gmt
		   	limit 0, " . $post_count;

      $all_unsubmitted_posts = $wpdb->get_results( $wpdb->prepare( $sql, $post_status_list ) );

      return $all_unsubmitted_posts;
   }

}

// construct an instance so that the actions get loaded
$ow_auto_submit_service = new OW_Auto_Submit_Service();
?>