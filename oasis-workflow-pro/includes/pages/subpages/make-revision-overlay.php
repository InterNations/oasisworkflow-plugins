<?php
/*
 * Make Revision Overlay
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

$workflow_terminology_options = get_option( 'oasiswf_custom_workflow_terminology' );
$make_revision_label = !empty( $workflow_terminology_options['makeRevisionText'] ) ? $workflow_terminology_options['makeRevisionText'] : __( 'Make Revision', 'oasisworkflow' );

$doc_revision_make_revision_overlay = get_option( 'oasiswf_revise_post_make_revision_overlay' );
?>
<div class="info-setting make-revision-info owf-hidden" id="make-revision-overlay-submit-div">
   <div class="dialog-title"><strong><?php echo __( "Make Revision", "oasisworkflow" ); ?></strong></div>
   <div>
      <div class="select-part revision-wrap">
         <p>
            <?php echo $doc_revision_make_revision_overlay; ?>
         </p>
         <div class="ow-btn-group changed-data-set">
            <input class="button button-primary" id="make_revision_overlay" type="button"
            	value="<?php echo esc_attr( $make_revision_label ); ?>"/>
            <span>&nbsp;</span>	
            <div class="btn-spacer"></div>
            <input class="button" id="make_revision_overlay_cancel" type="button"
            	value="<?php echo __( 'Cancel', 'oasisworkflow' ); ?>"
            	onclick="window.history.back();" />
         </div>
      </div>
   </div>
</div>