<?php
global $wp_roles;
$ow_groups_service = new OW_Groups_Service();

$used_by_workflows = $ow_groups_service->get_group_used_by_workflow_count();

// filter val
$has_filter = '';
if( isset( $_GET['filter'] ) && !empty( $_GET['filter'] ) ) {
   $has_filter = sanitize_text_field( $_GET['filter'] );
}

if( isset( $_REQUEST['owt_bulk_action'] ) && sanitize_text_field( $_REQUEST['owt_bulk_action'] ) == "Apply" ) {
   $ow_groups_service->delete_groups();
}

$groups = $ow_groups_service->get_all_groups();
$all_groups = array();
foreach ( $groups as $group ) {
   $all_groups[$group->group_id][] = array( "group_id" => $group->group_id, "group_name" => $group->name, "user_id" => $group->user_id, "user_role" => $group->role );
}
$groups_with_members = array();
$groups_without_members = array();
foreach ( $all_groups as $k => $data ) {
   if( $data[0]['user_id'] == '' ) {
      $groups_without_members[$k] = $data;
   } else {
      $groups_with_members[$k] = $data;
   }
}
?>
<div class="wrap">
   <h2>
      <?php _e( 'Groups', 'owgroups' ); ?>
      <a class="add-new-h2"
         href="<?php echo esc_url( add_query_arg( array( "page" => "add-new-group" ) ) ); ?>">
            <?php _e( 'Add New Group', 'owgroups' ); ?>
      </a>
   </h2>

   <ul class="subsubsub">

      <li class="all">
         <a <?php echo '' == $has_filter ? "class='current'" : ''; ?> href="<?php echo admin_url( 'admin.php?page=oasiswf-groups' ); ?>">
            <?php _e( 'All', 'owgroups' ); ?>
            <span class="count">(<?php echo count( $all_groups ); ?>)</span>
         </a> |
      </li>

      <li class="has_users">
         <a <?php echo 'has_users' === $has_filter ? "class='current'" : ''; ?>
            href="<?php echo esc_url( add_query_arg( array( "page" => "oasiswf-groups", "filter" => "has_users" ) ) ); ?>">
               <?php _e( 'Has Users', 'owgroups' ); ?>
            <span class="count">(<?php echo count( $groups_with_members ); ?>)</span>
         </a> |
      </li>

      <li class="no_users">
         <a <?php echo 'no_users' === $has_filter ? "class='current'" : ''; ?>
            href="<?php echo esc_url( add_query_arg( array( "page" => "oasiswf-groups", "filter" => "no_users" ) ) ); ?>"><?php _e( 'No Users', 'owgroups' ); ?>
            <span class="count">(<?php echo count( $groups_without_members ); ?>)</span>
         </a>
      </li>
   </ul>

   <form method="post" action="" id="posts-filter">
      <br class="clear">

      <table class="wp-list-table widefat fixed striped posts">
         <thead>
            <?php $ow_groups_service->get_groups_header(); ?>
         </thead>

         <tfoot>
            <?php $ow_groups_service->get_groups_header(); ?>
         </tfoot>

         <tbody id="the-list">
            <?php
            if( 'has_users' === $has_filter ) {
               $all_groups = $groups_with_members;
            } else if( 'no_users' === $has_filter ) {
               $all_groups = $groups_without_members;
            }
            if( $all_groups ) :
               foreach ( $all_groups as $group_id => $group_data ):
                  $groups_members = array();
                  ?>
                  <tr>
                     <th class="check-column" scope="row">
                        <label for="cb-select-<?php echo $group_data[0]['group_id']; ?>"
                               class="screen-reader-text"><?php printf( __( 'Select', 'owgroups' ) . '%s', $group_id ); ?>
                        </label>
                        <input type="checkbox" value="<?php echo $group_data[0]['group_id']; ?>"
                               name="groups[]" class="groups-check" id="cb-select-<?php echo $group_data[0]['group_id']; ?>"/>
                        <div class="locked-indicator"></div>
                     </th>
                     <?php
                     $user_count = 0;
                     for ( $i = 0; $i < count( $group_data ); $i ++ ) {

                        // if user id is not set then skip
                        if( empty( $group_data[$i]["user_id"] ) ) {
                           continue;
                        }
                        $groups_members[$group_data[$i]["user_role"]][] = array( "user_id" => $group_data[$i]["user_id"] );
                        ++$user_count;
                     }
                     ?>
                     <td class="post-title page-title column-title">
                        <strong>
                           <a title="Edit" href="<?php echo esc_url( add_query_arg( array( "page" => "add-new-group", "group" => $group_data[0]['group_id'], "action" => "edit" ) ) ); ?>"
                              class="row-title"><?php echo $group_data[0]['group_name']; ?>
                           </a>
                        </strong>
                        <div class='row-actions'>
                           <?php if( current_user_can( 'ow_edit_workflow' ) ) : ?>
                              <span>
                                 <a href="<?php echo esc_url( add_query_arg( array( "page" => "add-new-group", "group" => $group_data[0]['group_id'], "action" => "edit" ) ) ); ?>">
                                    <?php echo __( "Edit", 'owgroups' ) ?></a>
                              </span>&nbsp;|&nbsp;
                           <?php endif; ?>
                           <?php if( current_user_can( 'ow_delete_workflow' ) ) : ?>
                              <span>
                                 <?php $current_group_id = $group_data[0]['group_id']; ?>
                                 <a href=<?php echo "javascript:void(0); onclick=\"ow_delete_group('" . $current_group_id . "')\""; ?>>
                                    <?php echo __( "Delete", 'owgroups' ) ?>
                                 </a>
                              </span>
                           <?php endif; ?>
                        </div>
                     </td>
                     <td>
                        <?php
                        printf( _n( '%d User', '%d Users', $user_count, 'owgroups' ), $user_count );
                        ?>
                     </td>
                     <td><?php echo isset( $used_by_workflows[$group_id] ) ? $used_by_workflows[$group_id] : 0; ?></td>
                  </tr>
                  <?php
               endforeach; // Outer loop
            else:
               ?>
               <tr>
                  <td colspan="3"><?php _e( 'No groups found.', 'owgroups' ); ?></td>
               </tr>
            <?php endif; ?>
         </tbody>
      </table>

      <div class="tablenav bottom">
         <div class="alignleft actions bulkactions">
            <select name="action2">
               <option selected="selected" value="-1"><?php _e( 'Bulk Actions', 'owgroups' ); ?></option>
               <option class="hide-if-no-js" value="delete"><?php _e( 'Delete', 'owgroups' ); ?></option>
            </select>
            <input type="submit" value="Apply" class="button action" id="owt_bulk_action2" name="owt_bulk_action">
         </div>
         <div class="alignleft actions"></div>
         <br class="clear">
      </div>
      <?php wp_nonce_field( 'bulk_delete_groups', '_delete_groups', false ); ?>
   </form>
   <br class="clear">
</div>
