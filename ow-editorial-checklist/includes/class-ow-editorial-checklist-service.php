<?php

class OW_Editorial_Checklist_Service {

   public function __construct() {
      add_action( 'wp_ajax_add_condition_group_to_step', array( $this, 'add_condition_group_to_step' ) );
      add_action( 'wp_ajax_get_step_checklist_page', array( $this, 'get_step_checklist_page' ) );
      add_action( 'wp_ajax_validate_condition_group_delete', array( $this, 'validate_condition_group_delete' ) );
      add_action( 'wp_ajax_trash_condition_group', array( $this, 'trash_condition_group' ) );
      
      add_filter( 'owf_display_condition_group_list', array( $this, 'display_condition_group_list' ), 10, 3 );
      add_filter( 'owf_display_custom_data', array( $this, 'display_pre_publish_conditions' ), 10, 4 );
      add_filter( 'owf_set_history_meta', array( $this, 'set_pre_publish_checklist_meta' ), 10, 3 );
      add_filter( 'display_history_column_header', array( $this, 'display_checklist_column_header' ), 10, 1 );
      add_filter( 'display_history_column_content', array( $this, 'display_checklist_column_content' ), 10, 3 );
      add_filter( 'owf_submit_to_workflow_pre', array( $this, 'validate_condition_group_for_step' ), 10, 2 );
      add_action( 'owf_sign_off_workflow_pre', array( $this, 'validate_condition_group_for_step' ), 10, 2 );

//      Example code for ow_checklist_context_attribute filter
//      add_filter( 'ow_checklist_context_attribute', array( $this, 'custom_contents_for_checklist' ), 10, 2 );
   }
   
   /**
    * To display a drop down for condition groups on the step info
    *
    * @param unknown $step_info
    * @param $is_first_step whether this condition group is for first step OR not
    * @param $condition_group_id - element ID
    *
    * @return html for displaying the condition group list
    */
   public function display_condition_group_list( $step_info, $is_first_step, $condition_group_id ) {
      $condition_groups = $this->get_all_condition_groups();
      $sel_cond_group = '';

      if( ! $is_first_step && is_object( $step_info ) && isset( $step_info->condition_group ) ) {
         $sel_cond_group = $step_info->condition_group;
      }

      $result_html = "<p>";
      $result_html .= "<label>" .  __( 'Condition Group:', 'oweditorialchecklist' ) . "</label>";
      $result_html .= "<select name='" . $condition_group_id . "' id='" . $condition_group_id . "'>";
      $result_html .= "<option value=''></option>";
      if( $condition_groups ) {
         foreach ( $condition_groups as $condition_group ) {
            if ( $sel_cond_group == $condition_group->ID ) {
               $selected = "selected='selected'";
            } else {
               $selected = "";
            }
            $result_html .= "<option value='" . $condition_group->ID . "' " .  $selected . ">";
            $result_html .= $condition_group->post_title;
            $result_html .= "</option>";
         }
      }
      $result_html .= "</select>";
      $result_html .= "</p>";

      echo $result_html;
   }

