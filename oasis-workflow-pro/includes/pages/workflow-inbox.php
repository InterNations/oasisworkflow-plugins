<?php
/*
 * Workflow Inbox Page
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

// sanitize the data
$selected_user = (isset( $_GET['user'] ) && sanitize_text_field( $_GET["user"] )) ? intval( sanitize_text_field( $_GET["user"] )) : get_current_user_id();
$page_number = (isset($_GET['paged']) && sanitize_text_field( $_GET["paged"] )) ? intval( sanitize_text_field( $_GET["paged"] )) : 1;

$ow_inbox_service = new OW_Inbox_Service();
$ow_process_flow = new OW_Process_Flow();
$ow_workflow_service = new OW_Workflow_Service();

// get assigned posts for selected user
$inbox_items = $ow_process_flow->get_assigned_post( null, $selected_user ) ;
$count_posts = count( $inbox_items );
$per_page = OASIS_PER_PAGE;

// get workflow settings
$hide_compare_button = get_option("oasiswf_hide_compare_button");

?>
<div class="wrap">
	<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>
	<h2><?php echo __("Inbox", "oasisworkflow"); ?></h2>
   <div id="owf-inbox-error" class="owf-hidden"></div>
	<div id="workflow-inbox">
		<div class="tablenav top">

      <!-- Bulk Actions Start -->
      <?php do_action( 'owf_bulk_actions_section' ); ?>
      <!-- Bulk Actions End -->

      <input type="hidden" id="hidden_task_user" value="<?php echo esc_attr( $selected_user ); ?>" />
		<?php if ( current_user_can( 'ow_view_others_inbox' )  ){?>
			<div class="alignleft actions">
				<select id="inbox_filter">
				<option value=<?php echo get_current_user_id();?> selected="selected"><?php echo __("View inbox of ", "oasisworkflow")?></option>
					<?php
					$assigned_users = $ow_process_flow->get_assigned_users();
					if( $assigned_users )
					{
						foreach ( $assigned_users as $assigned_user ) {
							if( ( isset( $_GET['user'] ) && $_GET["user"] == $assigned_user->ID ) )
								echo "<option value={$assigned_user->ID} selected>{$assigned_user->display_name}</option>" ;
							else
								echo "<option value={$assigned_user->ID}>{$assigned_user->display_name}</option>" ;
						}
					}
					?>
				</select>

				<a href="javascript:window.open('<?php echo admin_url('admin.php?page=oasiswf-inbox&user=')?>' + jQuery('#inbox_filter').val(), '_self')">
					<input type="button" class="button-secondary action" value="<?php echo __("Show", "oasisworkflow"); ?>" />
				</a>
			</div>
		<?php }?>
			<ul class="subsubsub"></ul>
			<div class="tablenav-pages">
				<?php OW_Utility::instance()->get_page_link( $count_posts, $page_number, $per_page );?>
			</div>
		</div>
		<table class="wp-list-table widefat fixed posts" cellspacing="0" border=0>
         <?php $inbox_column_headers = $ow_inbox_service->get_table_header(); ?>
			<thead>
            <tr>
               <?php
                  echo implode( '', $inbox_column_headers );
               ?>
            </tr>   
			</thead>
			<tfoot>
            <tr>
               <?php
                  echo implode( '', $inbox_column_headers );
               ?>
            </tr>
			</tfoot>
			<tbody id="coupon-list">
				<?php
               $inbox_data = array(
                  "page_number" => $page_number,
                  "per_page" => $per_page,
                  "selected_users" => $selected_user
               );
               $ow_inbox_service->get_table_rows( $inbox_data, $inbox_items, $inbox_column_headers );
				?>
			</tbody>
		</table>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php OW_Utility::instance()->get_page_link( $count_posts, $page_number, $per_page );?>
			</div>
		</div>
	</div>
</div>
<span id="wf_edit_inline_content"></span>
<div id ="step_submit_content"></div>
<div id="reassign-div"></div>
<div id="post_com_count_content"></div>
<input type="hidden" name="owf_claim_process_ajax_nonce" id="owf_claim_process_ajax_nonce" value="<?php echo wp_create_nonce( 'owf_claim_process_ajax_nonce' ); ?>" />
<input type="hidden" name="owf_inbox_ajax_nonce" id="owf_inbox_ajax_nonce" value="<?php echo wp_create_nonce( 'owf_inbox_ajax_nonce' ); ?>" />