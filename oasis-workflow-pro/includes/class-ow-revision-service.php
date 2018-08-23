<?php

/*
 * Service class for all document revision actions
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
 * OW_Revision_Service Class
 *
 * @since 2.0
 */

class OW_Revision_Service {
   /*
    * Set things up.
    *
    * @since 2.0
    */

   public function __construct() {
      add_action( 'wp_ajax_get_original', array( $this, 'get_original' ) );

      add_action( 'wp_ajax_get_current_revision', array( $this, 'get_current_revision' ) );
      add_action( 'wp_ajax_is_make_revision_allowed', array( $this, 'is_make_revision_allowed' ) );
      add_action( 'wp_ajax_is_post_a_revision', array( $this, 'is_post_a_revision' ) );

      add_action( 'wp_ajax_alert_make_revision_on_publish_post', array( $this, 'alert_make_revision_on_publish_post' ) );

      add_action( 'wp_ajax_save_as_new_post_draft', array( $this, 'save_as_new_post_draft_ajax' ) );

      add_action( 'wp_ajax_keep_untrashed_revision', array( $this, 'keep_untrashed_revision' ) );
      
      add_action( 'wp_ajax_update_published_post', array( $this, 'update_revision_to_published_post' ) );

      add_action( 'admin_action_save_as_new_post_draft', array( $this, 'save_as_new_post_draft' ) );

      add_action( 'owf_revision_workflow_complete', array( $this, 'schedule_or_publish_revision' ), 10, 1 );
      add_action( 'oasiswf_schedule_revision_update', array( $this, 'update_published_post' ), 10, 1 );

      add_action( 'owf_duplicate_post', array( $this, 'copy_post_meta_info' ), 10, 2 );
      add_action( 'owf_duplicate_page', array( $this, 'copy_post_meta_info' ), 10, 2 );

      add_action( 'owf_update_published_post', array( $this, 'update_post_meta_info' ), 10, 2 );
      add_action( 'owf_update_published_page', array( $this, 'update_post_meta_info' ), 10, 2 );

      add_action( 'owf_duplicate_post', array( $this, 'copy_post_copy_post_taxonomies' ), 10, 2 );
      add_action( 'owf_duplicate_page', array( $this, 'copy_post_copy_post_taxonomies' ), 10, 2 );

      add_action( 'owf_update_published_post', array( $this, 'copy_post_copy_post_taxonomies' ), 10, 2 );
      add_action( 'owf_update_published_page', array( $this, 'copy_post_copy_post_taxonomies' ), 10, 2 );

      add_action( 'owf_duplicate_post', array( $this, 'copy_post_copy_children' ), 10, 2 );
      add_action( 'owf_duplicate_page', array( $this, 'copy_post_copy_children' ), 10, 2 );

      add_action( 'owf_update_published_post', array( $this, 'update_post_update_children' ), 10, 2 );
      add_action( 'owf_update_published_page', array( $this, 'update_post_update_children' ), 10, 2 );

      // should be the lowest priority, so that other hooks can complete and then the post is deleted
      add_action( 'owf_update_published_post', array( $this, 'delete_revision_after_merge_complete' ), 15, 2 );
      add_action( 'owf_update_published_page', array( $this, 'delete_revision_after_merge_complete' ), 15, 2 );

      add_filter( 'post_row_actions', array( $this, 'new_draft_link_row' ), 10, 2 );
      add_filter( 'page_row_actions', array( $this, 'new_draft_link_row' ), 10, 2 );

      add_filter( 'wp_save_post_revision_post_has_changed', array($this, 'has_post_changed' ), 10, 3 );

   }

   /*
    * AJAX function - Get the original version/published version of the post
    *
    * @since 3.0
    */

   public function get_original() {
      // nonce check
      check_ajax_referer( 'owf_workflow_abort_nonce', 'security' );

      /* sanitize incoming data */
      $revision_post_id = intval( sanitize_text_field( $_POST["post_id"] ) );

      $original_post_id = get_post_meta( $revision_post_id, '_oasis_original', true );
      if ( $original_post_id ) {
         wp_send_json_success( $original_post_id );
      } else {
         wp_send_json_error();
      }
   }

   /*
    * AJAX function - Get the current revision of the post
    *
    * @since 3.0
    */
   public function get_current_revision() {
      // nonce check
      check_ajax_referer( 'owf_make_revision_ajax_nonce', 'security' );

      /* sanitize incoming data */
      $original_post_id = intval( sanitize_text_field( $_POST["post_id"] ) );
      $revision_post_id = get_post_meta( $original_post_id, '_oasis_current_revision', true );
      $revised_post_status = get_post_status( $revision_post_id );

      if ( $revision_post_id && $revised_post_status !== 'trash' ) {
         $response = array( 'revision_post_id'     => $revision_post_id,
                            'revision_post_title'  => get_the_title( $revision_post_id ),
                            'revision_post_link'   => get_edit_post_link( $revision_post_id ) );
         wp_send_json_success( $response );
      } else {
         delete_post_meta( $original_post_id, '_oasis_current_revision' );
         wp_send_json_error();
      }
   }

   /*
    * AJAX function - Check if make revision is allowed
    * also takes into consideration the owf_is_make_revision_available_for_others filter
    *
    * @return boolean true if revision is allowed, false if not
    * @since 3.1
    */

