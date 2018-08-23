<?php
/*
 * Custom capabilities for Oasis Workflow plugin
*
* @copyright   Copyright (c) 2015, Nugget Solutions, Inc
* @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
* @since       3.4
*
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/**
 *
 * OW_Custom_Capabilities Class
 *
 * define custom capabilities for Oasis Workflow plugin here
 *
 * @since 3.4
 */

class OW_Custom_Capabilities {

	/**
	 * Add new custom capabilities
	 *
	 * @access public
	 * @since  3.4
	 * @global WP_Roles $wp_roles
	 * @return void
	 */
	public function add_capabilities() {
		global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		if ( is_object( $wp_roles ) ) {

			// since we want authors to be able to view, edit and sign off on posts/pages created by other users,
			// lets add "edit_others_posts" and "edit_others_pages" capability to the author role.
			$wp_roles->add_cap( 'author', 'edit_others_posts' );
			$wp_roles->add_cap( 'author', 'edit_others_pages' );

			// workflow crud capabilities
			$wp_roles->add_cap( 'administrator', 'ow_create_workflow' );
			$wp_roles->add_cap( 'administrator', 'ow_edit_workflow' );
			$wp_roles->add_cap( 'administrator', 'ow_delete_workflow' );

			// view report capabilities
			$wp_roles->add_cap( 'administrator', 'ow_view_reports' );
			$wp_roles->add_cap( 'editor', 'ow_view_reports' );
			$wp_roles->add_cap( 'author', 'ow_view_reports' );

			//workflow history capabilities
			$wp_roles->add_cap( 'administrator', 'ow_view_workflow_history' );
			$wp_roles->add_cap( 'administrator', 'ow_delete_workflow_history' );
			$wp_roles->add_cap( 'administrator', 'ow_download_workflow_history' );
			$wp_roles->add_cap( 'editor', 'ow_view_workflow_history' );
			$wp_roles->add_cap( 'editor', 'ow_download_workflow_history' );
			$wp_roles->add_cap( 'author', 'ow_view_workflow_history' );
			$wp_roles->add_cap( 'author', 'ow_download_workflow_history' );

			// workflow sign off actions capabilities
			$wp_roles->add_cap( 'administrator', 'ow_view_others_inbox' );
			$wp_roles->add_cap( 'administrator', 'ow_make_revision' );
			$wp_roles->add_cap( 'administrator', 'ow_make_revision_others' );
			$wp_roles->add_cap( 'administrator', 'ow_abort_workflow' );
			$wp_roles->add_cap( 'administrator', 'ow_reassign_task' );
			$wp_roles->add_cap( 'editor', 'ow_view_others_inbox' );
			$wp_roles->add_cap( 'editor', 'ow_make_revision' );
			$wp_roles->add_cap( 'editor', 'ow_make_revision_others' );
			$wp_roles->add_cap( 'editor', 'ow_abort_workflow' );
			$wp_roles->add_cap( 'editor', 'ow_reassign_task' );
			$wp_roles->add_cap( 'author', 'ow_make_revision' );
			$wp_roles->add_cap( 'author', 'ow_make_revision_others' );
			$wp_roles->add_cap( 'author', 'ow_reassign_task' );

			// other capabilities
			$wp_roles->add_cap( 'administrator', 'ow_skip_workflow' );
         
         // export-import workflows
         $wp_roles->add_cap( 'administrator', 'ow_export_import_workflow' );
         
         // submit-to-workflow
         $wp_roles->add_cap( 'administrator', 'ow_submit_to_workflow' );
         $wp_roles->add_cap( 'editor', 'ow_submit_to_workflow' );
         $wp_roles->add_cap( 'author', 'ow_submit_to_workflow' );
         $wp_roles->add_cap( 'contributor', 'ow_submit_to_workflow' );
         
         // sign-of-workflow
         $wp_roles->add_cap( 'administrator', 'ow_sign_off_step' );
         $wp_roles->add_cap( 'editor', 'ow_sign_off_step' );
         $wp_roles->add_cap( 'author', 'ow_sign_off_step' );
         $wp_roles->add_cap( 'contributor', 'ow_sign_off_step' );
         
         // Duplicate Post
         $wp_roles->add_cap( 'administrator', 'ow_duplicate_post' );
         $wp_roles->add_cap( 'editor', 'ow_duplicate_post' );
         $wp_roles->add_cap( 'author', 'ow_duplicate_post' );
         $wp_roles->add_cap( 'contributor', 'ow_duplicate_post' );
         
		}
	}

