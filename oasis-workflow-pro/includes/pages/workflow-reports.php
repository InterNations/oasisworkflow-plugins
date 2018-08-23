<?php
/*
 * Workflow Reports Main Page
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

$selected_tab = ( isset ( $_GET['tab'] ) && sanitize_text_field( $_GET["tab"] )) ?  sanitize_text_field( $_GET['tab'] ) : 'userAssignments';
?>
<div class="wrap">
	<?php
       $tabs = array( 'userAssignments' => __('Current Assignments', "oasisworkflow"),
       					 'workflowSubmissions' => __('Workflow Submissions', "oasisworkflow") );
       echo '<div id="icon-themes" class="icon32"><br></div>';
       echo '<h2 class="nav-tab-wrapper">';
       foreach( $tabs as $tab => $name ){
           $class = ( $tab == $selected_tab ) ? ' nav-tab-active' : '';
           echo "<a class='nav-tab$class' href='?page=oasiswf-reports&tab=$tab'>$name</a>";

       }
       echo '</h2>';
   	switch ( $selected_tab ){
   		case 'userAssignments' :
   		   include( OASISWF_PATH . "includes/pages/workflow-assignment-report.php" ) ;
   		   break;
   		case 'workflowSubmissions' :
   		   include( OASISWF_PATH . "includes/pages/workflow-submission-report.php" ) ;
   		   break;
   	}
	?>
</div>