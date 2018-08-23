<?php

/*
 * Utilities class for Front End Actions
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
 * Utilities class - singleton class
 *
 * @since 1.0
 */
class OW_Front_End_Actions_Utility {

   /**
    * Call this method to get singleton
    *
    * @return singleton instance of OW_Front_End_Actions_Utility
    */
   public static function instance() {

      static $instance = NULL;
      if ( is_null( $instance ) ) {
         $instance = new OW_Front_End_Actions_Utility();
      }

      return $instance;
   }

   public function logger( $message ) {
      if ( WP_DEBUG === true ) {
         if ( is_array( $message ) || is_object( $message ) ) {
            error_log( print_r( $message, true ) );
         } else {
            error_log( $message );
         }
      }
   }
   
    /**
    *Display error or success message in the admin section
    *
    * @param array $data containing type and message
    * @return string with html containing the error message
    * 
    * @since 1.0
    */

   public function admin_notice( $data = array() ) {
      // extract message and type from the $data array
      $message = isset( $data['message'] ) ? $data['message'] : "";
      $message_type = isset( $data['type'] ) ? $data['type'] : "";

      switch ( $message_type ) {
         case 'error':
            $admin_notice = '<div id="message" class="error notice is-dismissible">';
            break;
         case 'update':
            $admin_notice = '<div id="message" class="updated notice is-dismissible">';
            break;
         case 'update-nag':
            $admin_notice = '<div id="message" class="update-nag">';
            break;
         default:
            $message = __( 'There\'s something wrong with your code...', 'owfrontendactions' );
            $admin_notice = "<div id=\"message\" class=\"error\">\n";
            break;
      }

      $admin_notice .= "    <p>" . __( $message, 'owfrontendactions' ) . "</p>\n";
      $admin_notice .= "</div>\n";
      return $admin_notice;
   }

   /**
    * Convert a date format to a jQuery UI DatePicker format
    *
    * @param string $dateFormat a date format
    * @return string
    * @since 1.0
    */
   public function owf_date_format_to_jquery_ui_format( $dateFormat ) {

      $chars = array(
          // Day
          'd' => 'dd', 'j' => 'd', 'l' => 'DD', 'D' => 'D',
          // Month
          'm' => 'mm', 'n' => 'm', 'F' => 'MM', 'M' => 'M',
          // Year
          'Y' => 'yy', 'y' => 'y'
      );

      return strtr( (string) $dateFormat, $chars );
   }
   
}
