<?php
/*
 * Delete workflow confirmation
 *
 * @copyright   Copyright (c) 2016, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.3
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

?>

<div class="info-setting owf-hidden" id="delete-workflow-submit-div">
   <div class="dialog-title"><strong><?php echo __( "Confirm Workflow Delete", "oasisworkflow" ); ?></strong></div>
   <div>
      <div class="select-part">
         <p>
            <?php echo __( "Do you really want to delete the workflow?", "oasisworkflow" ); ?>
         </p>
         <div class="ow-btn-group changed-data-set">
            <input class="button delete-workflow button-primary"  type="button" value="<?php echo __( "Delete", "oasisworkflow" ); ?>" />
            <span>&nbsp;</span>
            <div class="btn-spacer"></div>
            <input class="button delete-workflow-cancel" type="button"
            	value="<?php echo __( 'Cancel', 'oasisworkflow' ); ?>"
            />
         </div>
      </div>
   </div>
</div>