	/**
	 * Remove the custom capabilities
	 *
	 * @access public
	 * @since  3.4
	 * @global WP_Roles $wp_roles
	 * @return void
	 */
	public function remove_capabilities() {
		global $wp_roles;

		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		if ( is_object( $wp_roles ) ) {

			// since we added "edit_others_posts" and "edit_others_pages" capability to the author role, lets remove it
			$wp_roles->remove_cap( 'author', 'edit_others_posts' );
			$wp_roles->remove_cap( 'author', 'edit_others_pages' );

			// workflow crud capabilities
			$wp_roles->remove_cap( 'administrator', 'ow_create_workflow' );
			$wp_roles->remove_cap( 'administrator', 'ow_edit_workflow' );
			$wp_roles->remove_cap( 'administrator', 'ow_delete_workflow' );

			// view report capabilities
			$wp_roles->remove_cap( 'administrator', 'ow_view_reports' );
			$wp_roles->remove_cap( 'editor', 'ow_view_reports' );
			$wp_roles->remove_cap( 'author', 'ow_view_reports' );

			//workflow history capabilities
			$wp_roles->remove_cap( 'administrator', 'ow_view_workflow_history' );
			$wp_roles->remove_cap( 'administrator', 'ow_delete_workflow_history' );
			$wp_roles->remove_cap( 'administrator', 'ow_download_workflow_history' );
			$wp_roles->remove_cap( 'editor', 'ow_view_workflow_history' );
			$wp_roles->remove_cap( 'editor', 'ow_download_workflow_history' );
			$wp_roles->remove_cap( 'author', 'ow_view_workflow_history' );
			$wp_roles->remove_cap( 'author', 'ow_download_workflow_history' );

			// workflow sign off actions capabilities
			$wp_roles->remove_cap( 'administrator', 'ow_view_others_inbox' );
			$wp_roles->remove_cap( 'administrator', 'ow_make_revision' );
			$wp_roles->remove_cap( 'administrator', 'ow_make_revision_others' );
			$wp_roles->remove_cap( 'administrator', 'ow_abort_workflow' );
			$wp_roles->remove_cap( 'administrator', 'ow_reassign_task' );
			$wp_roles->remove_cap( 'editor', 'ow_view_others_inbox' );
			$wp_roles->remove_cap( 'editor', 'ow_make_revision' );
			$wp_roles->remove_cap( 'editor', 'ow_make_revision_others' );
			$wp_roles->remove_cap( 'editor', 'ow_abort_workflow' );
			$wp_roles->remove_cap( 'editor', 'ow_reassign_task' );
			$wp_roles->remove_cap( 'author', 'ow_make_revision' );
			$wp_roles->remove_cap( 'author', 'ow_make_revision_others' );
			$wp_roles->remove_cap( 'author', 'ow_reassign_task' );

			// other capabilities
			$wp_roles->remove_cap( 'administrator', 'ow_skip_workflow' );
         
         // export-import workflows
         $wp_roles->remove_cap( 'administrator', 'ow_export_import_workflow' );
         
         // submit-to-workflow
         $wp_roles->remove_cap( 'administrator', 'ow_submit_to_workflow' );
         $wp_roles->remove_cap( 'editor', 'ow_submit_to_workflow' );
         $wp_roles->remove_cap( 'author', 'ow_submit_to_workflow' );
         $wp_roles->remove_cap( 'contributor', 'ow_submit_to_workflow' );
         $wp_roles->remove_cap( 'subscriber', 'ow_submit_to_workflow' );
         
         // sign-of-workflow
         $wp_roles->remove_cap( 'administrator', 'ow_sign_off_step' );
         $wp_roles->remove_cap( 'editor', 'ow_sign_off_step' );
         $wp_roles->remove_cap( 'author', 'ow_sign_off_step' );
         $wp_roles->remove_cap( 'contributor', 'ow_sign_off_step' );
         $wp_roles->remove_cap( 'subscriber', 'ow_sign_off_step' );
         
		}
	}

}
?>