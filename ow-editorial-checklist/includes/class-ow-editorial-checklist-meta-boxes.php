<?php

/*
 * Abstract class for creating meta boxes for condition groups
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
 * OW_Editorial_Checklist_Meta_Boxes Abstract Class
 *
 * Abstract class for creating meta boxes for condition groups
 *
 * @since 1.0
 *
 */
abstract class OW_Editorial_Checklist_Meta_Boxes {

   protected $post_type = 'ow-condition-group'; // new custom post type for editorial checklist conditions
   protected $contain_condition = array();
   protected $count_type = array();
   protected $compare_condition = array();
   protected $taxonomy = array();

   public function __construct() {

      $this->contain_condition = array(
          'contain_at_least' => __( 'contains at least', 'oweditorialchecklist' ),
          'not_contain_more_than' => __( 'contains less than', 'oweditorialchecklist' )
      );
      
      $this->count_type = array(
          'words' => __( 'words', 'oweditorialchecklist' ),
          'letters' => __( 'letters', 'oweditorialchecklist' )
      );

      $this->taxonomy = OW_Editorial_Checklist_Utility::instance()->get_taxonomy_types();

      add_filter( 'post_updated_messages', array( $this,'condition_group_updated_messages' ) );
   }

   /**
    * Creates the html section for the top part of the condition meta box
    *
    * @param string $meta_box_label
    * @return string html string for the top part of the condition meta box
    */
   protected function get_meta_box_header( $meta_box_label ) {
      $header = "<table class='owcc-table widefat'>
               <tbody>
                  <tr>
                     <td class='label'>
                        $meta_box_label
                     </td>
                     <td class='ow-content-body'>
      ";
      return $header;
   }

   /**
    * Creates the html section for the bottom part of the condition meta box
    *
    * @return string html string for the top part of the condition meta box
    */
   protected function get_meta_box_footer() {
      $footer = "      </td>
                  </tr>
               </tbody>
            </table>";
      return $footer;
   }

   /**
    * Creates the "Add New Condition" button
    *
    * @return string html for the "Add New Condition" button
    */
   protected function add_new_condition_btn_html() {
      return '<input type="button" name="add_new_condition"
      		id="add_new_condition"
      		value="Add New Condition"
      		class="button button-primary add-new-condition" />';
   }

   /**
    * Creates the option list values for a given array to be used in a select drop down
    *
    * @param array $option_values
    * @param string $selected_option
    *
    * @return string html for the option values
    */
   protected function get_drop_down_option_values( $option_values, $selected_option ) {
      $options = '';
      foreach ( $option_values as $key => $val ) {
         $selected = (!empty( $selected_option ) && $selected_option == $key) ? 'selected' : '';
         $options .= "<option value='$key' $selected>$val</option>";
      }
      return $options;
   }

   /**
    * Sanitize any array
    *
    * @param array $data
    *
    * @return array sanitized data
    */
   protected function sanitize_meta_box_value( $data ) {
      return array_map( 'esc_attr', $data );
   }   
   
   /**
    * Show the admin notice when condition group is added/updated
    * @param array $messages
    * @return array $messages
    * @since 1.4
    */
   public function condition_group_updated_messages( $messages ) {
      global $post;

      $post_ID = $post->ID;
      $post_type = get_post_type( $post_ID );

      $obj = get_post_type_object( $post_type );
      $singular = $obj->labels->singular_name;

      $messages[ 'ow-condition-group' ] = array(
          1 => sprintf( __( '%s updated.', 'oweditorialchecklist' ), esc_attr( $singular ) ),
          6 => sprintf( __( '%s published. You can now use it in Workflow steps.', 'oweditorialchecklist' ), esc_attr( $singular ) ),
          7 => sprintf( __( '%s saved.', 'oweditorialchecklist' ), esc_attr( $singular ) )
      );
      return $messages;
   }

}

?>