   public function is_make_revision_allowed() {
      // nonce check
      check_ajax_referer( 'owf_make_revision_ajax_nonce', 'security' );

      /* sanitize incoming data */
      $post_id = intval( sanitize_text_field( $_POST["post_id"] ) );

      $post = get_post( $post_id );
      $is_revise_action_available = $this::is_make_revision_available_by_role( $post );

      if ( $is_revise_action_available ) {
         wp_send_json_success();
      } else {
         wp_send_json_error();
      }
   }

   /*
    * AJAX function - Checks if there is an existing revision for this original post
    * @since 3.1
    */
   public function is_post_a_revision() {
      /* sanitize incoming data */
      $untrashed_post_id = intval( $_POST['untrash_post_id'] );
      $original_post_id = trim( get_post_meta( $untrashed_post_id, '_oasis_original', true ) );

      $result = array (
         "is_restored" => true
      );

      if ( ! empty( $original_post_id ) ) { // looks like the post being restored is a revision
         // lets find out if there is an existing revision for this original post
         $current_revision_post_id = trim( get_post_meta( $original_post_id, '_oasis_current_revision', true ) );

         if ( empty( $current_revision_post_id ) ) { //looks like there isn't any new revision yet, so lets connect this restored post to the original again
         	update_post_meta( $original_post_id, '_oasis_current_revision', $untrashed_post_id );
         	wp_untrash_post( $untrashed_post_id );
            wp_send_json_success( $result );
         } else {
         	ob_start();
         	include_once OASISWF_PATH . 'includes/pages/subpages/make-revision-untrashed.php';
            $result = ob_get_contents();
         	ob_get_clean();
            wp_send_json_success( htmlentities( $result ) );
         }
      } else { // looks like there isn't any revision, this is the original post, so simply restore it
         wp_untrash_post( $untrashed_post_id );
         wp_send_json_success( $result );
      }
   }


   /**
    * AJAX function - Display make revision overlay on publish post before editing the post
    * @since 3.3
    */
   public function alert_make_revision_on_publish_post() {
      // nonce check
      check_ajax_referer( 'owf_make_revision_ajax_nonce', 'security' );

      /* sanitize incoming data */
      $post_id = intval( sanitize_text_field( $_POST["post_id"] ) );

      // additional filter for deciding if the user can skip the workflow or not.
      // for example, based on tag or categories etc.

      $owf_skip_workflow_filter = false;
      $show_overlay = true;

      if ( has_filter( 'owf_skip_workflow' ) ) {
         $owf_skip_workflow_filter = apply_filters( 'owf_skip_workflow', $post_id );
      }

      // check the capability and filter value
      if ( current_user_can( 'ow_skip_workflow' ) || $owf_skip_workflow_filter == true ) {
         $show_overlay = false;
      }

      // capability check
      // if user can skip the workflow then do not show make revision overlay
      if ( ( $show_overlay == true ) &&
      		( current_user_can( 'ow_make_revision' ) || current_user_can( 'ow_make_revision_others' ) ) ) {
         ob_start();
         include_once( OASISWF_PATH . "includes/pages/subpages/make-revision-overlay.php" );
         $result = ob_get_contents();
         ob_end_clean();

         wp_send_json_success( htmlentities( $result ) );
      } else {
         wp_send_json_error();
      }
   }

   /*
    * AJAX function - version of save_as_new_post
    *
    * @since 3.0
    */
   public function save_as_new_post_draft_ajax() {
      // nonce check
      check_ajax_referer( 'owf_make_revision_ajax_nonce', 'security' );

      $new_post_id = $this->save_as_new_post( 'draft' );
      $redirect_url = admin_url( "post.php?action=edit&post=$new_post_id" );
      $redirect_url = apply_filters( 'owf_redirect_after_revision', $redirect_url, $new_post_id );

      wp_send_json_success( esc_sql( $redirect_url ) );
   }

   /*
    * AJAX function - To untrash the post
    *
    * @since 3.0
    */
   public function keep_untrashed_revision() {
      /* sanitize incoming data */
      $untrash_post_id = intval( $_POST['untrash_post_id'] );

      // now from revise postID we will get original post id
      $original_post_id = trim( get_post_meta( $untrash_post_id, '_oasis_original', true ) );

      // now from original postID we will get current revision post id
      $current_revision_post_id = get_post_meta( $original_post_id, '_oasis_current_revision', true );
      // trash the current revision post
      wp_trash_post( $current_revision_post_id );
      // restore the trashed post
      wp_untrash_post( $untrash_post_id );

      update_post_meta( $original_post_id, '_oasis_current_revision', $untrash_post_id );
      wp_send_json_success();
   }
   
   /**
    * AJAX function - To copy revision to published post if revision post is aborted
    * @since 5.1
    */
   public function update_revision_to_published_post() {  
      
      // nonce check
      check_ajax_referer( 'owf_update_published_nonce', 'security' );
      
      $revised_post_id = intval( $_POST['revision_post_id'] );     
      
      OW_Utility::instance()->logger( "copy aborted revision to published post" );
      
      $this->update_published_post( $revised_post_id );
     
      $link = admin_url() . "admin.php?page=oasiswf-inbox";      
      
      wp_send_json_success( array( 'redirect' => $link ) );
   }


   /*
    * Hook - Adds redirect URL for the newly created revision.
    *
    * @since 3.0
    */

