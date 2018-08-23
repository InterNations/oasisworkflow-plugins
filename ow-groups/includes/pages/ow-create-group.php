<?php
global $wp_roles;
$ow_groups_service = new OW_Groups_Service();
if( isset( $_GET['action'] ) && sanitize_text_field( $_GET['action'] ) == 'edit' ) {
   $group_info = $ow_groups_service->get_group( intval( $_GET['group'] ) );
}
?>
<div class="wrap" style="margin: 1em 3em;">
   <?php
   echo (isset( $_GET['action'] ) && sanitize_text_field( $_GET['action'] ) == 'edit') ? '<h2>' . __( 'Edit Workflow Group', 'owgroups' ) . '</h2>' : '<h2>' . __( 'New Workflow Group', 'owgroups' ) . '</h2>';
   ?>
   <div class="container">
      <form method="post">
         <div class="select-info">
            <div class="list-section-heading"><label><?php _e( 'Name', 'owgroups' ); ?></label></div>
            <input type="text" class="form-element" name="user_group_name" id="user_group_name" value="<?php echo !empty( $group_info ) ? $group_info[0]->name : ''; ?>">
            <br/><span class="description"><?php _e( 'Assign a name for the group to recognize it.', 'owgroups' ); ?></span>
         </div>
         <br>
         <div class="select-info">
            <div class="list-section-heading"><label><?php _e( 'Description', 'owgroups' ); ?></label></div>
            <textarea name="workflow_group_desc" id="workflow_group_desc" rows="4" cols="85" class="form-element"><?php echo!empty( $group_info ) ? $group_info[0]->description : ''; ?></textarea>
            <br/><span class="description"><?php _e( 'Add description about the group.', 'owgroups' ); ?></span>
         </div>
         <br>
         <div class="select-info">
            <div class="list-section-heading"><label><?php _e( 'Add User(s) to the Group', 'owgroups' ); ?></label></div>
            <select name="add_user_to_group[]" id="add_user_to_group" class="" multiple="multiple">
               <?php
                  // Get the users according to the selected participants on the workflow settings tab.
                  $participants = get_option( 'oasiswf_participating_roles_setting' );
                  
                     foreach ( $participants as $role => $name ) {
                        echo "<optgroup label='$name'>";
                        $users = OW_Groups_Plugin_Utility::instance()->get_users_by_roles( array( $role => $name ) );
                        if ( ! empty( $users ) ) {
                           foreach ( $users as $user ) {
                              echo "<option value='" . $user->ID . '@' . $role . "'>$user->name</option>";
                           }
                        }
                              echo "</optgroup>";
                     }
               ?>
            </select>
            <br/><span class="description"><?php _e( 'Select users to add them to the group.', 'owgroups' ); ?></span>
         </div>

         <fieldset>
            <legend><?php _e( 'Existing Group Member (s)', 'owgroups' ); ?></legend>
            <?php
            if( isset( $_GET['action'] ) && $_GET['action'] == 'edit' ) {
               foreach ( $group_info as $group_user ) :
                  $user_object = new WP_User( $group_user->user_id );

                  $group_members[] = array(
                      "user_id" => $user_object->ID,
                      "user_login" => $user_object->user_login,
                      "user_name" => $user_object->display_name,
                      "user_email" => $user_object->user_email,
                      "user_role" => $group_user->role
                  );
               endforeach;
            }
            ?>

            <table class="wp-list-table widefat fixed members">
               <thead>
                  <tr>
                     <th class="manage-column column-cb check-column" id="cb" scope="col"><label for="cb-select-all-1" class="screen-reader-text"><?php _e( 'Select All', 'owgroups' ); ?></label><input type="checkbox" id="cb-select-all-1"></th>
                     <th class="manage-column column-name" scope="col"><span><?php _e( 'Name', 'owgroups' ); ?></span><span class="sorting-indicator"></span></th>
                     <th class="manage-column column-username" scope="col"><span><?php _e( 'User Name', 'owgroups' ); ?></span><span class="sorting-indicator"></span></th>
                     <th class="manage-column column-email" scope="col"><span><?php _e( 'Email', 'owgroups' ); ?></span><span class="sorting-indicator"></span></th>
                     <th class="manage-column column-role" scope="col"><span><?php _e( 'Role', 'owgroups' ); ?></span><span class="sorting-indicator"></span></th>
                  </tr>
               </thead>

               <tfoot>
                  <tr>
                  <tr>
                     <th class="manage-column column-cb check-column" id="cb" scope="col"><label for="cb-select-all-1" class="screen-reader-text"><?php _e( 'Select All', 'owgroups' ); ?></label><input type="checkbox" id="cb-select-all-1"></th>
                     <th class="manage-column column-name" scope="col"><span><?php _e( 'Name', 'owgroups' ); ?></span><span class="sorting-indicator"></span></th>
                     <th class="manage-column column-username" scope="col"><span><?php _e( 'User Name', 'owgroups' ); ?></span><span class="sorting-indicator"></span></th>
                     <th class="manage-column column-email" scope="col"><span><?php _e( 'Email', 'owgroups' ); ?></span><span class="sorting-indicator"></span></th>
                     <th class="manage-column column-role" scope="col"><span><?php _e( 'Role', 'owgroups' ); ?></span><span class="sorting-indicator"></span></th>
                  </tr>
               </tfoot>

               <tbody id="the-list">
                  <?php
                  if( !empty( $group_members ) && $group_members[0]['user_id'] ) :
                     $num = 0;
                     foreach ( $group_members as $member ):
                        ?>
                        <tr id="user-<?php echo $member['user_id']; ?>" class="<?php echo ($num % 2 != 0 ) ? 'alternate' : ''; ?>">
                           <th class="check-column" scope="row">
                              <label for="cb-select-<?php echo $member['user_id'] . "-" . $member['user_role']; ?>" class="screen-reader-text">Select test</label>
                              <input type="checkbox" value="<?php echo $member['user_id'] . "@" . $member['user_role']; ?>" class="members-check" name="user[]" id="cb-select-<?php echo $member['user_id'] . "-" . $member['user_role']; ?>">
                              <div class="locked-indicator"></div>
                           </th>
                           <td class="name column-username">
                              <strong>
                                 <?php echo get_avatar( $member['user_id'], 32, '', $member['user_name'] ); ?>
                                 <?php echo $member['user_name']; ?>
                              </strong>
                           </td>
                           <td class="username column-username">
                              <strong>
                                 <a title="<?php echo __( 'View Profle of ', 'owgroups' ) . $member['user_name']; ?>" href="<?php echo admin_url( 'user-edit.php?user_id=' . $member['user_id'] ); ?>" class="row-title">
                                    <?php echo $member['user_login']; ?></a></strong>
                           </td>
                           <td class="email column-email"><strong><a title="mail to <?php echo $member['user_name']; ?>" href="mailto:<?php echo $member['user_email']; ?>" class="row-title"><?php echo $member['user_email']; ?></a></strong></td>
                           <td class="role column-role"><?php echo ($wp_roles->role_names[$member['user_role']]); ?></td>
                        </tr>
                        <?php
                        $num ++;
                     endforeach;
                  else:
                     ?>
                     <tr>
                        <td colspan="5"><?php _e( 'No group members found.', 'owgroups' ); ?></td>
                     </tr>
                  <?php
                  endif;
                  ?>

               </tbody>
            </table>
            <?php
            if( isset( $_GET['group'] ) && $_GET['group'] != '' ):
               $group_id = intval( $_GET['group'] );
               ?>
               <div class="tablenav bottom">
                  <div class="alignleft actions bulkactions">
                     <select name="delete_members_action">
                        <option selected="selected" value="-1"><?php _e( 'Bulk Actions', 'owgroups' ); ?></option>
                        <option class="hide-if-no-js" value="delete"><?php _e( 'Delete', 'owgroups' ); ?></option>
                     </select>
                     <?php wp_nonce_field( 'delete_selected_group_members', '_bulk_remove_members', false ); ?>
                     <input type="submit" value="Apply" class="button action" id="delete_members" name="delete_members" data-group = "<?php echo $group_id; ?>">
                  </div>
                  <div class="alignleft actions"></div>
                  <br class="clear">
               </div>
               <?php
            endif;
            ?>
         </fieldset>
         <br>
         <div class="select-info">
            <?php
            wp_nonce_field( 'save_workflow_group', '_save_group', false );
            if( isset( $_GET['action'] ) && sanitize_text_field( $_GET['action'] ) == 'edit' ) {
               $group_id = intval( $_GET['group'] );
               echo '<input type="hidden" name="group_action" id="group_action" value="update_group">';
               echo '<input type="hidden" name="group_id" id="group_id" value="' . $group_id . '">';
            }
            ?>
            <input type="submit" name="workflow_group_save" id="workflow_group_save" value="Save" class="button button-primary button-large"/>
         </div>
      </form>

   </div>

</div>
