<?php

/*
 * Service class for Editorial Comment Actions (CRUD)
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
 * Widget Class
 *
 * @since 1.0
 */
class OW_Comments_Service {

   public function __construct() {

      remove_action( 'wp_ajax_get_step_comment_page', 'get_step_comment_page' );
      // Register ajax actions here..
      $ajax_events = array(
          'show_contextual_comment_popup',
          'create_contextual_comment',
          'update_contextual_comment',
          'delete_contextual_comment',       
          'edit_contextual_comment_popup',
          'get_step_comment_page'
      );

      foreach ( $ajax_events as $ajax_event ) {
         add_action( 'wp_ajax_' . $ajax_event, array( $this, $ajax_event ) );
      }

      add_action( 'owf_save_workflow_signoff_action', array( $this, 'update_history_id_for_contextual_comments' ), 10, 2 );

      add_action( 'owf_save_workflow_reassign_action', array( $this, 'update_history_id_for_contextual_comments' ), 10, 2 );

      //filter for user comments
      add_action( 'owf_get_user_comments', array( $this, 'get_user_contextual_comments' ) );
      add_filter( 'owf_get_contextual_comments_by_post_id', array( $this, 'get_contextual_comments' ), 10, 3 );
      add_filter( 'owf_get_contextual_comments_by_history_id', array( $this, 'get_contextual_comments_by_history_id' ), 10, 1 );

      // delete contextual comments when post is deleted
      add_action( 'owf_when_post_trash_delete', array( $this, 'when_post_trash_delete_contextual_comments' ), 10, 2 );

      // copy contextual comments on claim action
      add_action( 'owf_claim_action', array( $this, 'copy_contextual_comments' ), 10, 2 );
      add_filter( 'owf_inbox_row_actions', array( $this, 'add_view_row_action' ), 10, 2 );
      add_filter( 'template_include', array( $this, 'editorial_comment_page_template' ), 10, 1 );
   }
   
   /**
    * AJAX function -  get the contextual popup page
    * @return type HTML
    * @since 1.5
    */
   public function show_contextual_comment_popup() {
      check_ajax_referer( 'owf_tinymce_ajax_nonce', 'security' );

      ob_start();
      include_once( 'contextual-comment-popup.php' );
      $result = ob_get_contents();
      ob_end_clean();

      wp_send_json_success( $result );
   }
   
   /**
    * AJAX function - Create contextual comment
    * @global type $wpdb
    *
    * @since 1.0
    */
   public function create_contextual_comment() {
      global $wpdb;

      check_ajax_referer( 'owf_tinymce_ajax_nonce', 'security' );

      // Sanitize the $_POST array
      $data = array_map( 'sanitize_text_field', $_POST );
      $contextual_text = stripcslashes( $data['contextual_text'] );
      $contextual_comment = stripslashes( wp_filter_post_kses( addslashes ( $_POST['contextual_comment'] ) ) );
      $post_id = (int) $data['post_id'];
      $current_user_id = get_current_user_id();
      $comment_timestamp = current_time( 'mysql' );

      $contextual_comments['contextual_text'] = $contextual_text;
      $contextual_comments['contextual_comment'] = $contextual_comment;
      $contextual_comments['send_id'] = $current_user_id;
      $contextual_comments['comment_timestamp'] = $comment_timestamp;

      // when creating a contextual comment, you will most likely do not have the workflow_history_id
      $new_contextual_comment_id = $wpdb->query( $wpdb->prepare( "INSERT INTO " .
                      OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name() .
                      " (comments, post_id, user_id) VALUES ('%s', '%d', '%d')", serialize( $contextual_comments ), $post_id, $current_user_id ) );

      // get last inserted id
      $new_contextual_comment_id = $wpdb->insert_id;

      $ow_comments_widget = new OW_Comments_Widget();

      $editorial_comment = new OW_Editorial_Comment( );
      $editorial_comment->ID = $new_contextual_comment_id;
      $editorial_comment->workflow_history_id = '';
      $editorial_comment->post_id = $post_id;
      $editorial_comment->user_id = $current_user_id;
      $editorial_comment->comments = serialize( $contextual_comments );

      // send json response
      $result = array( 'row_id' => $new_contextual_comment_id - 1, 'layout' => $ow_comments_widget->display_editorial_comment_widget( $editorial_comment ) );
      wp_send_json_success( $result );
   }
   
