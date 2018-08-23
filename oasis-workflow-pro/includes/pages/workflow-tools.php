<?php
/*
 * Workflow Tools Main Page
 *
 * @copyright   Copyright (c) 2016, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       5.3
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

$selected_tab = ( isset ( $_GET['tab'] ) && sanitize_text_field( $_GET["tab"] )) ?  sanitize_text_field( $_GET['tab'] ) : 'import_export';
?>

<div class="wrap">
	<?php
       $tabs = array( 'import_export' => __('Import/Export', "oasisworkflow"),
       					 'system_info' => __('System Info', "oasisworkflow") );
       echo '<div id="icon-themes" class="icon32"><br></div>';
       echo '<h2 class="nav-tab-wrapper">';
       foreach( $tabs as $tab => $name ){
           $class = ( $tab == $selected_tab ) ? ' nav-tab-active' : '';
           echo "<a class='nav-tab$class' href='?page=oasiswf-tools&tab=$tab'>$name</a>";

       }
       echo '</h2>';
   	switch ( $selected_tab ){
   		case 'import_export' :
   		   include( OASISWF_PATH . "includes/pages/ow-import-export.php" ) ;
   		   break;
   		case 'system_info' :
   		   include( OASISWF_PATH . "includes/pages/ow-system-info.php" ) ;
   		   break;
   	}
	?>
</div>

