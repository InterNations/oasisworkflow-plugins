<?php
/*
 * Inbox for front end actions
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

/**
 * OW_Front_End_Actions_Inbox Class
 *
 * @since 1.0
 */
class OW_Front_End_Actions_Inbox {

   public function __construct() {
      add_shortcode( 'ow_workflow_inbox', array( $this, 'get_assigned_tasks_ui' ) );
   }

   /**
    * Shortcode - Draw a layout of workflow inbox
    * @return HTML Layout
    * @since 1.0
    */
   public function get_assigned_tasks_ui( $atts ) {
      /**
       * If license status not valid then do not process further more
       * @since 1.0
       */
      $license_status = get_option( 'oasiswf_front_end_license_status' );
      if( $license_status != 'valid' ) {
         return FALSE;
      }

      $html = '';
      if ( is_user_logged_in() ) {
         $attributes = array();
         if( isset( $atts['attributes'] ) && ! empty( $atts['attributes'] ) ) {
            /**
             * As we are allowing spaces in attributes so WP Will break the code after WP4.4
             * and also we are also allowing one attribute into shortcode so concat $atts by comma
             * and then remove multiple comma with single comma into the string
             * @since 1.2
             */
            $attributes = implode( ",", $atts );
            $attributes = preg_replace( "/,+/", ",", $attributes );
            
         	// convert into an array with "," as separator
            $attributes = array_filter( explode( ',', trim( $attributes ) ) );
         }

         $html = sprintf( "<div class='owfe-panel owfe-panel-default'>
                              <div class='owfe-panel-heading'>%s</div>
                              <div class='owfe-panel-body'>
                                 <div class='owfe-table'>
                                    <div class='owfe-table-heading'>
                                       <div class='owfe-table-row'>%s</div>
                                    </div>
                                    <div class='owfe-table-body'>
                 ", __( 'Workflow Inbox', 'owfrontendactions' ), $this->ow_fe_get_header( $attributes ) );

         $inbox_workflow = $ow_inbox_service = new OW_Inbox_Service();
         $ow_process_flow = new OW_Process_Flow();
         $ow_workflow_service = new OW_Workflow_Service();
         // Sanitize data from URL
         $data = array_map( 'sanitize_text_field', $_GET );
         $selected_user = isset( $data['user'] ) ? $data["user"] : get_current_user_id();

         // get assigned post/page to selected user
         $assigned_posts = $ow_process_flow->get_assigned_post( null, $selected_user );
         $count_posts = count( $assigned_posts );
         $pagenum = (isset( $data['paged'] ) && $data["paged"]) ? $data["paged"] : 1;

         $per_page = OASIS_PER_PAGE;

         $default_due_days = get_site_option( 'oasiswf_default_due_days' );
         $default_date = '';
         if ( ! empty( $default_due_days ) ) {
            $default_date = date( "m/d/Y", current_time( 'timestamp' ) + DAY_IN_SECONDS * $default_due_days );
         }
         $reminder_days = get_site_option( 'oasiswf_reminder_days' );
         $reminder_days_after = get_site_option( 'oasiswf_reminder_days_after' );
         $sspace = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

         // If count is zero then no assigned post/page for current logged in user
         if ( count( $assigned_posts ) == 0 ) {
            include_once(OW_FE_ACTIONS_ROOT.'/templates/inbox/no-assigned-tasks.php');
            return;
         }

         $count = 0;
         $start = ($pagenum - 1) * $per_page;
         $end = $start + $per_page;

         $ow_custom_statuses = new OW_Custom_Statuses();

         // Display all assigned_post through foreach loop
         foreach ( $assigned_posts as $assigned_post ) {
            $step_id = $assigned_post->step_id;
            $assigned_post_history_id = (int) $assigned_post->ID;
            $step = $ow_workflow_service->get_step_by_id( $step_id );
            $workflow = $ow_workflow_service->get_workflow_by_id( $step->workflow_id );
            $workflow_id = $workflow->ID;
            $process = $ow_workflow_service->get_gpid_dbid( $workflow_id, $step_id, "process" );
            $chk_claim = $ow_process_flow->check_for_claim( $assigned_post_history_id );

            if ( $count >= $end )
               break;
            if ( $count >= $start ) {

               $assigned_post_id = $assigned_post->post_id;
               $due_date = OW_Utility::instance()->format_date_for_display( $assigned_post->due_date );
               $due_date = ( ! empty( $due_date )) ? $due_date : $sspace;

               $comment_count = $ow_process_flow->get_sign_off_comments_count_by_post_id( $assigned_post_id ); // get comment count

               $html .= $this->ow_fe_get_inbox_contents( $assigned_post_id, $assigned_post_history_id,
               		$due_date, $comment_count, $chk_claim, $attributes, $workflow, $step, $ow_custom_statuses );
            }
            $count ++;
         }

         $html .= '<div id="step_submit_content"></div>';
         $html .= '<div id="post_com_count_content"></div>';
         // footer
         $html .= sprintf( "</div> <!-- .owfe-table-body -->
                           <div class='owfe-table-heading'>
                           	<div class='owfe-table-row'>%s</div>
                           </div> <!-- .owfe-table-heading -->
                           </div> <!-- .owfe-table -->
                           </div> <!-- .owfe-panel-body -->
                           </div> <!-- .owfe-panel -->
                        ", $this->ow_fe_get_header( $attributes ) );
      } else {
         // Need to login to view this contents.
         include_once(OW_FE_ACTIONS_ROOT.'/templates/global/not-logged-in-user.php');
      }
      return $html;
   }

   /**
    * Return the header of workflow inbox shortcode
    * @access private
    * @return HTML string
    * @since 1.0
    */
   private function ow_fe_get_header( $attributes ) {
      // default headers
      $headers = array(
          'Post/Page',
          'Due Date',
          'Comments',
          'Action'
      );

      if( is_array( $attributes ) && ! empty( $attributes ) ) {
      	array_push( $attributes, "action");
         $headers = $attributes;
      }

      $html = '';
      foreach ( $headers as $header ) {
         $header = ucwords( str_replace( '_', ' ', $header ) );
         $html .= sprintf( '<div class="owfe-table-head">%s</div>', __( $header, 'owfrontendactions' ) );
      }

      if ( has_filter( 'owfe_add_header' ) ) {
         $html .= apply_filters( 'owfe_add_header', $html );
      }
      return $html;
   }

   /**
    * Retrive the HTML for post title
    * @param int $post_id
    * @return HTML string
    * @since 1.2
    */
   private function show_post_title( $post_id ) {
      $post_id = intval( $post_id );
      $html = '<div class="owfe-table-cell">';
         $html .= "<a href='" . esc_url( get_permalink( $post_id ) ) . "' target='_blank'>
                     " . get_the_title( $post_id ) . " - " . get_post_status( $post_id ) . "
                  </a>";
      $html .= '</div>';
      return $html;
   }

   /**
    * Retrive the HTML for duedate
    * @param string $due_date
    * @return HTML string
    * @since 1.2
    */
   private function show_due_date( $due_date ) {
      $html = '<div class="owfe-table-cell">';
         $html .= "<span>$due_date</span>";
      $html .= '</div>';
      return $html;
   }

   /**
    * Retrive the HTML for comment
    * @param string $comment_link
    * @param int $comment_count
    * @param string $comment_link_close
    * @return HTML string
    * @since 1.2
    */
   private function show_comment( $comment_link, $comment_count, $comment_link_close ) {
      $html = '<div class="owfe-table-cell">';
         $html .= '<div class="post-com-count-wrapper">';
            $html .= '<strong>';
               $html .= $comment_link;
                  $html .= "<span class='comment-count'>$comment_count</span>";
               $html .= $comment_link_close;
            $html .= '</strong>';
         $html .= '</div>';
      $html .= '</div>';
      return $html;
   }

   /**
    * Display signoff link
    * @param string $action
    * @return HTML string
    * @since 1.2
    */
   private function show_action( $action ) {
      $html = '<div class="owfe-table-cell">';
         $html .= "<span>$action</span>";
      $html .= '</div>';
      return $html;
   }

   /**
    * Get body contents of workflow inbox shortcode
    * @return string
    * @since 1.0
    */
   public function ow_fe_get_inbox_contents( $post_id, $history_id, $due_date,
   		$comment_count, $is_claimable, $attributes = array(), $workflow_object, $step, $ow_custom_statuses ) {

      $comment_link = $comment_link_close = '';
      if ( $comment_count > 0 ) {
         $comment_link = "<a href='javascript:void(0);' actionid='$history_id' class='post-com-count' post_id='$post_id' data-comment='inbox_comment'>";
         $comment_link_close = '</a>';
      }

      $sspace = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
      if ( $is_claimable ) {
         $action = sprintf( '<a href="javascript:void(0);" class="claim" actionid="%d">%s</a><span class="loading">%s</span>',
         		$history_id, __( "Claim", "owfrontendactions" ), $sspace );
      } else {
         $action = sprintf( '<a href="javascript:void(0);" wfid="%d" postid="%d" class="quick_sign_off" onclick="ow_fe_populate_ids(%d, %d)">%s</a>',
         		$history_id, $post_id, $post_id, $history_id, __( "Sign Off", "owfrontendactions" ) );
      }

      $html = '';
      $html .= '<div class="owfe-table-row">';

      if( ! empty( $attributes ) && in_array( 'post_title', $attributes ) ) {
         $post_title = $this->show_post_title( $post_id );

         // if $attribute is empty then show post title
      } else if( empty( $attributes ) ) {
         $html .= $this->show_post_title( $post_id );
      }

      if( ! empty( $attributes ) && in_array( 'workflow', $attributes ) ) {
      	$step_info = $step->step_info;
      	$obj = json_decode($step_info);
      	$step_name = $obj->step_name;

         $workflow = '<div class="owfe-table-cell">';
            $workflow .= "<span>";
            $workflow .= $workflow_object->name . " [" . $step_name . "]";
            $workflow .= "</span>";
         $workflow .= '</div>';
      }

      if( ! empty( $attributes ) && in_array( 'post_status', $attributes ) ) {
      	$post_status_object = $ow_custom_statuses->get_single_term_by( 'slug', get_post_status( $post_id ) );
      	$post_status_value = is_object( $post_status_object ) && isset( $post_status_object->name ) ? $post_status_object->name : "";

         $post_status = '<div class="owfe-table-cell">';
            $post_status .= "<span>$post_status_value</span>";
         $post_status .= '</div>';
      }

      if( ! empty( $attributes ) && in_array( 'author', $attributes ) ) {
         $author = '<div class="owfe-table-cell">';
            $author .= '<span>' . get_userdata( get_post_field( 'post_author', $post_id ) )->display_name . '</span>';
         $author .= '</div>';
      }

      if( ! empty( $attributes ) && in_array( 'category', $attributes ) ) {
         $category = '<div class="owfe-table-cell">';
         $post_categories = OW_Utility::instance()->get_post_categories( $post_id );
         $category .= '<span>' . $post_categories . '</span>';
         $category .= '</div>';
      }

      if( ! empty( $attributes ) && in_array( 'post_type', $attributes ) ) {
         $post_type = '<div class="owfe-table-cell">';
            $post_type_obj = get_post_type_object( get_post_type( $post_id ) );
            $post_type .= '<span>'.  $post_type_obj->labels->singular_name .'</span>';
         $post_type .= '</div>';
      }

      if( ! empty( $attributes ) && in_array( 'due_date', $attributes ) ) {
         $due_date = $this->show_due_date( $due_date );
      } else if( empty( $attributes ) ) {
         $html .= $this->show_due_date( $due_date );
      }

      if( ! empty( $attributes ) && in_array( 'comment', $attributes ) ) {
         $comment = $this->show_comment( $comment_link, $comment_count, $comment_link_close );
      } else if( empty( $attributes ) ) {
         $html .= $this->show_comment( $comment_link, $comment_count, $comment_link_close );
      }

      if( ! empty( $attributes ) && in_array( 'priority', $attributes ) ) {
         $priority_value = get_post_meta( $post_id, '_oasis_task_priority', true );
         if ( ! empty ( $priority_value ) ) {
         	$task_priority = OW_Utility::instance()->get_priorities();
         	$task_priority = $task_priority[$priority_value];
            $css_class = substr( $priority_value, 1 );
         	$priority = '<div class="owfe-table-cell">';
            	$priority .= '<span class="post-priority '.$css_class.'-priority">'. $task_priority .'</span>';
         	$priority .= '</div>';
         }
      }

      if( ! empty( $attributes ) ) {
      	array_push( $attributes, "action");
         $action = $this->show_action( $action );
      } else if( empty( $attributes ) ) {
         $html .= $this->show_action( $action );
      }

      $attribute_vars = $attributes;
      $result = compact( $attribute_vars );
      $html .=  implode( "", array_values( $result ) );
      $html .= '</div>'; // .owfe-table-row

      return $html;
   }

}

return new OW_Front_End_Actions_Inbox();
