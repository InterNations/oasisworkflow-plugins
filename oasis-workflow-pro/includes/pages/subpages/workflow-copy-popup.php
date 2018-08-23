<?php
/*
 * Workflow Copy Popup
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
<div id='copy-workflow-popup' class="medium-wide-window">
    <div class="dialog-title"><strong><?php _e('Copy Workflow', 'oasisworkflow');?></strong></div>
    <div class="full-width">
        <div class="full-width">
            <div class="one-third">
                <label><?php _e('Title', 'oasisworkflow'); ?>:</label>
            </div>
            <div class="two-third">
                <input type="text"  id="copy-workflow-title" name="copy-workflow-title" value="" class="field-width-medium" />
            </div>
        </div>
        <div class="full-width">
            <div class="one-third">
                <label><?php _e('Description', 'oasisworkflow'); ?>:</label>
            </div>
            <div class="two-third">
                <textarea id="copy-workflow-description" name="copy-workflow-description" cols="20" rows="10" class="field-width-medium field-height-medium"></textarea>
            </div>
        </div>
        
	    <div class="full-width changed-data-set">
	        <input type="button" id="copy-wf-submit" name="copy_workflow" class="button-primary" value=<?php _e('Copy', 'oasisworkflow'); ?> />
	        <span>&nbsp;</span>
	        <a href="javascript:jQuery.modal.close();" id="copy-wf-cancel"><?php _e('Cancel', 'oasisworkflow'); ?></a>
	    </div>        
    </div>
    <br class="clear" />                       
</div>