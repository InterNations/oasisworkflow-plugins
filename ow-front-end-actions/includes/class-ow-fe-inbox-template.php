<?php

/*
 * Inbox Template class for front end inbox actions
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

/**
 * OW_Front_End_Inbox_Template Class
 *
 * @since 1.0
 */
class OW_Front_End_Inbox_Template {

   public $template_path = 'oasisworkflow';
   public $owfes_get_loop_element_start_post_title = '<div class="col-md-3 post-title">';
   public $owfes_get_loop_element_start_due_date = '<div class="col-md-3">';
   public $owfes_get_loop_element_start_comments = '<div class="col-md-3 comments">';
   public $owfes_get_loop_element_start_action = '<div class="col-md-3 action">';
   public $owfes_get_loop_element_end = '</div>';

   public function owfes_get_template_part( $template_path, $template_name = '' ) {

      // Trim off any slashes from the template name
      $template_name = ltrim( $template_name, '/' );

      if ( file_exists( OW_FE_ACTIONS_CURRENT_THEME . "/" . $this->template_path . "/{$template_path}/{$template_name}.php" ) ):
         $template = locate_template( array( "{$template_path}/{$template_name}.php", $this->$template_path . "{$template_path}/{$template_name}.php" ) );
      else:
         $template = untrailingslashit( OW_FE_ACTIONS_PATH ) . "/templates/{$template_path}/{$template_name}.php";
      endif;

      if ( $template ) {
         load_template( $template, false );
      }
   }

}
