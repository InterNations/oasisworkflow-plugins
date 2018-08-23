<?php

/*
 * Comparator for different types of ACF fields
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
   exit;

/**
 *
 * OW_ACF_Comparator Class
 *
 * functions to compare ACF fields between original and revision posts
 * Following are not covered:
 * Password
 * oEmbed
 * Taxonomy
 * Google Map
 * Message
 * Tab
 *
 * @since 1.0
 */
class OW_ACF_Comparator {

   private $original_post_id;
   private $revision_post_id;
   private $comparator;
   private $basic_types_array = array( "text", "email", "textarea", "number", "url" );
   private $choice_types_array = array( "select", "checkbox", "radio", "true_false" );
   private $content_types_array = array( "image", "file", "gallery", "wysiwyg" );
   private $relational_types_array = array( "post_object", "page_link", "relationship" );
   private $jquery_types_array = array( "date_picker", "date_time_picker", "time_picker", "color_picker" );

   public function __construct( $original_post_id, $revision_post_id, $comparator ) {
      $this->original_post_id = $original_post_id;
      $this->revision_post_id = $revision_post_id;
      $this->comparator = $comparator;
   }

   /**
    * Main Comparator function
    *
    * @return string differences between ACF fields
    *
    * @since 1.0
    */
   public function compare_acf_fields( $compare_by ) {
      $original_fields = get_field_objects( $this->original_post_id ); 
      $revision_fields = get_field_objects( $this->revision_post_id );

      $acf_diff = '';
      $compare_fields = $original_fields;
      $original_empty = false;
      
      if ( empty( $original_fields ) && ( ! empty( $revision_fields ) ) ) {
         $compare_fields = $revision_fields;
         $original_empty = true;
      }

      if ( $compare_fields ) {
         foreach ( $compare_fields as $field_name => $field_value ) {            
            // Check if original is empty than compare it using revision fields
            if( $original_empty == true ) {
               $original_field = $original_fields[$field_value['name']];
               $revision_field = $field_value;
            } else {               
               $revision_field = $revision_fields[$field_value['name']];
               $original_field = $field_value;
            }

            if ( in_array( $field_value['type'], $this->basic_types_array ) ) {
               $acf_diff .= $this->compare_basic_type( $original_field, $revision_field, $compare_by );
            }

            if ( $field_value['type'] == 'user' ) {
               $acf_diff .= $this->compare_user_type( $original_field, $revision_field );
            }

            if ( in_array( $field_value['type'], $this->choice_types_array ) ) {
               $acf_diff .= $this->compare_choice_field_type( $original_field, $revision_field );
            }

            if ( in_array( $field_value['type'], $this->content_types_array ) ) {
               $acf_diff .= $this->compare_content_field_type( $original_field, $revision_field, $compare_by );
            }

            if ( in_array( $field_value['type'], $this->relational_types_array ) ) {
               $acf_diff .= $this->compare_relational_field_type( $original_field, $revision_field );
            }
            
            if ( $field_value['type'] == 'group' ) {
               $acf_diff .= $this->compare_group_field_type( $original_field, $revision_field, $compare_by );
            }

            if ( $field_value['type'] == 'repeater' ) {
               $acf_diff .= $this->compare_repeater_field_type( $original_field, $revision_field, $compare_by );
            }
            
            if ( $field_value['type'] == 'flexible_content' ) {
               $acf_diff .= $this->compare_flexible_field_type( $original_field, $revision_field, $compare_by );
            }
            
            if ( in_array( $field_value['type'], $this->jquery_types_array ) ) {
               $acf_diff .= $this->compare_jquery_field_type( $original_field, $revision_field );
            }
         }
      }

      return $acf_diff;
   }

   private function compare_basic_type( $original_field, $revision_field, $compare_by ) {
      $original_field_value = $original_field['value'];
      $revision_field_value = $revision_field['value'];
      
      if ( $original_field["type"] == "textarea" && $compare_by == "content" ) {
         $original_field_value = strip_tags( $original_field['value'] );
         $revision_field_value = strip_tags( $revision_field['value'] );
      }
      
      $original_field_data = $this->format_basic_type( $original_field['label'],
      		$original_field_value );
      $revision_field_data = $this->format_basic_type( $revision_field['label'],
      		$revision_field_value );

      $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
        $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;
   }