   public function save_as_new_post_draft() {
      $new_post_id = $this->save_as_new_post( 'draft' );
      wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
      die();
   }

   /*
    * Hook - Update published post
    *
    * @since 3.0
    */

   public function update_published_post( $revised_post_id ) {
      global $wpdb;

      $revised_post_id = intval( $revised_post_id );

      $revised_post = get_post( $revised_post_id );

      //do we want to copy revisions of revised post onto the original post
      $preserve_revision = get_option( 'oasiswf_preserve_revision_of_revised_article' );

      $original_post_id = get_post_meta( $revised_post_id, '_oasis_original', true );
      if ( empty( $original_post_id ) ) {
         return; // we are probably dealing with an incorrect article
      }

      $original_post = get_post( $original_post_id );
      $original_post_name = $original_post->post_name;

      if ( $revised_post->post_type != 'attachment' ) {
         $prefix = get_option( 'oasiswf_doc_revision_title_prefix' );
         $suffix = get_option( 'oasiswf_doc_revision_title_suffix' );
      }

      $post_title = $revised_post->post_title;

      $special_chars = '[]()';

      // check if prefix contains parentheses
      if ( OW_Utility::instance()->has_special_char( $prefix, $special_chars ) ) { // then we have to do some special treatment to remove the prefix
         $prefix = addcslashes( $prefix, $special_chars );
         $post_title = preg_replace( '@' . $prefix . '{1}@', '', $post_title );
      } else { // usual way to remove the prefix
         $post_title = preg_replace( '/' . $prefix . '/', '', $post_title );
      }

      // check if suffix contains parentheses
      if ( OW_Utility::instance()->has_special_char( $suffix, $special_chars ) ) { // then we have to do some special treatment to remove the suffix
         $suffix = addcslashes( $suffix, $special_chars );
         $post_title = preg_replace( '@' . $suffix . '{1}@', '', $post_title );
      } else { // usual way to remove the suffix
         $post_title = preg_replace( '/' . $suffix . '/', '', $post_title );
      }

      // remove any whitespaces before and after the title
      $post_title = trim( $post_title );

      $ow_process_flow = new OW_Process_Flow();

      if ( $preserve_revision === 'yes' ) {
         $this->copy_revised_article_with_revisions_to_original( $revised_post, $original_post_id, $post_title );
      } else {
         $this->copy_revised_article_to_original( $revised_post, $original_post_id, $post_title );
      }
      
      // additional filter for allowing revised permalink changes.
      $owf_update_revision_permalink = false;
      if ( has_filter( 'owf_update_revision_permalink' ) ) {
         $owf_update_revision_permalink = apply_filters( 'owf_update_revision_permalink', $original_post_id, $revised_post_id );
      }
      
      $post_name = $original_post_name;
      if ( $owf_update_revision_permalink == true ) {
         $post_name = $revised_post->post_name;
      }

      // update the post title with the updated post title.
      // update post name, as was changing slug if prefix and suffix is empty
      // wp_update_post, strips the html from the post title, so using wpdb->update to directly update the DB
      $wpdb->update(
         $wpdb->posts,
         array(
            'post_title'  => $post_title,
            'post_name'   => $post_name
         ),
         array( 'ID' => $original_post_id )
      );

      // finally change the revised post status to usedrevision again.
      $ow_process_flow->ow_update_post_status( $revised_post->ID, "usedrev" );

      // once we are done updating with revised item, delete the post metadata
      delete_post_meta( $revised_post->ID, '_oasis_original' );
      delete_post_meta( $original_post_id, '_oasis_current_revision' );

      if ( $revised_post->post_type == 'page' || (function_exists( 'is_post_type_hierarchical' ) && is_post_type_hierarchical( $revised_post->post_type )) ) {
         do_action( 'owf_update_published_page', $original_post_id, $revised_post );
      }
      else {
         do_action( 'owf_update_published_post', $original_post_id, $revised_post );
      }
   }

   /**
    * When updating the original post with revision, sometimes the post content hasn't been changed. But even
    * in that case, we should create a new revision of the post.
    *
    * Helpful in copy_revised_article_with_revisions_to_original
    *
    * @param $post_has_changed
    * @param $last_revision
    * @param $post
    *
    * @return bool
    *
    */
   public function has_post_changed( $post_has_changed, $last_revision, $post) {
      $revision_post_id = get_post_meta( $post->ID, '_oasis_current_revision', true );

      if ( ! empty( $revision_post_id ) ) {
         return true;
      } else {
         return $post_has_changed;
      }

   }