   public function display_pre_publish_conditions( $custom_data, $post_id, $step_id, $history_id ) {
      $result = $custom_data;
      // if by pass checklist is checked then do NOT display the checklist items.
      if ( get_option( 'oasiswf_checklist_action' ) === 'by_pass_the_checklist' ) {
         return $result;
      }
      if ( empty( $step_id ) ) {
         $ow_history_service = new OW_History_Service();
         $history = $ow_history_service->get_action_history_by_id( $history_id );
         $step_id = $history->step_id;
      }
      $sel_cond_group = $this->get_condition_group_for_step( $post_id, $step_id );
      if( empty( $sel_cond_group ) ) {
         return $result;
      }

      $checklist_conditions = get_post_meta( $sel_cond_group, 'ow_pre_publish_meta', true );

      $count = 0;
      if ( ! empty ( $checklist_conditions ) ) {
         $count = count( $checklist_conditions );
      }

      // no checklist conditions exist
      if ( $count == 0 ) {
         return $result;
      }

      $result .= "<div class='left'>";
      $result .= "<label>" . __( "Pre Publish Checklist :", "oweditorialchecklist" ) . "</label>";
      $result .= "</div>";
      $result .= "<div class='ow-pre-publish-conditions'>";
      $applicable_conditions = 0;
      foreach ( $checklist_conditions as $condition ) {
         // only display required conditions
         if ($condition['required'] !== 'yes') {
            continue;
         }

         // check if condition is applicable to current post type
         // if condition_post_type is -1, then the condition is applied to all post types
         $condition_post_type = $condition['post_type'];
         if( $condition_post_type !== "-1" && $condition_post_type !== get_post_type( $post_id ) ) {
            continue;
         }

         $result .= "<p class='check-column ow-pre-publish-checkbox'>";
         $result .= "<input type='checkbox' name='custom_condition[]' id='custom_condition[]' value= '" .
                    $sel_cond_group . "-" . $condition['question_id'] . "'/>";
         $result .=  $condition['checklist_condition'];
         $result .= "</p>";
         $applicable_conditions++; // increment the applicable conditions
      }
      $result .= "</div>";

      if ( $applicable_conditions > 0 ) { // we found some conditions which should be completed
         return $result;
      }

      return $custom_data; // return incoming string as-is, if we didn't find any applicable conditions.
   }
   
   /*
    * AJAX function - Checks if condition group is used in workflow
    * @since 1.5
    */
   public function validate_condition_group_delete() {
      global $wpdb;

      // nonce check
      check_ajax_referer( 'owf_editorial_checklist_nonce', 'security' );

      // check capability 
      if ( ! current_user_can( 'ow_delete_workflow' ) ) {
         wp_die( __( 'You are not allowed to delete the condition group.', 'oasisworkflow' ) );
      }
          
      /* sanitize incoming data */
      $trashed_condition_id = intval( $_POST['trashed_condition_id'] );
      $post_type = get_post_type( $trashed_condition_id );
      
      if ( $post_type === 'ow-condition-group' ) {
         $steps_table = OW_Utility::instance()->get_workflow_steps_table_name();
         $sql = "SELECT ID, step_info FROM $steps_table";
         $steps = $wpdb->get_results( $sql );
         foreach ( $steps as $step ) {
            $step_info = json_decode( $step->step_info );
            if ( array_key_exists( 'condition_group', $step_info ) && $step_info->condition_group == $trashed_condition_id ) {
                  ob_start();
                  include_once OW_EDITORIAL_CHECKLIST_PATH . 'includes/pages/sub-pages/condition-group-trash.php';
                  $result = ob_get_contents();
                  ob_get_clean();
                  wp_send_json_error( htmlentities( $result ) );
            }     
         }
         // seems condition group is not being used in any workflow, proceed with delete
         wp_send_json_success();
      }   
   } 
   
   /*
    * AJAX function - delete condition group
    * @since 1.5
    */
   public function trash_condition_group() {
      // nonce check
      check_ajax_referer( 'owf_editorial_checklist_nonce', 'security' );

      // check capability
      if ( ! current_user_can( 'ow_delete_workflow' ) ) {
         wp_die( __( 'You are not allowed to delete the condition group.', 'oasisworkflow' ) );
      }

      /* sanitize incoming data */
      $trashed_condition_id = intval( $_POST['trashed_condition_id'] );
      
      $this::delete_condition_group( $trashed_condition_id );
      wp_send_json_success( admin_url() );
   }

