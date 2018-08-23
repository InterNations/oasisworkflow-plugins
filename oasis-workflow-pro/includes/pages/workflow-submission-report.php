<?php
/*
 * Workflow Submission Report
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

$action = ( isset( $_REQUEST["action"] ) && sanitize_text_field( $_REQUEST["action"] )) ? sanitize_text_field( $_REQUEST["action"] ) : "in-workflow";
$post_type = ( isset( $_REQUEST["type"] ) && sanitize_text_field( $_REQUEST["type"] )) ? sanitize_text_field( $_REQUEST["type"] ) : "all";
$page_number = (isset( $_GET['paged'] ) && sanitize_text_field( $_GET["paged"] )) ? intval( sanitize_text_field( $_GET["paged"] ) ) : 1;

// Filter post/page by team
$team_filter = ( isset( $_REQUEST["team-filter"] ) ) ? intval( $_REQUEST["team-filter"] ) : -1;

$ow_report_service = new OW_Report_Service();
$ow_process_flow = new OW_Process_Flow();

$submitted_posts = $ow_process_flow->get_submitted_articles( $post_type, $team_filter, $page_number );

$submitted_post_count = $ow_process_flow->get_submitted_article_count( $post_type, $team_filter );
$un_submitted_posts = $ow_process_flow->get_unsubmitted_articles( $post_type, $page_number );
$un_submitted_post_count = $ow_process_flow->get_unsubmitted_article_count( $post_type );

if ( $action == "in-workflow" ) {
   $posts = $submitted_posts;
   $count_posts = $submitted_post_count;
} else {
   $posts = $un_submitted_posts;
   $count_posts = $un_submitted_post_count;
   $action = 'not-workflow';
}

$per_page = OASIS_PER_PAGE;
?>
<div class="wrap">
    <div id="view-workflow">
        <form id="submission_report_form" method="post" action="<?php echo admin_url( 'admin.php?page=oasiswf-reports&tab=workflowSubmissions' ); ?>">
            <div class="tablenav top">
                <input type="hidden" name="page" value="oasiswf-submission" />
                <input type="hidden" id="action" name="action" value="<?php echo esc_attr( $action ); ?>" />
                <div class="alignleft actions">
                    <select name="type">
                        <option value="all" <?php echo ( $post_type == "all" ) ? "selected" : ""; ?> >All Post Types</option>
                        <?php OW_Utility::instance()->owf_dropdown_post_types_multi( $post_type ); ?>
                    </select>
                  <?php
                     if ( has_filter( 'owf_report_team_filter' ) ) { 
                        if ( $action == 'in-workflow' ) { ?>
                           <select name="team-filter">
                              <option value="-1" <?php echo ( $team_filter == -1 ) ? "selected" : ""; ?> >Select Team</option>
                               <option value="0" <?php echo ( $team_filter == 0 ) ? "selected" : ""; ?> >All Teams</option>
                              <?php
                              $team_options = apply_filters( 'owf_report_team_filter', $team_filter );
                              echo $team_options;
                              ?>
                           </select>
                  <?php
                        }
                     }
                  ?>
                    <input type="submit" class="button action" value="Filter" />
                </div>
                <div>
                    <ul class="subsubsub">
                        <?php
                        $all = ( $action == "all" ) ? "class='current'" : "";
                        $not_in_wf = ( $action == "not-workflow" ) ? "class='current'" : "";
                        $in_wf = ( $action == "in-workflow" ) ? "class='current'" : "";
                        echo '<li class="all"><a id="notInWorkflow" href="#" ' . $not_in_wf . '>' . __( 'Not in Workflow' ) .
                        '<span class="count"> (' . $un_submitted_post_count . ')</span></a> </li>';
                        echo ' | <li class="all"><a id="inWorkflow" href="#" ' . $in_wf . '>' . __( 'In Workflow' ) .
                        '<span class="count"> (' . $submitted_post_count . ')</span></a> </li>';
                        ?>
                    </ul>
                </div>
                <div class="tablenav-pages">
                     <?php
                        $filters = array();
                        $filters["type"]     = $post_type;
                        $filters["team-filter"] = $team_filter;
                        OW_Utility::instance()->get_page_link( $count_posts, $page_number, $per_page, $action, $filters );
                     ?>
                </div>
            </div>
           <?php wp_nonce_field( 'owf_workflow_abort_nonce', 'owf_workflow_abort_nonce' ); ?>
        </form>
        <table class="wp-list-table widefat posts" cellspacing="0" border=0>
            <?php $report_column_header = $ow_report_service->get_submission_report_table_header( $action, $post_type ); ?>
            <thead>
               <tr>
                  <?php
                     echo implode( '', $report_column_header );
                  ?>
               </tr>              
            </thead>
            <tfoot>
               <tr>
                  <?php
                     echo implode( '', $report_column_header );
                  ?>
               </tr>               
            </tfoot>
            <tbody id="coupon-list">
               <?php
                  $ow_report_service->get_submission_report_table_rows( $posts, $action, $report_column_header );
		         ?> 
            </tbody>
        </table>
       
        <?php if ( $action == 'in-workflow' && current_user_can( 'ow_abort_workflow' ) ) : ?>

           <div class="tablenav bottom">
               <!-- Bulk Actions Start -->
               <div class="alignleft actions">
                   <select name="action_type" id="action_type">
                       <option value="none"><?php echo __( "-- Select Action --" ); ?></option>
                       <option value="abort"><?php echo __( "Abort" ); ?></option>
                   </select>
                   <input type="button" class="button action" id="apply_action" value="Apply"><span class='loading owf-hidden' class='inline-loading'></span>
               </div>
               <!-- Bulk Actions End -->
               <!-- Display pages Start -->
               <div class="tablenav-pages">
                   <?php
                     $filters = array();
                     $filters["type"]     = $post_type;
                     $filters["team-filter"] = $team_filter;
                     OW_Utility::instance()->get_page_link( $count_posts, $page_number, $per_page, $action, $filters );
                   ?>
               </div>
               <!-- Display pages End -->
           </div>

        <?php endif; ?>
    </div>
    <div id="out"></div>
</div>
<script type="text/javascript">
   jQuery(document).ready(function () {
       jQuery('#notInWorkflow').click(function (event) {
           jQuery("#action").val("not-workflow");
           jQuery("#submission_report_form").submit();
       });

       jQuery('#inWorkflow').click(function (event) {
           jQuery("#action").val("in-workflow");
           jQuery("#submission_report_form").submit();
       });

       jQuery('input[name=abort-all]').click(function (event) {
           jQuery('input[type=checkbox]').prop('checked', jQuery(this).prop("checked"));
       });

       jQuery('#apply_action').click(function ()
       {
           if (jQuery('#action_type').val() == 'none')
               return;

           var arr = jQuery('input[name=abort]:checked');
           var post_ids = new Array();
           jQuery.each(arr, function (k, v)
           {
               post_ids.push(jQuery(this).val());
           });
           if (post_ids.length === 0)
               return;

           data = {
              action: 'multi_workflow_abort',
              post_ids: post_ids,
              security: jQuery('#owf_workflow_abort_nonce').val()
           };

           jQuery(".loading").show();
           jQuery.post(ajaxurl, data, function (response) {
              if ( response.success ) {
                   jQuery(".loading").hide();
                   jQuery('#inWorkflow').click();
               }
           });
       });

   });
</script>