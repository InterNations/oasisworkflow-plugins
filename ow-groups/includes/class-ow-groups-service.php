<?php

/*
 * CRUD actions for Groups
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 *
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) {
   exit();
}

/**
 * Groups Class - CRUD operations
 *
 * @since 1.0
 */
class OW_Groups_Service {

   /**
    * Set things up.
    *
    * @since 1.0
    */
   public function __construct() {

      //ajax actions
      add_action( 'wp_ajax_create_or_update_group', array( $this, 'create_or_update_group' ) );
      add_action( 'wp_ajax_delete_groups', array( $this, 'delete_groups' ) );
      add_action( 'wp_ajax_delete_group', array( $this, 'delete_group' ) );
      add_action( 'wp_ajax_delete_members_from_group', array( $this, 'delete_members_from_group' ) );
      add_action( 'delete_user', array( $this, 'delete_group_member' ) );

      add_filter( 'owf_display_assignee_groups', array( $this, 'display_assignee_groups' ), 10, 1 );
      add_action( 'owf_get_group_users', array( $this, 'get_group_users' ), 10, 1 );
      add_action( 'owf_get_group_members', array( $this, 'get_group_members' ), 10, 1 );

      // Trigger after user deleted
      add_action( 'deleted_user', array( $this, 'purge_group_members' ), 10, 1 );
   }


   /**
    * AJAX: Save groups info
    * @global object $wpdb
    * return int $group_id
    * @since 1.0
    */
   public function create_or_update_group() {
   	global $wpdb;

   	check_ajax_referer( 'save_workflow_group', 'security' );

   	if( !current_user_can( 'ow_create_workflow' ) || !current_user_can( 'ow_edit_workflow' ) ) {
   		return;
   	}

   	$group_name = sanitize_text_field( $_POST['group_name'] );
   	$group_desc = sanitize_text_field( $_POST['group_desc'] );
      $group_members = array();
      if ( isset ( $_POST['group_members'] ) ) {
         $group_members = array_map( 'sanitize_text_field', $_POST['group_members'] );
      }


   	$groups_table = OW_Groups_Plugin_Utility::instance()->get_groups_table_name();

   	// update the group
   	if( isset( $_POST['group_action'] ) && $_POST['group_action'] == "update_group" ) {
   		$group_id = intval( sanitize_text_field( $_POST['group_id'] ) );
   		$data = array(
   				'name' => $group_name,
   				'description' => $group_desc,
   				'update_datetime' => current_time( 'mysql' )
   		);

   		$wpdb->update( $groups_table, $data, array( 'id' => $group_id ), array( '%s', '%s', '%s' ), array( '%d' ) );

   		$this->add_members( $group_id, $group_members );
   	} else {
   		$group_data = array(
   				'name' => $group_name,
   				'description' => $group_desc,
   				'create_datetime' => current_time( 'mysql' )
   		);

   		$group_id = OW_Utility::instance()->insert_to_table( $groups_table, $group_data );
   		if( $group_id && !empty( $group_members ) ) {
   			$this->add_members( $group_id, $group_members );
   		}
   	}
   	echo $group_id;
   	exit();
   }

   /**
    * Delete given user_id from groups members table
    * @global object $wpdb
    * @param type $deleted_user_id
    * @since 1.0
    */
   public function purge_group_members( $deleted_user_id ) {
      global $wpdb;

      if ( !empty( $deleted_user_id ) ) {
      	$deleted_user_id = intval( $deleted_user_id );
      }

      $wpdb->delete( OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name(), array(
          'user_id'  => $deleted_user_id
      ), array(
          '%d'
      ));
   }


   /**
    * List out all the selected group members
    * @param array $args
    * @since 1.0
    */
   public function get_group_members( &$args ) {
      $actors = $args[0];
      $task_assignee = $args[1];
      $assignee_groups = isset( $task_assignee->groups ) ? $task_assignee->groups : array();
      $members = array();
      foreach( $assignee_groups as $group_id ) {
         $members = $this->get_group_actors( $group_id );
      }
      $args[0] = array_merge( $actors, $members );
   }

