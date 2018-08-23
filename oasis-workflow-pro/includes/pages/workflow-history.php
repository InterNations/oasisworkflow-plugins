<?php
/*
 * Workflow History Page
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

if( isset( $_GET['post'] ) && sanitize_text_field( $_GET["post"] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'owf_view_history_nonce' ) ) {
   $selected_post_id = intval( sanitize_text_field( $_GET["post"] ) );
} else {
   $selected_post_id = NULL;
}
$page_number = ( isset( $_GET['paged'] ) && sanitize_text_field( $_GET["paged"] )) ? intval( sanitize_text_field( $_GET["paged"] ) ) : 1;
$trashed     = (isset( $_GET['trashed'] ) && sanitize_text_field( $_GET["trashed"] )) ? sanitize_text_field( $_GET["trashed"] ) : "";

if( $selected_post_id && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'owf_view_history_nonce' ) ) {
   $selected_post = $selected_post_id;
} else {
   $selected_post = NULL;
}

$ow_history_service = new OW_History_Service();
$workflow_service = new OW_Workflow_Service();
$ow_process_flow = new OW_Process_Flow();

$histories = $ow_history_service->get_workflow_history_all( $selected_post, $page_number, "online" );
$count_posts = $ow_history_service->get_workflow_history_count( $selected_post );
$per_page = OASIS_PER_PAGE;
$option = get_option( 'oasiswf_custom_workflow_terminology' );
$workflow_history = ! empty( $option['workflowHistoryText'] ) ? $option['workflowHistoryText'] : __( 'Workflow History', 'oasisworkflow' );
?>
<div class="wrap">
    <div id="icon-edit" class="icon32 icon32-posts-post"><br></div>
    <h2><?php echo $workflow_history; ?></h2>
    <?php if( ! empty( $trashed ) && $trashed === "success_history_deleted" ): ?>
       <div id="message" class="updated notice notice-success is-dismissible">
           <p>
               <?php echo __( "Workflow history deleted successfully.", "oasisworkflow" ); ?>
           </p>
           <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
       </div>
    <?php endif; ?>
   <?php if( ! empty( $trashed ) && $trashed === "success_no_history_deleted" ): ?>
      <div id="message" class="updated notice notice-success is-dismissible">
         <p>
            <?php echo __( "No eligible workflow history found to delete.", "oasisworkflow" ); ?>
         </p>
         <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
      </div>
   <?php endif; ?>
    <?php if( ! empty( $trashed ) && $trashed === "error" ): ?>
       <div id="message " class="error">
           <p>
               <?php echo __( "There was an error while deleting the history. Try again OR contact your administrator.", "oasisworkflow" ); ?>
           </p>
       </div>
    <?php endif; ?>
    <div id="view-workflow" class="workflow-history">
        <div class="tablenav">
           <form method="post"
                 id="workflow-history"
                 action="<?php echo wp_nonce_url( admin_url( 'admin.php?page=oasiswf-history' ), 'owf-workflow-history', 'security' ); ?>">
            <div class="alignleft actions">
               <select id="post_filter" name="post_filter">
                    <option selected="selected" value="0"><?php echo __( "View Post/Page Workflow History", "oasisworkflow" ) ?></option>
                    <?php
                    $wf_posts = $ow_process_flow->get_posts_in_all_workflow();
                    if( $wf_posts ) {
                       $option = '';
                       foreach ( $wf_posts as $wf_post ) {

                          $selected = selected( $selected_post, $wf_post->post_id );
                          $option .= "<option value='$wf_post->post_id' $selected>" . esc_html( $wf_post->title ) . "</option>";
                       }
                       echo $option;
                    }
                    ?>
                </select>

                <a href="javascript:window.open('<?php echo admin_url( 'admin.php?page=oasiswf-history&post=' ) ?>' + jQuery('#post_filter').val() + '<?php echo '&_wpnonce=' . wp_create_nonce( 'owf_view_history_nonce' ); ?>', '_self')">
                    <input type="button" class="button-secondary action" value="<?php echo __( "Filter", "oasisworkflow" ); ?>" />
                </a>&nbsp;

                <?php
                if( current_user_can( 'ow_download_workflow_history' ) ) {
                   ?>
                   <!-- Download Report Button -->
                   <input type="submit" class="button-secondary action" name="download_history" id="download" value="<?php echo __( "Download Workflow History Report", "oasisworkflow" ); ?>" />
                   <?php
                }
                ?>
                <?php
                if( current_user_can( 'ow_delete_workflow_history' ) ) {
                   ?>
                   <input type="button" class="button-secondary action" id="owf-delete-history" value="<?php echo __( "Delete History", "oasisworkflow" ); ?>" />
                   <?php
                }
                ?>

            </div>
           </form>
            <div class="tablenav-pages">
                <?php OW_Utility::instance()->get_page_link( $count_posts, $page_number, $per_page ); ?>
            </div>
        </div>
        <table class="wp-list-table widefat fixed posts" cellspacing="0" border="0">
            <thead>
                <?php $ow_history_service->get_table_header(); ?>
            </thead>
            <tfoot>
                <?php $ow_history_service->get_table_header(); ?>
            </tfoot>
            <tbody id="coupon-list">
                <?php
                // Hide/Show actor column using the filter
                $current_user_id = get_current_user_id();
                $current_user_role = OW_Utility::instance()->get_user_role( $current_user_id );

                $roles = array();

                $user_roles = apply_filters( 'owf_hide_attributes_by_role', $roles );
               
                if( $histories ):                   
                   foreach ( $histories as $row ) {
                      $workflow_name = "<a href='admin.php?page=oasiswf-admin&wf_id=" . $row->workflow_id . "'><strong>" . $row->wf_name;
                      if( !empty( $row->version ) ) {
                         $workflow_name .= " (" . $row->version . ")";
                      }
                      $workflow_name .= "</strong></a>";

                         if( $row->assign_actor_id != -1 ) { //assignment and/or publish steps
                            echo "<tr>";
                            echo "<th scope='row' class='check-column'><input type='checkbox' name='linkcheck[]' value='1'></th>";
                            echo "<td>
										<a href='post.php?post={$row->post_id}&action=edit'><strong>" . esc_html( $row->post_title ) . "</strong></a>
									</td>";
                                                        
                            if( ! in_array( $current_user_role, $user_roles ) ) {
                               if( $row->userid == 0 ) {
                               $actor = "System";
                              } else {
                                 $actor = OW_Utility::instance()->get_user_name( $row->userid );
                                 if( empty( $actor ) ) { // in case the actor is deleted or non existent
                                    $actor = "System";
                                 }
                              }
                              echo "<td>{$actor}</td>";
                            }            
                            
                            echo "<td>{$workflow_name} <br> [{$workflow_service->get_step_name( $row )}]</td>";                           
                            echo "<td>" . OW_Utility::instance()->format_date_for_display( $row->create_datetime, "-", "datetime" ) . "</td>";
                            echo "<td>" . OW_Utility::instance()->format_date_for_display( $ow_process_flow->get_sign_off_date( $row ), "-", "datetime" ) . "</td>";
                            echo "<td>{$ow_process_flow->get_sign_off_status( $row )}</td>";
                            echo "<td class='comments column-comments'>
										<div class='post-com-count-wrapper'>
											<strong>
												<a href='#' actionid='{$row->ID}' actionstatus='{$row->action_status}' class='post-com-count post-com-count-approved' real='history'>
													<span class='comment-count-approved'>{$ow_process_flow->get_sign_off_comment_count( $row )}</span>
												</a>
												<span class='loading' style='display:none'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
											</strong>
										</div>
									  </td>";
                             // add-ons to add rows to history table on history page
                            apply_filters( 'display_history_column_content', $row->post_id, $row, 'history' );
                            echo "</tr>";
                         }
                         if( $row->assign_actor_id == -1 ) { //review step
                            $review_rows = $ow_history_service->get_review_action_by_history_id( $row->ID, "update_datetime" );
                            if( $review_rows ) {
                               foreach ( $review_rows as $review_row ) {
                                  echo "<tr>";
                                  echo "<th scope='row' class='check-column'><input type='checkbox' name='linkcheck[]' value='1'></th>";
                                  echo "<td><a href='post.php?post={$row->post_id}&action=edit'><strong>" . esc_html( $row->post_title ) . "</strong></a></td>";
                                                                    
                                  if( ! in_array( $current_user_role, $user_roles ) ) {
                                    if( $review_row->actor_id == 0 ) {
                                     $actor = "System";
                                    } else {
                                       $actor = OW_Utility::instance()->get_user_name( $review_row->actor_id );
                                       if( empty( $actor ) ) { // in case the actor is deleted or non existent
                                          $actor = "System";
                                       }
                                    }
                                    echo "<td>{$actor}</td>";
                                  }
                                  
                                  echo "<td>{$workflow_name} <br> [{$workflow_service->get_step_name( $row )}] </td>";
                                  echo "<td>" . OW_Utility::instance()->format_date_for_display( $row->create_datetime, "-", "datetime" ) . "</td>";
                                  $signoff_date = $review_row->update_datetime;
                                  echo "<td>" . OW_Utility::instance()->format_date_for_display( $signoff_date, "-", "datetime" ) . "</td>";
                                  // If editors' review status is "no_action" (Not acted upon) then set user status as "No action taken"
                                  if( $review_row->review_status == "no_action" || $review_row->review_status == "abort_no_action" ) {
                                     $review_status = __( "No Action Taken", "oasisworkflow" );
                                  } else {
                                     if( $ow_process_flow->get_next_step_sign_off_status( $row ) == "complete" ) {
                                        $review_status = __( "Workflow completed", "oasisworkflow" );
                                     } else if( $ow_process_flow->get_next_step_sign_off_status( $row ) == "cancelled" ) {
                                        $review_status = __( "Cancelled", "oasisworkflow" );
                                     } else {
                                        $review_status = $ow_process_flow->get_review_sign_off_status( $row, $review_row );
                                     }
                                  }
                                  echo "<td>$review_status</td>";
                                  echo "<td class='comments column-comments'>
												<div class='post-com-count-wrapper'>
													<strong>
														<a href='#' actionid='{$review_row->ID}' actionstatus='{$review_row->review_status}' class='post-com-count post-com-count-approved' real='review'>
															<span class='comment-count-approved'>{$ow_process_flow->get_review_sign_off_comment_count( $review_row, $row->post_id, $review_row->actor_id )}</span>
														</a>
														<span class='loading' style='display:none'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													</strong>
												</div>
											  </td>";
                                   // add-ons to add rows to history table on history page
                                   apply_filters( 'display_history_column_content', $row->post_id, $review_row, 'review_history' );
                                  echo "</tr>";
                               }
                            }
                         }                     
                   }
                else:
                   echo "<tr>";
                   echo "<td colspan='9' class='no-found-td'><lavel>";
                   echo __( "No workflow history data found.", "oasisworkflow" );
                   echo "</label></td>";
                   echo "</tr>";
                endif;
                ?>
            </tbody>
        </table>
        <?php wp_nonce_field( 'owf-workflow-history', 'owf_workflow_history_nonce' ); ?>
        <input type="hidden" name="owf_inbox_ajax_nonce" id="owf_inbox_ajax_nonce" value="<?php echo wp_create_nonce( 'owf_inbox_ajax_nonce' ); ?>" />
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php OW_Utility::instance()->get_page_link( $count_posts, $page_number, $per_page ); ?>
            </div>
        </div>
    </div>
</div>

<div id="post_com_count_content"></div>
<div id="ajaxcc"></div>