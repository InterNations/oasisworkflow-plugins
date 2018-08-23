<?php

/*
 * Creates meta box for the "conditions applied on the containing attribute" condition
 *
 * @copyright   Copyright (c) 2016, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 *
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) )
   exit;

/**
 *
 * OW_Containing_Attribute_Meta_Box Class
 *
 * Creates meta box for the "conditions applied on the containing attribute" condition
 *
 * @since 1.0
 *
 */
class OW_Containing_Attribute_Meta_Box extends OW_Editorial_Checklist_Meta_Boxes {

   public function __construct() {
      parent::__construct();
      add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'ow_editorial_checklist_meta_box' ) );

      // Save meta box values
      add_action( 'save_post', array( $this, 'ow_save_containing_attribute_meta' ) );
   }

   /**
    * Adds a meta box on the post.
    * Applicable for "conditions applied on the containing attribute".
    *
    * @since 1.0
    */
   public function ow_editorial_checklist_meta_box() {

      add_meta_box(
              'ow-editorial-checklist-containing-attribute-meta-box', // meta box id
              __( 'Conditions applied on the containing attribute', 'oweditorialchecklist' ), // meta box title
              array( $this, 'ow_containing_attribute_meta_box_callback' ), // callback
              $this->post_type // post type
      );
   }

   /**
    * Creates the metabox
    *
    * @param WP_Post $post - post of ow-condition-group post-type
    * @return string html content for the meta box
    *
    * @since 1.0
    */
   public function ow_containing_attribute_meta_box_callback( $post ) {

      wp_nonce_field( 'ow_containing_attribute_meta_box', 'ow_containing_attribute_meta_box' );
      // provide the label of metabox content
      $label = __( 'Applied to: tags, categories, images, links', 'oweditorialchecklist' );

      $body = $this->get_meta_box_header( $label );

      $body .= $this->ow_containing_attribute_meta_box_body( $post );

      $body .= $this->get_meta_box_footer();

      echo $body;
   }

   /**
    *
    * @param WP_Post $post - post of ow-condition-group post-type
    * @param array $post_type_list - list of post types on which conditions can be created.
    * @return string html_content for the actual condition content
    *
    * @since 1.0
    */
   private function ow_containing_attribute_meta_box_body( $post ) {
      $ow_containing_attribute_meta = get_post_meta( $post->ID, 'ow_containing_attribute_meta', true );

      $existing_conditions = array();
      if( !empty( $ow_containing_attribute_meta ) ) {
         $existing_conditions = $ow_containing_attribute_meta;
      }

      // get count of conditions
      $count = count( $existing_conditions );
      $html = '';
      // Initialize the post_type array with available post types
      $post_types = OW_Utility::instance()->owf_get_post_types();
      $post_type_list = array();
      $post_type_list["-1"] = "All Post Types";
      foreach ( $post_types as $post_type ) {

         // remove our custom post type
         if( $this->post_type === $post_type['name'] ) {
            continue;
         }

         $post_type_list[$post_type['name']] = esc_attr( $post_type['label'] );
      }


      // create hidden element
      $html .= $this->ow_containing_attribute_meta_box_body_contents( $post_type_list, array(), TRUE );
      if( $count > 0 ) {
         for ( $index = 0; $index < $count; $index ++ ) {
            $selected_condition_values = array(
                'post_type' => $ow_containing_attribute_meta[ $index ][ 'post_type' ],
                'contain_condition' => $ow_containing_attribute_meta[ $index ][ 'contain_condition' ],
                'taxonomy_count' => $ow_containing_attribute_meta[ $index ][ 'taxonomy_count' ],
                'taxonomy' => $ow_containing_attribute_meta[ $index ][ 'taxonomy' ],
                'required' => $ow_containing_attribute_meta[ $index ][ 'required' ]
            );

            $html .= $this->ow_containing_attribute_meta_box_body_contents( $post_type_list, $selected_condition_values );
         }
      }

      // create add new condition button
      $html .= $this->add_new_condition_btn_html();

      return $html;
   }

   /**
    *
    * @param array $post_type_list - list of post types on which conditions can be created.
    * @param array $selected_values - selected values in each drop down for existing conditions
    *
    * @return string html_content for the actual condition
    */
   private function ow_containing_attribute_meta_box_body_contents( $post_type_list, $selected_values = array(), $is_hidden = FALSE ) {

      $sel_post_type = $sel_contain = $sel_compare = $sel_taxonomy_count = $sel_taxonomy = $required_check = '';
      if( is_array( $selected_values ) && !empty( $selected_values ) ) {
         $sel_post_type = $selected_values[ 'post_type' ];
         $sel_contain = $selected_values[ 'contain_condition' ];
         $sel_taxonomy_count = $selected_values[ 'taxonomy_count' ];
         $sel_taxonomy = $selected_values[ 'taxonomy' ];
      }

      $class = 'owcc-wrapper';
      if( $is_hidden ) {
         $class = 'owcc-wrapper-hidden';
      }

      $html = "<div class='$class'>";
      $html .= "<select class='attribute-name' name='containing_post_types[]'>";
      $html .= '<option value="">' . __( 'Select Post Type', 'oweditorialchecklist' ) . '</option>';
      $html .= $this->get_drop_down_option_values( $post_type_list, $sel_post_type );
      $html .= '</select>';

      $html .= "<select name='containing_contain_conditions[]' class='contain-conditions'>";
      $html .= $this->get_drop_down_option_values( $this->contain_condition, $sel_contain );
      $html .= '</select>';

      $html .= '<input type="number" name="containing_taxonomy_count[]" value="' . esc_attr( $sel_taxonomy_count ) . '" class="ow-containing-taxonomy-count" />';

      $html .= '<select name="containing_taxonomy[]" class="ow-taxonomy">';
      $html .= '<option value="">' . __( "Select", "oweditorialchecklist" ) . '</option>';
      $html .= $this->get_drop_down_option_values( OW_Editorial_Checklist_Utility::instance()->get_taxonomy_types(), $sel_taxonomy );
      $html .= '</select>';
      
      $html .= '<input type="checkbox" name="required_containing_attribute[]" class="required-condition" value="yes" ';
      $html .= isset( $selected_values[ 'required' ] ) ? checked( $selected_values[ 'required' ], 'yes', false ) : 'checked';
      $html .= ' />';
       
      $html .= '<label>&nbsp;' . __( "Required?", "oweditorialchecklist" ) . '</label>';

      $html .= '<div class="icon-remove remove-condition">';
      $html .= '<img src="';
      $html .= OW_EDITORIAL_CHECKLIST_URL . '/assets/img/trash.png';
      $html .= '" title="delete condition" />';
      $html .= '</div>';

      $html .= '</div>';

      return $html;
   }

   /**
    * Saves the condition as a postmeta for the ow-condition-group type posts
    * @param int $post_id - post id for the ow-condition-group post type
    */
   public function ow_save_containing_attribute_meta( $post_id ) {

      // Check if our nonce is set.
      if( !isset( $_POST['ow_containing_attribute_meta_box'] ) ) {
         return;
      }


      // Verify that the nonce is valid.
      if( !wp_verify_nonce( $_POST['ow_containing_attribute_meta_box'], 'ow_containing_attribute_meta_box' ) ) {
         return;
      }
      $conditions_array = array();
      $required = array();

      $post_types = $this->sanitize_meta_box_value( $_POST[ 'containing_post_types' ] );
      $contain_conditions = $this->sanitize_meta_box_value( $_POST[ 'containing_contain_conditions' ] );
      $taxonomy_count = $this->sanitize_meta_box_value( $_POST[ 'containing_taxonomy_count' ] );
      $taxonomy = $this->sanitize_meta_box_value( $_POST[ 'containing_taxonomy' ] );
      if (isset ( $_POST[ 'required_containing_attribute' ] ) ) {
         $required = $this->sanitize_meta_box_value( $_POST['required_containing_attribute'] );
      }

      foreach ( $post_types as $key => $val ) {
         if( empty( $post_types[ $key ] ) ) {
            unset( $post_types[ $key ] );
            unset( $contain_conditions[ $key ] );
            unset( $taxonomy_count[ $key ] );
            unset( $taxonomy[ $key ] );
            unset( $required[ $key ] );
         }
      }

      foreach ( $taxonomy_count as $key => $val ) {
         if( empty( $taxonomy_count[ $key ] ) ) {
            unset( $post_types[ $key ] );
            unset( $contain_conditions[ $key ] );
            unset( $taxonomy_count[ $key ] );
            unset( $taxonomy[ $key ] );
            unset( $required[ $key ] );
         }
      }

      foreach ( $taxonomy as $key => $val ) {
         if( empty( $taxonomy[ $key ] ) ) {
            unset( $post_types[ $key ] );
            unset( $contain_conditions[ $key ] );
            unset( $taxonomy_count[ $key ] );
            unset( $taxonomy[ $key ] );
            unset( $required[ $key ] );
         }
      }

      // since we are creating hidden element so that skip the first index[0]
      if( count( $post_types ) > 0 ) {
         for ( $index = 1; $index <= count( $post_types ); $index ++ ) {
            $data = array(
                'post_type' => $post_types[$index],
                'contain_condition' => $contain_conditions[ $index ],
                'taxonomy_count' => $taxonomy_count[ $index ],
                'taxonomy' => $taxonomy[ $index ],
                'required' => $required[ $index ]
            );

            array_push( $conditions_array, $data );
         }
      }

      delete_post_meta( $post_id, 'ow_containing_attribute_meta' );
      update_post_meta( $post_id, 'ow_containing_attribute_meta', $conditions_array );
   }

   public function unset_empty_condition( $condition ) {
      foreach ( $condition as $key => $val ) {
         if( empty( $condition[ $key ] ) ) {
            unset( $condition[ $key ] );
         }
      }
   }

}

$ow_containing_attribute_meta_box = new OW_Containing_Attribute_Meta_Box();
?>