<?php
/*
 * Custom Statuses page
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

$ow_custom_statuses = new OW_Custom_Statuses();
$terms = $ow_custom_statuses->get_all_custom_statuses();
?>
<div class="wrap">
   <h2><?php _e( 'Custom Statuses', 'oasisworkflow' ); ?></h2>
   <div id="col-right">
      <div class="col-wrap">
         <table class="wp-list-table widefat fixed striped customstatuses">
            <thead>
               <?php $ow_custom_statuses->get_custom_status_header(); ?>
            </thead>
            <tbody id="the-list" class="ui-sortable">
               <?php if( $terms ) : ?>
                  <?php foreach ( $terms as $term ) : ?>
                     <tr id="term-<?php esc_attr_e( $term->term_id ); ?>" class="term-static ui-sortable-handle">
                        <td class="name column-name">
                           <strong><a href="#"><?php echo $term->name; ?></a></strong>
                           <div class="row-actions">
                              <span class="edit">
                                 <a href="<?php echo add_query_arg( array( 'term_id' => esc_attr( $term->term_id ),
                                 		'_wpnonce' => wp_create_nonce( 'edit_custom_status' ),
                                 		'action' => 'edit-status' ) ); ?>">
                                 		<?php _e( 'Edit', 'oasisworkflow' ); ?>
                                 </a>
                                 &nbsp;|&nbsp;
                              </span>
                              <span class="delete delete-status">
                                 <a href="<?php echo add_query_arg( array( 'term_id' => esc_attr( $term->term_id ),
                                 		'_wpnonce' => wp_create_nonce( 'delete-custom-status' ),
                                 		'action' => 'delete-status' ) ); ?>"
                                 		onclick="if (!confirm('<?php _e( 'Are you sure you want to delete the post status?', 'oasisworkflow' ); ?>')) {
                                                return false;
                                             }">
                              		<?php _e( 'Delete', 'oasisworkflow' ); ?>
                              	</a>
                              </span>
                           </div>
                        </td>
                        <td class="slug column-slug">
                           <?php echo $term->slug; ?>
                        </td>
                        <td class="description column-description">
                           <?php echo $term->description; ?>
                        </td>
                     </tr>
                  <?php endforeach; ?>
               <?php endif; ?>
            </tbody>
            <tfoot>
               <?php $ow_custom_statuses->get_custom_status_header(); ?>
            </tfoot>
         </table>
      </div> <!-- .col-wrap -->
   </div> <!-- #col-right -->
   <?php
   $term = $term_id = FALSE;
   if( isset( $_GET['action'] ) && $_GET['action'] == 'edit-status' ) {
      check_admin_referer( 'edit_custom_status', '_wpnonce' );
      $term_id = intval( sanitize_text_field( $_GET['term_id'] ) );
      $term = $ow_custom_statuses->get_single_term_by( 'id', $term_id );
   }
   ?>
   <div id="col-left">
      <div id="col-wrap">
         <div class="form-wrap">
            <form method="post">

               <div class="form-field form-required">
                  <label for="status_name"><?php _e( 'Name', 'oasisworkflow' ); ?></label>
                  <input type="text" aria-required="true" size="20" maxlength="20" id="status_name" name="status_name" value="<?php esc_attr_e( $term ? $term->name : ''  ); ?>">
                  <p class="description"><?php _e( 'The name is used to identify the status. (Max: 20 characters)', 'oasisworkflow' ); ?></p>
               </div>

               <div class="form-field form-required">
                  <label for="slug_name"><?php _e( 'Slug', 'oasisworkflow' ); ?></label>
                  <input type="text" aria-required="true" size="20" maxlength="20" id="slug_name" name="slug_name" value="<?php esc_attr_e( $term ? $term->slug : ''  ); ?>" />
                  <p class="description"><?php _e( 'The slug is the unique ID for the status. It is usually all lowercase and contains only letters, numbers and hyphens.', 'oasisworkflow' ); ?></p>
               </div>

               <div class="form-field">
                  <label for="status_description"><?php _e( 'Description', 'oasisworkflow' ); ?></label>
                  <textarea cols="40" rows="5" id="status_description" name="status_description"><?php echo esc_textarea( $term ? $term->description : ''  ); ?></textarea>
                  <p class="description"><?php _e( 'The description is primarily for administrative use, to give you some context on what the custom status is to be used for.', 'oasisworkflow' ); ?></p>
               </div>

               <?php
               $btn_val = 'add-new';
               $btn_name = 'Add New Status';
               if( $term ) {
                  $btn_val = 'update-status';
                  $btn_name = 'Update Status';
                  wp_nonce_field( 'edit_custom_status' );
               } else {
                  wp_nonce_field( 'custom-status-add-nonce' );
               }
               ?>
               <?php echo '<input id="action" name="action" type="hidden" value="' . esc_attr( $btn_val ) . '" />'; ?>
               <?php echo '<input id="term_id" name="term_id" type="hidden" value="' . esc_attr( $term_id ) . '" />'; ?>
               <p class="submit"><?php submit_button( __( $btn_name, 'oasisworkflow' ), 'primary', 'submit', false ); ?></p>
            </form>
         </div> <!-- .form-wrap -->
      </div> <!-- #col-wrap -->
   </div> <!-- #col-left -->
</div> <!-- .wrap -->
<script>
   jQuery(document).ready(function () {
      jQuery(document).on('blur', '#status_name', function () {
         var status_name = jQuery(this).val();
         if (status_name === '') {
            return false;
         }

         var slug = status_name.toLowerCase()
                 .replace(/[^\w ]+/g, '') // remove hyphens (but not spaces)
                 .replace(/ +/g, '-'); // remove spaces into a single hyphen
         jQuery('#slug_name').val(slug);
      });
   });
</script>