   /**
    * First copies the revisions of revised article to original article and then
    * Copies the final revised article to original article
    *
    * @param int $revised_post
    * @param int $original_post_id
    * @param string $post_title
    * @since 4.8
    */
   private function copy_revised_article_with_revisions_to_original( $revised_post, $original_post_id, $post_title ) {
      global $wpdb;

      $revision_args = array(
         'orderby' => 'ID',
         'order'   => 'ASC',
      );

      $revised_post_revisions = wp_get_post_revisions( $revised_post->ID, $revision_args );
      
      // If empty revised post revisions than directly copy revisied article to original
      if ( empty( $revised_post_revisions ) ) {
         
         $this->copy_revised_article_to_original( $revised_post, $original_post_id, $post_title );
         
      } else {

         $ow_process_flow = new OW_Process_Flow();

         $original_post = get_post( $original_post_id );

         // loop through all the revisions of the "copy-of"
         // copy contents of each revision to the original post and update it
         // This way, we do not loose any revision history made on the "copy-of".
         foreach( $revised_post_revisions as $revised_post_revision ){
            $published_post = array(
               'ID'                 => $original_post_id,
               'menu_order'         => $original_post->menu_order,
               'comment_status'     => $revised_post_revision->comment_status,
               'ping_status'        => $revised_post_revision->ping_status,
               'post_content'       => $revised_post_revision->post_content,
               'post_excerpt'       => $revised_post_revision->post_excerpt,
               'post_mime_type'     => $revised_post_revision->post_mime_type,
               'post_parent'        => $new_post_parent = empty( $parent_id ) ? $revised_post->post_parent : $parent_id,
               'post_password'      => $revised_post_revision->post_password,
               'post_title'         => $post_title,
               'post_type'          => $revised_post->post_type
            );

            remove_action( 'save_post', array( $ow_process_flow, 'check_unauthorized_post_update' ), 10 );

            wp_update_post( $published_post );

            add_action( 'save_post', array( $ow_process_flow, 'check_unauthorized_post_update' ), 10, 1 );

            // get the latest revision created by wp_update_post and
            $all_revisions = wp_get_post_revisions( $original_post_id );
            $latest_revision = current( $all_revisions ); //http://php.net/manual/en/function.current.php

            if ( class_exists( 'acf_pro' ) ) { // applicable to pro version of ACF
               $this->add_post_meta_info( $latest_revision->ID, $revised_post_revision );
            }

            // update the post title with the updated post title.
            // wp_update_post, strips the html from the post title, so using wpdb->update to directly update the DB
            // update the post author on the latest revision by revision post author
            $wpdb->update(
               $wpdb->posts,
               array(
                  'post_title'      => $post_title,
                  'post_author'     => $revised_post_revision->post_author,
                  'post_date'       => $revised_post_revision->post_date,
                  'post_date_gmt'   => $revised_post_revision->post_date_gmt
               ),
               array( 'ID' => $latest_revision->ID )
            );

         }
      }
   }

   /**
    * Copies the final revised article to original article
    *
    * @param int $revised_post
    * @param string $original_post_id
    * @param array $post_title
    * @since 4.8
    */
   private function copy_revised_article_to_original( $revised_post, $original_post_id, $post_title ) {
      global $wpdb;

      $ow_process_flow = new OW_Process_Flow();

      $original_post = get_post( $original_post_id );

      $published_post = array(
          'ID'             => $original_post_id,
          'menu_order'     => $original_post->menu_order,
          'comment_status' => $revised_post->comment_status,
          'ping_status'    => $revised_post->ping_status,
          'post_content'   => $revised_post->post_content,
          'post_excerpt'   => $revised_post->post_excerpt,
          'post_mime_type' => $revised_post->post_mime_type,
          'post_parent'    => $new_post_parent = empty( $parent_id ) ? $revised_post->post_parent : $parent_id,
          'post_password'  => $revised_post->post_password,
          'post_title'     => $post_title,
          'post_type'      => $revised_post->post_type
      );
      remove_action( 'save_post', array( $ow_process_flow, 'check_unauthorized_post_update' ), 10 );

      wp_update_post( $published_post );

      add_action( 'save_post', array( $ow_process_flow, 'check_unauthorized_post_update' ), 10, 1 );

      // get the latest revision created by wp_update_post and
      $all_revisions = wp_get_post_revisions( $original_post_id );
      $latest_revision = current( $all_revisions ); //http://php.net/manual/en/function.current.php

      // update the post title with the updated post title.
      // wp_update_post, strips the html from the post title, so using wpdb->update to directly update the DB
      // update the post author on the latest revision by revision post author
      $wpdb->update(
         $wpdb->posts,
         array(
            'post_title' => $post_title,
            'post_author' => $revised_post->post_author
         ),
         array( 'ID' => $latest_revision->ID )
      );
   }

   /**
    * Hook - Schedule or Publish the revision
    * @since 3.0
    *
    * @param $post_id
    */
   public function schedule_or_publish_revision( $post_id ) {
      /* sanitize incoming data */
   	$post_id = intval( sanitize_text_field( $post_id ) );

      OW_Utility::instance()->logger( "Inside schedule or publish revision" );

   	$new_id = $post_id;
      $ow_process_flow = new OW_Process_Flow();

      $revision_post = get_post ( $new_id );

      OW_Utility::instance()->logger( "Revision Post Status:" . $revision_post->post_status );

      if ( $revision_post->post_status == "currentrev") { // looks like the revision post status is publish, so update the revision immediately
         OW_Utility::instance()->logger( "Inside publish revision immediately" );
         $this->update_published_post( $post_id );
      } else { // user provided a publish date (future date hopefully)

         OW_Utility::instance()->logger( "Inside scheduling a revision update event" );
         $args = array( $new_id );
         $ow_process_flow->ow_update_post_status( $new_id, "owf_scheduledrev" );
         $is_scheduled = wp_schedule_single_event( strtotime( $revision_post->post_date_gmt ),
         		'oasiswf_schedule_revision_update', $args );

         $timestamp = wp_next_scheduled( 'oasiswf_schedule_revision_update', $args );
         OW_Utility::instance()->logger( "Next Scheduled Event: " . date( "Y-m-d H:i:s", $timestamp ) );
      }
   }