   /**
    *
    * Delete condition group
    * Also delete any references from the workflows
    *
    * @param int $condition_group_id
    *
    * @since 1.5
    */
   public function delete_condition_group( $condition_group_id ) {
      global $wpdb;

      /* sanitize incoming data */
      $trashed_condition_id = intval( $condition_group_id );

      // remove condition group from setps
      $steps_table = OW_Utility::instance()->get_workflow_steps_table_name();
      $sql = "SELECT ID, step_info FROM $steps_table";
      $steps = $wpdb->get_results( $sql );
      foreach ( $steps as $step ) {
         $step_info = json_decode( $step->step_info );
         if ( array_key_exists( 'condition_group', $step_info ) && $step_info->condition_group == $trashed_condition_id )  {
            $step_info->condition_group = '';
            $step->step_info = wp_json_encode( $step_info );
            $wpdb->update( $steps_table, array(
               'step_info' => $step->step_info
            ), array(
               'ID' => $step->ID
            ) );
         }
      }

      //remove condition group if the step is set to first-step
      $workflows_table = OW_Utility::instance()->get_workflows_table_name();
      $workflows = $wpdb->get_results( "SELECT ID, wf_info FROM $workflows_table" );
      foreach( $workflows as $workflow ) {
         $wf_info = json_decode( $workflow->wf_info );
         $first_step_info = $wf_info->first_step[0];
         if ( ! empty( $first_step_info ) && array_key_exists( 'condition_group', $first_step_info ) &&
              $first_step_info->condition_group == $trashed_condition_id ) {
            $first_step_info->condition_group = '';
            $wf_info->first_step[0] = $first_step_info;
            $wf_info = wp_unslash( wp_json_encode( $wf_info ) );
            $wpdb->update( $workflows_table, array(
               'wf_info' => $wf_info
            ), array(
               'ID' => $workflow->ID
            ));
         }
      }
      // trash the condition group
      wp_trash_post( $trashed_condition_id );
   }

   public function set_pre_publish_checklist_meta( $history_meta, $post_id, $workflow_data ) {
      if ( ! empty( $workflow_data['pre_publish_checklist'] ) ) {
         $user_selected_checklist = $workflow_data['pre_publish_checklist'];

         $user_selected_questions = array();
         $user_selected_condition_group = "";

         foreach ( $user_selected_checklist as $user_selected_question ) {
            $user_selected_pair = explode( "-", $user_selected_question );
            $user_selected_condition_group = $user_selected_pair[0];
            array_push( $user_selected_questions, $user_selected_pair[1] );
         }

         $pre_publish_checklist = array();
         $pre_publish_checklist['condition_group_id']  = $user_selected_condition_group;
         $pre_publish_checklist['meta_key']            = 'ow_pre_publish_meta';
         $pre_publish_checklist['checklist_questions'] = implode( ",", $user_selected_questions );


         $history_meta["pre_publish_checklist"] = $pre_publish_checklist;
      }

      return $history_meta;
   }
   
    /**
    * To display checklist column header for pre-publish conditions on history page
    *
    * @return html for displaying checklist column header
    * @since 1.4
    */
   public function display_checklist_column_header( $result_html ) {
      $result_html = "<th scope='col' class='history-condition' >" . __("Checklist", "oasisworkflow") . "</th>";
      echo $result_html;
   }

   /**
    * To display checklist column content for pre-publish conditions on history page
    *
    * @param $post_id
    * @param $history_row
    * @param $type
    *
    * @return html for displaying checklist column content
    * @since 1.4
    */
   public function display_checklist_column_content( $post_id, $history_row, $type ) {
      if( is_object( $history_row ) && isset( $history_row->ID ) ) {
         $history_id = $history_row->ID;
      }
      $ow_process_flow = new OW_Process_Flow();
      
      $result_html = "<td class='pre-publish-checklist column-comments'>
								<div class='post-com-count-wrapper'>
									<strong>
										<a href='#' action_id={$history_id} class='post-condition-count post-com-count-approved' post_id={$post_id} history_type={$type}>
										<span class='comment-count-approved'>{$ow_process_flow->get_checklist_count_by_history( $history_row )}</span>
										</a>
										<span class='loading' style='display:none'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
									</strong>
								</div>
							</td>";
      echo $result_html;   
   }
   
    /*
	 * AJAX - get_step_checklist_page 
	 * Dispaly popup with slected pre-publish conditions during submit-to-workflow/sign-off
    * on history page when click on checklist count blurb 
    * 
	 * @since 1.4
	 */
	public function get_step_checklist_page() {
		check_ajax_referer( 'owf_inbox_ajax_nonce', 'security' );
		ob_start();
		include( OW_EDITORIAL_CHECKLIST_PATH . "includes/pages/sub-pages/pre-publish-checklist.php" );
		$result = ob_get_contents();
		ob_end_clean();

		wp_send_json_success( $result );
	}

