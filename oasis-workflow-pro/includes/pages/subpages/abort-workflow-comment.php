<?php
   $workflow_terminology_options = get_option( 'oasiswf_custom_workflow_terminology' );
   $heading = ! empty( $workflow_terminology_options['abortWorkflowText'] ) ? $workflow_terminology_options['abortWorkflowText'] : __( 'Abort Workflow', 'oasisworkflow' );
      
?>
<div class="info-setting owf-hidden" id="abort-workflow-settings">
   <div class="dialog-title"><strong><?php echo $heading; ?></strong></div>
   <br class="clear">
   
   <div class="owf-text-info left full-width">
      <div class="left">
         <label><?php echo __( 'Comments:', 'oasisworkflow' ); ?></label>
      </div>
      <div class="left">
         <textarea id="abortComments" class="workflow-comments"></textarea>
      </div>
   </div>
   <br class="clear">
   <p class="abort-set">
      <input type="button" id="abortSave" class="button-primary" value="<?php echo __( "Submit", "oasisworkflow" ); ?>"  />
      <span class="loading">&nbsp;</span>
      <a href="#" id="abortCancel" style="color:blue;"><?php echo __( "Cancel", "oasisworkflow" ); ?></a>
   </p>
</div>