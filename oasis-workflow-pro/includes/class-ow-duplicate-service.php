<?php

/*
 * Service class for duplicate post types
 *
 * @copyright   Copyright (c) 2018, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       5.3
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/**
 * OW_Duplicate_Service Class
 * @since 5.3
 */
class OW_Duplicate_Service {
   
   /**
    * Set things up.
    * @since 5.3
    */
   public function __construct() {
      add_filter( 'post_row_actions', array( $this, 'duplicate_link_row' ), 10, 2 );
      add_filter( 'page_row_actions', array( $this, 'duplicate_link_row' ), 10, 2 );
      
      add_action( 'wp_ajax_save_duplicate_post_as_draft', array( $this, 'save_duplicate_post_as_draft' ) );
      
      add_action( 'owf_post_duplication', array( $this, 'duplicate_post_meta_info' ), 10, 2 );
      add_action( 'owf_page_duplication', array( $this, 'duplicate_post_meta_info' ), 10, 2 );
      
      add_action( 'owf_post_duplication', array( $this, 'duplicate_post_duplicate_post_taxonomies' ), 10, 2 );
      add_action( 'owf_page_duplication', array( $this, 'duplicate_post_duplicate_post_taxonomies' ), 10, 2 );
   }
   
   /**
    * Filter Hook - Add "Duplicate" link to the post list page.
    * Verifies various conditions before showing/hiding the "Duplicate Post" link.
    * 1. is the post published/scheduled/private
    * 2. is the post_type allowed to participate in workflows
    * 3. is the workflow process active
    * 4. is duplicate post available to current user's role
    * @since 5.3
    */
   public function duplicate_link_row( $actions, $post ) {
      
      $option = get_option( 'oasiswf_custom_workflow_terminology' );

      $duplicate_post_label = ! empty( $option['duplicatePostText'] ) ? $option['duplicatePostText'] : __( 'Duplicate Post', "oasisworkflow" );
      
      $post_status = get_post_status( $post->ID );
      $allowed_post_types = get_option( 'oasiswf_show_wfsettings_on_post_types' );
      
      $is_duplicate_action_available = $this::is_duplicate_post_available_by_role( $post );
      
      if ( ( $post_status == 'publish' || $post_status == 'future' || $post_status == 'private') &&
      		in_array( $post->post_type, $allowed_post_types ) &&
      		get_option( "oasiswf_activate_workflow" ) == "active"
      		&& ( $is_duplicate_action_available ) ) {
         $nonce = wp_create_nonce('ow_duplicate_post_nonce');
         $actions['ow_duplicate_post'] = "<a href='javascript:void(0);' class='ow-duplicate-post'
         	postid='$post->ID'
            duplicatenonce = '$nonce'
         	title='" . esc_attr( __( 'Duplicate Post', "oasisworkflow" ) ) . "'>{$duplicate_post_label}</a><span class='loading' style='display: none;'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>";
      }
      
      return $actions;
   }
   
   
   /**
    * Check if Duplicate Post is available for the logged in user role.
    * Also checks the custom role - PostAuthor, if PostAuthor is selected, check if the current_user_id is the post author
    * @return boolean true if duplicate post is allowed, false if not
    * @since 5.3
    */
   public function is_duplicate_post_available_by_role( $post ) {
      $post_author_id = 0;
      if ( $post ) {
         $post_author_id = $post->post_author;
      }

      $is_duplicate_action_available = false;
      if ( current_user_can( 'ow_duplicate_post' ) ) {
         $is_duplicate_action_available = true;
      }
      if ( current_user_can( 'ow_duplicate_post' ) && get_current_user_id() == $post_author_id ) {
         $is_duplicate_action_available = true;
      }

      return $is_duplicate_action_available;
   }
   
   /**
    * AJAX function - Generate duplicate of a post
    * @since 5.3
    */
   public function save_duplicate_post_as_draft() {
      // nonce check
      check_ajax_referer( 'ow_duplicate_post_nonce', 'security' );

      $duplicate_post_id = $this->save_duplicate_post( 'draft' );
      $redirect_url = admin_url( "post.php?action=edit&post=$duplicate_post_id" );

      wp_send_json_success( esc_sql( $redirect_url ) );
   }
   
