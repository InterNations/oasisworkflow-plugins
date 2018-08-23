<?php

/**
 * Helper class for Workflow Emails
 *
 * @copyright   Copyright (c) 2017, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.6
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/**
 * OW_Email_Settings_Helper Class
 *
 * @since 4.6
 */
class OW_Email_Settings_Helper {
   
   // define the email placeholder constants
	const FIRST_NAME = "{first_name}";
	const LAST_NAME = "{last_name}";
	const POST_TITLE = "{post_title}";
	const POST_CATEGORY = "{category}";
	const POST_LAST_MODIFIED_DATE = "{last_modified_date}";
	const POST_PUBLISH_DATE = "{publish_date}";
   const POST_AUTHOR = "{post_author}";
   const BLOG_NAME = "{blog_name}";
   const CURRENT_USER = "{current_user}";

   // email types
   const POST_PUBLISH_EMAIL = "post-published";
   const REVISED_POST_PUBLISH_EMAIL = "revised-post-published";
   const UNAUTHORIZED_UPDATE_EMAIL = "unauthorized-update";
   const TASK_CLAIMED_EMAIL = "task-claimed";
   const POST_SUBMITTED_EMAIL = "post-submitted";
   const WORKFLOW_ABORT_EMAIL = "workflow-abort";

   
   /**
	 * Set things up.
	 *
	 * @since 4.6
	 */
	public function __construct() {
      
	}
      
   /**
    * set email types
    * @return array
    * @since 4.6
    */
   public function email_types() {
      return array(
          'post_publish' => __( 'Post Publish Notification', 'oasisworkflow' ),
          'revised_post' => __( 'Revised Post Published Notification', 'oasisworkflow' ),
          'unauthorized_update' => __( 'Unauthorized Update Notification', 'oasisworkflow' ),
          'task_claim' =>  __( 'Task Claimed Notification', 'oasisworkflow' ),
          'post_submit' => __( 'Post Submit Notification', 'oasisworkflow' ),
          'workflow_abort' => __( 'Workflow Abort Notification', 'oasisworkflow' )
      );
   }
   
   /**
    * Set email type option values for email templates
    * @param string $sel_type default selected email type will be post_publish
    * @return HTML of email type options tag
    * @since 4.6
    */
   public function get_email_type_dropdown( $sel_type = 'post_publish' ){
		$email_type = $this->email_types();
	   $option = '';
	   foreach ( $email_type as $key => $val ) {
			$option .= '<option value="'. $key . '" ' . selected( $sel_type, $key, FALSE ) . '>' . __( $val, 'oasisworkflow' ).'</option>';
	   }
	   echo $option;
	}
   
   /**
    * Set user roles for emails
    * @return array
    * @since 4.6
    */
   public function email_user_roles() {
      return array(
          'post_author'             => __( 'Post Author(s)', 'oasisworkflow' ),
          'administrator'           => __( 'Administrator(s)', 'oasisworkflow' ),
          'post_submitter'          => __( 'Post Submitter', 'oasisworkflow' ),
          'current_task_assignees'  => __( 'Current Task Assignees', 'oasisworkflow' )
      );
   }

   /**
    * Create HTML checkbox section for email user roles
    *
    * @param $setting_name
    * @param $action
    * @param $selected_users
    *
    * @return HTML for roles checkbox
    * @since 4.6
    */
   public function ow_email_user_roles( $setting_name, $action, $selected_users ) {
      $selected_row = '';
      $email_user_roles = $this->email_user_roles();
      foreach ( $email_user_roles as $role => $display_name ) {
         // for certain email settings, we do not want to show the current task assignees as an option
         if ( ( $action == OW_Email_Settings_Helper::POST_PUBLISH_EMAIL ||
                $action == OW_Email_Settings_Helper::REVISED_POST_PUBLISH_EMAIL ||
                $action == OW_Email_Settings_Helper::POST_SUBMITTED_EMAIL ) &&
              $role == "current_task_assignees"
         ) {
            continue;
         }

         if ( ! empty( $selected_users ) && is_array( $selected_users ) && in_array( esc_attr( $role ), $selected_users ) ) { // preselect specified role
          	$checked = " ' checked='checked' ";
         } else {
         	$checked = '';
         }

         $selected_row .= "<label class='owf-email-checkbox'><input type='checkbox' class='owf-checkbox'
					name='" . $setting_name . "' value='" . esc_attr( $role ) . "'" . $checked . "'/>";
         $selected_row .= __( $display_name, "oasisworkflow" );
         $selected_row .= "</label>";
      }
      echo $selected_row;
   }
   