   /**
    * Hook - Copy the meta information of a post to another post
    * @param $revised_post_id ID of the revision
    * @param $original_post original published post
    *
    * @since 3.0
    */
   public function copy_post_meta_info( $revised_post_id, $original_post ) {
      $post_meta_keys = get_post_custom_keys( $original_post->ID );
      if ( empty( $post_meta_keys ) ) {
         return;
      }

      // Filter to remove post meta keys
      $ignore_postmeta_keys = array();
      $ignore_keys = apply_filters( 'owf_unset_postmeta', $ignore_postmeta_keys );


      foreach ( $post_meta_keys as $meta_key ) {
         $meta_key_trim = trim( $meta_key );
         if ( '_edit_lock' == $meta_key_trim || //ignore keys like _edit_last, _edit_lock
              '_edit_last' == $meta_key_trim ||
              substr( $meta_key, 0, 6 ) === "_oasis" || // ignore any keys starting with '_oasis'
              substr( $meta_key, 0, 3 ) === "ow_" ||
              in_array( $meta_key_trim, $ignore_keys ) ) { // ignore any keys starting with 'ow_'
            continue;
         }
         $meta_values = get_post_custom_values( $meta_key, $original_post->ID );
         foreach ( $meta_values as $meta_value ) {
            $meta_value = maybe_unserialize( $meta_value );
            add_post_meta( $revised_post_id, $meta_key, $meta_value, false );
         }
      }
   }

   /**
    * Hook - Copy the taxonomies of a post to another post
    * @param $revised_post_id ID of the revision
    * @param $original_post original published post
    *
    * @since 3.0
    */
   public function copy_post_copy_post_taxonomies( $revised_post_id, $original_post ) {
      global $wpdb;
      if ( isset( $wpdb->terms ) ) {
         // Clear default category (added by wp_insert_post)
         wp_set_object_terms( $revised_post_id, NULL, 'category' );

         $post_taxonomies = get_object_taxonomies( $original_post->post_type );
         foreach ( $post_taxonomies as $taxonomy ) {
            $post_terms = wp_get_object_terms( $original_post->ID, $taxonomy, array( 'orderby' => 'term_order' ) );
            $terms = array();
            for ( $i = 0; $i < count( $post_terms ); $i ++ ) {
               $terms[] = $post_terms[$i]->slug;
            }
            wp_set_object_terms( $revised_post_id, $terms, $taxonomy );
         }
      }
   }

   /**
    * Hook - Copy the attachments
    * It simply copies the table entries, actual file won't be duplicated
    *
    * @param $revised_post_id ID of the revision
    * @param $original_post original published post
    *
    * @since 3.0
    */
   public function copy_post_copy_children( $revised_post_id, $original_post ) {
      // get children
      $children = get_posts( array( 'post_type' => 'any', 'numberposts' => -1, 'post_status' => 'any', 'post_parent' => $original_post->ID ) );

      $copy_children_on_revision = get_option( 'oasiswf_copy_children_on_revision' );
      // clone old children
      foreach ( $children as $child ) {
         if ( $copy_children_on_revision == 'yes' ) {
            $this->create_copy( $child, '', $revised_post_id );
         }
      }
   }

   /**
    * Create post meta for revisions.
    * When creating revisions of post, call this function to add meta to revisions.
    *
    * @param $post_id
    * @param $revised_post
    *
    * @since 4.8
    */
   private function add_post_meta_info( $post_id, $revised_post ) {
      global $wpdb;

      // sanitize the data
      $post_id = intval( $post_id );

      // get post meta keys from the revision post
      $post_meta_keys = get_post_custom_keys( $revised_post->ID );
      if ( ! is_array( $post_meta_keys ) ) {
         $post_meta_keys = array();
      }

      if ( empty( $post_meta_keys ) ) {
         return;
      }

      // Filter to remove post meta keys
      $ignore_postmeta_keys = array();
      $ignore_keys = apply_filters( 'owf_unset_postmeta', $ignore_postmeta_keys );

      foreach ( $post_meta_keys as $meta_key ) {
         $meta_key = trim( $meta_key );

         if ( '_edit_lock' == $meta_key || //ignore keys like _edit_last, _edit_lock
              '_edit_last' == $meta_key ||
              '_vc_post_settings' == $meta_key ||
              substr( $meta_key, 0, 6 ) === "_oasis" || // ignore any keys starting with '_oasis', like _oasis_task_priority
              substr( $meta_key, 0, 3 ) === "ow_" ||
              in_array( $meta_key, $ignore_keys ) ) { // ignore any keys starting with 'ow_'
            continue;
         }

         $revised_meta_values = get_post_custom_values( $meta_key, $revised_post->ID );
         $meta_values_count = count( $revised_meta_values );

         // loop through the meta values to find what's added, modified and deleted.
         for ( $i = 0; $i < $meta_values_count; $i ++ ) {
            $new_meta_value = maybe_unserialize( $revised_meta_values[$i] );

            $post_meta_value = get_post_meta( $post_id, $meta_key, true);
            if ( $post_meta_value != null ) {
               continue;
            }

            // add only if we do not have an existing meta for the given revision_post_id
            add_metadata( 'post', $post_id, $meta_key, $new_meta_value );
         }
      }
   }

