<?php
/*
 * Utilities class for Oasis Workflow Teams
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/*
 * Utilities class - singleton class
 *
 * @since 2.0
 */
 
class OW_Teams_Utility {

	/**
	 * Private ctor so nobody else can instance it
	 *
	 */
	private function __construct() {

	}

	/**
	 * Call this method to get singleton
	 *
	 * @return singleton instance of OW_Utility
	 */
	public static function instance() {

		static $instance = NULL;
		if ( is_null( $instance ) ) {
			$instance = new OW_Teams_Utility();
		}

		return $instance;
	}
	
	// Returns workflow team table name
	public function get_teams_table_name()
	{
		global $wpdb;
		return $wpdb->prefix . "fc_teams";
	}
	
	// Returns workflow team members table name
	public function get_teams_members_table_name()
	{
		global $wpdb;
		return $wpdb->prefix . "fc_team_members";
	}	
   
   /**
    *Display error or success message in the admin section
    *
    * @param array $data containing type and message
    * @return string with html containing the error message
    * 
    * @since 2.0 initial version
    */
	
	public function admin_notice( $data = array() ) {
		// extract message and type from the $data array
      $message = isset( $data['message'] ) ? $data['message'] : "";
      $message_type = isset( $data['type'] ) ? $data['type'] : "";

      switch ( $message_type ) {
         case 'error':
            $admin_notice = '<div id="message" class="error notice is-dismissible">';
            break;
         case 'update':
            $admin_notice = '<div id="message" class="updated notice is-dismissible">';
            break;
         case 'update-nag':
            $admin_notice = '<div id="message" class="update-nag">';
            break;
			default:
				$message = __( 'There\'s something wrong with your code...', 'owfteams' );
				$admin_notice = "<div id=\"message\" class=\"error\">\n";
				break;
		}
	
		$admin_notice .= "    <p>" . __( $message, 'owfteams' ) . "</p>\n";
		$admin_notice .= "</div>\n";
		return $admin_notice;
	}
	
	public function get_users_by_roles( $roles ) {
		global $wpdb;
		if( count( $roles ) > 0 )
		{
			$userstr = null;
			// Instead of using WP_User_Query, we have to go this route, because user role editor
			// plugin has implemented the pre_user_query hook and excluded the administrator users to appear in the list
	
			foreach ( $roles as $k => $v ){
				$user_role = '%' . $k . '%';
				$users = $wpdb->get_results( $wpdb->prepare( "SELECT users_1.ID, users_1.display_name FROM {$wpdb->base_prefix}users users_1
				INNER JOIN {$wpdb->base_prefix}usermeta usermeta_1 ON ( users_1.ID = usermeta_1.user_id )
				WHERE (usermeta_1.meta_key = '{$wpdb->prefix}capabilities' AND CAST( usermeta_1.meta_value AS CHAR ) LIKE %s)",
				$user_role ) );
	
				foreach ( $users as $user ) {
	
					$userObj = new WP_User( $user->ID );
					if ( !empty( $userObj->roles ) && is_array( $userObj->roles ) ) {
						foreach ( $userObj->roles as $userrole )
						{
							if ( $userrole == $k || 'owfpostauthor' == $k) // if the selected role is 'postauthor'- the custom role.
							{
								$part["ID"] = $user->ID ;
								$part["name"] = $user->display_name;
								$userstr[] =(object) $part ;
								break;
							}
						}
					}
				}
			}
			return $userstr;
		}
		return null ;
	}
	
	public function get_meta_data_by_key($meta_key, $meta_value, $limit = 1){
		global $wpdb;
		if (1 == $limit && !empty( $meta_value ))
			return $value = $wpdb->get_var( $wpdb->prepare("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1" , $meta_key, $meta_value) );
		else
			return $value = $wpdb->get_results( $wpdb->prepare("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT %d" , $meta_key, $meta_value, $limit) );
	}	
   
   /**
    * Utility function to get the current user's role
    *
    * @since 2.8
    */

   public function get_current_user_role() {
      global $wp_roles;
      foreach ( $wp_roles->role_names as $role => $name ) :
         if ( current_user_can( $role ) ) {
            return $role;
         }
      endforeach;
   }
   
   
}

?>