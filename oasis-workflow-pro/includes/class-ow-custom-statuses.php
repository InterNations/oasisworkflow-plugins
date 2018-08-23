<?php

/**
 * Custom Statuses for Workflow
 *
 * @copyright   Copyright (c) 2016, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6
 *
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

/**
 * OW_Custom_Statuses Class
 *
 * @since 3.6
 */
class OW_Custom_Statuses {

   // This is taxonomy name used to store all our custom statuses
   var $taxonomy_key = 'post_status';

   public function __construct() {
      add_action( 'wp_ajax_get_all_custom_statuses', array( $this, 'get_all_custom_statuses_ajax' ) );
      
      add_action( 'init', array( $this, 'register_custom_taxonomy' ) );
      add_action( 'init', array( $this, 'register_custom_statuses' ) );

      // Methods for handling the actions of creating, making default, and deleting post stati
      add_action( 'admin_init', array( $this, 'add_custom_status' ) );
      add_action( 'admin_init', array( $this, 'edit_custom_status' ) );
      add_action( 'admin_init', array( $this, 'delete_custom_status' ) );

      add_filter( 'display_post_states', array( $this, 'add_custom_post_states' ), 10, 2 );

      foreach ( array( 'post', 'post-new' ) as $hook ) {
         add_action( "admin_footer-{$hook}.php", array( $this,'display_custom_post_status' ) );
      }

   }
      
   /**
    * AJAX handler - Get all custom statuses and then send the json string
    *
    * @since 2.1
    */
   public function get_all_custom_statuses_ajax() {
   	wp_send_json_success( $this->get_all_custom_statuses() );
   }

   /**
    * Hook - Register custom post status taxonomy - used by workflows.
    *
    * @since 3.6
    */
   public function register_custom_taxonomy() {
      if ( ! taxonomy_exists( $this->taxonomy_key ) ) {
         $args = array( 'hierarchical' => false,
             'update_count_callback' => '_update_post_term_count',
             'label' => false,
             'query_var' => false,
             'rewrite' => false,
             'show_ui' => false
         );
         register_taxonomy( $this->taxonomy_key, 'post', $args );
      }
   }
   
    /**
    * Hook - Register custom post status - used by workflows.
    *
    * @since 3.6
    */
   public function register_custom_statuses() {
      $args = array( 'hide_empty' => false );
      $custom_statuses = get_terms( $this->taxonomy_key, $args );
      foreach ( $custom_statuses as $status ) {
         register_post_status( $status->slug, array(
             'label' => $status->name,
             'protected' => true,
             '_builtin' => false,
             'label_count' => _n_noop( "{$status->name} <span class='count'>(%s)</span>", "{$status->name} <span class='count'>(%s)</span>" )
         ) );
      }
   }
   
   /**
    * Hook - Add custom status to term table
    *
    * @return string
    * @since 3.6
    */
   public function add_custom_status() {

      if ( isset( $_POST['submit'] ) && isset( $_POST['action'] ) && $_POST['action'] == 'add-new' ) {
         // check capability
         if ( ! current_user_can( 'ow_create_workflow' ) ) {
            wp_die( __( 'You are not allowed to create custom statuses.', 'oasisworkflow' ) );
         }
         
         check_admin_referer( 'custom-status-add-nonce' );

         // Validate and sanitize the form data
         $term = sanitize_text_field( trim( $_POST['status_name'] ) );
         $slug = sanitize_text_field( trim( $_POST['slug_name'] ) );
         $slug = $slug ? $slug : sanitize_title( $term );
         $status_description = stripslashes( wp_filter_nohtml_kses( trim( $_POST['status_description'] ) ) );
         //handle_add_custom_status
         $args = array(
             'slug' => $slug,
             'description' => $status_description
         );
         $response = wp_insert_term( $term, $this->taxonomy_key, $args );
         if ( is_wp_error( $response ) ) {
            wp_die( __( 'Could not add status: ', 'oasisworkflow' ) . $response->get_error_message() );
         }

         add_action( 'admin_notices', array( $this, 'custom_status_added' ) );
      }
   }
   
