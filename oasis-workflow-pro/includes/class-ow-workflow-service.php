<?php
/**
 * Service class for Workflow CRUD operations
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/**
 * OW_Workflow_Service Class
 *
 * @since 2.0
 */

class OW_Workflow_Service {

	/**
	 * Set things up.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_create_new_workflow', array( $this, 'create_new_workflow' ) );
      add_action( 'wp_ajax_validate_workflow_name', array( $this, 'validate_workflow_name' ) );
      
      add_action( 'wp_ajax_get_first_step', array( $this, 'get_first_step' ) );
		add_action( 'wp_ajax_save_workflow_step', array( $this, 'save_workflow_step' ) );
		add_action( 'wp_ajax_copy_step', array( $this, 'copy_step' ) );
      
      add_action( 'wp_ajax_load_step_info', array( $this, 'load_step_info' ) );	
      
      add_action( 'wp_ajax_delete_workflow_confirmation', array( $this, 'delete_workflow_confirmation' ) );  
      add_action( 'wp_ajax_delete_workflow', array( $this, 'delete_workflow' ) );  
	}
   
   
   /**
	 * AJAX function - Creates new workflow
	 * Checks for existing workflow with the same name before creating the workflow
	 *
	 * @since 2.0
	 */
	public function create_new_workflow() {
		global $wpdb;
         
      // nonce check
		check_ajax_referer( 'owf_workflow_create_nonce', 'security' );
      
      // capability check
		if ( ! current_user_can( 'ow_create_workflow' ) ) {
			wp_die( __( 'You are not allowed to create workflows.' ) );
		}

      /* sanitize incoming data */
		$workflow_name = sanitize_text_field( $_POST["name"] );
      
      // check if a workflow with this name already exists?
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT count(*) count FROM " . OW_Utility::instance()->get_workflows_table_name() . " WHERE LOWER(name) = %s", $workflow_name ) );
		if ( $result->count > 0 ) { // we found an identical named workflow
			wp_send_json_error();
		}

		// continue saving the data to create a new workflow
		$data = array(
				'name' => stripcslashes( $_POST["name"] ),
				'description' => stripcslashes( $_POST["description"] ),
				'create_datetime' => current_time( 'mysql' ),
				'update_datetime' => current_time( 'mysql' )
		);
		$workflow_table = OW_Utility::instance()->get_workflows_table_name( );
		$new_id = OW_Utility::instance()->insert_to_table( $workflow_table, $data ) ;
		wp_send_json_success( $new_id ) ;
	}

   /**
	 * AJAX function - Validate workflow name for duplicate
	 *
	 * @since 2.0
	 */

	public function validate_workflow_name() {
		global $wpdb;
      // nonce check
		check_ajax_referer( 'owf_workflow_create_nonce', 'security' );

      /* sanitize incoming data */
		$workflow_name = sanitize_text_field( $_POST["name"] );
      
      // check if a workflow with this name already exists?
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT count(*) count FROM " . OW_Utility::instance()->get_workflows_table_name() . " WHERE LOWER(name) = %s", $workflow_name ) );
		if ( $result->count > 0 ) { // we found an identical named workflow
         wp_send_json_error();
		} else {
			wp_send_json_success();
		}
	}
   
   /**
	 * AJAX function - Get the first step in the workflow
	 *
	 * @return json string OR "wrong"
	 *
	 * @since 2.0
	 */
	public function get_first_step() {
      // nonce check
		check_ajax_referer( 'owf_signoff_ajax_nonce', 'security');
      
      /* sanitize incoming data */
		$workflow_id = intval( sanitize_text_field( $_POST["wf_id"] ) );

		$steps = $this->get_first_step_internal( $workflow_id );
		if ( $steps != null ) {
			wp_send_json_success( $steps );
		} else {
			wp_send_json_error();
		}
	}
   
   /**
	 * AJAX function - Saves workflow step
	 *
	 * @since 2.0
	 */
	public function save_workflow_step()
	{
		global $wpdb ;

		// nonce check
		check_ajax_referer( 'owf_workflow_create_nonce', 'security');
      
      // capability check
		if ( ! current_user_can( 'ow_edit_workflow' ) ) {
			wp_die( __( 'You are not allowed to create/edit workflows.' ) );
		}

		/* sanitize incoming data */
		$wf_id = isset( $_POST["wf_id"] ) ? intval( sanitize_text_field( $_POST["wf_id"] )) : "";
		$step_id = isset( $_POST["step_id"] ) ? sanitize_text_field( $_POST["step_id"] ) : "";
		$step_info = isset( $_POST["step_info"] ) ? sanitize_text_field( $_POST["step_info"] ) : "";
      // FIXED: Do not use sanitize_text_field or stripcslashes to keep user formated message
		$process_info = isset( $_POST["process_info"] ) ? $_POST["process_info"] : "";

		$workflow_step = new OW_Workflow_Step( );
		$workflow_step->ID = $step_id;
		$workflow_step->workflow_id = $wf_id;
		$workflow_step->step_info = trim( $step_info );
		$workflow_step->process_info = trim( $process_info );

		$workflow_service = new OW_Workflow_Service( );
		$step_id = $workflow_service->upsert_workflow_step( $workflow_step );

		wp_send_json_success( $step_id ) ;
	}
   
   /**
	 * AJAX function - Copies the workflow step
	 *
	 * @since 2.0
	 */

	public function copy_step()
	{
		// check nonce
		check_ajax_referer( 'owf_workflow_create_nonce', 'security' );

		if ( ! current_user_can( 'ow_edit_workflow' ) ) {
			wp_die( __( 'You are not allowed to create/edit workflows.' ) );
		}

		$step_id = intval( sanitize_text_field( $_POST["copy_step_id"] ) );

		$step = $this->get_step_by_id( $step_id ) ;
		if ( $step ) {
			$data = array(
				'step_info' => $step->step_info,
				'process_info' => $step->process_info,
				'workflow_id' => $step->workflow_id,
				'create_datetime' => current_time('mysql'),
				'update_datetime' => current_time('mysql')
			);

			$step_table = OW_Utility::instance()->get_workflow_steps_table_name();
			$new_step_id = OW_Utility::instance()->insert_to_table( $step_table, $data );
			if ( is_numeric( $new_step_id ) ) {
				wp_send_json_success( $new_step_id ) ;
			}
		}
      wp_send_json_error();
	}
   
