<?php
/*
 * Custom capabilities for Team Add-on
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.8
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/**
 *
 * OW_Team_Custom_Capabilities Class
 *
 * define custom capabilities for Team Add-on
 *
 * @since 2.8
 */

class OW_Team_Custom_Capabilities {

	/**
	 * Add new custom capabilities for team
	 *
	 * @access public
	 * @since  2.8
	 * @global WP_Roles $wp_roles
	 * @return void
	 */
	public function add_team_capabilities() {
		global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		if ( is_object( $wp_roles ) ) {
         
         // create teams
         $wp_roles->add_cap( 'administrator', 'ow_create_teams' );
         
         // edit teams
         $wp_roles->add_cap( 'administrator', 'ow_edit_teams' );
         
         // delete teams
         $wp_roles->add_cap( 'administrator', 'ow_delete_teams' );

			// view teams
			$wp_roles->add_cap( 'administrator', 'ow_view_teams' );
         
		}
	}

	/**
	 * Remove the custom capabilities for team
	 *
	 * @access public
	 * @since  2.8
	 * @global WP_Roles $wp_roles
	 * @return void
	 */
	public function remove_team_capabilities() {
		global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		if ( is_object( $wp_roles ) ) {
         
         // create teams
         $wp_roles->remove_cap( 'administrator', 'ow_create_teams' );
         $wp_roles->remove_cap( 'editor', 'ow_create_teams' );
         $wp_roles->remove_cap( 'author', 'ow_create_teams' );
         $wp_roles->remove_cap( 'contributor', 'ow_create_teams' ); 
         $wp_roles->remove_cap( 'subscriber', 'ow_create_teams' ); 
         
         // cedit teams
         $wp_roles->remove_cap( 'administrator', 'ow_edit_teams' );
         $wp_roles->remove_cap( 'editor', 'ow_edit_teams' );
         $wp_roles->remove_cap( 'author', 'ow_edit_teams' );
         $wp_roles->remove_cap( 'contributor', 'ow_edit_teams' );
         $wp_roles->remove_cap( 'subscriber', 'ow_create_teams' ); 
         
         // delete teams
         $wp_roles->remove_cap( 'administrator', 'ow_delete_teams' );
         $wp_roles->remove_cap( 'editor', 'ow_delete_teams' );
         $wp_roles->remove_cap( 'author', 'ow_delete_teams' );
         $wp_roles->remove_cap( 'contributor', 'ow_delete_teams' );
         $wp_roles->remove_cap( 'subscriber', 'ow_create_teams' );

         // view teams
         $wp_roles->remove_cap( 'administrator', 'ow_view_teams' );
         $wp_roles->remove_cap( 'editor', 'ow_view_teams' );
         $wp_roles->remove_cap( 'author', 'ow_view_teams' );
         $wp_roles->remove_cap( 'contributor', 'ow_view_teams' );
         $wp_roles->remove_cap( 'subscriber', 'ow_view_teams' );

      }
	}

}
?>