   public function save_duplicate_post( $status = '' ) {

      // Get the original post
      $id = (isset( $_GET['post'] ) && sanitize_text_field( $_GET["post"] )) ? intval( sanitize_text_field( $_GET['post'] ) ) : intval( sanitize_text_field( $_POST['post'] ) );
      $post = get_post( $id );

      // check capability
      $is_post_duplication_allowed = $this::is_duplicate_post_available_by_role( $post );

      if ( ! $is_post_duplication_allowed ) {
         wp_die( __( 'You are not allowed to make duplication of post.' ) );
      }
      
      if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) || ( isset( $_REQUEST['action'] ) && 'save_duplicate_post_as_draft' == $_REQUEST['action'] ) ) ) {
         wp_die( __( 'No post for duplication has been supplied!', "oasisworkflow" ) );
      }

      // nullify $_POST (coming from ajax call) as some plugins, like PODS have a hook on save_post
      // and will fail on wp_insert_post()
      unset( $_POST );

      // Copy the post and insert it
      if ( isset( $post ) && $post != null ) {
         $new_id = $this->create_duplication( $post, $status );
         return $new_id;
         exit;
      } else {
         wp_die( esc_attr( __( 'Duplication failed, could not find original:', "oasisworkflow" ) ) . ' ' . $id );
      }
   }
   
   /**
    * Create a duplication from a post
    * @since 5.3
    */
   public function create_duplication( $post, $status = '', $parent_id = '' ) {
      global $wpdb;

      // We don't want to clone revisions
      if ( $post->post_type == 'revision' ) {
         return;
      }
      
      $duplicate_post_author = '';
      if ( $post->post_type != 'attachment' ) {
         $status = 'draft';
         $duplicate_post_author = get_current_user_id();
      }
      elseif ( $post->post_type == 'attachment' ) {
         $duplicate_post_author = $post->post_author;
      }
      
      $duplicate_post = array(
          'menu_order'     => $post->menu_order,
          'comment_status' => $post->comment_status,
          'ping_status'    => $post->ping_status,
          'post_author'    => $duplicate_post_author,
          'post_content'   => htmlspecialchars_decode( htmlspecialchars( $post->post_content ) ),
          'post_excerpt'   => $post->post_excerpt,
          'post_mime_type' => $post->post_mime_type,
          'post_parent'    => $duplicate_post_parent = empty( $parent_id ) ? $post->post_parent : $parent_id,
          'post_password'  => $post->post_password,
          'post_status'    => $duplicate_post_status = (empty( $status )) ? $post->post_status : $status,
          'post_title'     => $post->post_title,
          'post_type'      => $post->post_type,
      );

      $duplicate_post_id = wp_insert_post( $duplicate_post );
      
      // update the post title with the updated post title.
      // wp_update_post, strips the html from the post title, so using wpdb->update to directly update the DB
      $wpdb->update(
         $wpdb->posts,
         array(
            'post_title' => $post->post_title
         ),
         array( 'ID' => $duplicate_post_id )
      );
      
      // If you have written a plugin which uses non-WP database tables to save
      // information about a post you can hook this action to dupe that data.
      if ( $post->post_type == 'page' || (function_exists( 'is_post_type_hierarchical' ) && is_post_type_hierarchical( $post->post_type ) ) ) {
         do_action( 'owf_page_duplication', $duplicate_post_id, $post );
      } else {
         do_action( 'owf_post_duplication', $duplicate_post_id, $post );
      }
      
      return $duplicate_post_id;
   }
   
   /**
    * Hook - Duplicate the meta information of a post to another post
    * @param $duplicate_post_id ID of the duplicated post
    * @param $original_post original published post
    * @since 5.3
    */
   public function duplicate_post_meta_info( $duplicate_post_id, $original_post ) {
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
            add_post_meta( $duplicate_post_id, $meta_key, $meta_value, false );
         }
      }
   }
   
   /**
    * Hook - Duplicate the taxonomies of a post to another post
    * @param $duplicate_post_id ID of the duplicated post
    * @param $original_post original published post
    * @since 5.3
    */
   public function duplicate_post_duplicate_post_taxonomies( $duplicate_post_id, $original_post ) {
      global $wpdb;
      if ( isset( $wpdb->terms ) ) {
         // Clear default category (added by wp_insert_post)
         wp_set_object_terms( $duplicate_post_id, NULL, 'category' );

         $post_taxonomies = get_object_taxonomies( $original_post->post_type );
         foreach ( $post_taxonomies as $taxonomy ) {
            $post_terms = wp_get_object_terms( $original_post->ID, $taxonomy, array( 'orderby' => 'term_order' ) );
            $terms = array();
            for ( $i = 0; $i < count( $post_terms ); $i ++ ) {
               $terms[] = $post_terms[$i]->slug;
            }
            wp_set_object_terms( $duplicate_post_id, $terms, $taxonomy );
         }
      }
   }
   
}

// construct an instance so that the actions get loaded
$ow_duplicate_service = new OW_Duplicate_Service();
?>