   /**
	 * AJAX function - To show the step info popup
	 *
	 * @since 2.0
	 */
	public function load_step_info() {
		require_once( OASISWF_PATH . "includes/pages/subpages/step-info-content.php" );
	}
   
   /**
	 * AJAX function - To show the delete workflow confirmation popup
	 *
	 * @since 4.3
	 */
	public function delete_workflow_confirmation() {
      // nonce check
      check_ajax_referer( 'workflow_delete_nonce', 'security' );

      // check capability 
      if ( ! current_user_can( 'ow_delete_workflow' ) ) {
         wp_die( __( 'You are not allowed to delete workflows.', 'oasisworkflow' ) );
      }
      
		ob_start();
      include_once OASISWF_PATH . 'includes/pages/subpages/delete-workflow.php';
      $result = ob_get_contents();
      ob_get_clean();
      wp_send_json_success( htmlentities( $result ) );
	}
   
   /**
	 * AJAX function - Delete the selected workflow
	 *
	 * @since 2.0
	 */
	public function delete_workflow()
	{
		global $wpdb;

		// nonce check
      check_ajax_referer( 'workflow_delete_nonce', 'security' );

      // check capability 
      if ( ! current_user_can( 'ow_delete_workflow' ) ) {
         wp_die( __( 'You are not allowed to delete workflows.', 'oasisworkflow' ) );
      }

		// sanitize the input
		$wf_id = intval( sanitize_text_field( $_POST["workflow_id"] ) );

		// first delete all the steps
		$this->delete_workflow_steps( $wf_id ) ;

		// now delete the workflow
		$result = $wpdb->get_results( $wpdb->prepare( "DELETE FROM " . OW_Utility::instance()->get_workflows_table_name() . " WHERE ID = %d", $wf_id ) );
      
     // hook to do something after workflow is deleted
      do_action( 'owf_workflow_delete', $wf_id );

		wp_send_json_success();
	}

   
   /**
	 * for internal use only
	 * get_first_step in workflow
	 *
	 * @since 2.0
	 */
	public function get_first_step_internal( $workflow_id ) {

		$workflow_id = intval( sanitize_text_field( $workflow_id ) );

		$result = $this->get_workflow_by_id( $workflow_id );
		$wf_info = json_decode( $result->wf_info );
		$steps = $this->get_first_and_last_steps( $wf_info );

		if ( $wf_info->first_step && count( $wf_info->first_step ) == 1 ) {
         
         $first_step = $wf_info->first_step[0];
         if ( is_object( $first_step ) ) {
            $first_step = $first_step->step;
         }
         
			$step_db_id = $this->get_gpid_dbid( $wf_info, $first_step );
			$step_lbl = $this->get_gpid_dbid( $wf_info, $first_step, "lbl" );
			$process = $this->get_gpid_dbid( $wf_info, $first_step, "process" );
			unset( $steps["first"] );
			$steps["first"][] = array( $step_db_id, $step_lbl, $process );
			return $steps;
		} else {
			return null;
		}
	}
   
   
   /**
	 * get first and last steps from the workflow. There could be more than 1
	 *
	 * @param mixed $wf_info workflow information
	 * @return mixed array of steps containing all the first and last steps
	 *
	 * @since 2.0
	 */
	public function get_first_and_last_steps( $wf_info ) {
		if ( $wf_info->steps ) {
			$first_step = array();
			$last_step = array();

			foreach ( $wf_info->steps as $k => $v ) {
				if ( $v->fc_dbid == "nodefine" ) {
					return "nodefine" ;
				}
				$step_structure = $this->get_step_structure( $wf_info, $v->fc_dbid, "target" );
				if ( isset($step_structure["success"] ) && $step_structure["success"] ) {
					continue ;
				}
				$first_step[] = array( $v->fc_dbid, $v->fc_label, $v->fc_process );
			}

			foreach ( $wf_info->steps as $k => $v ) {
				if ( $v->fc_dbid == "nodefine" ) {
					return "nodefine" ;
				}
				$step_structure = $this->get_step_structure( $wf_info, $v->fc_dbid, "source" );
				if ( isset( $step_structure["success"] ) && $step_structure["success"] ) {
					continue ;
				}
				$last_step[] = array( $v->fc_dbid, $v->fc_label, $v->fc_process );
			}

			$steps["first"] = $first_step ;
			$steps["last"] = $last_step ;
		}

		return $steps ;
	}
   
