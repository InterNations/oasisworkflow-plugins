<?php

/*
 * Creates meta box for the "conditions applied on the attribute in context" condition
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
 * OW_Context_Attribute_Meta_Box Class
 *
 * Creates meta box for the "conditions applied on the attribute in context" condition
 *
 * @since 1.0
 *
 */
class OW_Context_Attribute_Meta_Box extends OW_Editorial_Checklist_Meta_Boxes {

   public function __construct() {
      parent::__construct();
      add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'ow_editorial_checklist_meta_box' ) );

      // Save meta box values
      add_action( 'save_post', array( $this, 'ow_save_context_attribute_meta' ) );
   }

   /**
    * Adds a meta box on the post.
    * Applicable for "conditions applied on the attribute in context".
    *
    * @since 1.0
    */
   public function ow_editorial_checklist_meta_box() {

      add_meta_box(
              'ow-editorial-checklist-context-attribute-meta-box', // meta box id
              __( 'Conditions applied on the attribute in context', 'oweditorialchecklist' ), // meta box title
              array( $this, 'ow_context_attribute_meta_box_callback' ), // callback
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
   public function ow_context_attribute_meta_box_callback( $post ) {
      // Add a nonce field so we can check for it later.
      wp_nonce_field( 'ow_context_attribute_meta_box', 'ow_context_attribute_meta_box' );

      // provide the label of metabox content
      $label = __( 'Applied to: title, content, excerpt', 'oweditorialchecklist' );

      $body = $this->get_meta_box_header( $label );

      $body .= $this->ow_context_attribute_meta_box_body( $post );

      $body .= $this->get_meta_box_footer();


      echo $body;
   }

   /**
    *
    * @param WP_Post $post - post of ow-condition-group post-type
    * @param array $context_attributes - list of context_attributes on which conditions can be created.
    * @return string html_content for the actual condition content
    *
    * @since 1.0
    */
   private function ow_context_attribute_meta_box_body( $post ) {
      $ow_context_attribute_meta = get_post_meta( $post->ID, 'ow_context_attribute_meta', true );
      $existing_conditions = array();
      if( ! empty( $ow_context_attribute_meta ) ) {
         $existing_conditions = $ow_context_attribute_meta;
      }

      // get count of conditions
      $count = count( $existing_conditions );
      $html = '';

      $context_attributes = OW_Editorial_Checklist_Utility::instance()->get_context_attribute_types();

      // create hidden element
      $html .= $this->ow_context_attribute_meta_box_body_contents( $context_attributes, array(), TRUE );
      if( $count > 0 ) {
         for ( $index = 0; $index < $count; $index ++ ) {
            $selected_values = array(
                'context_attribute' => $ow_context_attribute_meta[ $index ][ 'context_attribute' ],
                'contain_condition' => $ow_context_attribute_meta[ $index ][ 'contain_condition' ],
                'word_count' => $ow_context_attribute_meta[ $index ][ 'word_count' ],
                'count_type' => $ow_context_attribute_meta[ $index ][ 'count_type' ],
                'required' => $ow_context_attribute_meta[ $index ][ 'required' ]
            );

            $html .= $this->ow_context_attribute_meta_box_body_contents( $context_attributes, $selected_values );
         }
      }

      // create add new condition button
      $html .= $this->add_new_condition_btn_html();

      return $html;
   }

   /**
    *
    * @param array $context_attributes - list of context attributs on which conditions can be created.
    * @param array $selected_values - selected values in each drop down for existing conditions
    *
    * @return string html_content for the actual condition
    */
   private function ow_context_attribute_meta_box_body_contents( $context_attributes, $selected_values = array(), $is_hidden = FALSE ) {

      $sel_context_attr = $sel_contain = $sel_compare = $sel_word_count =  $sel_count_type = $required_check = '';
      if( is_array( $selected_values ) && !empty( $selected_values ) ) {
         $sel_context_attr = $selected_values[ 'context_attribute' ];
         $sel_contain = $selected_values[ 'contain_condition' ];
         $sel_word_count = $selected_values[ 'word_count' ];
         $sel_count_type = $selected_values[ 'count_type' ];
      }

      $class = 'owcc-wrapper';
      if( $is_hidden ) {
         $class = 'owcc-wrapper-hidden';
      }

      $html = "<div class='$class'>";

      $html .= "<select class='attribute-name' name='context_attribute[]'>";
      $html .= '<option value="">' . __( 'Select Attribute', 'oweditorialchecklist' ) . '</option>';
      $html .= $this->get_drop_down_option_values( $context_attributes, $sel_context_attr );
      $html .= '</select>';

      $html .= "<select name='contain_conditions[]' class='contain-conditions'>";
      $html .= $this->get_drop_down_option_values( $this->contain_condition, $sel_contain );
      $html .= '</select>';

      $html .= '<input type="number" name="word_count[]" value="' . esc_attr( $sel_word_count ) . '" class="ow-word-count" />';

      $html .= "<select name='count_type[]' class='contain-conditions'>";
      $html .= $this->get_drop_down_option_values( $this->count_type, $sel_count_type );
      $html .= '</select>';
      
      $html .= '<input type="checkbox" name="required_context_attribute[]" class="required-condition" value="yes"';
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
   public function ow_save_context_attribute_meta( $post_id ) {

      // Check if our nonce is set.
      if( !isset( $_POST['ow_context_attribute_meta_box'] ) ) {
         return;
      }

      // Verify that the nonce is valid.
      if( !wp_verify_nonce( $_POST['ow_context_attribute_meta_box'], 'ow_context_attribute_meta_box' ) ) {
         return;
      }

      if ( ! current_user_can( 'ow_create_workflow' ) && ! current_user_can( 'ow_edit_workflow' ) ) {
      	wp_die( __( 'You are not allowed to create/edit condition groups.' ) );
      }

      $conditions_array = array();
      $required = array();

      $context_attributes = $this->sanitize_meta_box_value( $_POST[ 'context_attribute' ] );
      $contain_conditions = $this->sanitize_meta_box_value( $_POST[ 'contain_conditions' ] );
      $word_count = $this->sanitize_meta_box_value( $_POST[ 'word_count' ] );
      $count_type = $this->sanitize_meta_box_value( $_POST[ 'count_type' ] );
      if (isset ( $_POST[ 'required_context_attribute' ] ) ) {
         $required = $this->sanitize_meta_box_value( $_POST['required_context_attribute'] );
      }

      foreach ( $context_attributes as $key => $val ) {
         if( empty( $context_attributes[ $key ] ) ) {
            unset( $context_attributes[ $key ] );
            unset( $contain_conditions[ $key ] );
           // unset( $compare_conditions[$key] );
            unset( $word_count[ $key ] );
            unset( $count_type[ $key ] );
            unset( $required[ $key ] );
         }
      }

      foreach ( $word_count as $key => $val ) {
         if( empty( $word_count[ $key ] ) ) {
            unset( $context_attributes[ $key ] );
            unset( $contain_conditions[ $key ] );
            //unset( $compare_conditions[$key] );
            unset( $word_count[ $key ] );
            unset( $count_type[ $key ] );
            unset( $required[ $key ] );
         }
      }

      // since we are creating hidden element so that skip the first index[0]
      if( count( $context_attributes ) > 0 ) {
         for ( $index = 1; $index <= count( $context_attributes ); $index ++ ) {
            $data = array(
                'context_attribute' => $context_attributes[ $index ],
                'contain_condition' => $contain_conditions[ $index ],
                'word_count' => $word_count[ $index ],
                'count_type' => $count_type[ $index ],
                'required' => $required[ $index ]
            );

            array_push( $conditions_array, $data );
         }
      }

      delete_post_meta( $post_id, 'ow_context_attribute_meta' );
      update_post_meta( $post_id, 'ow_context_attribute_meta', $conditions_array );
   }

}

$ow_context_attribute_meta_box = new OW_Context_Attribute_Meta_Box();
?>