   /**
    * AJAX function - Update contextual comment
    * @global type $wpdb
    *
    * @since 1.0
    */
   public function update_contextual_comment() {
      global $wpdb;

      check_ajax_referer( 'edit_contextual_comment_ajax_nonce', 'security' );

      $data = array_map( 'sanitize_text_field', $_POST );
      $comment_id = $data['comment_id'];
      $contextual_comment = stripslashes( wp_filter_post_kses( addslashes ( $_POST['contextual_comment'] ) ) );

      // get contextual comment
      $results = $this->get_editorial_comment_from_comment_id( $comment_id );
      $comments = unserialize( $results->comments );
      // update into $commment array
      $comments['contextual_comment'] = $contextual_comment;

      // Update the row
      $wpdb->query( $wpdb->prepare( "UPDATE " . OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name() . " SET comments = '%s' WHERE ID = '%d'", serialize( $comments ), $comment_id ) );
      wp_send_json_success( $contextual_comment );
   }
   
   /**
    * AJAX function - Delete selected contextual comment
    *
    * @since 1.0
    */
   public function delete_contextual_comment() {
      check_ajax_referer( 'delete_contextual_comment_ajax_nonce', 'security' );
      global $wpdb;

      $comment_id = (int) sanitize_text_field( $_POST['comment_id'] );
      // Delete contextual comment
      $wpdb->delete( OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name(), array( 'ID' => $comment_id ), array( '%d' ) );
      wp_send_json_success();
   }
   
   /**
    * AJAX function - get the edit contextual popup page
    * @return type HTML
    * @since 1.0
    */
   public function edit_contextual_comment_popup() {
      check_ajax_referer( 'edit_contextual_comment_ajax_nonce', 'security' );

      ob_start();
      include_once( 'comments-upsert-popup.php' );
      $result = ob_get_contents();
      ob_end_clean();

      wp_send_json_success( $result );
   }

   /**
    * AJAX function - get the step comments 
    * @return type HTML
    * @since 1.0
    */
   public function get_step_comment_page() {
      check_ajax_referer( 'owf_inbox_ajax_nonce', 'security' );

      ob_start();
      include_once( 'comments-view-popup.php' );
      $result = ob_get_contents();
      ob_end_clean();

      wp_send_json_success( $result );
   }
   

   /**
    * Hook - Update History id on the contextual comments after sign off
    * @param int $post_id
    * @param int $history_id
    * @param int $user_id
    * @since 1.4
    */
   public function update_history_id_for_contextual_comments( $post_id, $history_id ) {
      global $wpdb;
      $comment_table = OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name();
      $wpdb->query( $wpdb->prepare( "UPDATE $comment_table
              SET workflow_history_id = '%d'
   			  WHERE post_id = '%d'
   			  AND workflow_history_id = 0",
         $history_id, $post_id ) );
   }

   /**
    * Copy contextual comments on claim action with the new history id
    *
    * @param array $old_action_histories
    * @param int $new_history_id
    */
   public function copy_contextual_comments( $old_action_histories, $new_history_id ){
      global $wpdb;
      // copy contextual comments as new records, just like editorial comments are copied to the assignment.
      // the contextual comments are attached to one specific history ID only, so loop through all
      foreach ( $old_action_histories as $old_action_history ) {
         $contextual_comments = $this->get_contextual_comments_by_history_id( $old_action_history->ID );

         foreach ( $contextual_comments as $contextual_comment ) {
            $contextual_comment->workflow_history_id = $new_history_id;
            $wpdb->query(
               $wpdb->prepare( "INSERT INTO " .
                               OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name() .
                               " (workflow_history_id, comments, post_id, user_id) VALUES ('%d','%s', '%d', '%d')",
                  $contextual_comment->workflow_history_id,
                  $contextual_comment->comments,
                  $contextual_comment->post_id,
                  $contextual_comment->user_id ) );
         }
      }
   }
   
   /**
    * Hook - owf_inbox_row_actions
    * To add link of view with editorial comments in inbox row actions.
    * @param int $post_id
    * @param array $inbox_row_actions
    * @return array  $inbox_row_actions
    * @since 1.8
    */
   public function add_view_row_action( $inbox_row_actions_data, $inbox_row_actions ) { 
      
      $post_id = intval( $inbox_row_actions_data["post_id"] );
		$user_id = intval( $inbox_row_actions_data["user_id"] );
		$workflow_history_id = intval( $inbox_row_actions_data["workflow_history_id"] ); 
      
      $inbox_row_actions = array_slice( $inbox_row_actions, 0, 2, true ) +
         array( "editorial_comments" => "<span><a target='_blank' href='" . get_preview_post_link( $post_id, array( 'oasiswf' => $workflow_history_id ) ) . "'>" . __( "View ( With Comments )", "oweditorialcomments" ) . "</a></span>" ) +
         array_slice( $inbox_row_actions, 2, count( $inbox_row_actions ) - 1, true) ;   
      return $inbox_row_actions;
   }
   
