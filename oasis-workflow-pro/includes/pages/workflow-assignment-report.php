<?php
/*
 * Workflow Assignment Report
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

$page_number = (isset( $_GET['paged'] ) && sanitize_text_field( $_GET["paged"] )) ? intval( sanitize_text_field( $_GET["paged"] )) : 1;

$ow_report_service = new OW_Report_Service();
$ow_process_flow = new OW_Process_Flow();
$workflow_service = new OW_Workflow_Service();

$count_assignments = $ow_report_service->count_workflow_assignments();
$assigned_tasks = $ow_report_service->generate_workflow_assignment_report( $page_number ) ;

$per_page = OASIS_PER_PAGE;

?>
<div class="wrap">
	<form id="assignment_report_form" method="post" action="<?php echo admin_url('admin.php?page=oasiswf-reports&tab=userAssignments');?>">
      <div class="tablenav">
      	<ul class="subsubsub"></ul>
      	<div class="tablenav-pages">
      		<?php OW_Utility::instance()->get_page_link( $count_assignments, $page_number, $per_page );?>
      	</div>
      </div>
   </form>
   <table class="wp-list-table widefat fixed posts" cellspacing="0" border=0>
      <?php $report_column_header = $ow_report_service->get_current_assigment_table_header(); ?>
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
            $ow_report_service->get_assignment_table_rows( $assigned_tasks, $report_column_header );
		   ?>  
   	</tbody>
   </table>
   <div class="tablenav">
   	<div class="tablenav-pages">
   		<?php OW_Utility::instance()->get_page_link( $count_assignments,$page_number, $per_page );?>
   	</div>
   </div>
</div>