   /**
    * compare the values for the ACF relationship attribute - User type
    *
    * @param array $original_field
    * @param array $revision_field
    *
    * @return difference between the user object under the relationship attribute
    *
    * @since 1.0
    */
   private function compare_user_type( $original_field, $revision_field ) {
      
      $original_field_data = $this->format_basic_type( $original_field['label'],
      		$original_field['value']['display_name'] );

      $revision_field_data = $this->format_basic_type( $revision_field['label'],
      		$revision_field['value']['display_name'] );
      
      $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
        $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;
   }

   /**
    * compare the values for the ACF choice type
    *
    * @param array $original_field
    * @param array $revision_field
    *
    * @return difference between the selected choices
    *
    * @since 1.0
    */
   private function compare_choice_field_type( $original_field, $revision_field ) {
      $original_field_data = '';
      $revision_field_data = '';

      // get the available choices as defined in ACF
      // if label is true_false then we will not get choices
      $available_choices = isset( $original_field['choices'] ) ? $original_field['choices'] : FALSE;

      // get the selected values for the original article
      $original_field_data .= $this->format_choice_type( $original_field['label'],
      		$original_field['value'], $available_choices );

      // get the selected values for the revision article
      $revision_field_data = $this->format_choice_type( $revision_field['label'],
      		$revision_field['value'], $available_choices );

      $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
         $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;      
   }

   /**
    * compare the values for the ACF content type
    *
    * @param array $original_field
    * @param array $revision_field
    *
    * @return difference between the selected choices
    *
    * @since 1.0
    */
   private function compare_content_field_type( $original_field, $revision_field, $compare_by ) {
      $original_field_data = '';
      $revision_field_data = '';
      
      $original_field_value = $original_field['value'];
      $revision_field_value = $revision_field['value'];
      
      if ( $original_field['type'] == 'wysiwyg' && $compare_by == 'content' ) {
      $original_field_value = strip_tags( $original_field['value'] );
      $revision_field_value = strip_tags( $revision_field['value'] );
      }

      // get the selected values for the original article
      $original_field_data .= $this->format_content_type( $original_field['label'],
      		$original_field_value );

      // get the selected values for the revision article
      $revision_field_data .= $this->format_content_type( $revision_field['label'],
      		$revision_field_value );

     $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
        $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;
   }

   /**
    * compare the values for the ACF relational type
    *
    * @param array $original_field
    * @param array $revision_field
    *
    * @return difference between the field data
    *
    * @since 1.0
    */
   private function compare_relational_field_type( $original_field, $revision_field ) {
      $original_field_data = '';
      $revision_field_data = '';

      // get the selected values for the original article
      $original_field_data .= $this->format_relational_post_type( $original_field['label'],
      		$original_field['value'] );

      // get the selected values for the revision article
      $revision_field_data .= $this->format_relational_post_type( $revision_field['label'],
      		$revision_field['value'] );

      $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
        $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;
   }
   
   /**
    * compare the values for the ACF jquery type
    *
    * @param array $original_field
    * @param array $revision_field
    * 
    * @return difference between the field data
    *
    * @since 1.2
    */
   private function compare_jquery_field_type( $original_field, $revision_field ) {
      $original_field_data = '';
      $revision_field_data = '';
      
      // get the values for the original article
      $original_field_data .= $this->format_basic_type( $original_field['label'],
      		$original_field['value'] );

      // get the values for the revision article
      $revision_field_data .= $this->format_basic_type( $revision_field['label'],
      		$revision_field['value'] );
      
      $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
        $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;

   }
   