   /**
    * Hook - template_include
    * Load template for view page with editorial comments
    * @param string $page_template
    * @return page template path $page_template
    * @since 1.8
    */
   public function editorial_comment_page_template( $page_template ) {     
      if ( is_preview() && ( isset( $_GET['oasiswf'] ) ) ) {
          $page_template = OW_EDITORIAL_COMMENTS_ROOT . '/includes/comment-page-template.php';
      }
      return $page_template;
   }


   /**
    * Hook - To get user contextual comments
    * @global type $wpdb
    * @since 1.0
    */
   public function get_user_contextual_comments( &$args ) {
      global $wpdb;
      $action_history_id = $args[1];
      $comment_object = $args[2];
      $comments_str = $args[0];

      // get the full name of the user
      $full_name = OW_Utility::instance()->get_user_name( $comment_object->send_id );

      // get contextual comments from history id and send_id
      $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " .
                      OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name()
                      . " WHERE workflow_history_id = %d AND user_id = %d", $action_history_id, $comment_object->send_id ) );
      if ( $results ) {
         foreach ( $results as $result ) {
            $comments = unserialize( $result->comments );
            $comments_str .= "<p><strong>" . __("Contextual Text:", "oweditorialcomments" ) . "</strong> $comments[contextual_text]<br/>";
            $comments_str .= "<strong>" . __("Contextual Comment:", "oweditorialcomments" ) . "</strong><br/> $comments[contextual_comment]</p>";
         }
      }
      $args[0] = $comments_str;
      return $args;
   }
   
   /**
    *
    * Hook - Delete contextual comments when post is trashed/deleted
    *
    * @param $post_id
    * @param $action_history_id
    */
   public function when_post_trash_delete_contextual_comments( $post_id ) {
      global $wpdb;

      $post_id = intval( $post_id );

      $wpdb->get_results( $wpdb->prepare( "DELETE FROM " . OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name() . " WHERE post_id = %d",
         $post_id ) );
   }

   /**
    * Get contextual comment for current post/page and action history (LIFO)
    * @param int $history_id - action_history_id
    * @param int $post_id - post_id
    * @param int $user_id - user_id
    *
    * @return type mixed, array of contextual comments
    * @since 1.0
    */
   public function get_contextual_comments( $history_id, $post_id, $user_id ) {
      global $wpdb;

      $history_id = intval( sanitize_text_field( $history_id ) );
      $post_id = intval( sanitize_text_field( $post_id ) );
      $editorial_comments = array();

      $results = $wpdb->get_results( $wpdb->prepare( "SELECT *  FROM " .
                         OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name() .
                         " WHERE workflow_history_id = '%d' AND post_id = '%d' AND user_id = '%d'".
                         " ORDER BY ID DESC, workflow_history_id DESC", $history_id, $post_id, $user_id ) );
      
      foreach ( $results as $result ) {
         $editorial_comment = $this->get_editorial_comments_from_result_set( $result );
         array_push( $editorial_comments, $editorial_comment );
      }

      return $editorial_comments;
   }

   /**
    * Get contextual comment by action history (LIFO)
    * @param int $history_id - action_history_id
    *
    * @return type mixed, array of contextual comments
    * @since 1.6
    */
   public function get_contextual_comments_by_history_id( $history_id ) {
      global $wpdb;

      $history_id = intval( sanitize_text_field( $history_id ) );
      $editorial_comments = array();

      $results = $wpdb->get_results(
            $wpdb->prepare( "SELECT *  FROM " .
                            OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name() .
                            " WHERE workflow_history_id = '%d' " .
                            " ORDER BY ID DESC, workflow_history_id DESC", $history_id ) );

      foreach ( $results as $result ) {
         $editorial_comment = $this->get_editorial_comments_from_result_set( $result );
         array_push( $editorial_comments, $editorial_comment );
      }

      return $editorial_comments;
   }
   
   /**
    * Get contextual comment by post id
    * @param int $post_id     *
    * @return type mixed, array of contextual comments
    * @since 1.8
    */
   public function get_contextual_comments_by_post_id( $post_id ) {
      global $wpdb;

      $post_id = intval( $post_id ); 
      
      $history_id = ( isset($_GET["oasiswf"]) && sanitize_text_field( $_GET["oasiswf"] )) ? sanitize_text_field( $_GET["oasiswf"] ) : 0; 
      
      // Get post content by post id
      $post = get_post( $post_id );
      $post_content = apply_filters( 'the_content', $post->post_content );
      
      // Get contextual comments
      $all_contextual_comments = $this->get_contextual_comments_by_history_id( $history_id );
      
      $comment_map = array();
      if ( ! empty( $all_contextual_comments ) ) {
         foreach( $all_contextual_comments as $comments ) {
            $comment = unserialize( $comments->comments );
            $contextual_text = $comment['contextual_text'];
            $contextual_comment = $comment['contextual_comment'];   
            
            // Get User Name
            $user_id = $comments->user_id;
            $user = OW_Utility::instance()->get_user_role_and_name( $user_id );
            $user_name = $user->username;
            
            // Map comments into one for each contextual text
            if( ! empty( $comment_map ) && array_key_exists( $contextual_text, $comment_map ) ) {
               $comment_map_recursive[ $contextual_text ] = array( array( 
                   'title' => $user_name, 
                   'body' => $contextual_comment ) ); 
               
               $merged_comments = array_merge_recursive( $comment_map, $comment_map_recursive );
            } else {            
               $comment_map[ $contextual_text ] = array( array(
                      'title' => $user_name, 
                      'body' => $contextual_comment ) ); 

               $merged_comments = $comment_map;
            }
         }  
      }  
      
      // wrap contextual text with <span>
      if( ! empty( $merged_comments ) ) {
         $data_comment = array();
         foreach( $merged_comments as $contextual_text=>$comments ) {
            if ( stripos( $post_content, $contextual_text ) !== false ) {
               $data_comment['comments'] = $comments;   
               $post_content = preg_replace( "@" . $contextual_text . "@", "<span class='owfc-comment' data-comment='". json_encode($data_comment)."'>".$contextual_text."</span>", $post_content );
            }
            unset( $data_comment );
         }
      }
            
      return $post_content;
   }

   /**
    * Fetch contextual comment from ID
    *
    * @param int $comment_id
    *
    * @return OW_Editorial_Comment - instance of OW_Editorial_Comment
    * @since 1.0
    */
   private function get_editorial_comment_from_comment_id( $comment_id ) {
      global $wpdb;

      $comment_id = intval( sanitize_text_field( $comment_id ) );

      $result = $wpdb->get_row( $wpdb->prepare( "SELECT *  FROM " .
                      OW_Editorial_Comments_Utility::instance()->get_editorial_comment_table_name()
                      . " WHERE ID = '%d'", $comment_id ) );

      $comments = $this->get_editorial_comments_from_result_set( $result );
      return $comments;
   }

   /**
    * Function to convert DB result set to OW_Editorial_Comment object
    * @param mixed $result - $result set object
    * @return OW_Editorial_Comment - instance of OW_Editorial_Comment
    *
    * @since 2.0
    */
   public function get_editorial_comments_from_result_set( $result ) {
      if ( ! $result ) {
         return "";
      }

      $editorial_comment = new OW_Editorial_Comment( );
      $editorial_comment->ID = $result->ID;
      $editorial_comment->workflow_history_id = $result->workflow_history_id;
      $editorial_comment->post_id = $result->post_id;
      $editorial_comment->user_id = $result->user_id;
      $editorial_comment->comments = $result->comments;

      return $editorial_comment;
   }

   /**
    * Check given user can perform Edit/Delete on the contextual comment
    * 1. if passed in user_id is same as the current_user_id
    * 2. if history_id is zero
    *
    * @param int $user_id - user id to compare with
    * @para OW_Editorial_Comment $editorial_comment - editorial comment object under consideration
    *
    * @return string html to show the Delete/Edit links
    * @since 1.0
    */
   public function can_user_modify_comment( $user_id, OW_Editorial_Comment $editorial_comment ) {
      $actions_html = '';

      /**
       * If history_id = 0 then the logged in user can modify the comment because
       * its a current contextual comment and its not submitted for next step of workflow
       * if history_id > 0 then it's from the previous steps in the workflow.
       */
      if ( get_current_user_id() == $user_id && $editorial_comment->workflow_history_id == 0 ) {
         $actions_html = '<li><span><a href="javascript:void()" class="delete-contextual-comment-div" id="' . $editorial_comment->ID . '" title="Delete">Delete</a></span> | <span><a href="javascript:void()" class="edit-contextual-comment" id="' . $editorial_comment->ID . '" title="Edit">Edit</a></span></li>';
      }

      return $actions_html;
   }

}

return new OW_Comments_Service();
?>