<?php
/*
 * Delete History Popup
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
?>
<div class="info-setting owf-hidden" id="delete-history-div">
	<div class="dialog-title"><strong><?php echo __("Delete History", "oasisworkflow") ;?></strong></div>
	<div>
		<div id="delete_history_msg" class="ow-info-message select-info">
		   <?php echo __("Workflow History for posts/pages that are currently active in a workflow will NOT be deleted.", "oasisworkflow" );?>
		</div>
		<div class="select-info owf-text-info left full-width">	
			<label class="half-width">
				<?php echo __("Delete Workflow History for posts/pages which were last updated : ", "oasisworkflow") ;?>
			</label>
			<select id="delete-history-range-select">
				<?php OW_Utility::instance()->get_purge_history_period_dropdown(); ?>
			</select>	
			<br class="clear">			
		</div>	
		<br class="clear">
		<div class="select-info left changed-data-set full-width">
			<input type="button" id="deleteHistoryConfirm" class="button-primary" value="<?php echo __("Delete Workflow History", "oasisworkflow") ;?>" />
			<span>&nbsp;</span>
			<a href="#" id="deleteHistoryCancel"><?php echo __("Cancel", "oasisworkflow") ;?></a>
			<br class="clear">
		</div>
		<br class="clear">	
	</div>
</div>		