   /**
    * Default subject for post publish notification 
    * @return email subject text $default_subject
    * @since 4.6
    */
   public function get_post_publish_subject() {
      $default_subject = "[{blog_name}]" . __( " Your article has been published.", "oasisworkflow" );
      return $default_subject;
   }
   
   /**
    * Default template text for post publish notification content  
    * @return email message text $default_email_body  
    * @since 4.6
    */
   public function get_post_publish_content() {
      $default_email_body =  __( 'Hello ', 'oasisworkflow' ) . '{first_name}' . ",\n\n";
      $default_email_body .= __('Your article ', 'oasisworkflow' ). '{post_title}' . '';
      $default_email_body .= __( ' has been published on ', 'oasisworkflow' ) . '{blog_name}' .".\n\n";
      $default_email_body .= __( 'Thanks.', 'oasisworkflow' );
      return $default_email_body;
   }
   
   /**
    * Default subject for revised post publish notification 
    * @return email subject text $default_subject
    * @since 4.6
    */
   public function get_revised_post_publish_subject() {
      $default_subject = "[{blog_name}]" . __( " Your revised article has been published.", "oasisworkflow" );
      return $default_subject;
   }
   
   /**
    * Default template text for revised post publish notification content   
    * @return email message text $default_email_body  
    * @since 4.6
    */
   public function get_revised_post_publish_content() {
      $default_email_body =  __( 'Hello ', 'oasisworkflow' ) . '{first_name}' . ",\n\n";
      $default_email_body .= __('Your revised article ', 'oasisworkflow' ). '{post_title}' . '';
      $default_email_body .= __( ' has been published on ', 'oasisworkflow' ) . '{blog_name}' .".\n\n";
      $default_email_body .= __( 'Thanks.', 'oasisworkflow' );
      return $default_email_body;
   }
   
   /**
    * Default subject for unauthorized update notification 
    * @return email subject text $default_subject
    * @since 4.6
    */
   public function get_unauthorized_update_subject() {
      $default_subject = "[{blog_name}]" . __( " Article was updated outside the workflow.", "oasisworkflow" );
      return $default_subject;
   }
   
   /**
    * Default template text for runauthorized update notification content  
    * @return email message text $default_email_body   
    * @since 4.6
    */
   public function get_unauthorized_update_content() {
      $default_email_body =  __( 'Hello ', 'oasisworkflow' ) . '{first_name}' . ",\n\n";
      $default_email_body .= '{current_user}, ' . __('who is not part of the assignee list has updated the article ', 'oasisworkflow' ). '{post_title}' . '';
      $default_email_body .= __( ' outside the workflow on ', 'oasisworkflow' ) . '{blog_name}' .".\n\n";
      $default_email_body .= __( 'Thanks.', 'oasisworkflow' );
      return $default_email_body;
   }
   
   /**
    * Default subject for task claimed notification 
    * @return email subject text $default_subject
    * @since 4.6
    */
   public function get_task_claimed_subject() {
      $default_subject = "[{blog_name}]" . __( " Task claimed.", "oasisworkflow" );
      return $default_subject;
   }
   
   /**
    * Default template text for task clamied notification content   
    * @return email message text $default_email_body  
    * @since 4.6
    */
   public function get_task_claimed_content() {
      $default_email_body =  __( 'Hello ', 'oasisworkflow' ) . '{first_name}' . ",\n\n";
      $default_email_body .=  __('Another user has claimed the task for the article ', 'oasisworkflow' ). '{post_title}' . '.';
      $default_email_body .= __( ' Please ignore the task. ', 'oasisworkflow' ) ."\n\n";
      $default_email_body .= __( 'Thanks.', 'oasisworkflow' );
      return $default_email_body;
   }
   
   /**
    * Default subject for post submit notification 
    * @return email subject text $default_subject
    * @since 4.6
    */
   public function get_post_submit_subject() {
      $default_subject = "[{blog_name}]" . __( " Your article has been submitted.", "oasisworkflow" );
      return $default_subject;
   }
   