    /**
    * AJAX - add condition group to step table
    * @global type $wpdb
    * @since 1.0
    */
   public function add_condition_group_to_step() {
      global $wpdb;
      $data = array_map( 'sanitize_text_field', $_POST );

      if ( ! current_user_can( 'ow_create_workflow' ) &&  ! current_user_can( 'ow_edit_workflow' ) ) {
      	wp_die( __( 'You are not allowed to create/edit workflows.' ) );
      }

      $step_id = $data['step_id'];

      // get step information
      $workflow_service = new OW_Workflow_Service();
      $step = $workflow_service->get_step_by_id( $step_id );

      $step_info = (array) json_decode( $step->step_info );
      $step_info['condition_group'] = trim( $data['condition_group'] );

      $step->step_info = json_encode( $step_info );
      $workflow_service->upsert_workflow_step( $step );

      wp_die();
   }

   private function get_condition_group_for_step( $post_id, $step_id ) {
      $sel_cond_group = '';
      $is_in_workflow = 1;
      if ( ! empty ($post_id ) ) {
         $is_in_workflow = get_post_meta( $post_id, '_oasis_is_in_workflow', true );
      }

      // if the post is being submitted to the workflow, then get the condition group from the workflow info -> first_step
      if ( empty( $is_in_workflow ) || $is_in_workflow == 0 ) {
         $workflow_service = new OW_Workflow_Service();
         $step = $workflow_service->get_step_by_id( $step_id );
         $workflow = $workflow_service->get_workflow_by_id( $step->workflow_id );
         $workflow_info = json_decode( $workflow->wf_info );
         $first_step_info = $workflow_info->first_step[0];
         if ( array_key_exists( 'condition_group', $first_step_info ) && ! empty( $first_step_info->condition_group ) ) {
            $sel_cond_group = $first_step_info->condition_group;
         }
      } else { // if the post is already in a workflow, then simply get condition group from the step_info
         $workflow_service = new OW_Workflow_Service();
         $step = $workflow_service->get_step_by_id( $step_id );
         $step_info = json_decode( $step->step_info );

         if ( array_key_exists( 'condition_group', $step_info ) && ! empty( $step_info->condition_group ) ) {
            $sel_cond_group = $step_info->condition_group;
         }
      }

      return $sel_cond_group;
   }

