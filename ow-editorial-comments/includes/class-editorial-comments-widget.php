<?php

/*
 * Widget class for Editorial Comment
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
class OW_Comments_Widget {

   public function __construct() {
      // Do nothing
   }

   /**
    * Register widget to post/page widget
    * @since 1.0
    */
   public function register_contextual_comment_widget() {
      $allowed_post_types = get_option( 'oasiswf_show_wfsettings_on_post_types' );

      foreach ( $allowed_post_types as $allowed_post_type ) {
         if ( get_option( "oasiswf_activate_workflow" ) == "active" ) { // show meta box only if workflow process is active
            add_meta_box( 'owf_editorial_comments_metabox', __( 'Editorial Comments', 'oweditorialcomments' ), array( $this, 'editorial_comments_metabox' ), $allowed_post_type, 'side', 'high' );
         }
      }
   }

   /**
    * Set content on editorial comments widget
    * 1. sign off comments
    * 2. contextual comments
    * @since 1.0
    */
   public function editorial_comments_metabox() {
      global $post;
      $post_id = $post->ID;

      // Define nonce for editorial comment metabox actions
      wp_nonce_field( 'delete_contextual_comment_ajax_nonce', 'delete_contextual_comment_ajax_nonce' );
      wp_nonce_field( 'edit_contextual_comment_ajax_nonce', 'edit_contextual_comment_ajax_nonce' );

      /**
       * step 1. show contextual comments which have history_id = 0
       * Note: This will not have signoff comment because history_id = 0 - The post is not yet signed off
       */
      // Get all Contextual comments
      $ow_comments_service = new OW_Comments_Service();
      $contextual_comments = $ow_comments_service->get_contextual_comments( 0, $post_id, get_current_user_id() );

      $layout = '<div id="ow-scrollbar" class="ow-widget-scrollbar">';

      if ( $contextual_comments ) {

         // Initialize the outer wrapper
         $layout .= '<div class="ow-editorial-comment-box ow-outer"><span class="loading" style="display:none;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><div class="ow-accordion ow-collapse">' . __( 'Contextual Comments', 'oweditorialcomments' ). '</div>';

         foreach ( $contextual_comments as $contextual_comment ) {
            // Show comments
            $layout .= $this->display_editorial_comment_widget( $contextual_comment );
         }

         // End of outer wrapper
         $layout .= '</div>';

         // Put hidden empty box
      } else {
         $layout .= '<div class="ow-editorial-comment-box owf-hidden"><span class="loading" class="owf-hidden">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><div class="ow-accordion ow-collapse"></div>';
         $layout .= '</div>';
      }

      /**
       * Step 2. show comments which has history_id but before that show the signoff comment
       *         and then show the contextual comment
       */
      $ow_process_flow = new OW_Process_Flow();
      $sign_off_comments = $ow_process_flow->get_sign_off_comments_map( $post_id );
      if ( $sign_off_comments ) {
         foreach ( $sign_off_comments as $history_id => $comments ) {
            if ( $comments ) {
               foreach ( $comments as $comment ) {

                  // Initialize the outer wrapper for each sign off comment
                  $layout .= '<div class="ow-editorial-comment-box ow-outer">';

                  // show signoff comment
                  $layout .= $this->display_sign_off_comment_widget( $comment );

                  // show contextual comment if there are exist in comment table for a given history id
                  $contextual_comments = $ow_comments_service->get_contextual_comments( $history_id, $post_id, $comment->send_id );
                  if ( empty( $contextual_comments ) ) { // see if there are any contextual comments which are not completely signed off yet (history_id = 0)
                     $contextual_comments = $ow_comments_service->get_contextual_comments( 0, $post_id, $comment->send_id );
                  }
                  if ( $contextual_comments ) {
                     foreach ( $contextual_comments as $contextual_comment ) {
                        $layout .= $this->display_editorial_comment_widget( $contextual_comment );
                     }
                  }

                  // End of outer wrapper
                  $layout .= '</div>';
               }
            }
         }
      }

      // No comments for this post.
      if ( ! $sign_off_comments && ! $contextual_comments ) {
         $layout .= '<div class="ow-editorial-comment-box ow-no-comments-box"><span class="loading owf-hidden">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>';
         $layout .= sprintf( '<span id="ow-no-editorial-comments">%s</span>', __( 'No comments for this post.', 'oweditorialcomments' ) );
         $layout .= '</div>';
      }

      // End of scrollbar
      $layout .= '</div>';

      // Finally echo the HTML
      echo $layout;
   }

   /**
    * Display Contextual widget template
    *
    * @param OW_Editorial_Comment $editorial_comment - instance of OW_Editorial_Comment
    * @return type string, html string
    * @since 1.0
    */
   public function display_editorial_comment_widget( OW_Editorial_Comment $editorial_comment, $show_author_info = TRUE, $is_editable = TRUE ) {

      $ow_comments_service = new OW_Comments_Service();
      $comments_array = unserialize( $editorial_comment->comments );

      $comment_datetime = OW_Utility::instance()->format_date_for_display( $comments_array['comment_timestamp'], '-', 'datetime' );
      $user = OW_Utility::instance()->get_user_role_and_name( $editorial_comment->user_id );
      $role_name = $user->role;
      $user_name = $user->username;


      $layout = '';
      if ( $show_author_info ) {
         $layout .= '<p class="author-info">
                        <span class="signature-name">By ' . $user_name . '</span>
                         |
                        <span class="signature-destination">' . $role_name . '</span>
                     </p>';
      }
      $actions_html = '';
      if ( $is_editable ) {
         // Wheather user can do edit-delete contextual comments
         $actions_html = $ow_comments_service->can_user_modify_comment( $editorial_comment->user_id, $editorial_comment );
      }
      return '<div class="ow-editorial-comment-box ow-inner" id="inner-' . $editorial_comment->ID . '">
                  <blockquote class="ow-editorial-comments-blockquote">
                     <p class="contextual-text">' . $comments_array['contextual_text'] . '</p>
                  </blockquote>
                  <div class="comment-contents">
                     <div class="contextual-comment">' . stripslashes( $comments_array['contextual_comment'] ) . '</div>
                     ' . $layout . '
                     <ul class="timestamp">
                        <li>' . $comment_datetime . '</li>
                        ' . $actions_html . '
                     </ul>
                  </div>
              </div>';
   }

   /**
    * Display signoff comments widget
    * @param object $comment, single sign off comment
    * @return string
    */
   public function display_sign_off_comment_widget( $comment, $accordion = TRUE ) {
      $layout = $sign_off_comment_ts = '';
      if ( isset( $comment->comment_timestamp ) ) {
         $sign_off_comment_ts = $comment->comment_timestamp;
      }

      // If signoff comment is empty then skip the below code
      if ( ! empty( $sign_off_comment_ts ) ) {


         $timestamp = OW_Utility::instance()->format_date_for_display( $comment->comment_timestamp, '-', 'datetime' );

         $user = OW_Utility::instance()->get_user_role_and_name( $comment->send_id );
         if ( $accordion ) {
            $layout .= sprintf( '<div class="ow-accordion ow-collapse">By %s | %s</div>', $user->username, $user->role );
         }

         if ( empty( $comment->comment ) ) {
         	$sign_off_comment = __("No Comments.", "oweditorialcomments");
         } else {
         	$sign_off_comment = $comment->comment;
         }
         $layout .= sprintf( '
               <div class="ow-signed-off">
                    <p class="ow-sign-off-text">%s <span class="timestamp">%s</span></p>
                    <p class="ow-signed-off-comment">%s</p>
               </div>', __( 'Signed off on', 'oweditorialcomments' ), $timestamp, $sign_off_comment );
      }

      return $layout;
   }

}

$ow_comment_widget = new OW_Comments_Widget();

add_action( 'admin_init', array( $ow_comment_widget, 'register_contextual_comment_widget' ) );
?>