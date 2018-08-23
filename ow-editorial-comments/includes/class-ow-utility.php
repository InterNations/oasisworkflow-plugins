<?php

/*
 * Utilities class for Editorial Comment
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
class OW_Editorial_Comments_Utility {

   /**
    * Private constructor so nobody else can instance it
    *
    */
   private function __construct() {
      // Do Nothing
   }

   /**
    * Call this method to get singleton
    *
    * @return singleton instance of OW_Utility
    */
   public static function instance() {

      static $instance = NULL;
      if ( is_null( $instance ) ) {
         $instance = new OW_Editorial_Comments_Utility();
      }

      return $instance;
   }

   public function get_editorial_comment_table_name() {
      global $wpdb;
      return $wpdb->prefix . 'fc_comments';
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
            $message = __( 'There\'s something wrong with your code...', 'oweditorialcomments' );
            $admin_notice = "<div id=\"message\" class=\"error\">\n";
            break;
      }

      $admin_notice .= "    <p>" . __( $message, 'oweditorialcomments' ) . "</p>\n";
      $admin_notice .= "</div>\n";
      return $admin_notice;
   }

}

?>