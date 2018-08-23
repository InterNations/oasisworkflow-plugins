<?php
$ow_process_flow = new OW_Process_Flow();
$ow_history_service = new OW_History_Service();

$post_id = intval( $_POST["post_id"] );
$history_id = intval( $_POST["action_id"] );
$history_type = sanitize_text_field( $_POST["history_type"] );

$pre_publish_conditions = $ow_process_flow->get_selected_checklist_conditions( $post_id, $history_id, $history_type );

$count = 1;
?>

<div id="ow-editorial-readonly-checklist-popup">
   <div id="ow-checklist-popup" class="ow-modal-dialog ow-top_15">
      <a class="ow-modal-close" onclick="ow_modal_close(event);"></a>
      <div class="ow-modal-header">
         <h3 class="ow-modal-title" id="poststuff"><?php echo __( 'Pre-Publish checklist for : ' ) . get_the_title( $post_id ); ?></h3>
      </div>
      <div class="ow-modal-body">
         <div class="ow-textarea">
            <div id="ow-scrollbar" class="ow-checklist-popup-scrollbar">
            <?php foreach( $pre_publish_conditions as $conditions ){ ?>
               <p class="ow-sign-off-checklist"><?php echo $count ?>) <?php echo $conditions ?> </p>
             <?php $count ++ ; } ?>
            </div>
            <div class="clearfix"></div>
         </div>
      </div>

      <div class="ow-modal-footer">
         <a href="#" onclick="ow_modal_close(event);" class="modal-close"><?php _e( 'Close', 'oasisworkflow' ); ?></a>
      </div>
   </div>
   <div class="ow-overlay"></div>
</div>

<script>
   function ow_modal_close(event) {
      event.preventDefault();
      jQuery(document).find(".post-condition-count").show();
      jQuery('.loading').hide();
      jQuery('#ow-editorial-readonly-checklist-popup').remove();
   }
</script>