   /**
    * Return the group actors from given group_id
    * @global object $wpdb
    * @param int $group_id
    * @return array
    * @since 1.0
    */
   public function get_group_actors( $group_id ) {
      global $wpdb;
      if ( !empty( $group_id ) ) {
      	$group_id = intval( $group_id );
      }
      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();
      $members = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $group_members_table
      		WHERE group_id = %d", $group_id ) );
      $actors = array();
      if( $members ) {
         foreach( $members as $member ) {
            $actors[] = $member->user_id;
         }
      }
      return $actors;
   }

   /**
    * Return the available users list
    * @param array $args
    * @since 1.0
    */
   public function get_group_users( &$args ) {
      $task_assignee = $args[1];
      if( isset( $task_assignee->groups ) && ! empty( $task_assignee->groups ) ) {
         $groups = $task_assignee->groups;
         $users_list = array();
         foreach( $groups as $group_id ) {
            $group_info = $this->get_group( $group_id );
            foreach ( $group_info as $key => $group_user ) {

               $user_info = new WP_User( $group_user->user_id );
               /**
                * If user is deleted/non existent then skip and continue
                */
               if( empty( $user_info->ID ) || $user_info->ID == 0 ) {
                  continue;
               }
               $users_list[$key] = new stdClass();
               $users_list[$key]->ID = $group_user->user_id;
               $users_list[$key]->name = $user_info->display_name;
            }

         }
         $args[0] = (object) array_merge( (array) $args[0], (array) $users_list );
      }
   }

   /**
    * Return the group name for given group id
    * @global object $wpdb
    * @param int $group_id
    * @return string
    */
   public function get_group_name_by_group_id( $group_id ) {
      global $wpdb;
      if ( !empty( $group_id ) ) {
      	$group_id = intval( $group_id );
      }
      $groups_table = OW_Groups_Plugin_Utility::instance()->get_groups_table_name();
      $group = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM $groups_table WHERE ID = %d", $group_id ) );
      return $group->name;
   }

   /**
    * Print the available groups onto Step Information Popup
    * @global object $wpdb
    * @since 1.0
    */
   public function display_assignee_groups( $task_assignee ) {
      global $wpdb;

      $groups_table = OW_Groups_Plugin_Utility::instance()->get_groups_table_name();
      $groups = $wpdb->get_results( "SELECT * FROM $groups_table" );
      if( $groups ) {
         // display roles
         $options = '<optgroup label="' . __( 'Groups', 'owgroups' ) . '">';
         foreach ( $groups as $group ) {
         	$selected = '';
            if( is_object( $task_assignee ) && isset( $task_assignee->groups ) &&
            	! empty( $task_assignee->groups ) && in_array( $group->ID, $task_assignee->groups ) ) {

            	$task_assignee->groups = array_map('sanitize_text_field', $task_assignee->groups );
            	$selected = 'selected="selected"';
            }
            $options .= "<option value='g@{$group->ID}' $selected>$group->name</option>";
         }
         $options .= '</optgroup>';
         echo $options;
      }
   }