   /**
    * Default template text for post submit notification content   
    * @return email message text $default_email_body  
    * @since 4.6
    */
   public function get_post_submit_content() {
      $default_email_body =  __( 'Hello ', 'oasisworkflow' ) . '{first_name}' . ",\n\n";
      $default_email_body .= __('Your article ', 'oasisworkflow' ). '{post_title}' . '';
      $default_email_body .= __( ' has been successfully submitted to the workflow on ', 'oasisworkflow' ) . '{blog_name}' .".\n\n";
      $default_email_body .= __( 'Thanks.', 'oasisworkflow' );
      return $default_email_body;
   }
   
   /**
    * Default subject for workflow abort notification 
    * @return email subject text $default_subject
    * @since 4.6
    */
   public function get_workflow_abort_subject() {
      $default_subject = "[{blog_name}]" .  __( " Workflow has been aborted.", "oasisworkflow" );
      return $default_subject;
   }
   
   /**
    * Default template text for workflow abort notification content   
    * @return email message text $default_email_body  
    * @since 4.6
    */
   public function  get_workflow_abort_content() {
      $default_email_body =  __( 'Hello ', 'oasisworkflow' ) . '{first_name}' . ",\n\n";
      $default_email_body .= __('Your article ', 'oasisworkflow' ). '{post_title}' . '';
      $default_email_body .= __( ' has been aborted from the workflow on ', 'oasisworkflow' ) . '{blog_name}' .".\n\n";
      $default_email_body .= __( 'If you have further questions regarding your article, please contact the administrator. ', 'oasisworkflow' )."\n\n";
      $default_email_body .= __( 'Thanks.', 'oasisworkflow' );
      return $default_email_body;
   }
   
   /**
    * Returns users list for emails according the participating roles for workflow.
    * @return HTMl $options drop down box for mails additional users
    * @since 4.6
    */
   public function get_users_option_list( $selected_users ) {
      $participants = get_option( 'oasiswf_participating_roles_setting' );
      $user_role_keys = array_keys( $participants );
      
		// get all registered users in the site
		$args = array(
				'blog_id' => $GLOBALS['blog_id'],
            'role__in' => $user_role_keys,
				'fields' => array( 'ID', 'display_name' )
		);
		$users = get_users( $args );
      $options = '';
		foreach ( $users as $user ) {
         if ( ! empty( $selected_users ) && is_array( $selected_users ) && in_array( esc_attr( $user->ID ), $selected_users ) ) { // preselect specified role
          	$selected = " ' selected='selected' ";
         } else {
         	$selected = '';
         }
			$options .= "<option value='{$user->ID}' $selected >$user->display_name</option>";
		}
		echo $options;
	}
   
   /**
    * Placeholders for email content
    * @return HTML
    * @since 4.6
    */
   public function get_placeholders() { ?>
      <div class="select-info email-placeholders">
         <?php
         $placeholders = get_site_option( "oasiswf_email_placeholders" );
         ?>
         <ul>
         <li><span class="description"><?php echo __( "Available template placeholders for email subject and content:", "oasisworkflow" ); ?></span></li>
         <?php
         if ( $placeholders ) {
            foreach ( $placeholders as $k => $v ) {
               echo "<li><span class='description'>{$k} - $v</span></li>";
            }
         }
         ?> 
         </ul>
      </div>
   <?php
   }

   /**
    * Get Post Author
    *
    * @param $post_id
    *
    * @return array
    * @since 4.6
    */
   private function get_post_author( $post_id ) {
      $post_id = intval( $post_id );

      $user_ids = array();
      $post_author = get_post_field( 'post_author', $post_id );
      array_push( $user_ids, $post_author );

      return $user_ids;
   }

   /**
    * Get Site Administrators
    *
    * @return array
    * @since 4.6
    */
   private function get_administrators() {
      $user_ids = array();
      $args = array(
         'blog_id' => $GLOBALS['blog_id'],
         'role__in' => 'administrator',
         'fields' => array( 'ID' )
      );
      $users = get_users( $args );
      foreach( $users as $user) {
         array_push( $user_ids, $user->ID );
      }

      return $user_ids;
   }

   /**
    * Get the user who submitted post to workflow
    * @param $post_id
    *
    * @return array
    * @since 4.6
    */
   private function get_workflow_submitter ( $post_id ) {
      global $wpdb;
      $post_id = intval( $post_id );

      $user_ids = array();
      $row = $wpdb->get_row( $wpdb->prepare( "SELECT assign_actor_id FROM " . OW_Utility::instance()->get_action_history_table_name() .
                             " WHERE action_status = 'submitted' AND post_id = %d", $post_id ) );

      if ( $row->assign_actor_id != 0 ) { // not system submitted
         array_push( $user_ids, $row->assign_actor_id );
      }

      return $user_ids;
   }
   