   /**
    * Hook - Copy the meta information of the revised post back to the published post
    * @param $original_post_id
    * @param $revised_post
    *
    * @since 3.0
    */
   public function update_post_meta_info( $original_post_id, $revised_post ) {

      // sanitize the data
      $original_post_id = intval( $original_post_id );

   	$post_meta_keys_original = get_post_custom_keys( $original_post_id );

      if ( ! is_array( $post_meta_keys_original ) ) {
         $post_meta_keys_original = array();
      }

      $post_meta_keys_revision = get_post_custom_keys( $revised_post->ID );
      if ( ! is_array( $post_meta_keys_revision ) ) {
         $post_meta_keys_revision = array();
      }

   	$post_meta_keys = array_unique( array_merge( $post_meta_keys_original, $post_meta_keys_revision ) );

      if ( empty( $post_meta_keys ) ) {
         return;
      }

      // Filter to remove post meta keys
      $ignore_postmeta_keys = array();
      $ignore_keys = apply_filters( 'owf_unset_postmeta', $ignore_postmeta_keys );

      foreach ( $post_meta_keys as $meta_key ) {
         $meta_key = trim( $meta_key );
//         OW_Utility::instance()->logger( "the meta key is: " . $meta_key);
         if ( '_edit_lock' == $meta_key || //ignore keys like _edit_last, _edit_lock
         		'_edit_last' == $meta_key ||
               '_vc_post_settings' == $meta_key ||
               substr( $meta_key, 0, 6 ) === "_oasis" || // ignore any keys starting with '_oasis', like _oasis_task_priority
               substr( $meta_key, 0, 3 ) === "ow_" ||
              in_array( $meta_key, $ignore_keys ) ) {// ignore any keys starting with 'ow_'
            continue;
         }
         $revised_meta_values = get_post_custom_values( $meta_key, $revised_post->ID );
         $original_meta_values = get_post_custom_values( $meta_key, $original_post_id );

         // find the bigger array of the two
         $meta_values_count = count( $revised_meta_values ) > count( $original_meta_values ) ? count( $revised_meta_values ) : count( $original_meta_values );

         // loop through the meta values to find what's added, modified and deleted.
         for ( $i = 0; $i < $meta_values_count; $i ++ ) {
            $new_meta_value = "";
            // delete if the revised post doesn't have that key
            if ( count( $revised_meta_values ) >= $i + 1 ) {
               $new_meta_value = maybe_unserialize( $revised_meta_values[$i] );
            } else {
               $old_meta_value = maybe_unserialize( $original_meta_values[$i] );
//               OW_Utility::instance()->logger( "deleting from original");
               delete_post_meta( $original_post_id, $meta_key, $old_meta_value );
               continue;
            }

            // old meta values got updated, so simply update it
            if ( count( $original_meta_values ) >= $i + 1 ) {
               $old_meta_value = maybe_unserialize( $original_meta_values[$i] );
//               OW_Utility::instance()->logger( "updating on original");
               update_post_meta( $original_post_id, $meta_key, $new_meta_value, $old_meta_value );
            }

            // new meta values got added, so add it
            if ( count( $original_meta_values ) < $i + 1 ) {
//             	OW_Utility::instance()->logger( "adding to original");
               add_post_meta( $original_post_id, $meta_key, $new_meta_value );
            }
         }
      }
   }

   /**
    * Hook - Copy the attachments
    * It simply copies the table entries, actual file won't be duplicated
    * @param $post_id
    *
    * @since 3.0
    */
   public function update_post_update_children( $post_id ) {
      // get children
      $children = get_posts( array( 'post_type' => 'any', 'numberposts' => -1, 'post_status' => 'any', 'post_parent' => $post_id ) );

      $copy_children_on_revision = get_option( 'oasiswf_copy_children_on_revision' );
      // update children
      foreach ( $children as $child ) {
         if ( $copy_children_on_revision == 'yes' ) {
            $this->update_published_post( $child->ID );
         }
      }
   }

   /*
    * Hook - delete revision after it merge into original
    * also merge the history back to original
    * @param $original_post_id
    * @param $revised_post
    *
    * @since 3.0
    */
   public function delete_revision_after_merge_complete( $original_post_id, $revised_post ) {
      global $wpdb;
      OW_Utility::instance()->logger( "inside delete revision after merge complete" );
      $action_history_table = OW_Utility::instance()->get_action_history_table_name();
      $delete_revision_on_copy = get_option( 'oasiswf_delete_revision_on_copy' );

      if ( ($delete_revision_on_copy == "yes" ) ) {
         if ( ! empty( $original_post_id ) ) {
            $wpdb->update(
                    $action_history_table, array(
                'post_id' => $original_post_id,
                    ), array( 'post_id' => $revised_post->ID ), array(
                '%d'
                    ), array( '%d' )
            );
            OW_Utility::instance()->logger( "deleting the revision after merge complete" );
            $wpdb->delete( $wpdb->prefix . 'posts', array( 'ID' => $revised_post->ID ) );
            // delete postmeta values
            do_action( "owf_delete_revision", $revised_post->ID );
            $wpdb->delete( $wpdb->prefix . 'postmeta', array( 'post_id' => $revised_post->ID ) );
         }
      }
   }

