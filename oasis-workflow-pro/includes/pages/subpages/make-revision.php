<?php
/*
 * Make Revision Popup
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

// FIXED: instead of getting the value from url, use global $post object to get id of current post
global $post;
$ow_admin_post = new OW_Admin_Post();
$post_object = $ow_admin_post->get();
$post_id = $post_title = '';
if ( isset( $post_object ) && ! empty( $post_object ) ) {
   $post_id = esc_attr( $post_object->ID );
   $post_title = esc_attr( $post_object->post_title );
} else if(  isset( $post ) && ! empty( $post ) ) {
   $post_id = esc_attr( $post->ID );
   $post_title = esc_attr( $post->post_title );
}
echo '<script type="text/javascript">
         var ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";
         var post_id = "' . $post_id . '";
         var post_title = "' . $post_title . '";
         var ow_admin_url = "' . admin_url() . '";
      </script>';
?>

<input type="hidden" id="hi_post_id"  value="<?php echo $post_id; ?>" />

<div class="info-setting extra-wide owf-hidden" id="make-revision-submit-div">
   <div class="dialog-title"><strong><?php echo __( "Revision Already Exists", "oasisworkflow" ); ?></strong></div>
   <div>
      <div class="select-part revision-wrap">
         <p>
            <?php echo __( "An active revision already exists for this article. Do you want to delete the existing revised article and create a new revision?", "oasisworkflow" ); ?>
         </p>
         <div class="ow-btn-group changed-data-set">
            <input class="button revision revision-ok button-primary"  type="button" value="<?php echo __( "Yes, delete it and create new one", "oasisworkflow" ); ?>" />
            <span>&nbsp;</span>
            <div class="btn-spacer"></div>
            <input class="button revision revision-no button-primary"  type="button" value="<?php echo __( "No, take me to the revision", "oasisworkflow" ); ?>" />
            <div class="btn-spacer"></div>
            <input class="button revision revision-cancel"  type="button" value="<?php echo __( "Cancel", "oasisworkflow" ); ?>" />
         </div>
      </div>
   </div>
</div>