<div class="info-setting extra-wide owf-hidden" id="condition-group-trash">
   <div class="dialog-title"><strong><?php echo __( "Confirm Condition Group Delete", "oweditorialchecklist" ); ?></strong></div>
   <div>
      <div class="select-part">
         <p>
            <?php echo __( "This condition group is currently being used in one or multiple workflows. Deleting the condition group will also delete it’s reference from the workflow. Do you want to go ahead and delete it?", "oweditorialchecklist" ); ?>
         </p>
         <div class="ow-btn-group changed-data-set">
            <input class="button condition-trash button-primary"  type="button" value="<?php echo __( "Delete", "oweditorialchecklist" ); ?>" />
            <span>&nbsp;</span>
            <div class="btn-spacer"></div>
            <input class="button condition-trash-cancel" id="trash_cancel" type="button"
            	value="<?php echo __( 'Cancel', 'oweditorialchecklist' ); ?>"
            />
         </div>
      </div>
   </div>
</div>