   private function compare_group_field_type( $original_field, $revision_field, $compare_by ) {
      
      $original_field_data = '';
      $revision_field_data = '';
      
      // original field group data
      $original_field_data .= ( ! empty( $original_field ) ) ? $original_field['label'] . ' : ' : "";
      if ( ! empty( $original_field['sub_fields'] ) ) {
         foreach ( $original_field['sub_fields'] as $original_field_sub_field_data ) {
            // Get group field sub fields
            $original_field_data .= $this->get_group_sub_fields( $original_field_sub_field_data, $original_field['value'], $compare_by );
           
         } //close $original_field['value'] for loop
      } // close empty check
      

      // revision field group data
      $revision_field_data .= ( ! empty( $revision_field ) ) ? $revision_field['label'] . ' : ' : "";
      if ( ! empty( $revision_field['sub_fields'] ) ) {
         foreach ( $revision_field['sub_fields'] as $revision_field_sub_field_data ) {
            // Get group field sub fields
            $revision_field_data .= $this->get_group_sub_fields( $revision_field_sub_field_data, $revision_field['value'], $compare_by );
         } //close $revision_field['value'] for loop
      } // close empty check
      
      $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
        $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;
   }
   
   private function get_group_sub_fields( $sub_fields, $sub_field_data, $compare_by ) {    
      $field_data = "";
      $sub_field_type = $sub_fields['type'];
      $sub_field_name = $sub_fields['name'];
      
      $field_data .= "\n";
      $field_data .= "\t";

      if ( in_array( $sub_field_type, $this->basic_types_array ) ) {
         if ( $sub_field_type == 'textarea' && $compare_by == 'content' ) {
            $original_value = strip_tags( $sub_field_data[$sub_field_name] );
            $field_data .= $this->format_content_type( $sub_fields['label'],
               $original_value );
         } else {
            $field_data .= $this->format_basic_type( $sub_fields['label'],
               $sub_field_data[$sub_field_name] );
         }
      }

      if ( $sub_field_type == 'user' ) {
         $original_value = isset( $sub_field_data[$sub_field_name]['display_name'] ) ? $sub_field_data[$sub_field_name]['display_name'] : '';
         $field_data .= $this->format_basic_type( $sub_fields['label'],
            $original_value );
      }

      if ( in_array( $sub_field_type, $this->choice_types_array ) ) {
         // get the selected values for the original article
         $choices = isset ( $sub_fields['choices'] ) ? $sub_fields['choices'] : FALSE;
         $field_data .= $this->format_choice_type( $sub_fields['label'],
            $sub_field_data[$sub_field_name], $choices );
      }

      if ( in_array( $sub_field_type, $this->content_types_array ) ) {
         // get the selected values for the original article
         if ( $sub_field_type == 'wysiwyg' && $compare_by == 'content' ) {
            $original_value = strip_tags( $sub_field_data[$sub_field_name] );
            $field_data .= $this->format_content_type( $sub_fields['label'],
               $original_value );
         } else {
            $field_data .= $this->format_content_type( $sub_fields['label'],
               $sub_field_data[$sub_field_name] );
         }
      }

      if ( in_array( $sub_field_type, $this->relational_types_array ) ) {
         // get the selected values for the original article
         $field_data .= $this->format_relational_post_type( $sub_fields['label'],
            $sub_field_data[$sub_field_name] );
      }


      if ( in_array( $sub_field_type, $this->jquery_types_array ) ) {
         // get the values for original article
         $field_data .= $this->format_basic_type( $sub_fields['label'],
            $sub_field_data[$sub_field_name] );
      }
      
      if ( $sub_field_type == "group" ) {  
         if( isset( $sub_fields['sub_fields'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {
            foreach( $sub_fields['sub_fields'] as $sub_sub_field ) {
               $field_data .= $this->get_group_sub_fields( $sub_sub_field, $sub_fields['value'], $compare_by );
            }
         }            
      }

      if ( $sub_field_type == "repeater" ) {  
         if( isset( $sub_fields['sub_fields'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {
            foreach( $sub_field_data[ $sub_field_name ] as $sub_sub_field ) {
               $field_data .= $this->get_repeater_sub_fields( $sub_fields['sub_fields'], $sub_sub_field, $compare_by );
            }
         }            
      }
      
      if ( $sub_field_type == "flexible_content" ) {  
         if( isset( $sub_fields['layouts'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {
            foreach ( $sub_field_data[ $sub_field_name ] as $sub_sub_field ) {
               foreach( $sub_fields['layouts'] as $layout ) {
                  $field_data .= "\n";
                  $field_data .= "\t\t";
                  $field_data .= $layout['label'] . ' : ';
                  $field_data .= $this->get_flexible_sub_fields( $layout, $sub_sub_field, $compare_by, $sub_layout = "true"  );
               }
            }
         }            
      }
      
      return $field_data;
   }

   /**
    * compare the values for the ACF repeater type
    *
    * @param array $original_field
    * @param array $revision_field
    * @param array $compare_by
    * @return difference between the repeater fields
    *
    * @since 1.0
    */
   private function compare_repeater_field_type( $original_field, $revision_field, $compare_by ) {
      $original_field_data = '';
      $revision_field_data = '';
      
      // original field repeater data
      $original_field_data .= ( ! empty( $original_field ) ) ? $original_field['label'] . ' : ' : "";
      if ( ! empty( $original_field['value'] ) ) {
         foreach ( $original_field['value'] as $original_field_sub_field_data ) {
            // Get repeater field sub fields
            $original_field_data .= $this->get_repeater_sub_fields( $original_field['sub_fields'], $original_field_sub_field_data, $compare_by );
           
         } //close $original_field['value'] for loop
      } // close empty check
      

      // revision field repeater data
      $revision_field_data .= ( ! empty( $revision_field ) ) ? $revision_field['label'] . ' : ' : "";
      if ( ! empty( $revision_field['value'] ) ) {
         foreach ( $revision_field['value'] as $revision_field_sub_field_data ) {
            // Get repeater field sub fields
            $revision_field_data .= $this->get_repeater_sub_fields( $revision_field['sub_fields'], $revision_field_sub_field_data, $compare_by );
         } //close $revision_field['value'] for loop
      } // close empty check
      
      $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
        $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;
   }
   
   /**
    * Get the nestable subfields for repeater field type
    * @param array $sub_fields
    * @param array $sub_field_data
    * @param string $compare_by
    * @return string $field_data
    */
   private function get_repeater_sub_fields( $sub_fields, $sub_field_data, $compare_by ) {
      
      $field_data = "";
      foreach ( $sub_fields as $sub_field ) {
         $sub_field_type = $sub_field['type'];
         $sub_field_name = $sub_field['name'];

         $field_data .= "\n";
         $field_data .= "\t";

         if ( in_array( $sub_field_type, $this->basic_types_array ) ) {
            if ( $sub_field_type == 'textarea' && $compare_by == 'content' ) {
               $original_value = strip_tags( $sub_field_data[$sub_field_name] );
               $field_data .= $this->format_content_type( $sub_field['label'],
                  $original_value );
            } else {
               $field_data .= $this->format_basic_type( $sub_field['label'],
                  $sub_field_data[$sub_field_name] );
            }
         }

         if ( $sub_field_type == 'user' ) {
            $original_value = isset( $sub_field_data[$sub_field_name]['display_name'] ) ? $sub_field_data[$sub_field_name]['display_name'] : '';
            $field_data .= $this->format_basic_type( $sub_field['label'],
               $original_value );
         }

         if ( in_array( $sub_field_type, $this->choice_types_array ) ) {
            // get the selected values for the original article
            $choices = isset ( $sub_field['choices'] ) ? $sub_field['choices'] : FALSE;
            $field_data .= $this->format_choice_type( $sub_field['label'],
               $sub_field_data[$sub_field_name], $choices );
         }

         if ( in_array( $sub_field_type, $this->content_types_array ) ) {
            // get the selected values for the original article
            if ( $sub_field_type == 'wysiwyg' && $compare_by == 'content' ) {
               $original_value = strip_tags( $sub_field_data[$sub_field_name] );
               $field_data .= $this->format_content_type( $sub_field['label'],
                  $original_value );
            } else {
               $field_data .= $this->format_content_type( $sub_field['label'],
                  $sub_field_data[$sub_field_name] );
            }
         }

         if ( in_array( $sub_field_type, $this->relational_types_array ) ) {
            // get the selected values for the original article
            $field_data .= $this->format_relational_post_type( $sub_field['label'],
               $sub_field_data[$sub_field_name] );
         }


         if ( in_array( $sub_field_type, $this->jquery_types_array ) ) {
            // get the values for original article
            $field_data .= $this->format_basic_type( $sub_field['label'],
               $sub_field_data[$sub_field_name] );
         }
         
         if ( $sub_field_type == "group" ) {  
            if( isset( $sub_field['sub_fields'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {  
               foreach( $sub_field['sub_fields'] as $sub_sub_field ) {
                  $field_data .= $this->get_group_sub_fields( $sub_sub_field, $sub_field_data[ $sub_field_name ], $compare_by );
               }
            }            
         }

         if ( $sub_field_type == "repeater" ) {  
            if( isset( $sub_field['sub_fields'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {
               foreach( $sub_field_data[ $sub_field_name ] as $sub_sub_field ) {
                  $field_data .= $this->get_repeater_sub_fields( $sub_field['sub_fields'], $sub_sub_field, $compare_by );
               }
            }            
         }
         
         if ( $sub_field_type == "flexible_content" ) {  
            if( isset( $sub_field['layouts'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {
               foreach ( $sub_field_data[ $sub_field_name ] as $sub_sub_field ) {
                  foreach( $sub_field['layouts'] as $layout ) {
                     $field_data .= "\n";
                     $field_data .= "\t\t";
                     $field_data .= $layout['label'] . ' : ';
                     $field_data .= $this->get_flexible_sub_fields( $layout, $sub_sub_field, $compare_by, $sub_layout = "true"  );
                  }
               }
            }            
         }
         
      }       
      return $field_data;
   }
   
   /**
    * compare the values for the ACF flexible content
    * @param array $original_field
    * @param array $revision_field
    * @param array $compare_by
    * @return difference between the Flexible contents
    *
    * @since 1.2
    */
   private function compare_flexible_field_type( $original_field, $revision_field, $compare_by ) {
      $original_field_data = '';
      $revision_field_data = '';
      
      $original_field_data .= ( ! empty( $original_field ) ) ? $original_field['label'] . ' : ' : "";
      if ( ! empty( $original_field['value'] ) ) {
         foreach ( $original_field['value'] as $original_field_sub_field_data ) {
            $acf_layout = $original_field_sub_field_data['acf_fc_layout'];
            foreach ( $original_field['layouts'] as $layout ) {                           
               if( $acf_layout == $layout['name'] ) {
                  $original_field_data .= "\n";
                  $original_field_data .= "\t";
                  $original_field_data .= $layout['label'] . ' : ';     
                  // Get flexible field sub fields
                  $original_field_data .= $this->get_flexible_sub_fields( $layout, $original_field_sub_field_data, $compare_by, $sub_layout = "false"   );
               }
            } //close $original_field['layouts']
         } //close $original_field['value'] for loop
      } // close empty check
      
      $revision_field_data .= ( ! empty( $revision_field ) ) ? $revision_field['label'] . ' : ' : "";
      if ( ! empty( $revision_field['value'] ) ) {
         foreach ( $revision_field['value'] as $revision_field_sub_field_data ) {
            $revision_acf_layout = $revision_field_sub_field_data['acf_fc_layout'];
            foreach ( $revision_field['layouts'] as $revision_layout ) {                           
               if( $revision_acf_layout == $revision_layout['name'] ) {
                  $revision_field_data .= "\n";
                  $revision_field_data .= "\t";
                  $revision_field_data .= $revision_layout['label'] . ' : ';  
                  // Get flexible field sub fields
                  $revision_field_data .= $this->get_flexible_sub_fields( $revision_layout, $revision_field_sub_field_data, $compare_by, $sub_layout = "false"   );
               }
            } //close $revision_field['layouts']
         } //close $revision_field['value'] for loop
      } // close empty check
      
      $acf_field_diff = wp_text_diff( $original_field_data, $revision_field_data );
            
      if ( ! $acf_field_diff ) {
         // It's a better user experience to still show the Content, even if it didn't change.
        $acf_field_diff = $this->get_comparison_table( $original_field_data, $revision_field_data );
      }
      
      return $acf_field_diff;
   }
   
   /**
    * Get the nestable subfields for flexible field type
    * @param array $layout
    * @param array $sub_field_data
    * @param string $compare_by
    * @param string $sub_layout
    * @return string
    * @since 1.2
    */
   private function get_flexible_sub_fields( $layout, $sub_field_data, $compare_by, $sub_layout ) {
    
      $field_data = "";
      
      foreach ( $layout['sub_fields'] as $sub_field ) {
         $sub_field_type = $sub_field['type'];
         $sub_field_name = $sub_field['name'];        

         $field_data .= "\n";
         $field_data .= "\t\t";
         if( $sub_layout == "true" ) {    
            $field_data .= "\t\t\t\t";
         } 

         if ( in_array( $sub_field_type, $this->basic_types_array ) ) {
            if ( $sub_field_type == 'textarea' && $compare_by == 'content' ) {
               $value = strip_tags( $sub_field_data[$sub_field_name] );
               $field_data .= $this->format_content_type( $sub_field['label'],
                  $value );
            } else {
               $field_data .= $this->format_basic_type( $sub_field['label'],
                  $sub_field_data[$sub_field_name] );
            }
         }
         if ( $sub_field_type == 'user' ) {
            $value = isset( $sub_field_data[$sub_field_name]['display_name'] ) ? $sub_field_data[$sub_field_name]['display_name'] : '';
            $field_data .= $this->format_basic_type( $sub_field['label'],
               $value );
         }

         if ( in_array( $sub_field_type, $this->choice_types_array ) ) {
            // get the selected values for the original article
            $choices = isset ( $sub_field['choices'] ) ? $sub_field['choices'] : FALSE;
            $field_data .= $this->format_choice_type( $sub_field['label'],
               $sub_field_data[$sub_field_name], $choices );
         }

         if ( in_array( $sub_field_type, $this->content_types_array ) ) {
            // get the selected values for the original article
            if ( $sub_field_type == 'wysiwyg' && $compare_by == 'content' ) {
               $value = strip_tags( $sub_field_data[$sub_field_name] );
               $field_data .= $this->format_content_type( $sub_field['label'],
                  $value );
            } else {
               $field_data .= $this->format_content_type( $sub_field['label'],
                  $sub_field_data[$sub_field_name] );
            }
         }

         if ( in_array( $sub_field_type, $this->relational_types_array ) ) {
            // get the selected values for the original article
            $field_data .= $this->format_relational_post_type( $sub_field['label'],
               $sub_field_data[$sub_field_name] );
         }

         if ( in_array( $sub_field_type, $this->jquery_types_array ) ) {
            // get the values for original article
            $field_data .= $this->format_basic_type( $sub_field['label'],
               $sub_field_data[$sub_field_name] );
         }
         
         if ( $sub_field_type == "flexible_content" ) {  
            if( isset( $sub_field['layouts'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {
               foreach ( $sub_field_data[ $sub_field_name ] as $sub_sub_field ) {
                  foreach( $sub_field['layouts'] as $layout ) {
                     $field_data .= "\n";
                     $field_data .= "\t\t";
                     $field_data .= $layout['label'] . ' : ';
                     $field_data .= $this->get_flexible_sub_fields( $layout, $sub_sub_field, $compare_by, $sub_layout = "true"  );
                  }
               }
            }            
         }
         
         if ( $sub_field_type == "repeater" ) {  
            if( isset( $sub_field['sub_fields'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {
               foreach( $sub_field_data[ $sub_field_name ] as $sub_sub_field ) {
                  $field_data .= $this->get_repeater_sub_fields( $sub_field['sub_fields'], $sub_sub_field, $compare_by );
               }
            }            
         }
         
         if ( $sub_field_type == "group" ) {  
            if( isset( $sub_field['sub_fields'] ) && ( ! empty( $sub_field_data[ $sub_field_name ] ) ) ) {  
               foreach( $sub_field['sub_fields'] as $sub_sub_field ) {
                  $field_data .= $this->get_group_sub_fields( $sub_sub_field, $sub_field_data[ $sub_field_name ], $compare_by );
               }
            }            
         }
         
      }//close $layout['sub_fields']
      
      return $field_data;
   }

   /**
    * Formats a basic type for display purposes
    *
    * @param string $label
    * @param string $value
    *
    * @return formatted string for the given label and value
    *
    * @since 1.0
    */
   private function format_basic_type( $label, $value ) {
      $field_data = '';
      
      if ( ! empty( $value ) ) {
         $field_data .= $label . ' : ';
         $field_data .= $value;
      }

      return $field_data;
   }

   /**
    * Formats a choice type for display purposes
    *
    * @param string $label
    * @param array $selected_values
    * @param array $available_choices
    *
    * @return formatted string for the given choice type
    *
    * @since 1.0
    */
   private function format_choice_type( $label, $selected_values, $available_choices ) {
      $field_data_array = array();

      if ( ! empty( $selected_values ) ) {
         // get the selected values for the original article
         if ( is_array( $selected_values ) ) {
            foreach ( $selected_values as $selected_value ) {
               $field_data_array[] = $available_choices[$selected_value];
            }
         } elseif ( $available_choices === FALSE ) { // $label = true_false
            $field_data_array[] = $selected_values;
         } else { // looks like the selected value is a single value
            // lets check $selected value is empty or exist in $available_choices
            $field_data_array[] = isset($available_choices[$selected_values]) ? $available_choices[$selected_values] : $selected_values;
         }

         $field_data = $label . ' : ' . implode( " | ", $field_data_array );
      }

      return $field_data;
   }

   /**
    * Formats a content type for display purposes
    *
    * @param string $label
    * @param array/string $contents
    *
    * @return formatted string for the given label and contents
    *
    * @since 1.0
    */
   private function format_content_type( $label, $contents ) {
      $field_data_array = array();
      $field_data = "";

      if ( ! empty( $contents ) ) {
         if ( is_array( $contents ) ) {
            if ( array_key_exists( 'url', $contents ) ) {
               $field_data_array[] = $contents['url'];
            } else { // looks like to containing element is also an array
               $i = 1;
               foreach( $contents as $content) {
                  $field_data_array[] = $content['url'];
                  $i++;
               }
            }
         } else {
            $field_data_array[] = $contents;
         }

         $field_data = $label . ' : ' . implode( " | ", $field_data_array );
      }
      return $field_data;
   }

   /**
    * Formats a relational type for display purposes
    *
    * @param string $label
    * @param object $post_object
    *
    * @return formatted string for the given label and post
    *
    * @since 1.0
    */
   private function format_relational_post_type( $label, $post_object ) {

      $field_data_array = array();
      $field_data = "";
      
      if ( ! empty( $post_object ) ) {
         if ( is_array( $post_object ) ) { // relationship
            foreach ( $post_object as $post ) {
               $field_data_array[] = $post->post_title;
            }
         } else if ( is_object( $post_object ) ) { // post_object
            $field_data_array[] = $post_object->post_title;
         } else { // page link
            $field_data_array[] = $post_object;
         }

         $field_data = $label . ' : ' . implode( " | ", $field_data_array );
      }

      return $field_data;
   }
   
   /**
    * Create comparision table if wp_text_diff() is empty
    * @param string $original_field_data
    * @param string $revision_field_data
    * @return string $acf_field_diff
    * @since 1.2
    */
   private function get_comparison_table( $original_field_data, $revision_field_data ) {
      
      $acf_field_diff = '<table class="diff"><colgroup><col class="content diffsplit left"><col class="content diffsplit middle"><col class="content diffsplit right"></colgroup><tbody><tr>';
      $acf_field_diff .= '<td>' . $original_field_data . '</td><td></td><td>' . $revision_field_data . '</td>';
      $acf_field_diff .= '</tr></tbody>';
      $acf_field_diff .= '</table>';

      return $acf_field_diff;
   }

}

?>