   /**
    * Get current task assignees 
    *
    * @param $post_id
    *
    * @return array
    * @since 4.6
    */
   private function get_assigned_task_recipients( $post_id ) {

      // sanitize post_id
      $post_id = intval( $post_id );

      $user_ids = array();
      $ow_history_service = new OW_History_Service();
      $action_histories = $ow_history_service->get_action_history_by_status( "assignment", $post_id );
         foreach ( $action_histories as $action_history ) { 
            // if it's a review step, then get the actors from the fc_action table
            if ( $action_history->assign_actor_id == -1 ) {
               $review_action_history = $ow_history_service->get_review_action_by_status( "assignment", $action_history->ID );
               foreach ( $review_action_history as $review_action ) {
                  array_push( $user_ids, $review_action->actor_id );                     
               }
            } else {
               array_push( $user_ids, $action_history->assign_actor_id );          
            }
         }
      return $user_ids;
   }

   /**
    * Get current task assignees for workflow abort action
    *
    * @param $post_id
    *
    * @return array
    * @since 4.6
    */
   private function get_abort_workflow_recipients( $post_id ) {

      // sanitize post_id
      $post_id = intval( $post_id );

      $email_recipients = $this->get_email_recipients( $post_id );

      // get current assignees for this aborted task
      $ow_history_service = new OW_History_Service();
      $action_histories = $ow_history_service->get_action_history_by_status( "aborted", $post_id );

      // get the latest abort action only
      array_push( $email_recipients, $action_histories[0]->assign_actor_id );

      $action_histories = $ow_history_service->get_action_history_by_status( "abort_no_action", $post_id );
      foreach ( $action_histories as $action_history ) {
         if ( $action_history->assign_actor_id == -1 ) { // review process, then get the actors from fc_action
            $review_action_history = $ow_history_service->get_review_action_by_status( "abort_no_action", $action_history->ID );
            foreach ( $review_action_history as $review_action ) {
               array_push( $user_ids, $review_action->actor_id );
            }
         } else {
            array_push( $email_recipients, $action_history->assign_actor_id );
         }
      }

      return $email_recipients;
   }
   
   /**
    * Get unclaimed users 
    *
    * @param $post_id
    *
    * @return array
    * @since 4.6
    */
   private function get_task_unclaimed_recipients( $post_id ) {

      // sanitize post_id
      $post_id = intval( $post_id );
      
      $user_ids = array();
      $ow_history_service = new OW_History_Service();
      $action_histories = $ow_history_service->get_action_history_by_status( "claim_cancel", $post_id );
         foreach ( $action_histories as $action_history ) { 
               array_push( $user_ids, $action_history->assign_actor_id );          
            }
      return $user_ids;
   }

   /**
    * Get additional users specified in the email type
    *
    * @param $additional_users
    *
    * @return array
    * @since 4.6
    */
   private function get_additional_users( $additional_users ) {
      $user_ids = array();

      if ( ! empty( $additional_users ) ) {
         $user_ids = array_merge( $user_ids, $additional_users );
      }

      return $user_ids;
   }

   /**
    * Get email recipients for the email type
    *
    * @param $email_recipient_params
    *
    * @return array
    * @since 4.6
    */
   public function get_email_recipients( $email_recipient_params ) {

      $post_id = $email_recipient_params['post_id'];
      $post_id = intval( $post_id );

      if ( ! empty ( $email_recipient_params['user_roles'] ) ) {
         $user_roles = $email_recipient_params['user_roles'];
         $user_roles = array_map( 'esc_attr', $user_roles );
      }

      if ( ! empty ( $email_recipient_params['additional_users'] ) ) {
         $additional_users = $email_recipient_params['additional_users'];
         $additional_users = array_map( 'esc_attr', $additional_users );
      }
      
      $email_recipients = array();
      if ( ! empty ( $user_roles ) ){
         foreach( $user_roles as $user_role ) {
            // Post Author(s) role
            if ( $user_role == 'post_author' ) {
               $user_ids = $this::get_post_author( $post_id );
               $email_recipients = array_merge( $email_recipients, $user_ids );
            }

            // Administrator(s)
            if ( $user_role == 'administrator' ) {
               $user_ids = $this::get_administrators();
               $email_recipients = array_merge( $email_recipients, $user_ids );
            }

            // Post Submitter
            if ( $user_role == 'post_submitter' ) {
               $user_ids = $this::get_workflow_submitter( $post_id );
               $email_recipients = array_merge( $email_recipients, $user_ids );
            }

            if ( $user_role == 'current_task_assignees' ) {
               switch ( $email_recipient_params["action"] ) {
                  case "workflow-abort" :
                     $user_ids = $this->get_abort_workflow_recipients( $post_id );
                     $email_recipients = array_merge( $email_recipients, $user_ids );
                     break;
                  case "task-claimed" :
                     $user_ids = $this->get_task_unclaimed_recipients( $post_id );
                     $email_recipients = array_merge( $email_recipients, $user_ids );
                     break;
                  case "unauthorized-update" :
                     $user_ids = $this->get_assigned_task_recipients( $post_id );
                     $email_recipients = array_merge( $email_recipients, $user_ids );
                     break;
               }
            }
         }
      }

      // Additional Users
      if ( ! empty ( $additional_users ) ) {
         $user_ids = $this::get_additional_users( $additional_users );
         $email_recipients = array_merge( $email_recipients, $user_ids );
      }

      // find unique email recipients
      $unique_email_recipients = array_unique( $email_recipients );
      return $unique_email_recipients;
   }

