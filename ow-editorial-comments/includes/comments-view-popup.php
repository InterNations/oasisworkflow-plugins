<?php
$ow_comments_widget = new OW_Comments_Widget ();
$data = array_map( 'sanitize_text_field', $_POST );

$user_id = get_current_user_id();
$ow_process_flow = new OW_Process_Flow ();

$page_action = '';
if ( isset ($data['page_action'] ) ) {
	$page_action = $data ['page_action'];
}

// Initialize global variable for this page
$contextual_comments = $sign_off_comments = $post_id = FALSE;

if ( $page_action == 'inbox_comment' ) {
   $post_id = (int) $data ['post_id'];

   $sign_off_comments = $ow_process_flow->get_sign_off_comments_map( $post_id );
}

$ow_history_service = new OW_History_Service ();

if ( $page_action == 'history' ) {
   $action_id = $data ['actionid'];
   $history_details = "";
   $sign_off_comments = array();
   // get action history id from action id
   if ( $_POST["actionstatus"] == "aborted" || $_POST["actionstatus"] == "abort_no_action" ) {
      $history_details = $ow_history_service->get_action_history_by_id( $action_id );
   } else {
      $history_details = $ow_history_service->get_action_history_by_from_id( $action_id );
   }
   
   if ( ! empty( $history_details ) ) {
      $post_id = $history_details->post_id;
      $comments = json_decode( $history_details->comment );
      $sign_off_comments [$history_details->ID] = $comments;
   }   
}

if ( $page_action == 'review' ) {
	$action_id = $data['actionid'];
   $sign_off_comments = array();
   
   if ( $_POST["actionstatus"] == "aborted" ) {
      $history_details = $ow_history_service->get_action_history_by_id( $_POST["actionid"] );
      $post_id = $history_details->post_id;
      $comments = json_decode( $history_details->comments );
      $sign_off_comments[$history_details->ID] = $comments;      
   } else {   
	$review_action_details = $ow_history_service->get_review_action_by_id( $action_id );
	
	$comments = json_decode( $review_action_details->comments );
	$sign_off_comments[$review_action_details->action_history_id] = $comments;

	// get action_history from the history_id - to get the post_id
	$history_details = $ow_history_service->get_action_history_by_id( $review_action_details->action_history_id );
	$post_id = $history_details->post_id;
   }
}

?>
<div id="ow-editorial-readonly-comment-popup">
    <div id="ow-comment-popup" class="ow-modal-dialog ow-top_15">
        <a class="ow-modal-close" onclick="ow_modal_close( event );"></a>
        <div class="ow-modal-header">
            <h3 class="ow-modal-title" id="poststuff"><?php echo __( 'Editorial Comments On: ' ) . get_the_title( $post_id ); ?></h3>
        </div>
        <div class="ow-modal-body">
            <div class="ow-textarea">
                <div id="ow-scrollbar" class="ow-comment-popup-scrollbar">
                    <?php
                    if ( $sign_off_comments ) {
                       foreach ( $sign_off_comments as $history_id => $comments ) {
                          if ( $comments ) {
                             foreach ( $comments as $comment ) {
                                $send_id = $comment->send_id;
                                $user = OW_Utility::instance()->get_user_role_and_name( $send_id );
                                ?>
                                <ul id="readonly-comments">
                                    <li>
                                        <?php echo get_avatar( $send_id, 64 ); ?>
                                        <p class="author-name"><?php echo $user->username; ?></p>
                                        <p class="author-role"><?php echo $user->role; ?></p>
                                    </li>
                                    <li>
                                       <?php
                                       echo $ow_comments_widget->display_sign_off_comment_widget( $comment, FALSE );

                                       $contextual_comments = $this->get_contextual_comments( $history_id, $post_id, $comment->send_id );

                                       if ( empty( $contextual_comments ) ) { // this could be review action
                                           $history_details = $ow_history_service->get_action_history_by_from_id( $history_id );

                                           if ( $history_details ) {
                                              $contextual_comments = $this->get_contextual_comments( $history_details->ID, $post_id, $comment->send_id );
                                           } else { // get the comments for this post_id and this user_id
                                              $contextual_comments = $this->get_contextual_comments( 0, $post_id, $comment->send_id );
                                           }
                                       }
                                       if ( $contextual_comments ) {
                                          foreach ( $contextual_comments as $contextual_comment ) {
                                             echo $ow_comments_widget->display_editorial_comment_widget( $contextual_comment, FALSE, FALSE );
                                          }
                                       }
                                       ?>
                                    </li>
                                </ul>
                                <?php
                             }
                          }
                       }
                    }
                    ?>
                </div>
                <div class="clearfix"></div>
            </div>
        </div>

        <div class="ow-modal-footer">
            <a href="#" onclick="ow_modal_close( event );" class="modal-close"><?php _e( 'Close', 'oweditorialcomments' ); ?></a>
        </div>
    </div>
    <div class="ow-overlay"></div>
</div>
<script>
   function ow_modal_close( event ) {
       event.preventDefault();
       jQuery( '#ow-editorial-readonly-comment-popup' ).remove();
   }
</script>