   /*
    * Filter Hook - Add "Make Revision" link to the post list page.
    * Verifies various conditions before showing/hiding the "Make Revision" link.
    * 1. is the post published/scheduled/private
    * 2. is the post_type allowed to participate in workflows
    * 3. is the revision process active
    * 4. is the workflow process active
    * 5. is make revision available to current user's role
    * 6. no other revisions exist for this post.
    *
    * @since 3.0
    */

   public function new_draft_link_row( $actions, $post ) {

      $option = get_option( 'oasiswf_custom_workflow_terminology' );

      $make_revision_label = ! empty( $option['makeRevisionText'] ) ? $option['makeRevisionText'] : __( 'Make Revision', "oasisworkflow" );
      $post_status = get_post_status( $post->ID );
      // show 'Make Revision' to selected post/page types only
      $allowed_post_types = get_option( 'oasiswf_show_wfsettings_on_post_types' );
      $original_post_id = get_post_meta( $post->ID, '_oasis_original', true );

      $is_revise_action_available = $this::is_make_revision_available_by_role( $post );

      if ( ( $post_status == 'publish' || $post_status == 'future' || $post_status == 'private') &&
      		in_array( $post->post_type, $allowed_post_types ) &&
      		get_option( "oasiswf_activate_revision_process" ) == "active" &&
      		get_option( "oasiswf_activate_workflow" ) == "active"
      		&& ( $is_revise_action_available )
      		&& empty( $original_post_id ) ) {
         $actions['ow_edit_as_new_draft'] = "<a href='javascript:void(0);' class='ow-make-revision'
         	postid='$post->ID'
         	title='" . esc_attr( __( 'Make Revision', "oasisworkflow" ) ) . "'>{$make_revision_label}</a><span class='loading' style='display: none;'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>";
      }

      $hide_compare_button = get_option( "oasiswf_hide_compare_button" );

      $revision_post_id = get_post_meta( $original_post_id, '_oasis_current_revision', true );
      if ( empty( $hide_compare_button ) &&
      		($post_status != 'publish' || $post_status != 'future' ) &&
      		$post_status != 'trash' &&
      		($revision_post_id) ) {
         $revision_compare_nonce = wp_create_nonce( 'owf_compare_revision_nonce' );
         $href = "post.php?page=oasiswf-revision&revision=" . $revision_post_id . "&_nonce=" . $revision_compare_nonce;
         $actions['compare_revision'] = '<a href=' . $href . ' class="compare-post-revision" title="'
                 . esc_attr( __( 'Compare', "oasisworkflow" ) )
                 . '">' . __( 'Compare', "oasisworkflow" ) . '</a>';
      }
      return $actions;
   }

   public function get_clone_post_link( $post_id = 0 ) {

      // sanitize the data
      $post_id = intval( sanitize_text_field( $post_id ) );

      if ( ! $post = get_post( $post_id ) ) {
         return;
      }

      $action_name = "save_as_new_post_draft";
      $action = '?action=' . $action_name . '&post=' . $post->ID;

      return admin_url( "admin.php" . $action );
   }

