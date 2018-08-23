<?php

/*
 * Submit to workflow for front end actions
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.4
 *
 */


if ( !defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

/**
 * @class OW_Front_End_Actions_Submit_To_Workflow
 * @since 1.4
 */
class OW_Front_End_Actions_Submit_To_Workflow {

   public function __construct() {
      /**
       * 1. Oasis Workflow pro plugin is installed and activated
       * @since 1.4
       */
      if ( !file_exists( WP_PLUGIN_DIR . '/oasis-workflow-pro/oasis-workflow-pro.php' ) ) {
         return;
      }
      add_shortcode( 'ow_submit_to_workflow', array( $this, 'owf_submit_to_workflow_shortcode' ) );
   }

   public function owf_submit_to_workflow_shortcode() {
      if ( current_user_can( 'ow_submit_to_workflow' ) ) {

         global $post;

         // Include require script and js for submit to workflow button and model
         $this->enqueue_scripts_and_css();

         // Include submit-workflow.php file to show model
         include_once( OASISWF_PATH . 'includes/pages/subpages/submit-workflow.php' );
         
         $return  = "<form name='post' action='post.php' method='post' id='post'>";
         $return .= "<input type='hidden' id='post_ID' name='post_ID' value='{$post->ID}'>";
         $return .= "<input type='text' name='post_title' value='{$post->post_title}' id='title' >";
         $return .= "<textarea class='wp-editor-area' name='content' id='content'>{$post->post_content}</textarea>";
         $return .= "<input type='text' name='excerpt' value='{$post->post_excerpt}' id='excerpt' >";
         
         $return .= "<input type='button' id='workflow_submit' class='btn-submit-workflow' value='Submit to Workflow'>";
         $return .= "<input type='hidden' id='hi_workflow_id' name='hi_workflow_id' />";
         $return .= "<input type='hidden' id='hi_step_id' name='hi_step_id' />";
         $return .= "<input type='hidden' id='hi_priority_select' name='hi_priority_select' />";
         $return .= "<input type='hidden' id='hi_actor_ids' name='hi_actor_ids' />";
         $return .= "<input type='hidden' id='hi_due_date' name='hi_due_date' />";
         $return .= "<input type='hidden' id='hi_comment' name='hi_comment' />";
         $return .= "<input type='hidden' id='save_action' name='save_action' />";
         $return .= "<input type='submit' name='save' id='save-post' value='Save' style='' class='button'>";
         
         $return .= "</form>";
         
         return $return;
      }
   }

   public function enqueue_scripts_and_css() {
      wp_nonce_field( 'owf_signoff_ajax_nonce', 'owf_signoff_ajax_nonce' );
       
      wp_dequeue_script( 'owf_submit_step' );
     
      OW_Plugin_Init::enqueue_and_localize_simple_modal_script();

      $ow_process_flow = new OW_Process_Flow();
      if ( method_exists( $ow_process_flow, "enqueue_and_localize_submit_workflow_script" ) ) { // for Pro
         $ow_process_flow->enqueue_and_localize_submit_workflow_script();
      }
   }
 
}

$OW_Front_End_Actions_Submit_To_Workflow = new OW_Front_End_Actions_Submit_To_Workflow();
?>