   /**
    * Retrieve all groups with all user details
    * @global object $wpdb
    * @return mixed
    * @since 1.0
    */
   public function get_all_groups() {
      global $wpdb;
      $groups_table = OW_Groups_Plugin_Utility::instance()->get_groups_table_name();
      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();

      $groups = $wpdb->get_results( "SELECT A.id AS group_id, A.name, A.description, B.user_id AS user_id, B.role_name AS role
			FROM {$groups_table} AS A
			LEFT OUTER JOIN {$group_members_table} AS B ON A.id = B.group_id order by A.name" );
      return $groups;
   }

   /**
    * Return the single group for given group_id
    * @global object $wpdb
    * @param int $group_id
    * @return object
    * @since 1.0
    */
   public function get_group( $group_id ) {
      global $wpdb;
      if ( !empty( $group_id ) ) {
      	$group_id = intval( $group_id );
      }
      $groups_table = OW_Groups_Plugin_Utility::instance()->get_groups_table_name();
      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();

      $groups = $wpdb->get_results( $wpdb->prepare( "SELECT A.id AS group_id, A.name, A.description, B.user_id AS user_id, B.role_name AS role
			FROM {$groups_table} AS A
			LEFT OUTER JOIN {$group_members_table} AS B ON A.id = B.group_id WHERE A.id = %d order by B.role_name", $group_id ) );
      return $groups;
   }

   /**
    * Add group member into table
    * @param int $group_id
    * @param array $group_members
    * @return nothing
    * @since 1.0
    */
   private function add_members( $group_id, $group_members ) {
   	if( !current_user_can( 'ow_create_workflow' ) || !current_user_can( 'ow_edit_workflow' ) ) {
   		return;
   	}
   	if ( ! empty( $group_id ) ) {
   		$group_id = intval( $group_id );
   	}

   	$group_members = array_map( 'sanitize_text_field', $group_members );

      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();

      if( $group_id && !empty( $group_members ) ) {
         foreach ( $group_members as $member ) :
            if( $this->member_is_in_group( $group_id, $member ) ) {
               continue;
            }
            $group_member = explode( "@", $member );
            $group_member_data = array(
                'group_id' => $group_id,
                'user_id' => $group_member[0],
                'role_name' => $group_member[1],
                'create_datetime' => current_time( 'mysql' )
            );
            OW_Utility::instance()->insert_to_table( $group_members_table, $group_member_data );
         endforeach;
      }
   }

   /**
    * Check wheather member is exist in for given group_id
    * @global object $wpdb
    * @param int $group_id
    * @param array $group_member
    * @return boolean
    * @since 1.0
    */
   private function member_is_in_group( $group_id, $group_member ) {
      global $wpdb;
      if ( !empty( $group_id ) ) {
      	$group_id = intval( $group_id );
      }

      $group_member = sanitize_text_field( $group_member );
      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();

      if( $group_id && $group_member ) {
         $member = explode( "@", $group_member );
         $sql = "SELECT DISTINCT user_id FROM {$group_members_table}
         	WHERE group_id = %d AND user_id = %d AND role_name = %s";
         $query_result = $wpdb->get_var( $wpdb->prepare( $sql, trim( $group_id ), $member[0], $member[1] ) );
         if( $query_result != null ) {
            return true;
         }
      }

      return false;
   }

   /**
    * AJAX: Delete Groups
    * @global type $wpdb
    * @return -1 if nonce verification failed | boolean
    * @since 1.0
    */
   public function delete_groups() {
      global $wpdb;

      if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
         check_ajax_referer( 'bulk_delete_groups', 'security' );
      } else {
         check_admin_referer( 'bulk_delete_groups', '_delete_groups' );
      }

      if( !current_user_can( 'ow_delete_workflow' ) || sanitize_text_field( $_REQUEST['action2'] ) == -1 )
         return false;

      if( ! isset( $_REQUEST['groups'] ) || count( $_REQUEST['groups'] ) == 0 ) {
         return;
      }

      $groups = $_REQUEST['groups'];
      $groups = array_map( 'esc_attr', $groups );

      $groups_array = array();
      foreach ( $groups as $group ) {
         array_push( $groups_array, $group );
      }

      $groups_table = OW_Groups_Plugin_Utility::instance()->get_groups_table_name();
      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();

      foreach ( $groups_array as $group ) {
         $wpdb->delete( $groups_table, array( 'ID' => $group ), array( '%d' ) );
         $wpdb->delete( $group_members_table, array( 'group_id' => $group ), array( '%d' ) );
      }

      if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
         echo "success";
         exit;
      }
   }

   /**
    * Delete entire group for given group id
    * @global object $wpdb
    * @return string
    * @since 1.0
    */
   public function delete_group() {
      global $wpdb;

      check_ajax_referer( 'bulk_delete_groups', 'security' );
      if( !current_user_can( 'ow_delete_workflow' ) ) {
         return false;
      }

      $group_id = intval( sanitize_text_field( $_POST['group_id'] ) );

      if( empty( $group_id ) )
         exit;

      $groups_table = OW_Groups_Plugin_Utility::instance()->get_groups_table_name();
      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();

      $wpdb->delete( $groups_table, array( 'ID' => $group_id ), array( '%d' ) );
      $wpdb->delete( $group_members_table, array( 'group_id' => $group_id ), array( '%d' ) );
      echo "success";
      exit();
   }

   /**
    * AJAX: Delete member(s) from selected group
    * @global wpdb Object $wpdb
    * @return -1 if nonce verification failed | true on success
    * @since 1.0
    */
   public function delete_members_from_group() {
   	global $wpdb;

   	check_ajax_referer( 'delete_selected_group_members', 'security' );
      if( !current_user_can( 'ow_delete_workflow' ) ) {
      	return false;
      }

      $group_id = intval( sanitize_text_field( $_POST['group_id'] ) );
      $members = array_map( 'sanitize_text_field', $_POST['members'] );

      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();

      foreach ( $members as $member ) :
         $group_member = explode( "@", $member );
         $wpdb->delete( $group_members_table, array( 'user_id' => $group_member[0], 'role_name' => $group_member[1], 'group_id' => $group_id ), array( '%d', '%s', '%d' ) );
      endforeach;
      echo 'true';
      exit();
   }

   /**
    * Delete group member from all groups for given group id
    * @global object $wpdb
    * @param int $user_id
    * @return nothing
    * @since 1.0
    */
   public function delete_group_member( $user_id ) {
      global $wpdb;

      if( !current_user_can( 'ow_delete_workflow' ) ) {
      	return false;
      }

      $user_id = intval( sanitize_text_field( $user_id ) );

      $group_members_table = OW_Groups_Plugin_Utility::instance()->get_groups_members_table_name();
      $groups = $this->get_all_groups();
      foreach ( $groups as $group ) :
         if( $group->user_id == $user_id ) {
            $wpdb->delete( $group_members_table, array( 'user_id' => $user_id, 'group_id' => $group->group_id ), array( '%d', '%d' ) );
         }
      endforeach;
   }

   /**
    * Print the table row of groups page
    * @since 1.0
    */
   public function get_groups_header() {
      $header = '<tr>';
         $header .= '<th style="" class="manage-column column-cb check-column" id="cb" scope="col">';
            $header .= '<label for="cb-select-all-1" class="screen-reader-text">' . __( 'Select All' ) . '</label><input type="checkbox" id="cb-select-all-1">';
         $header .= '</th>';
         $header .= '<th class="manage-column column-title" id="title" scope="col">';
            $header .= __( 'Group Name', 'owgroups' );
         $header .= '</th>';
         $header .= '<th class="manage-column column-title" id="author" scope="col">';
            $header .= __( 'Users', 'owgroups' );
         $header .= '</th>';
         $header .= '<th class="manage-column column-title" id="used_by_workflow" scope="col">';
            $header .= __( 'Used By Workflows', 'owgroups' );
         $header .= '</th>';
      $header .= '</tr>';
      echo $header;
   }

   /**
    * Return the count of groups used in workflow
    * @return array
    */
   public function get_group_used_by_workflow_count() {
      if( method_exists( 'OW_Workflow_Service', 'get_step_info' ) ) {
         $ow_workflow_service = new OW_Workflow_Service();
         $step_infos = $ow_workflow_service->get_step_info();
         if( $step_infos ) {
            $used_by_workflows = array();
            foreach ( $step_infos as $step_info ) {
               $step_info = json_decode( $step_info->step_info );
               if( isset( $step_info->task_assignee )
                       && ! empty( $step_info->task_assignee )
                       && isset( $step_info->task_assignee->groups )
                       && is_array( $step_info->task_assignee->groups )
                       && ! empty( $step_info->task_assignee->groups ) ) {

                  foreach ( $step_info->task_assignee->groups as $group_id ) {
                     $used_by_workflows[] = $group_id;
                  }

               }
            }
            return array_count_values( $used_by_workflows );
         }
      }
   }

}

$ow_groups_service = new OW_Groups_Service();
?>