   /**
    * Get mail content from the email template configuration
    * Replace placeholders with actual value
    *
    * @param $email_params
    *
    * @return array
    */
   public function get_email_content( $email_params ) {
      /* sanitize the input */
      $email_to = $email_params['email_to'];
      $email_to = intval( $email_to );

      $post_id = $email_params['post_id'];
      $post_id = intval( $post_id );

      $email_subject = $email_params['email_subject'];
      $email_subject = sanitize_text_field( trim( $email_subject ) );

      $email_content = esc_html( $email_params['email_content'] );
      $email_content = wpautop( $email_content, false );

      /*
		 * Replace the placeholders with actual value
		 */
      $ow_placeholders = new OW_Place_Holders( );

      $mail_content = array( 'subject' => $email_subject, 'message' => $email_content );

      foreach( $mail_content as $k => $v ) {
         $v = str_replace( OW_Email_Settings_Helper::FIRST_NAME,
            $ow_placeholders->get_first_name( $email_to ), $v);
         $v = str_replace( OW_Email_Settings_Helper::LAST_NAME,
            $ow_placeholders->get_last_name( $email_to ), $v);
         $v = str_replace( OW_Email_Settings_Helper::POST_CATEGORY,
            $ow_placeholders->get_post_categories( $post_id ), $v);
         $v = str_replace( OW_Email_Settings_Helper::POST_LAST_MODIFIED_DATE,
            $ow_placeholders->get_post_last_modified_date( $post_id ), $v);
         $v = str_replace( OW_Email_Settings_Helper::POST_PUBLISH_DATE,
            $ow_placeholders->get_post_publish_date( $post_id ), $v);
         $v = str_replace( OW_Email_Settings_Helper::POST_AUTHOR,
            $ow_placeholders->get_author_display_name( $post_id ), $v );
         $v = str_replace(OW_Email_Settings_Helper::POST_TITLE,
            $this->get_post_title( $post_id, true ), $v);
         $v = str_replace( OW_Email_Settings_Helper::BLOG_NAME,
            addslashes( get_bloginfo( 'name' ) ), $v );
         $v = str_replace( OW_Email_Settings_Helper::CURRENT_USER,
            $ow_placeholders->get_first_name( get_current_user_id() ), $v );

         $mail_content[$k] = $v ;
      }
      return $mail_content ;
   }
   
   /**
	 * get post title
	 *
	 * @param int $post_id
	 * @param boolean $link, if true returns title as link.
	 * @return string post title as a link, if true
	 * @since 4.6
	 */
   public function get_post_title( $post_id, $link = true ) {
		// sanitize the input
		$post_id = intval( sanitize_text_field( $post_id ) );
      
		// get post details
		$post = get_post( $post_id ) ;
		$post_title = stripcslashes( $post->post_title );
		$post_url = esc_url( get_permalink( $post_id ) );

		if ( $link ) {
			$post_link = '<a href="' . $post_url . '" target="_blank">' . $post_title . '</a>';
		} else {
			$post_link = '"' . $post_title . '"';
		}

		return $post_link;
	}

   
}
// construct an instance so that the actions get loaded
$ow_email_settings_helper = new OW_Email_Settings_Helper();
?>