   /**
	 * get step variable info
	 *
	 * @param mixed|int $wf_info could be workflow info OR workflow ID
	 * @param int $step_id id of the step
	 * @param mixed|string $return_info what piece of info do you want to return
	 * @retun mixed - step variable info
	 *
	 * @since 2.0
	 */
	public function get_gpid_dbid( $wf_info, $step_id, $return_info = "" ) {
		if ( is_object( $wf_info ) ) {
			$wf_steps = $wf_info->steps;
		} else {
			if ( is_numeric( $wf_info ) ) { // looks like the user passed the id of the workflow
				$workflow = $this->get_workflow_by_id( $wf_info );
				$info = json_decode( $workflow->wf_info );
				$wf_steps = $info->steps;
			} else {
				$info = json_decode( $wf_info );
				$wf_steps = $info->steps;
			}
		}

		if ( $wf_steps ) {
			if ( is_numeric( $step_id ) ) {
				foreach ( $wf_steps as $k => $v ) {
					if ( $step_id == $v->fc_dbid ) {
						if ( $return_info == "lbl" )
							return $v->fc_label;
						if ( $return_info == "process" )
							return $v->fc_process;

						return $v->fc_addid;
					}
				}
			} else {
				if ( $return_info == "lbl" )
					return $wf_steps->$step_id->fc_label;
				if ( $return_info == "process" )
					return $wf_steps->$step_id->fc_process;
				return $wf_steps->$step_id->fc_dbid;
			}
		}

		return false;
	}

   
   /**
	 * Update/Insert workflow step
	 *
	 * @param OW_Workflow_Step step object
	 * @return int $step_id
	 */
	public function upsert_workflow_step( OW_Workflow_Step $workflow_step ) {
		global $wpdb;

		// first sanitize the data
		$workflow_step->sanitize_data( );

		if ( ! current_user_can( 'ow_create_workflow' ) ) {
			wp_die( __( 'You are not allowed to create/edit workflows.' ) );
		}

		$step_id = 0;

		$workflow_step_table = OW_Utility::instance()->get_workflow_steps_table_name();
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_workflow_steps_table_name() . " WHERE ID = %d" , $workflow_step->ID ));

		if ( $result ) { // we are basically updating an existing step
			$result = $wpdb->update(
					$workflow_step_table,
					array(
							'step_info' => stripcslashes( $workflow_step->step_info ),
							'process_info' => $workflow_step->process_info,
							'update_datetime' => current_time('mysql')
					),
					array( 'ID' =>  $workflow_step->ID )
			);
			$step_id = $workflow_step->ID;
		} else { // we are inserting a new step
			$result = $wpdb->insert(
					$workflow_step_table,
					array(
							'step_info' => stripcslashes( $workflow_step->step_info ),
							'process_info' => $workflow_step->process_info,
							'create_datetime' => current_time('mysql'),
							'update_datetime' => current_time('mysql'),
							'workflow_id' => $workflow_step->workflow_id,
					)
			);
			$insert_row = $wpdb->get_row("SELECT max(ID) as maxid FROM " . OW_Utility::instance()->get_workflow_steps_table_name());
			$step_id = $insert_row->maxid ;
		}

		return $step_id;
	}  
   
	/**
	 * Get workflow array for the given workflow ids
	 *
	 * @param array $workflow_ids
	 * @return mixed List of OW_Workflow
	 *
	 * @since 3.5
	 */
	public function get_multiple_workflows_by_id( $workflow_ids ) {
		global $wpdb;

		// sanitize the values
		$workflow_ids = array_map( 'intval', $workflow_ids );

		$int_place_holders = array_fill( 0, count( $workflow_ids ), '%d' );
		$place_holders_for_workflow_ids = implode( ",", $int_place_holders );

		$sql = "SELECT * FROM " . OW_Utility::instance()->get_workflows_table_name() .
			" WHERE ID IN (" . $place_holders_for_workflow_ids . ")";

		$workflows = array();
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $workflow_ids ) );
		foreach ( $results as $result ) {
			$workflow = $this->get_workflow_from_result_set ( $result );
			array_push( $workflows, $workflow );
		}

		return $workflows;
	}
   
   /**
	 * saves the workflow
	 *
	 * @since 2.0
	 */

	public function save_workflow()
	{
		global $wpdb;

		if ( ! wp_verify_nonce( $_POST['owf_workflow_create_nonce'], 'owf_workflow_create_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'ow_edit_workflow' ) ) {
			wp_die( __( 'You are not allowed to create/edit workflows.' ) );
		}

		// sanitize the input
		$workflow_id = intval( sanitize_text_field( $_POST["wf_id"] ));
		$title = sanitize_text_field( $_POST["define-workflow-title"] ) ;
		$description = sanitize_text_field( $_POST["define-workflow-description"] );
		$wf_graphical_info = stripcslashes( $_POST["wf_graphic_data_hi"] ) ;
		$start_date = '';
		$end_date = '';
		if ( isset( $_POST["start-date"] ) && ! empty( $_POST["start-date"] ) ) {
			$start_date = OW_Utility::instance()->format_date_for_db_wp_default( sanitize_text_field( $_POST["start-date"] ) ) ;
		}
		if ( isset( $_POST["end-date"] ) && ! empty( $_POST["end-date"] ) ) {
			$end_date = OW_Utility::instance()->format_date_for_db_wp_default( sanitize_text_field( $_POST["end-date"] ) ) ;
		}

		// wf additional info

		// does it apply to new posts, revised posts or both
		$wf_for_new_posts = ( isset( $_POST["new_post_workflow"] ) && sanitize_text_field( $_POST["new_post_workflow"] ) ) ? 1 : 0;
		$wf_for_revised_posts = ( isset( $_POST["revised_post_workflow"] ) && sanitize_text_field( $_POST["revised_post_workflow"] ) ) ? 1 : 0;

		// who can submit to this workflow
		$wf_for_roles = array();
		if ( isset( $_POST["wf_for_roles"] ) && count( $_POST["wf_for_roles"] ) > 0 )
		{
			$selected_options = $_POST["wf_for_roles"];
			// sanitize the values
			$selected_options = array_map( 'esc_attr', $selected_options );

			foreach ($selected_options as $selected_option)
			{
				array_push($wf_for_roles, $selected_option);
			}
		}

		// applicable post types for the workflow
		$wf_for_post_types = array();
		if ( isset( $_POST["wf_for_post_types"] ) && count( $_POST["wf_for_post_types"] ) > 0 )
		{
			$selected_options = $_POST["wf_for_post_types"];
			// sanitize the values
			$selected_options = array_map( 'esc_attr', $selected_options );

			foreach ($selected_options as $selected_option)
			{
				array_push($wf_for_post_types, $selected_option);
			}
		}

		$wf_additional_info = array('wf_for_new_posts' => $wf_for_new_posts,
				'wf_for_revised_posts' => $wf_for_revised_posts,
				'wf_for_roles' => $wf_for_roles,
				'wf_for_post_types' => $wf_for_post_types
		);

		$workflow_table = OW_Utility::instance()->get_workflows_table_name();
		$valid = 1 ; //since we have passed validation, the workflow is valid.
		$result = $wpdb->update( $workflow_table,
				array(
						'name' => stripcslashes( trim( $title )),
						'description' => stripcslashes( $description ),
						'wf_info' => $wf_graphical_info,
						'start_date' => $start_date,
						'end_date' => $end_date,
						'is_valid' => $valid,
						'update_datetime' => current_time('mysql'),
						'wf_additional_info' =>  serialize($wf_additional_info)
				),
				array( 'ID' => $workflow_id )
		);

		// if there were any steps deleted, delete those from the DB too
		if ( $_POST["deleted_step_ids"] ){
			$deleted_steps = sanitize_text_field( $_POST["deleted_step_ids"] );
			$deleted_steps = explode( "@", $deleted_steps ) ;
			for( $i = 0; $i < count( $deleted_steps ) -1 ; $i++ )
			{
				$sql = "DELETE FROM " . OW_Utility::instance()->get_workflow_steps_table_name() . " WHERE ID = %d";
				$wpdb->get_results( $wpdb->prepare( $sql, $deleted_steps[$i] ) ) ;
			}
		}

		// if this is a revision, we need to set the end date on the previous revision as start_date (of this version) - 1
		$wf = $this->get_workflow_by_id( $workflow_id );
		$parent_id= $wf->parent_id;
		if ( $parent_id > 0 ) {
			$end_date = str_replace('-', '/', $start_date);
			$end_date = date('Y-m-d', strtotime($end_date . '-1 days'));
			$wpdb->query( $wpdb->prepare( "UPDATE $workflow_table SET end_date = '%s' WHERE ID = '%d'", $end_date, $parent_id ) );
		}

		// everything went fine, lets redirect to the workflow list page
		wp_redirect( admin_url( 'admin.php?page=oasiswf-admin' ) );
		die();

	}

	/**
	 * saves the workflow with a new version and redirects to the newly created workflow version
	 *
	 * @since 2.0
	 */
	public function save_as_new_version()
	{
		global $wpdb;

		if ( ! wp_verify_nonce( $_POST['owf_workflow_create_nonce'], 'owf_workflow_create_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'ow_create_workflow' ) ) {
			wp_die( __( 'You are not allowed to create/edit workflows.' ) );
		}
      // sanitize the input
		$workflow_id = intval( sanitize_text_field( $_POST["wf_id"] ) );
		$wf = $this->get_workflow_by_id( $workflow_id ) ;
		if ( $wf ){
			$workflow_table = OW_Utility::instance()->get_workflows_table_name();
			// update end date on selected workflow
			$parent_id = ( $wf->parent_id == 0 ) ? $wf->ID : $wf->parent_id ;
			$new_version = $this->get_next_version_number( $parent_id );
			$data = array(
					'name' => stripcslashes( trim( $wf->name ) ),
					'description' => stripcslashes( $wf->description ),
					'version' => $new_version,
					'parent_id' => $parent_id,
					'create_datetime' => current_time('mysql'),
					'update_datetime' => current_time('mysql'),
					'wf_additional_info' =>  $wf->wf_additional_info
			);

			$new_wf_id = OW_Utility::instance()->insert_to_table( $workflow_table, $data );
			$wf_info = json_decode($wf->wf_info);

			foreach ( $wf_info->steps as $k => $v )
			{
				if ( $v->fc_dbid == "nodefine" ) continue ;

				$new_fc_dbid = $this->save_step_as_new( $new_wf_id, $v->fc_dbid ) ;

				if ( $new_fc_dbid ) {
					$wf_info->steps->$k->fc_dbid = $new_fc_dbid ;
				}

			}
			$wf_info = json_encode( $wf_info ) ;

			$wpdb->update( $workflow_table,
				array(
					"wf_info" => $wf_info
				),
				array( "ID" => $new_wf_id ) ) ;

			// redirect to the newly created version
			wp_redirect( admin_url( 'admin.php?page=oasiswf-admin&wf_id=' . $new_wf_id ) );
			die();
		}
	}
   
   /**
	 * creates a copy of the workflow
	 *
	 * @since 2.0
	 */
	public function copy_workflow()
	{
		global $wpdb;

		if ( ! wp_verify_nonce( $_POST['owf_workflow_create_nonce'], 'owf_workflow_create_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'ow_create_workflow' ) ) {
			wp_die( __( 'You are not allowed to create/edit workflows.' ) );
		}
      // sanitize the input
		$workflow_id = intval( sanitize_text_field( $_POST["wf_id"] ) );
		$workflow = $this->get_workflow_by_id( $workflow_id ) ;
		if ( $workflow ) {
			$data = array(
				'name' => stripcslashes( trim( $_POST['define-workflow-title'] ) ),
				'description' => stripcslashes( $_POST['define-workflow-description'] ),
				'version' => 1, // since it's a new copy
				'parent_id' => 0,
				'start_date' => $workflow->start_date,
				'end_date' => $workflow->end_date,
				'create_datetime' => current_time('mysql'),
				'update_datetime' => current_time('mysql'),
				'wf_additional_info' =>  $workflow->wf_additional_info
			);
			$workflow_table = OW_Utility::instance()->get_workflows_table_name();
			$new_wf_id = OW_Utility::instance()->insert_to_table( $workflow_table, $data );

			// now create copy of the steps
			$wf_info = json_decode( $workflow->wf_info );
			foreach ($wf_info->steps as $k => $v)
			{
				if ( $v->fc_dbid == "nodefine" ) {
               continue ;
            }
				$new_fc_dbid = $this->save_step_as_new( $new_wf_id, $v->fc_dbid ) ;
				if ( $new_fc_dbid ) {
					$wf_info->steps->$k->fc_dbid = $new_fc_dbid ;
				}
			}

			// update the workflow record with step information
			$wf_info = json_encode( $wf_info ) ;
			$wpdb->update( $workflow_table,
					array(
						"wf_info" => $wf_info
					),
					array( "ID" => $new_wf_id ) ) ;

			// redirect the page to the newly copied workflow
			wp_redirect( admin_url( 'admin.php?page=oasiswf-admin&wf_id=' . $new_wf_id ) );
			die();
		}
	}

	/**
	 * If there are posts in workflow, the workflow is not editable
	 *
	 * @param int $wf_id ID of the workflow
	 * @return bool true if editable
	 *
	 * @since 2.0
	 *
	 */
	public function is_workflow_editable( $wf_id )
	{
		global $wpdb;
      
      // sanitize the input
		$wf_id = intval( sanitize_text_field( $wf_id ) );

		$post_count = $this->get_post_count_in_workflow( $wf_id ) ;
		if ( $post_count ) { // if there are post then the workflow is not editable
			return false;
		}

		return true ;
	}
   
   /**
	 * get list of the workflows
	 *
	 * @param string $action, possible values are "all", "active" and "inactive", if null, it's considered as all.
	 *
	 * @since 2.0
	 */
	public function get_workflow_list( $action = null ) {
		global $wpdb;

		// sanitize the input
		$action = sanitize_text_field( $action );

      // use white list approach to set order by clause
      $order_by = array(
          'start_date' => 'start_date',
          'end_date' => 'end_date',
          'title' => 'name'
      );

      $sort_order = array(
          'asc' => 'ASC',
          'desc' => 'DESC',
      );

      // default order by
      $order_by_column = " ORDER BY name asc "; // default order by column
      // if user provided any order by and order input, use that
      if ( isset( $_GET['orderby'] ) && $_GET['orderby'] ) {
         // sanitize the input
         $user_provided_order_by = sanitize_text_field( $_GET['orderby'] );
         $user_provided_order = sanitize_text_field( $_GET['order'] );
         if ( array_key_exists( $user_provided_order_by, $order_by ) ) {
            $order_by_column = " ORDER BY " . $order_by[$user_provided_order_by] . " " . $sort_order[$user_provided_order];
         }
      }

		$workflows = array();

		$current_time = date("Y-m-d") ;
		if ( $action == "all"  || empty( $action ) ) {
			$sql = "SELECT * FROM " . OW_Utility::instance()->get_workflows_table_name() . $order_by_column ;
		}

		if ( $action == "active" ) {//only active workflows
			$sql = "SELECT * FROM " . OW_Utility::instance()->get_workflows_table_name() .
				" WHERE start_date <= '$current_time' AND ( end_date >= '$current_time' OR end_date is NULL OR end_date = '0000-00-00' ) AND is_valid = 1" .
				$order_by_column ;
		}

		if ( $action == "inactive" ) { // only inactive workflows
			$sql = "SELECT * FROM " . OW_Utility::instance()->get_workflows_table_name() .
				" WHERE NOT(start_date <= '$current_time' AND ( end_date >= '$current_time' OR end_date is NULL OR end_date = '0000-00-00' ) AND is_valid = 1)" .
				$order_by_column ;
		}

		$workflow_result_set = $wpdb->get_results( $sql ) ;

      if ( $workflow_result_set ){
         foreach ( $workflow_result_set as $result ) {
            $result->post_count = $this->get_post_count_in_workflow($result->ID);
         }
      }

      // sort the result set via post/pages in workflow
      if ( isset( $_GET['orderby'] ) && 'post_count' === $_GET['orderby'] && isset( $_GET['order'] )  ) {
         usort( $workflow_result_set, function( $obj1, $obj2 ) {

            if ( isset( $obj1->post_count ) && isset( $obj2->post_count ) ) {
               if ('desc' === $_GET['order']){
               	return $obj1->post_count < $obj2->post_count ? 1 : -1; // need to switch 1 and -1
               }
               else {
                  return $obj1->post_count > $obj2->post_count ? 1 : -1;
               }
            } else {
               return 1;
            }
         } );

      }
		foreach ( $workflow_result_set as $result ) {
			$workflow = new OW_Workflow( );
			$workflow->ID = $result->ID;
			$workflow->name = $result->name;
			$workflow->description = $result->description;
			$workflow->version = $result->version;
			$workflow->parent_id = $result->parent_id;
			$workflow->start_date = $result->start_date;
			$workflow->end_date = $result->end_date;
			$workflow->wf_info = $result->wf_info;
			$workflow->is_valid = $result->is_valid;
			$workflow->create_datetime = $result->create_datetime;
			$workflow->update_datetime = $result->update_datetime;
			$workflow->wf_additional_info = $result->wf_additional_info;
         $workflow->post_count = $result->post_count;

			// add to the array
			array_push( $workflows, $workflow );
		}

		return $workflows;
	}
   
   
	/**
	 * get the count of the posts currently in the workflow
	 *
	 * @param int $wf_id ID of the workflow
	 * @return int number of posts in the workflow
	 *
	 * @since 2.0
	 */

	public function get_post_count_in_workflow( $wf_id ) {
		global $wpdb;
      // sanitize the input
      $wf_id = intval( $wf_id );

      // try to get it from cache
      $cache_key = md5( 'ow_worklows_post_count_' . $wf_id );
      $post_count = wp_cache_get( $cache_key, 'ow-cache-workflows' );

      if ( $post_count === false ) {
         $sql = "SELECT DISTINCT(A.post_id) FROM
					(SELECT AA.post_id, BB.workflow_id FROM
							(SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE action_status = 'assignment') AS AA
							LEFT JOIN " . OW_Utility::instance()->get_workflow_steps_table_name() . " AS BB
							ON AA.step_id = BB.ID) as A
							WHERE A.workflow_id = %d";
         $result = $wpdb->get_results( $wpdb->prepare( $sql, $wf_id ) );
         $post_count = count( $result );

         // non existent cache, so, lets add/set it to cache
         wp_cache_set( $cache_key, $post_count, 'ow-cache-workflows' );
      }

		return absint( $post_count );
	}


	/**
	 * Get workflow count by status (All, Active, Inactive)
	 *
	 * @return mixed, object with all the counts
	 *
	 * @since 2.0 initial version
	 * @since 3.3 fixed issue with inactive workflows when end_date is empty
	 */
	public function get_workflow_count_by_status()
	{
		global $wpdb;
		$currenttime = date("Y-m-d") ;

		// get all the workflows
		// also get all the active workflows ( end date is null OR end date is greater than today AND the workflow is valid)
		$sql = "SELECT
					SUM(ID > 0) as wf_all,
					SUM((start_date <= %s AND end_date <> '0000-00-00' AND end_date >= %s AND is_valid = 1)
						  OR
						 (start_date <= %s AND end_date = '0000-00-00' AND is_valid = 1)) as wf_active
					FROM " . OW_Utility::instance()->get_workflows_table_name();
		$wf_count_map = $wpdb->get_row( $wpdb->prepare( $sql, array( $currenttime, $currenttime, $currenttime ))) ;

		// find the count of inactive workflows by subtracting active workflows from all workflows.
		$wf_count_map = (array)$wf_count_map;
		$wf_count_map['wf_inactive'] = $wf_count_map['wf_all'] - $wf_count_map['wf_active'];
		$wf_count_map = (object)$wf_count_map;

		return $wf_count_map;
	}
   
   
   /**
	 * get the process outcome given the from and to step.
	 *
	 * @param int $from_step id of the step
	 * @param int $to_step id of the step
	 *
	 * @return string step outcome success or failure
	 *
	 */
	public function get_process_outcome($from_step, $to_step)
	{
		$from_steps = $this->get_process_steps( $from_step ) ;
		if ( $from_steps && isset( $from_steps["success"] ) && $from_steps["success"] )
		{
			foreach ( $from_steps["success"] as $k => $v ) {
				if ( $k == $to_step )return "success" ;
			}
		}

		if ( $from_steps && $from_steps["failure"] )
		{
			foreach ( $from_steps["failure"] as $k => $v ) {
				if ( $k == $to_step ) return "failure" ;
			}
		}
	}
   
   /**
	 * get the entire step structure as laid out in the workflow graphic
	 *
	 * @param mixed $wf_info workflow information
	 * @param int $step_id id of the step
	 * @param string $direction source or target
	 *
	 * @return null|mixed step information
	 *
	 */
	public function get_process_steps( $step_id, $direction = "source" ) {
		$step = $this->get_step_by_id( $step_id );
		if ( $step ) {
			$workflow = $this->get_workflow_by_id( $step->workflow_id );
			if ( $workflow ) {
				$wf_info = json_decode( $workflow->wf_info );
				return $this->get_step_structure( $wf_info, $step_id, $direction);
			}
		}
		return null;
	}
   
   public function get_step_by_id( $step_id ) {

		global $wpdb;

		// sanitize the input
		$step_id = intval( sanitize_text_field( $step_id ) );

      // try to get it from cache
      $cache_key = md5( 'ow_worklow_steps_' . $step_id );
      $workflow_step = wp_cache_get( $cache_key, 'ow-cache-workflows' );

      if( $workflow_step === false ) {

         $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_workflow_steps_table_name() . " WHERE ID = %d", $step_id ) );
         if ( ! $result ) {
            return "";
         }
         $workflow_step                  = new OW_Workflow_Step();
         $workflow_step->ID              = $result->ID;
         $workflow_step->process_info    = $result->process_info;
         $workflow_step->step_info       = $result->step_info;
         $workflow_step->workflow_id     = $result->workflow_id;
         $workflow_step->create_datetime = $result->create_datetime;
         $workflow_step->update_datetime = $result->update_datetime;

         // non existent cache, so, lets add/set it to cache
         wp_cache_set( $cache_key, $workflow_step, 'ow-cache-workflows' );
      }

		return $workflow_step;

	}
   
	/**
	 * check is the workflow is applicable
	 *
	 * @since 2.0
	 */
	public function is_workflow_applicable( $wf_id, $post_id ) {
      // sanitize th input
		$wf_id = intval( sanitize_text_field( $wf_id ) );
		$post_id = intval( sanitize_text_field( $post_id ) );

		$workflow = $this->get_workflow_by_id( $wf_id );

		// valid date check
		$start_date_timestamp = OW_Utility::instance()->get_date_int( $workflow->start_date );
		$end_date_timestamp = OW_Utility::instance()->get_date_int( $workflow->end_date );
		$current_date_timestamp = OW_Utility::instance()->get_date_int();
		if ( $start_date_timestamp > $current_date_timestamp ) {
			return false; // filter-1
      }

		// If end date is not provided then workflow will be valid
		if ( $workflow->end_date != '0000-00-00' ) {
			if ( $end_date_timestamp < $current_date_timestamp ) {
				return false;  // filter-2
         }
		}

		$additional_info = @unserialize( $workflow->wf_additional_info );

		// applicable post type
		$post_type = get_post_type( $post_id );
		if ( ! empty( $additional_info['wf_for_post_types'] ) ) {
			if ( ! in_array( $post_type, $additional_info['wf_for_post_types'] ) ) {
				return false;
			}
		}

		// applicable roles
		if ( ! empty( $additional_info[ 'wf_for_roles' ] ) ) {
         $current_roles = OW_Utility::instance()->get_current_user_roles();
         $intersect = array_intersect( $current_roles, $additional_info[ 'wf_for_roles' ] );

         if ( count( $intersect ) == 0 ) {
            return false;
         }
      }

		return true;
	}
   
   
   /**
	 * get step name from the history data
	 * If it's submit_to_workflow, then get it from the custom terminology, else get it from the step_info
	 *
	 * @param mixed $action_history_data - workflow history row
	 * @return string step name to display
	 *
	 * @since 2.0
	 */
	public function get_step_name( $action_history_data )
	{
		$option = get_option( 'oasiswf_custom_workflow_terminology' );
		$submit_to_workflow = ! empty( $option['submitToWorkflowText'] ) ? $option['submitToWorkflowText'] : __( 'Submit to Workflow', "oasisworkflow" );
		if ( $action_history_data->action_status == "submitted" ) {
			return $submit_to_workflow;
		}
		$info = $action_history_data->step_info;
		if ( $info ){
			$stepinfo = json_decode( $info )	;
			if ( $stepinfo )
				return $stepinfo->step_name ;
		}
		return "";
	}
   
   /**
	 * Retrieve step-info for active and valid workflows
	 * @return object list of step_info
	 *
	 * @since 3.6
	 */
	public function get_step_info() {
		global $wpdb;

		// get active workflows
		$workflows = $this->get_workflow_by_validity( 1 );
		$workflow_ids = array();
		foreach ( $workflows as $workflow ) {
			$workflow_ids[] = $workflow->ID;
		}

		// get step-info from step table
		$int_place_holders = array_fill( 0, count( $workflow_ids ), '%d' );
		$place_holders_for_workflow_ids = implode( ",", $int_place_holders );
		$sql = "SELECT step_info FROM " .
			OW_Utility::instance()->get_workflow_steps_table_name() .
			" WHERE workflow_id IN (" . $place_holders_for_workflow_ids . ")";
		$step_info_array = $wpdb->get_results( $wpdb->prepare( $sql, $workflow_ids ) );

		return $step_info_array;
	}
   
   
	/**
	 * Get Workflows by validity
	 *
	 * @param int $valid ( 1 or 0 )
	 * @return mixed List of Workflows
	 *
	 * @since 2.0
	 */
	public function get_workflow_by_validity( $valid ) {
		global $wpdb;

		// sanitize the data
		$valid = intval( $valid );

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_workflows_table_name() .
				" WHERE is_valid = %d ORDER BY ID desc", $valid ) );

		$workflows = array();
		foreach ( $results as $result ) {
			$workflow = $this->get_workflow_from_result_set ( $result );
			array_push( $workflows, $workflow );
		}

		return $workflows;
	}


   
   /**
	 * get connection info
	 * @param int $workflow DB object
	 * @param int $source_id step id of source
	 * @param int $target_id step id of target
	 *
	 * @return mixed connection info
	 *
	 * @since 2.0
	 */
	public function get_connection( $workflow, $source_id, $target_id ) {
		global $conn_count;
		$wf_info = json_decode( $workflow->wf_info );
		$conns = $wf_info->conns;
		if ( $conns ) {
			$conn_count ++;
			$source_gp_id = $this->get_gpid_dbid( $workflow->wf_info, $source_id );
			$target_gp_id = $this->get_gpid_dbid( $workflow->wf_info, $target_id );

			foreach ( $conns as $conn ) {
				if ( $conn->sourceId == $source_gp_id && $conn->targetId == $target_gp_id ) {
					$conn->connset->paintStyle->lineWidth = 1;
					$conn->connset->labelStyle = (object) array( "cssClass" => "labelcomponent" );
					$conn->connset->label = "$conn_count";
					return $conn;
				}
			}
		}
	}

	/**
	 * Get the table header for the workflows list page
	 *
	 * @since 2.0 initial version
	 */
	public function get_table_header()
	{
      $sortby = ( isset( $_GET['order'] ) && sanitize_text_field( $_GET["order"] ) == "desc" ) ? "asc" : "desc";

      // sorting the workflow list page via start date and end date
		$title_class = $start_date_class = $end_date_class = $post_count_class = '';
		if ( isset( $_GET['orderby'] ) && isset( $_GET['order'] ) ) {
			$orderby = sanitize_text_field( $_GET['orderby'] );
			switch ( $orderby ) {
            case 'title':
					$title_class = $sortby;
					break;
				case 'start_date':
					$start_date_class = $sortby;
					break;
				case 'end_date':
					$end_date_class = $sortby;
					break;
            case 'post_count':
					$post_count_class = $sortby;
					break;
			}
		}

		echo "<tr>";
		echo "<td scope='col' class='manage-column column-cb check-column'><input type='checkbox'></td>";
      echo "<th>" . __( "Workflow ID", "oasisworkflow" ) . "</th>";
      $sorting_args = add_query_arg( array( 'orderby' => 'title', 'order' => $sortby ) );
		echo "<th scope='col' class='field-width-200 sorted $title_class'>
               <a href='$sorting_args'>
                  <span>" .  __( "Title", "oasisworkflow" ) . "</span>
                  <span class='sorting-indicator'></span>
               </a>
			  </th>";

		echo "<th>" . __( "Version", "oasisworkflow" ) . "</th>";

      $sorting_args = add_query_arg( array( 'orderby' => 'start_date', 'order' => $sortby ) );
		echo "<th scope='col' class='sorted $start_date_class'>
               <a href='$sorting_args'>
                  <span>" . __( "Start Date", "oasisworkflow" ) . "</span>
                  <span class='sorting-indicator'></span>
               </a>
			  </th>";

      $sorting_args = add_query_arg( array( 'orderby' => 'end_date', 'order' => $sortby ) );
		echo "<th scope='col' class='sorted $end_date_class'>
               <a href='$sorting_args'>
                  <span>" .  __( "End Date", "oasisworkflow" ) . "</span>
                  <span class='sorting-indicator'></span>
               </a>
			  </th>";

      $sorting_args = add_query_arg( array( 'orderby' => 'post_count', 'order' => $sortby ) );
		echo "<th scope='col' class='sorted $post_count_class'>
               <a href='$sorting_args'>
                  <span>" . __( "Post/Pages in workflow", "oasisworkflow" ) . "</span>
                  <span class='sorting-indicator'></span>
               </a>
			  </th>";

		echo "<th>" . __( "Is Valid?", "oasisworkflow" ) . "</th>";
		echo "</tr>";
	}

     /**
	 * Get Workflow object from ID
	 *
	 * @param int $workflow_id
	 * @return OW_Workflow $workflow object
	 *
	 * @since 2.0
	 */
	public function get_workflow_by_id( $workflow_id ) {
		global $wpdb;

      // sanitize the input
      $workflow_id = intval( sanitize_text_field( $workflow_id ) );

      // try to get it from cache
      $cache_key = md5( 'ow_worklows_' . $workflow_id );
      $workflow = wp_cache_get( $cache_key, 'ow-cache-workflows' );

      if( $workflow === false ) {
         $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_workflows_table_name() . " WHERE ID = %d", $workflow_id ) );
         $workflow = $this->get_workflow_from_result_set( $result );

         // non existent cache, so, lets add/set it to cache
         wp_cache_set( $cache_key, $workflow, 'ow-cache-workflows');
      }

		return $workflow;

	}

	/**
	 * Function to convert DB result set to OW_Workflow object
	 *
	 * @param mixed $result - $result set object
    * @return OW_Workflow - instance of OW_Workflow
	 *
	 * @since 2.0
	 */
	private function get_workflow_from_result_set( $result ) {
		if ( ! $result ) {
			return "";
		}

		$workflow = new OW_Workflow( );
		$workflow->ID = $result->ID;
		$workflow->name = $result->name;
		$workflow->description = $result->description;
		$workflow->version = $result->version;
		$workflow->parent_id = $result->parent_id;
		$workflow->start_date = $result->start_date;
		$workflow->end_date = $result->end_date;
		$workflow->wf_info = $result->wf_info;
		$workflow->is_auto_submit = $result->is_auto_submit;
		$workflow->auto_submit_info = $result->auto_submit_info;
		$workflow->is_valid = $result->is_valid;
		$workflow->create_datetime = $result->create_datetime;
		$workflow->update_datetime = $result->update_datetime;
		$workflow->wf_additional_info = $result->wf_additional_info;

		return $workflow;
	}
   
   /*
	 * Delete all the workflow steps
	 *
	 * @param int $wf_id workflow id
	 *
	 * @since 2.0
	 */
	private function delete_workflow_steps( $wf_id ) {
		global $wpdb;
		$wf_id = intval( $wf_id );
		$workflow = $this->get_workflow_by_id( $wf_id ) ;
		if ( $workflow ){
			$wf_info = $workflow->wf_info ;
			if ( $wf_info ) {
				$wf_info = json_decode( $wf_info ) ;
				foreach ( $wf_info->steps as $k => $v ) {
					if ( $v->fc_dbid == "nodefine" ) {
						continue;
					}
					$result = $wpdb->get_results( $wpdb->prepare( "DELETE FROM " . OW_Utility::instance()->get_workflow_steps_table_name() .
							" WHERE workflow_id = %d and ID = %d", $wf_id, $v->fc_dbid ) );
				}
			}
		}
	}
   
	/*
	 * For the workflow_id, return the next version
	 *
	 * @param $workflow_id
	 * @return the next version
	 */
	private function get_next_version_number( $workflow_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT max(version) as current_max_version FROM " . OW_Utility::instance()->get_workflows_table_name() . " WHERE parent_id = %s OR ID = %s", $workflow_id, $workflow_id ) );
		$current_version = $row->current_max_version;
		return $current_version + 1;
	}


	/**
	 * creates a copy of the step for the new version of the workflow and assigns it to the new version of the workflow
	 *
	 * @param int $new_wf_id - workflow id to which this new step needs to be assigned
	 * @param int $current_step_id step_id of the step which needs to be copied and assigned to the new workflow
	 * @return int - id of the new step
	 *
	 * @since 2.0
	 */

	private function save_step_as_new( $new_wf_id, $current_step_id )
	{
		global $wpdb ;

		if ( ! current_user_can( 'ow_edit_workflow' ) ) {
			wp_die( __( 'You are not allowed to create/edit workflows.' ) );
		}
      // santize the input
		$new_wf_id = intval( sanitize_text_field( $new_wf_id ) );
		$current_step_id = intval( sanitize_text_field( $current_step_id ) );

		$workflow_step_table = OW_Utility::instance()->get_workflow_steps_table_name();
		$new_step_id = 0;

		// get the current step id details and insert a copy
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $workflow_step_table WHERE ID = %d", $current_step_id ) );
		if ( $result ) {
			foreach ( $result as $k => $v ) {
				if ( $k == "ID" ) // skip the ID, since we are going to create a new ID
					continue;
				$data[ $k ] = $v;
			}
			$new_step_id = OW_Utility::instance()->insert_to_table( $workflow_step_table, $data );
		} else {
			return false;
		}

		// now that we have the new step id, lets update the ID and workflow_id with the new ids
		if ( $new_step_id != 0 ){
			$wpdb->update( $workflow_step_table, array( "workflow_id" => $new_wf_id ), array( "ID" => $new_step_id ) ) ;
			return $new_step_id ;
		} else {
			return false;
		}
	}
	
	/**
	 * get the entire step structure as laid out in the workflow graphic
	 *
	 * @param mixed $wf_info workflow information
	 * @param int $step_id id of the step
	 * @param string $direction source or target
	 *
	 * @return null|mixed step information
	 *
	 */

	private function get_step_structure( $wf_info, $step_id, $direction = "source" )
	{

		$workflow_info = $wf_info ;
		$conns = $workflow_info->conns ;
		$step_gp_id = $this->get_gpid_dbid( $workflow_info, $step_id );
		$all_path = get_site_option("oasiswf_path") ;
		foreach ( $all_path as $k => $v ) {
			$path[$v[1]] = $k ;
		}
		$steps = array();
		if ( $conns ){
			if ( $direction == "source" )
				foreach ($conns as $k => $v) {
				if ( $step_gp_id == $v->sourceId ) {
					$color = $v->connset->paintStyle->strokeStyle ;
					$steps[$path[$color]][$this->get_gpid_dbid( $workflow_info, $v->targetId )] = $this->get_gpid_dbid( $workflow_info, $v->targetId, "lbl" ) ;
				}
			}
			else {
				foreach ( $conns as $k => $v ) {
					if ( $step_gp_id == $v->targetId ) {
						$color = $v->connset->paintStyle->strokeStyle ;
						$steps[$path[$color]][$this->get_gpid_dbid( $workflow_info, $v->sourceId)] =  $this->get_gpid_dbid( $workflow_info, $v->sourceId, "lbl" ) ;
					}
				}
			}
			if ( count( $steps ) > 0 ) {
				return $steps ;
			}
		}

		return false;
	}

}

// construct an instance so that the actions get loaded
$ow_workflow_service = new OW_Workflow_Service();

// these actions reload the page, so need to be outside the page
// also we need access to wp_verify_nonce
if ( isset( $_POST['save_action'] ) && $_POST["save_action"] == "workflow_save" ) {
	$workflow_service = new OW_Workflow_Service();
	$workflow_service->save_workflow();
}

if ( isset( $_POST['save_action'] ) && $_POST["save_action"] == "workflow_save_as_new_version" ) {
	$workflow_service = new OW_Workflow_Service();
	$workflow_service->save_as_new_version();
}

if ( isset( $_POST['save_action'] ) && $_POST["save_action"] == "workflow_copy" ) {
	$workflow_service = new OW_Workflow_Service();
	$workflow_service->copy_workflow();
}

?>