<?php

/*
 * Creates meta box to create condition that should be checked before post/page is published
 *
 * @copyright   Copyright (c) 2016, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.3
 *
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) )
   exit;

/**
 *
 * OW_Pre_Publish_Post_Meta_Box Class
 *
 * Creates meta box for the condition applied to article as a whole
 *
 * @since 1.3
 *
 */
class OW_Pre_Publish_Meta_Box extends OW_Editorial_Checklist_Meta_Boxes {

   public function __construct() {
      parent::__construct();
      add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'pre_publish_meta_box' ) );
      
      // Save meta box values
      add_action( 'save_post', array( $this, 'save_pre_publish_conditions' ) );
   }
   
   /**
    * Adds a meta box on the post.
    * Applicable for "conditions applied to article as a whole".
    *
    * @since 1.3
    */
   public function pre_publish_meta_box() {

      add_meta_box(
              'ow-pre-publish-meta-box', // meta box id
              __( 'Conditions applied to the article as a whole', 'oweditorialchecklist' ), // meta box title
              array( $this, 'meta_box_callback' ), // callback
              $this->post_type // post type
      );
   }
   
   /**
    * Creates the metabox
    *
    * @param WP_Post $post - post of ow-condition-group post-type
    * @return string html content for the meta box
    *
    * @since 1.3
    */
   public function meta_box_callback( $post ) {
      // Add a nonce field so we can check for it later.
      wp_nonce_field( 'ow_pre_publish_meta_box', 'ow_pre_publish_meta_box' );

      // provide the label of metabox content
      $label = __( 'Applied to: various post types.', 'oweditorialchecklist' );
      $label .= "<p>&nbsp;</p>";
      $label .= "<p><i>" . __( 'Example: Do the post images have alt tags?', 'oweditorialchecklist' ) . "</i></p>";

      $body = $this->get_meta_box_header( $label );

      $body .= $this->meta_box_body( $post );

      $body .= $this->get_meta_box_footer();


      echo $body;
   }
   
   /**
    *
    * @param WP_Post $post - post of ow-condition-group post-type
    * @return string $html to show the actual condition content
    *
    * @since 1.3
    */
   private function meta_box_body( $post ) {

      $ow_pre_publish_meta = get_post_meta( $post->ID, 'ow_pre_publish_meta', true );

      $existing_conditions = array();
      if ( ! empty( $ow_pre_publish_meta ) ) {
         $existing_conditions = $ow_pre_publish_meta;
      }

      // get count of conditions
      $count = count( $existing_conditions );
      $html = ''; 
      
      // Initialize the post_type array with available post types
      $post_types = OW_Utility::instance()->owf_get_post_types();
      $post_type_list = array();
      $post_type_list[ "-1" ] = "All Post Types";
      foreach ( $post_types as $post_type ) {

         // remove our custom post type
         if ( $this->post_type === $post_type[ 'name' ] ) {
            continue;
         }

         $post_type_list[ $post_type[ 'name' ] ] = esc_attr( $post_type[ 'label' ] );
      }
      
      // create hidden element
      $html .= $this->meta_box_contents( $post_type_list, array(), TRUE );

      if ( $count > 0 ) {
         for ( $index = 0; $index < $count; $index ++ ) {
            $selected_checklist_condition_values = array(
                'question_id' => $ow_pre_publish_meta[ $index ][ 'question_id' ],
                'checklist_condition' => $ow_pre_publish_meta[ $index ][ 'checklist_condition' ],
                'post_type' => $ow_pre_publish_meta[ $index ][ 'post_type' ],
                'required' => $ow_pre_publish_meta[ $index ][ 'required' ]
            );

            $html .= $this->meta_box_contents( $post_type_list, $selected_checklist_condition_values );
         }
      }

      // create add new condition button
      $html .= $this->add_new_condition_btn_html();

      return $html;
   }
   
   /**
    * @param array $post_type_list - list of post types
    * @param array $selected_values - selected values in each drop down for existing conditions
    * @param boolean $is_hidden, whether to hide this condition or not, used for hiding the first condition
    *
    * The hidden first condition, is used by the JS to replicate, when adding a new condition
    *
    * @return string html_content for the actual condition
    */
   private function meta_box_contents( $post_type_list, $selected_values = array(), $is_hidden = FALSE ) {

      $question_id = $checklist_condition = $sel_post_type = $required_check = $id = '';
      if ( is_array( $selected_values ) && ! empty( $selected_values ) ) {
         $question_id = $selected_values[ 'question_id' ];
         $checklist_condition = $selected_values[ 'checklist_condition' ];
         $sel_post_type = $selected_values[ 'post_type' ];
      }

      // if $question_id is empty, then this is a new condition, otherwise, it's an existing condition
      $id = ( isset( $question_id ) && ( ! empty( $question_id ) ) ) ? $question_id : "new";

      // generate the HTML for the condition
      $class = 'owcc-wrapper';
      if ( $is_hidden ) {
         $class = 'owcc-wrapper-hidden';
      }
 
      $html = "<div class='$class'>";
      $html .= "<input type='hidden' value='" . $id . "' name='question_id[]' />";
      $html .= '<input type="text" class="checklist_condition" placeholder="' .
               __( " Write your checklist question here ", "oweditorialchecklist" ) .
               '" name="checklist_condition[]" value="' .
               esc_attr( $checklist_condition ) . '"  />';

      $html .= "<select class='attribute' name='applicable_post_types[]'>";
      $html .= '<option value="">' . __( 'Applicable to', 'oweditorialchecklist' ) . '</option>';
      $html .= $this->get_drop_down_option_values( $post_type_list, $sel_post_type );
      $html .= '</select>';
      
      $html .= '<input type="checkbox" name="required_pre_publish_condition[]" class="required-condition" value="yes" ';
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
   public function save_pre_publish_conditions( $post_id ) {
      // Check if our nonce is set.
      if ( !isset( $_POST[ 'ow_pre_publish_meta_box' ] ) ) {
         return;
      }

      // Verify that the nonce is valid.
      if ( !wp_verify_nonce( $_POST[ 'ow_pre_publish_meta_box' ], 'ow_pre_publish_meta_box' ) ) {
         return;
      }

      $conditions_array = array();
      $required = array();
      
      $question_ids = $this->sanitize_meta_box_value( $_POST[ 'question_id' ] );
      foreach( $question_ids as $key => $val ) {
         if ( $val == "new" ) { //it's a new condition, so let's assign a question_id
            $question_ids[$key] = rand(100, 9999999);
         }
      }

      $checklist_conditions = $this->sanitize_meta_box_value( $_POST[ 'checklist_condition' ] );
      $post_types = $this->sanitize_meta_box_value( $_POST[ 'applicable_post_types' ] );
      if (isset ( $_POST[ 'required_pre_publish_condition' ] ) ) {
         $required = $this->sanitize_meta_box_value( $_POST['required_pre_publish_condition'] );
      }


      foreach ( $checklist_conditions as $key => $val ) {
         if ( empty( $checklist_conditions[ $key ] ) ) {
            unset( $question_ids[ $key ] );
            unset( $checklist_conditions[ $key ] );
            unset( $post_types[ $key ] );
            unset( $required[ $key ] );
         }
      }

      foreach ( $post_types as $key => $val ) {
         if ( empty( $post_types[ $key ] ) ) {
            unset( $question_ids[ $key ] );
            unset( $checklist_conditions[ $key ] );
            unset( $post_types[ $key ] );
            unset( $required[ $key ] );
         }
      }


      // since we are creating hidden element so that skip the first index[0]
      if ( count( $checklist_conditions ) > 0 ) {
         for ( $index = 1; $index <= count( $checklist_conditions ); $index ++ ) {
            $data = array(
                'question_id' => $question_ids[ $index ],
                'checklist_condition' => $checklist_conditions[ $index ],
                'post_type' => $post_types[ $index ],
                'required' => $required[ $index ]
            );

            array_push( $conditions_array, $data );
         }
      }

      delete_post_meta( $post_id, 'ow_pre_publish_meta' );
      update_post_meta( $post_id, 'ow_pre_publish_meta', $conditions_array );
   }

   
}
  
$ow_pre_publish_meta_box = new OW_Pre_Publish_Meta_Box();
?>