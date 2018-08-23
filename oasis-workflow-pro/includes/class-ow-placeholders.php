<?php
/*
 * Place Holders class for Oasis Workflow emails
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 *
 */

 // Exit if accessed directly
 if ( ! defined( 'ABSPATH' ) ) {
    exit;
 }

 /*
  * OW_Place_Holders Class
  *
  * @since 2.0
  */


class OW_Place_Holders {

	// define the placeholder constants
	const FIRST_NAME = "%first_name%";
	const LAST_NAME = "%last_name%";
	const POST_TITLE = "%post_title%";
	const POST_CATEGORY = "%category%";
	const POST_LAST_MODIFIED_DATE = "%last_modified_date%";
	const POST_PUBLISH_DATE = "%publish_date%";
   const POST_AUTHOR = "%post_author%";
   const BLOG_NAME = "%blog_name%";
   const POST_SUBMITTER = "%post_submitter%";


	/*
	 * get first name of the user
	 *
	 * @param int $user_id - id of the user
	 * @return string first name of the user
	 * @since 2.0
	 */
	public function get_first_name( $user_id ) {
		// sanitize the input
		$user_id = intval( sanitize_text_field( $user_id ) );

		$nickname = get_user_meta( $user_id, "nickname", true ) ;
		$first_name = get_user_meta( $user_id, "first_name", true ) ;

		// if first name empty then use nickname
		$first_name = ( $first_name ) ? $first_name : $nickname ;

		return $first_name;
	}

	/*
	 * get last name of the user
	 *
	 * @param int $user_id - id of the user
	 * @return string last name of the user
	 * @since 2.0
	 */
	public function get_last_name ( $user_id ) {
		// sanitize the input
		$user_id = intval( sanitize_text_field( $user_id ) );

		$nickname = get_user_meta( $user_id, "nickname", true ) ;
		$last_name = get_user_meta( $user_id, "last_name", true ) ;

		// if last name empty thne use nickname
		$last_name = ( $last_name ) ? $last_name : $nickname ;

		return $last_name;
	}

	/*
	 * get post title
	 *
	 * @param int $post_id
	 * @param string $action_id oasis workflow action name
	 * @param boolean $link, if true returns title as link.
	 * @return string post title as a link, if true
	 *
	 * @since 2.0
	 */
	public function get_post_title( $post_id, $action_id, $link = true ) {
		// sanitize the input
		$post_id = intval( sanitize_text_field( $post_id ) );
		$action_id = intval( sanitize_text_field( $action_id ) );

		// get post details
		$post = get_post( $post_id ) ;
		$post_title = stripcslashes( $post->post_title );
		$post_url = admin_url( 'post.php?post=' . $post_id . '&action=edit&oasiswf=' . $action_id );
      
      // Filter to add custom base url
      $custom_admin_url = apply_filters( 'owf_custom_admin_url', get_admin_url() );
      if( ! empty( $custom_admin_url ) ) {
         $post_url = $custom_admin_url . 'post.php?post=' . $post_id . '&action=edit&oasiswf=' . $action_id;
      }

		if ( $link ) {
			$post_link = '<a href="' . $post_url . '" target="_blank">' . $post_title . '</a>';
		} else {
			$post_link = '"' . $post_title . '"';
		}
      
		return $post_link;
	}

	/*
	 * get post categories
	 *
	 * @param int $post_id
	 * @return mixed category array
	 * @since 2.0
	 * @since 3.8, also return custom categories
	 */
	public function get_post_categories( $post_id ) {
		// sanitize the input
		$post_id = intval( sanitize_text_field( $post_id ) );

		$all_cats = OW_Utility::instance()->get_post_categories( $post_id );

		return $all_cats;

	}

	/*
	 * get post last modified date
	 *
	 * @param int $post_id
	 * @return string in d-m-Y h:i:s format
	 * @since 2.0
	 */
	public function get_post_last_modified_date( $post_id ) {
		// sanitize the input
		$post_id = intval( sanitize_text_field( $post_id ) );

		// get post details
		$post = get_post( $post_id ) ;

		$last_modified = date('d-m-Y h:i:s', strtotime($post->post_modified));

		return $last_modified;
	}

	/*
	 * get post publish date
	 *
	 * @param int $post_id
	 * @return string in d-m-Y h:i:s format
	 * @since 2.0
	 */
	public function get_post_publish_date( $post_id ) {
		// sanitize the input
		$post_id = intval( sanitize_text_field( $post_id ) );

		// get post details
		$post = get_post( $post_id ) ;

		$publish_date = date( 'd-m-Y h:i:s', strtotime($post->post_date) );

		return $publish_date;
	}

   public function get_author_display_name( $post_id ) {
   	// sanitize the input
      $post_id = intval( sanitize_text_field( $post_id ) );
      $author_id = (int) get_post_field( 'post_author', $post_id );

      return get_the_author_meta( 'display_name', $author_id );
   }
   
   /*
	 * get post submitter name	 *
	 * @param int $user_id
	 * @return string $user_name
	 * @since 5.0
	 */
   public function get_post_submitter( $user_id ) {
   	// sanitize the input
		$user_id = intval( sanitize_text_field( $user_id ) );
      $user_details  = get_userdata( $user_id );
      $user_name     = $user_details->data->display_name;
      return $user_name;
   }
}
?>