   public function save_as_new_post( $status = '' ) {

      // Get the original post
      $id = (isset( $_GET['post'] ) && sanitize_text_field( $_GET["post"] )) ? intval( sanitize_text_field( $_GET['post'] ) ) : intval( sanitize_text_field( $_POST['post'] ) );
      $post = get_post( $id );

      // check capability
      $is_make_revision_allowed = $this::is_make_revision_available_by_role( $post );

      if ( ! $is_make_revision_allowed ) {
         wp_die( __( 'You are not allowed to make the revision.' ) );
      }
      if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) || ( isset( $_REQUEST['action'] ) && 'save_as_new_post_draft' == $_REQUEST['action'] ) ) ) {
         wp_die( __( 'No post to copy has been supplied!', "oasisworkflow" ) );
      }

      // nullify $_POST (coming from ajax call) as some plugins, like PODS have a hook on save_post
      // and will fail on wp_insert_post()
      $_POST = array();
     
      // Copy the post and insert it
      if ( isset( $post ) && $post != null ) {
         $new_id = $this->create_copy( $post, $status );
         
         return $new_id;
         exit;
      } else {
         wp_die( esc_attr( __( 'Copy failed, could not find original:', "oasisworkflow" ) ) . ' ' . $id );
      }
   }

   /**
    * Create a copy from a post
    *
    * @since 3.0
    */
   public function create_copy( $post, $status = '', $parent_id = '' ) {

      global $wpdb;

      // We don't want to clone revisions
      if ( $post->post_type == 'revision' ) {
         return;
      }

      $prefix = '';
      $suffix = '';
      $new_post_author = '';
      if ( $post->post_type != 'attachment' ) {
         $prefix = get_option( 'oasiswf_doc_revision_title_prefix' );
         $suffix = get_option( 'oasiswf_doc_revision_title_suffix' );
         if ( ! empty( $prefix ) )
            $prefix.= " ";
         if ( ! empty( $suffix ) )
            $suffix = " " . $suffix;

         // reset the status of the revision to draft
         $status = 'draft';
         $new_post_author = get_current_user_id();
      }
      elseif ( $post->post_type == 'attachment' ) {
         $new_post_author = $post->post_author;
      }

      $new_post = array(
          'menu_order'     => $post->menu_order,
          'comment_status' => $post->comment_status,
          'ping_status'    => $post->ping_status,
          'post_author'    => $new_post_author,
          'post_content'   => htmlspecialchars_decode( htmlspecialchars( $post->post_content ) ),
          'post_excerpt'   => $post->post_excerpt,
          'post_mime_type' => $post->post_mime_type,
          'post_parent'    => $new_post_parent = empty( $parent_id ) ? $post->post_parent : $parent_id,
          'post_password'  => $post->post_password,
          'post_status'    => $new_post_status = (empty( $status )) ? $post->post_status : $status,
          'post_title'     => $prefix . $post->post_title . $suffix,
          'post_type'      => $post->post_type,
      );

      $new_post_id = wp_insert_post( $new_post );

      // update the post title with the updated post title.
      // wp_update_post, strips the html from the post title, so using wpdb->update to directly update the DB
      $wpdb->update(
         $wpdb->posts,
         array(
            'post_title' => $prefix . $post->post_title . $suffix
         ),
         array( 'ID' => $new_post_id )
      );

      // If you have written a plugin which uses non-WP database tables to save
      // information about a post you can hook this action to dupe that data.
      if ( $post->post_type == 'page' || (function_exists( 'is_post_type_hierarchical' ) && is_post_type_hierarchical( $post->post_type ) ) ) {
         do_action( 'owf_duplicate_page', $new_post_id, $post );
      } else {
         do_action( 'owf_duplicate_post', $new_post_id, $post );
      }
      delete_post_meta( $new_post_id, '_oasis_original' );
      add_post_meta( $new_post_id, '_oasis_original', $post->ID );

      // add the revised post id as a post meta data to the original post
      // this will help us to identify if there are any active revisions currently being worked on
      add_post_meta( $post->ID, '_oasis_current_revision', $new_post_id );
      
      return $new_post_id;
   }

   /*
    * Check if Make Revision is available for the logged in user role.
    * Also checks the custom role - PostAuthor, if PostAuthor is selected, check if the current_user_id is the post author
    *
    * @return boolean true if make revision is allowed, false if not
    * @since 3.1
    */

   public function is_make_revision_available_by_role( $post ) {
      $post_author_id = 0;
      if ( $post ) {
         $post_author_id = $post->post_author;
      }

      $is_revise_action_available = false;
      if ( current_user_can( 'ow_make_revision_others' ) ) {
         $is_revise_action_available = true;
      }
      if ( current_user_can( 'ow_make_revision' ) && get_current_user_id() == $post_author_id ) {
         $is_revise_action_available = true;
      }

      return $is_revise_action_available;
   }


   /**
    * Compare tags between the original and the revision
    *
    * @param int $original_post_id
    * @param int $revision_post_id
    * @return array comparison data with heading
    *
    * @since 3.4
    * @since 5.1 return array
    */
   public function compare_tags( $original_post_id, $revision_post_id ) {

      $data = array();

      $tags = wp_get_post_tags( $revision_post_id );
      $revision_tags = $original_tags = array();
      foreach ( $tags as $tag ) {
         $revision_tags[] = $tag->name;
      }

      $tags = wp_get_post_tags( $original_post_id );
      foreach ( $tags as $tag ) {
         $original_tags[] = $tag->name;
      }
      
      $data['original_tag'] = implode( ", ", $original_tags );
      $data['revision_tag'] = implode( ", ", $revision_tags );     

      return $data;
   }

   /**
    * Compare categories between the original and the revision
    *
    * @param int $original_post_id
    * @param int $revision_post_id
    * @return array comparision data with heading
    *
    * @since 3.4
    * @since 3.8 included compare for custom categories as well
    * @since 5.1 return array
    */
   public function compare_categories( $original_post_id, $revision_post_id ) {
      $data = array();

      $revise_categories = $original_categories = "";
		$revise_categories = OW_Utility::instance()->get_post_categories( $revision_post_id );
		$original_categories = OW_Utility::instance()->get_post_categories( $original_post_id );

     $data['original_category'] = $original_categories;
     $data['revision_category'] = $revise_categories;

      return $data;
   }

   /**
    * Compare featured image between the original and the revision
    *
    * @param int $original_post_id
    * @param int $revision_post_id
    * @return array comparision data with heading
    *
    * @since 3.4
    * @since 5.1 return array
    */
   public function compare_featured_image( $original_post_id, $revision_post_id ) {
      $data = array();

      $revision_feature_image = wp_get_attachment_url( get_post_thumbnail_id( $revision_post_id ) );
      $original_feature_image = wp_get_attachment_url( get_post_thumbnail_id( $original_post_id ) );
      
     $data['original_image'] = $original_feature_image;
     $data['revision_image'] = $revision_feature_image; 

      return $data;
   }
   
   /**
    * Create comparision table if wp_text_diff() is empty
    * @param string $original_field_data
    * @param string $revision_field_data
    * @return string $acf_field_diff
    * @since 5.1
    */
   public function get_comparison_table( $original_field_data, $revision_field_data ) {
      
      $acf_field_diff = '<table class="diff"><colgroup><col class="content diffsplit left"><col class="content diffsplit middle"><col class="content diffsplit right"></colgroup><tbody><tr>';
      $acf_field_diff .= '<td>' . $original_field_data . '</td><td></td><td>' . $revision_field_data . '</td>';
      $acf_field_diff .= '</tr></tbody>';
      $acf_field_diff .= '</table>';

      return $acf_field_diff;
   }

}

// construct an instance so that the actions get loaded
$ow_revision_service = new OW_Revision_Service();
?>