   /**
    * Hook - Update custom status
    * @since 3.6
    */
   public function edit_custom_status() {

      if ( isset( $_POST['submit'] ) && isset( $_POST['action'] ) && 'update-status' == $_POST['action'] ) {
         // check capability
         if ( ! current_user_can( 'ow_edit_workflow' ) ) {
            wp_die( __( 'You are not allowed to edit/update the custom status.', 'oasisworkflow' ) );
         }

         check_admin_referer( 'edit_custom_status' );

         // Validate and sanitize the form data
         $term_id = intval( sanitize_text_field( $_POST['term_id'] ) );
         $term = sanitize_text_field( trim( $_POST['status_name'] ) );
         $slug = sanitize_text_field( trim( $_POST['slug_name'] ) );
         $slug = $slug ? $slug : sanitize_title( $term );
         $status_description = stripslashes( wp_filter_nohtml_kses( trim( $_POST['status_description'] ) ) );
         $args = array(
             'slug' => $slug,
             'description' => $status_description,
             'name' => $term
         );
         $response = wp_update_term( $term_id, 'post_status', $args );
         if ( is_wp_error( $response ) ) {
            wp_die( __( 'Could not add status: ', 'oasisworkflow' ) . $response->get_error_message() );
         }

         add_action( 'admin_notices', array( $this, 'custom_status_updated' ) );
      }
   }

   /**
    * Hook - Delete custom status for given term id
    * @since 3.6
    */
   public function delete_custom_status() {

      if ( isset( $_GET['action'] ) && 'delete-status' == $_GET['action'] ) {
         // check capability
         if ( ! current_user_can( 'ow_delete_workflow' ) ) {
            wp_die( __( 'You are not allowed to delete the custom status.', 'oasisworkflow' ) );
         }

         check_admin_referer( 'delete-custom-status' );
         //sanitize the input
         $term_id = intval( sanitize_text_field( $_GET['term_id'] ) );
         wp_delete_term( $term_id, $this->taxonomy_key );
         wp_redirect( admin_url( 'admin.php?page=oasiswf-custom-statuses' ) );
         exit();
      }
   }
   
   /**
    * Filter: Add our custom post status if its not set into $post_states
    * @param array $post_states
    * @param object $post
    * @return array
    * @since 3.6
    */
   public function add_custom_post_states( $post_states, $post ) {
      if ( empty( $post_states ) && $post->post_status != 'trash' && $post->post_status != 'publish' ) {
         $post_status = $this->get_single_term_by( 'slug', get_post_status( $post->ID ) );
         if ( is_object( $post_status ) && $post_status->slug )
            $post_states[$post_status->slug] = $post_status->name;
      }
      return $post_states;
   }

   
   /**
    * Notice: Custom status added successfully.
    *
    * @since 3.6
    */
   public function custom_status_added() {
      echo OW_Utility::instance()->admin_notice( array(
          'type' => 'update',
          'message' => __( 'Custom status has been added successfully.', 'oasisworkflow' )
      ) );
   }

   /**
    * Notice: Custom status updated successfully.
    *
    * @since 3.6
    */
   public function custom_status_updated() {
      echo OW_Utility::instance()->admin_notice( array(
          'type' => 'update',
          'message' => __( 'Custom status has been updated successfully.', 'oasisworkflow' )
      ) );
   }

   /**
    * Retrive all custom status from db
    * @return object
    *
    * @since 3.6
    */
   public function get_all_custom_statuses() {
      $args = array(
          'hide_empty' => false
      );
      return get_terms( $this->taxonomy_key, $args );
   }

   /**
    * Return the single term for given field and its value
    * @param string $field
    * @param mixed $value
    * @return object
    */
   public function get_single_term_by( $field, $value ) {
      return get_term_by( $field, $value, $this->taxonomy_key );
   }


   /**
    * Add the header/footer of custom status
    * @since 3.6
    */
   public function get_custom_status_header() {
      echo '<tr>
                  <th scope="col" class="manage-column column-name">' . __( 'Name', 'oasisworkflow' ) . '</th>
                  <th scope="col" class="manage-column column-slug">' . __( 'Slug', 'oasisworkflow' ) . '</th>
                  <th scope="col" class="manage-column column-description">' . __( 'Description', 'oasisworkflow' ) . '</th>
               </tr>';
   }

   /**
    * Hook - post, post-new
    * Display custom status on the post edit page
    *
    * @since 4.9
    */
   public function display_custom_post_status() {
      global $post, $ow_custom_statuses;
      $custom_statuses = $ow_custom_statuses->get_all_custom_statuses();

      foreach ( $custom_statuses as $custom_status ) {
         if ($custom_status->slug === $post->post_status) {
            $custom_post_status = $custom_status->name;
         }
      }

      if ( ! empty ( $custom_post_status ) ) {

         ?>

         <script type="text/javascript">
            jQuery(document).ready(function () {
               if (jQuery('#post-status-display').html().trim() != '') {
                  return false;
               }
               jQuery('#post-status-display').html('<?php echo $custom_post_status ?>');
               return false;
            });
         </script>

         <?php
      }
   }

}

$ow_custom_statuses = new OW_Custom_Statuses();
?>