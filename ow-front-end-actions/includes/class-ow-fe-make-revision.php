<?php

/*
 * Make Revision for front end actions
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 *
 */

if( !defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

/**
 * @class OW_Front_End_Actions_Make_Revision
 * @since 1.0
 */
class OW_Front_End_Actions_Make_Revision {

   public function __construct() {
      
      /**
       * Shortcode can be accessible if
       * 1. Oasis Workflow Pro plugins is installed and activated
       * @since 1.3
       */
      if( ! file_exists( WP_PLUGIN_DIR . '/oasis-workflow-pro/oasis-workflow-pro.php' ) ) {
         return;
      }
      add_shortcode( 'ow_make_revision_link', array( $this, 'owf_revise_post_shortcode' ) );
   }

   public function owf_revise_post_shortcode( $atts ) {

      global $post;

      // if post_id is passed as part of the short_code, then use that post_id.
      if ( ! empty( $atts ) && array_key_exists( 'post_id',  $atts ) ) {
         $current_post = get_post( $atts['post_id'] );
         $post = $current_post;
      }

      // show make-revision button if user is having capabilities
      if( ( current_user_can( 'ow_make_revision' ) && get_current_user_id() == $post->post_author ) || current_user_can( 'ow_make_revision_others' ) ) {

         $default_atts = array(
             'text'  => __( 'Make Revision', 'owfrontendactions' ),
             'type'  => 'button',
             'class' => 'btn-make-revision'
         );

         $atts = wp_parse_args( $atts, $default_atts );

         // Include require script and js for make revision button
         $this->enqueue_scripts_and_css();

         // Include make-revision.php file to show model if revision is already exists
         include_once( OASISWF_PATH . 'includes/pages/subpages/make-revision.php' );
         
         $class = $atts['class'];
         $text = $atts['text'];

         switch( $atts['type'] ) {
            case 'text':
               $return = "<a href='#' id='oasiswf_make_revision' postid= '$post->ID' class='$class' alt='$text' title='$text'>$text</a>";
               break;
            case 'button':
            default:
               $return = "<input type='button' id='oasiswf_make_revision' postid= '$post->ID' class='$class' value='$text'>";
               break;
         }

         $return .= '<span class="loading"></span>';
         return $return;
      }
   }

   public function enqueue_scripts_and_css() {
      wp_nonce_field( 'owf_make_revision_ajax_nonce', 'owf_make_revision' );
      wp_enqueue_style( 'owf-oasis-workflow-css', OASISWF_URL . 'css/pages/oasis-workflow.css', false, OASISWF_VERSION, 'all' );

      OW_Plugin_Init::enqueue_and_localize_simple_modal_script();

      $ow_process_flow = new OW_Process_Flow();
      if( method_exists( $ow_process_flow, "enqueue_and_localize_make_revision_script" ) ) { // for Pro
         $ow_process_flow->enqueue_and_localize_make_revision_script();
      } else {
         $ow_document_revision_init = new OW_Document_Revision_Init();
         $ow_document_revision_init->enqueue_and_localize_make_revision_script();
      }
   }

}

return new OW_Front_End_Actions_Make_Revision();
