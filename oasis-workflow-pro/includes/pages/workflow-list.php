<?php
/*
 * Workflow List Page
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


$action = (isset( $_GET['action'] ) && sanitize_text_field( $_GET["action"] )) ? sanitize_text_field( $_GET["action"] ) : "all";
$pagenum = (isset( $_GET['paged'] ) && sanitize_text_field( $_GET["paged"] )) ? intval( sanitize_text_field( $_GET["paged"] ) ) : 1;

$workflow_service = new OW_Workflow_Service();
$workflows = $workflow_service->get_workflow_list( $action );

$wf_class[$action] = 'class="current"';
$wf_count_by_status = $workflow_service->get_workflow_count_by_status();
$workflow_count = count( $workflows );

$per_page = OASIS_PER_PAGE;
?>
<form id="wf-form" method="post" action="<?php echo admin_url( 'admin.php?page=oasiswf-admin' ); ?>">
    <input type="hidden" id="hi_wf_id" name="wf_id" />
    <input type="hidden" id="save_action" name="save_action" value="workflow_copy" />
    <input type="hidden" id="define-workflow-title" name="define-workflow-title" />
    <input type="hidden" id="define-workflow-description" name="define-workflow-description" />
    <?php wp_nonce_field( 'owf_workflow_create_nonce', 'owf_workflow_create_nonce' ); ?>
</form>
<?php
	// include the file for the workflow copy popup
	include( OASISWF_PATH . 'includes/pages/subpages/workflow-copy-popup.php' );
?>
<div class="wrap">
    <div id="icon-edit" class="icon32 icon32-posts-post"><br></div>
    <h2><?php echo __( "Edit Workflows", "oasisworkflow" )?>
    <?php
    if ( current_user_can( 'ow_create_workflow' ) ) {
    ?>
    	<a href="admin.php?page=oasiswf-add" class="add-new-h2"><?php echo __( "Add New", "oasisworkflow" ); ?></a>
    <?php
    }
    ?>
    </h2>
    <div id="view-workflow">
        <div class="tablenav">
            <ul class="subsubsub">
                <?php
                $active_val = isset( $wf_class["active"] ) ? $wf_class["active"] : "";
                $inactive_val = isset( $wf_class["inactive"] ) ? $wf_class["inactive"] : "";
                $all_val = isset( $wf_class["all"] ) ? $wf_class["all"] : "";
                echo '<li class="all"><a href="admin.php?page=oasiswf-admin"' . $all_val . ' >' . __( 'All', "oasisworkflow" ) .
                '<span class="count"> (' . $wf_count_by_status->wf_all . ')</span></a></li>';
                echo ' | <li class="all"><a href="admin.php?page=oasiswf-admin&action=active"' . $active_val . '>' . __( 'Active', "oasisworkflow" ) .
                '<span class="count"> (' . $wf_count_by_status->wf_active . ')</span></a> </li>';
                echo ' | <li class="all"><a href="admin.php?page=oasiswf-admin&action=inactive"' . $inactive_val . '>' . __( 'Inactive', "oasisworkflow" ) .
                '<span class="count"> (' . $wf_count_by_status->wf_inactive . ')</span></a> </li>';
                ?>
            </ul>
            <div class="tablenav-pages">
                <?php OW_Utility::instance()->get_page_link( $workflow_count, $pagenum, $per_page ); ?>
            </div>

        </div>
        <form method="post">
        <table class="wp-list-table widefat fixed posts" cellspacing="0" border="0">
            <thead>
                <?php $workflow_service->get_table_header(); ?>
            </thead>
            <tfoot>
                <?php $workflow_service->get_table_header(); ?>
            </tfoot>
            <tbody id="coupon-list">
                <?php
                if ( $workflows ):
                   $act = array( "", "active" );
                   $count = 0;
                   $start = ($pagenum - 1) * $per_page;
                   $end = $start + $per_page;
                   foreach ( $workflows as $wf ) {
                      if ( $count >= $end ) {
                         break;
                      }   
                      if ( $count >= $start ) {
                         $postcount = $workflow_service->get_post_count_in_workflow( $wf->ID );
                         $valid = ( $wf->is_valid ) ? "Yes" : "No";
                         echo "<tr class='alternate author-self status-publish format-default iedit'>";
                         echo "<th scope='row' class='check-column'><input type='checkbox' name='workflows[]' value='".esc_attr( $wf->ID )."'></th>";
                         echo "<td>{$wf->ID}</td>";
                         echo "<td>";
                         $class = "";
                         $content = "";
                         $datadesc = "";
                         if( ! empty( $wf->description ) ){
                         	// display description on mouse over
                           $class = "wf-desc";
                           $content = "hover-data";
                           $datadesc = $wf->description;
                         }
								 echo "<a href='admin.php?page=oasiswf-admin&wf_id=" . $wf->ID . "' class='$class'>";
								 echo "<div class='bold-label' id=" . "workflow-name-" . $wf->ID . ">{$wf->name}</div>";
								 echo "<span class='$content'>$datadesc</span></a>";
								 echo "<div class='row-actions'>";
                         if ( current_user_can( 'ow_edit_workflow' ) ) {
								 	echo "<span><a href='admin.php?page=oasiswf-admin&wf_id=" . $wf->ID . "'>" . __( "Edit", "oasisworkflow" ) . "</a></span>";
                         }
                         if ( ! $postcount && current_user_can( 'ow_delete_workflow' ) ) {
                            $delete_nonce = wp_create_nonce('workflow-delete-nonce');
                            echo "&nbsp;|&nbsp;<span><a href='admin.php?page=oasiswf-admin&wf_id=" . $wf->ID . "&action=delete&_nonce=$delete_nonce' class='workflow-delete'>" . __( "Delete", "oasisworkflow" ) . "</a></span>";
                         }
                         if ( current_user_can( 'ow_create_workflow' ) ) {
	                         echo "&nbsp;|&nbsp;<span>
	                         	<a href='javascript:void(0)' class='duplicate_workflow' wf_id='{$wf->ID}'>" . __( "Copy", "oasisworkflow" ) . "</a>
	                			 </span>";
                         }
                         $action_url = admin_url( 'admin.php?page=oasiswf-admin' );
                         echo "</div>
									</td>";
                         echo "<td>{$wf->version}</td>";
                         echo "<td>" . OW_Utility::instance()->format_date_for_display( $wf->start_date ) . "</td>";
                         echo "<td>" . OW_Utility::instance()->format_date_for_display( $wf->end_date ) . "</td>";
                         echo "<td>{$postcount}</td>";
                         echo "<td>{$valid}</td>";
                         echo "</tr>";
                      }
                      $count ++;
                   }
                else:
                   if ( $action == "all" && current_user_can( 'ow_create_workflow' ) ) {
                      $msg = "<label>" . __( "You don't have any workflows. Let's go ", "oasisworkflow" ) . "</label>
								<a href='admin.php?page=oasiswf-add'>" . __( "create one", "oasisworkflow" ) . "</a> !";
                   } else {
                      $msg = __( "You don't have $action workflows", "oasisworkflow" );
                   }
                   echo "<tr>";
                   echo "<td colspan='8' class='no-found-lbl'>$msg</td>";
                   echo "</tr>";
                endif;
                ?>
            </tbody>
        </table>
        </form>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php OW_Utility::instance()->get_page_link( $workflow_count, $pagenum, $per_page ); ?>
            </div>
        </div>
    </div>
</div>