   /**
    * Get all condition groups where post status = publish
    * @global object $wpdb
    * @return object
    * @since 1.0
    */
   public function get_all_condition_groups() {
      global $wpdb;
      $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}posts
      	WHERE post_type LIKE '%s' AND post_status LIKE '%s'", 'ow-condition-group', 'publish' ) );
      return $results;
   }

   public function validate_condition_group_for_step( $validation_result, $workflow_action_params ) {
      global $wpdb, $post;

      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );

      if ( ! current_user_can( 'ow_submit_to_workflow' ) &&  ! current_user_can( 'ow_sign_off_step' ) ) {
      	wp_die( __( 'You are not allowed to submit to workflow/sign off from workflow.' ) );
      }    
       
      if ( get_option( 'oasiswf_checklist_action' ) === 'by_pass_the_checklist' ) {
         return $validation_result;
      }

      $post_id = intval( $workflow_action_params["post_id"] );

      $step_id = "";
      if ( isset( $workflow_action_params["step_id"] ) ) {
         $step_id = intval( $workflow_action_params["step_id"] );
      }

      $step_decision =  '';
      if( isset( $workflow_action_params["step_decision"] ) ){
         $step_decision = sanitize_text_field( $workflow_action_params["step_decision"] );
      }

      // if the history_id is not null, it indicates that the post is in some workflow.
      // So, the step_id from the $workflow_action_params is essentially the next step_id and NOT current step_id
      // Use history to get the current step_id
      $ow_history_service = new OW_History_Service();
      $history_id = $workflow_action_params['history_id'];
      if ( ! empty( $history_id ) ){
         // get the current step_id from the history
         $history_details = $ow_history_service->get_action_history_by_id( $history_id );
         $step_id = $history_details->step_id;
      }

      $associated_condition_group = $this->get_condition_group_for_step( $post_id, $step_id );

      if( empty( $associated_condition_group ) ) {
         return $validation_result;
      }
     
      // don't evaluate the conditions if the user signed off as "rejected" or "unable to complete"
      // return an empty validation message
      if( ! empty( $step_decision ) && $step_decision == "unable" ){
         return $validation_result;
      }

      // validate the context condition
      $context_messages = (array) $this->validate_context_condition( $post_id, $associated_condition_group, $workflow_action_params );
      $validation_result = array_merge( $validation_result, $context_messages );

      // validate the contains condition
      $contain_messages = (array) $this->validate_contain_condition( $post_id, $associated_condition_group, $workflow_action_params );
      $validation_result = array_merge( $validation_result, $contain_messages );

      // validate pre publish checklist conditions
      $pre_publish_checklist_messages = (array) $this->validate_pre_publish_condition( $post_id, $associated_condition_group, $workflow_action_params );
      $validation_result = array_merge( $validation_result, $pre_publish_checklist_messages );

      return $validation_result;
   }

   /**
    * Returns the word count of HTML string
    *
    * @param string $string
    * @return integer - count of words
    * @since 1.0
    */
   private function get_word_count( $string ) {
      // ----- remove HTML TAGs -----
      $string = preg_replace( '/<[^>]*>/', ' ', trim( $string ) );

      // ----- remove control characters -----
      $string = str_replace( "\r", '', $string );    // --- replace with empty space
      $string = str_replace( "\n", ' ', $string );   // --- replace with space
      $string = str_replace( "\t", ' ', $string );   // --- replace with space
//       $string = preg_replace( '/[^a-zA-Z0-9\-\']+/', ' ', $string ); // replace special characters like :+- etc with space

      // split the string by whitespace and count the results
      $results = preg_split('/\s+/', trim( $string ));
      if (empty( $results[0] ) ) {
      	return 0;
      }
      else {
      	return count( $results );
      }

   }
   
   /**
    * Returns the letters count of string
    *
    * @param string $string
    * @return integer - count of letters
    * @since 1.5
    */   
   private function get_character_count( $string ) {
      
      // ----- remove HTML TAGs -----
      $string = preg_replace( '/<[^>]*>/', ' ', trim( $string ) );
      // ----- remove control characters -----
      $string = str_replace( "\r", '', $string ); 
      $string = str_replace( "\n", '', $string ); 
      $string = str_replace( "\t", '', $string );  
      $string = str_replace( " ", '', $string ); // replace space with no space
      $string = preg_replace( '/[^a-zA-Z0-9\-\']+/', '', $string ); // replace special characters like :+- etc with no space
      //count total characters
      $results = strlen($string);
      if ( empty( $results ) ) {
      	return 0;
      }
      else {
      	return $results;
      }
      
   }

   /**
    * Validates "ow_containing_attribute_meta" - Contain Conditions
    * @param int $post_id
    * @param int $condition_group_id
    * @param array $post_data
    *
    * @return $error_messages[] - array of error messages if any
    *
    * @since 1.0
    */
   private function validate_contain_condition( $post_id, $condition_group_id, $post_data ) {
      $contain_conditions = get_post_meta( $condition_group_id, 'ow_containing_attribute_meta', true );
      $count = 0;
      if ( ! empty ( $contain_conditions ) ) {
         $count = count( $contain_conditions );
      }

      $error_messages = array();

      if( $count > 0 ) {
         for ( $index = 0; $index < $count; $index ++ ) {
            // check if condition is applicable to current post type
            // if condition_post_type is -1, then the condition is applied to all post types
            $condition_post_type = $contain_conditions[$index]['post_type'];
            if( $condition_post_type !== "-1" && $condition_post_type !== get_post_type( $post_id ) ) {
               continue;
            }

            // check if the condition is a required condition. If not required, ignore it.
            if( $contain_conditions[$index]['required'] !== 'yes' ) {
               continue;
            }


            $contain_attr = $contain_conditions[$index]['contain_condition'];
            $expected_taxonomy_count = $contain_conditions[$index]['taxonomy_count'];
            $taxonomy = $contain_conditions[$index]['taxonomy'];
            $post = get_post( $post_id );

            // since we will not get post tags until user save the post
            // so get the taxonomy value from $post_data array
            switch ( $taxonomy ) {
               case 'featured_image':
                  global $wpdb;
                  // see if featured image is attached or not
                  $taxonomy_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$wpdb->prefix}postmeta WHERE post_id = '%d' AND meta_key LIKE '_thumbnail_id'", $post_id ) );
                  break;
               case 'link':
                  $taxonomy_count = preg_match_all( '/\\<a href="(.*?)\\">/', $post_data['post_content'], $matches );
                  $image_link_count = preg_match_all( '~<a href="([^"]++)"><img ~', $post_data['post_content'], $matches );
                  // images also have href link, so we need to minus those to count the actual links
                  $taxonomy_count = $taxonomy_count - $image_link_count;

                  break;
               case 'image':
                  $taxonomy_count = preg_match_all( '~<img [^\>]*\ />~', $post_data['post_content'], $matches );
                  break;
               default :
                  $taxonomy_count = $post_data[$taxonomy];
                  break;
            }
            $taxonomy_type = OW_Editorial_Checklist_Utility::instance()->get_taxonomy_types();
            $taxonomy_type = $taxonomy_type[$taxonomy];

            if( $taxonomy == 'post_tag' || $taxonomy == 'category' ||
                    $taxonomy == 'image' || $taxonomy == 'featured_image' || $taxonomy == 'link' ) {
               switch ( $contain_attr ) {
                  case 'contain_at_least':
                     if( $taxonomy_count < $expected_taxonomy_count ) {
                        $error_messages[] = sprintf( __( 'The post should have atleast %d %s but currently has %d %s.', 'oweditorialchecklist' ), $expected_taxonomy_count, $taxonomy_type, $taxonomy_count, $taxonomy_type );
                     }
                     break;
                  case 'not_contain_more_than':
                     if( $taxonomy_count > $expected_taxonomy_count ) {
                        $error_messages[] = sprintf( __( 'The post should not contain more than %d %s but currently has %d %s.', 'oweditorialchecklist' ), $expected_taxonomy_count, $taxonomy_type, $taxonomy_count, $taxonomy_type );
                     }
                     break;
               }
            }
         }
      }

      return $error_messages;
   }

   /**
    * Validates "ow_context_attribute_meta" - Context Conditions
    * @param int $post_id
    * @param int $condition_group_id
    * @param array $post_data
    *
    * @return $error_messages[] - array of error messages if any
    *
    * @since 1.0
    */
   private function validate_context_condition( $post_id, $condition_group_id, $post_data ) {
      $context_conditions = get_post_meta( $condition_group_id, 'ow_context_attribute_meta', true );
     
      $count = 0;
      if ( ! empty ( $context_conditions ) ) {
         $count = count( $context_conditions );
      }

      $error_messages = array();

      if( $count > 0 ) {
         for ( $index = 0; $index < $count; $index ++ ) {

            // check if the condition is a required condition. If not required, ignore it.
            if( $context_conditions[$index]['required'] !== 'yes') {
               continue;
            }

            $context_attr = $context_conditions[$index]['context_attribute']; //title, excerpt, content etc
            $contain_attr = $context_conditions[$index]['contain_condition'];
            $expected_word_count = $context_conditions[$index]['word_count'];
            $count_type = $context_conditions[$index]['count_type'];

            $post = get_post( $post_id );
            $excerpt = $post->post_excerpt;
            // Pages and some custom post types may not have excerpt field
            if( $context_attr == 'post_excerpt' && empty( $excerpt ) ) {
               continue;
            }

            $context_attr_types = OW_Editorial_Checklist_Utility::instance()->get_context_attribute_types();
            $context_attr_type = $context_attr_types[$context_attr];
            
            // hook to count the contents against specific content
            if ( has_filter( 'ow_checklist_context_attribute' ) ) {
               $content = apply_filters( 'ow_checklist_context_attribute', $post_id, $post_data );
               if ( ! empty( $content ) ) {
                  $post_data = $content;
               }
            }
            
            if ( $count_type == 'words' ) {
               $count_value = $this->get_word_count( $post_data[$context_attr] );
            } 
            
            if ( $count_type == 'letters' ) {
               $count_value = $this->get_character_count( $post_data[$context_attr] );
            }

            switch ( $contain_attr ) {
               case 'contain_at_least':
                  if( $count_value < $expected_word_count ) {
                     if ( $count_type == 'words' ) {
                        $error_messages[] = sprintf( __( '%s requires atleast %d word(s) but currently has %d word(s).', 'oweditorialchecklist' ), $context_attr_type, $expected_word_count, $count_value );
                     }
                     if ( $count_type == 'letters' ) {
                        $error_messages[] = sprintf( __( '%s requires atleast %d letter(s) but currently has %d letter(s).', 'oweditorialchecklist' ), $context_attr_type, $expected_word_count, $count_value );
                     }
                     
                  }
                  break;
               case 'not_contain_more_than':
                  if ( $count_value > $expected_word_count ) {
                     if ( $count_type == 'words' ) {
                        $error_messages[] = sprintf( __( "%s should not contain more than %d word(s) but currently has %d word(s).", 'oweditorialchecklist' ), $context_attr_type, $expected_word_count, $count_value );
                     }
                     if ( $count_type == 'letters' ) {
                        $error_messages[] = sprintf( __( "%s should not contain more than %d letter(s) but currently has %d letter(s).", 'oweditorialchecklist' ), $context_attr_type, $expected_word_count, $count_value );
                     }
                  }
                  break;
               default :
                  break;
            }
         }
      }

      return $error_messages;
   }
   
    /**
    * Validates "ow_pre_publish_meta" - pre publish conditions
    * @param int $post_id
    * @param int $condition_group_id
    * @param array $post_data
    *
    * @return $error_messages[] - array of error messages if any
    *
    * @since 1.4
    */
   private function validate_pre_publish_condition( $post_id, $condition_group_id, $post_data ) {
      
      $all_pre_publish_conditions = get_post_meta( $condition_group_id, 'ow_pre_publish_meta', true );

      $user_selected_checklist = $post_data['pre_publish_checklist'];
      $user_selected_questions = array();
      $user_selected_condition_group = "";
      foreach($user_selected_checklist as $user_selected_question) {
         $user_selected_pair = explode( "-", $user_selected_question );
         $user_selected_condition_group = $user_selected_pair[0];
         array_push( $user_selected_questions, $user_selected_pair[1] );
      }

      $count = 0;
      if ( ! empty ( $all_pre_publish_conditions ) ) {
         $count = count( $all_pre_publish_conditions );
      }

      $error_messages = array();
      $conditions_met = true;

      if( $count > 0 ) {
         for ( $index = 0; $index < $count; $index ++ ) {
            $condition_post_type = $all_pre_publish_conditions[ $index ][ 'post_type' ];

            // check if condition is applicable to current post type
            // if condition_post_type is -1, then the condition is applied to all post types
            if( $condition_post_type !== "-1" && $condition_post_type !== get_post_type( $post_id ) ) {
               continue;
            }

            if( $all_pre_publish_conditions[ $index ][ 'required' ] !== 'yes' ) {
               continue;
            }

            $question_id = $all_pre_publish_conditions[ $index ][ 'question_id' ];
            // if required condition and not selected in user sign off checklist show error message
            if( ! in_array( $question_id, $user_selected_questions ) ) {
               $conditions_met = false;
            }     
         }       
      }

      // if not all checklist conditions are checked, display an error.
      if ( ! $conditions_met ) {
         $error_messages[] = sprintf( __( "You must check all the Pre-Publish Checklist items to complete your task." ) );
      }
      return $error_messages;
   }

     /*
      * Example code for ow_checklist_context_attribute filter
      */
//   public function custom_contents_for_checklist( $post_id, $post_data ) {
//      // like I don't want to count the word 'attribute' from the post content
//      $post_data['post_content'] = str_replace('attribute', '', $post_data['post_content']);
//      return $post_data;
//   }
   
}

return new OW_Editorial_Checklist_Service();
?>