<?php
/*
 * Workflow validator
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


/*
 * OW_Workflow_Validator Class
 * 
 * @since 2.0
 */
class OW_Workflow_Validator
{
	/*
	 * Set things up.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		// validate workflow via ajax
		add_action( 'wp_ajax_validate_workflow', array( $this, 'validate_workflow_ajax' ) );
	}
	
	/*
	 * Ajax function - Validate if the given workflow is valid. This is ajax version of the validate_workflow function.
	 * See validate_workflow for details
	 */
	public function validate_workflow_ajax( )
	{
		// nonce check
		check_ajax_referer( 'owf_workflow_create_nonce', 'security' );
		
		$start_date = "";
		$end_date = "";
		$wf_info = "";
		$auto_submit_site_teams_array = "";
      /* Santize incoming data */
		$wf_id = intval( sanitize_text_field( $_POST["wf_id"] ) ) ;
		$start_date = "";
		$end_date = "";
		if ( isset( $_POST["start_date"] ) && ! empty( $_POST["start_date"] ) ) {
			$start_date = sanitize_text_field( $_POST["start_date"] );
		}
		if ( isset( $_POST["end_date"] ) && ! empty( $_POST["end_date"] ) ) {
			$end_date = sanitize_text_field( $_POST["end_date"] );
		}			
		$auto_submit_site_teams_array = ( isset( $_POST["auto_submit_team"] ) && $_POST["auto_submit_team"] ) ? $_POST["auto_submit_team"] : '';
		if ( is_array ( $auto_submit_site_teams_array ) ) {
			// sanitize the values
			$auto_submit_site_teams_array = array_map( 'esc_attr', $auto_submit_site_teams_array );
		}
		$wf_info = stripcslashes( $_POST["wf_info"] ) ;
		
		$args = array (
				'wf_id' => $wf_id,
				'start_date' => $start_date,
				'end_date' => $end_date,
				'wf_info' => $wf_info,
				'auto_submit_site_teams_array' => $auto_submit_site_teams_array
			);
		
		$error_messages = $this->validate_workflow( $args );
		
		echo $error_messages;
		die( );
	}
	
	/*
	 * Validate if the given workflow is valid.
	 * 1. validate start and end dates
	 * 2. validate steps
	 * 3. validate connections
	 * 4. validate teams per site for auto submit
	 *
	 * @param mixed $args array containing the attributes to the validated
	 * @return string $error_messages error messages, if any
	 */	
	public function validate_workflow( $args = array() ) {
		
		$error_messages = ""; //start with an empty string
		
		// validate start and end dates
		$error_messages .= $this->validate_dates( $args['start_date'], $args['end_date'], $args['wf_id'] );
		
		// validate steps
		$error_messages .= $this->validate_steps( $args['wf_info'] );
		
		// validate connections
		$error_messages .= $this->validate_connections( $args['wf_info'] );
		
		// validate teams for auto submit
// 		$error_messages .= $this->validate_teams_for_auto_submit( $args['auto_submit_site_teams_array'] );
		
		return $error_messages;
		
	}
	
	/*
	 * Validate dates (start and end dates) on the workflow
	 * Start date cannot be empty
	 * Start date cannot be greater than End date
	 * 
	 * @param string $start_date start date for the workflow
	 * @param string $end_date end date for the workflow
	 * @param int $wf_id workflow ID
	 * @return string $error_messages error messages, if any
	 * 
	 * @since 2.0
	 */
	private function validate_dates ( $start_date, $end_date, $wf_id ) {
		global $wpdb;
		
		$error_messages = "";
		
		// start date is required
		if ( empty( $start_date ) || $start_date == "0000-00-00") {
			$error_messages .= __( "Start date is required.", "oasisworkflow" ) ;
			$error_messages .= "<br>"; // add new line for new messages to append on new line
		}
		
		if ( ! empty( $start_date ) && $start_date != "0000-00-00" ) {
		
			$start_date_int = 0;
			$end_date_int = 0;
			
			$start_date = OW_Utility::instance()->format_date_for_db_wp_default( $start_date ) ;
			$start_date_int = OW_Utility::instance()->get_date_int( $start_date ) ;
			
			// if end date is not empty, end date cannot be greater than start date
			
			if ( ! empty ( $end_date ) ) {
				$end_date = OW_Utility::instance()->format_date_for_db_wp_default( $end_date ) ;
				$end_date_int = OW_Utility::instance()->get_date_int( $end_date ) ;
				if ( $start_date_int > $end_date_int ){
					$error_messages .= __( "End date should be greater than the start date.", "oasisworkflow" ) ;
					$error_messages .= "<br>"; // add new line for new messages to append on new line
				}			
			}
		}
		
		//TODO : revisit this validation
		/*
		if ( ! empty( $start_date ) && $start_date != "0000-00-00" ) {
			// start date or end date of new version cannot be between the old version
			$workflow = FCWorkflowCRUD::get_workflow_by_id( $wf_id ) ;
			
			if ( ! empty ( $end_date ) ) {
				$condition = "((start_date <= %s && end_date >= %s) OR (start_date <= %s && end_date >= %s))";
				$format = array($workflow->parent_id, $workflow->parent_id, $wf_id, $start_date, $start_date, $end_date, $end_date);
			} else {
				$condition = "start_date <= %s && end_date >= %s";
				$format = array($workflow->parent_id, $workflow->parent_id, $wf_id, $start_date, $start_date);
			}
			
			$where_clause = "ID <> %d && $condition";
			$result = "";
			if( $workflow->parent_id ) {
				$sql = "SELECT * FROM " . OW_Utility::instance()->get_workflows_table_name() . " WHERE (ID = %d || parent_id = %d) && $where_clause	" ;
				$result = $wpdb->get_row( $wpdb->prepare( $sql, $format));
			} else {
				$sql = "SELECT * FROM " . OW_Utility::instance()->get_workflows_table_name() . " WHERE (parent_id = %d) && $where_clause " ;
				$result = $wpdb->get_row( $wpdb->prepare( $sql, $format));
			}
			
			if( count( $result ) ){
				$error_messages .= __( "The start date or end date is between ", "oasisworkflow" ) . $result->name . "(" . $result->version . ")"  ;
				$error_messages .= "<br>"; // add new line for new messages to append on new line
			}
		}
		*/
		
		return $error_messages;
	}
	
	/*
	 * Validate steps for missing information
	 * If no steps are found, throw an error
	 * Workflow should contain one and only one "starting" step
	 *
	 * @param mixed $wf_info workflow info (all steps and connections data)
	 * @return string $error_messages error messages, if any
	 * 
	 * @since 2.0
	 */
	private function validate_steps( $wf_info ) {
		$error_messages = "";
		$wf_info = json_decode( $wf_info ) ;
		$step_count = 0 ;

		// loop through all the steps
		if ( $wf_info->steps ) {
			foreach ( $wf_info->steps as $step ) {
				if ( $step->fc_dbid == "nodefine" ){ // if fc_dbid is not defined, which means we have a missing step information
					$error_messages .= __( "Missing \"", "oasisworkflow" );
					$error_messages .= $step->fc_label;
					$error_messages .= __( "\" step information. Right click on the step to edit step information.", "oasisworkflow" ) ;
					$error_messages .= "<br>"; // add new line for new messages to append on new line
				} // end if
				$step_count++ ;
			} // end for
		} // end if
		
		if ( $step_count == 0 ) {
			$error_messages .= __( "No steps found.", "oasisworkflow" ) ;
			$error_messages .= "<br>"; // add new line for new messages to append on new line
		}		
		
		$workflow_service = new OW_Workflow_Service();
		$steps = $workflow_service->get_first_and_last_steps( $wf_info );
		if ( $steps != "nodefine" && count( $steps["first"] ) == 0 && count( $steps["last"] ) == 0 ) {
			$error_messages .= __( "The workflow doesn't have a valid exit path.	Items in this workflow will never exit the workflow. Please provide a valid exit path.", "oasisworkflow" );
			$error_messages .= "<br>"; // add new line for new messages to append on new line
		}
		
		if ( count( $wf_info->first_step ) > 1 ) {
			$error_messages .= __( 'Multiple steps marked as first step. Workflow can have only one starting point.' , "oasisworkflow" ) ;
			$error_messages .= "<br>"; // add new line for new messages to append on new line
		}		
		
		if ( count( $wf_info->first_step) == 0 ) {
			$error_messages .= __( 'Starting step not found. Workflow should have a starting point.', "oasisworkflow" ) ;
			$error_messages .= "<br>"; // add new line for new messages to append on new line
		}
		
		return $error_messages;
	}
	
	/*
	 * Validate if we have atleast one connection and atleast one failure connection
	 * 
	 * @param mixed $wf_info workflow info (all steps and connections data)
	 * @return string $error_messages error messages, if any 
	 * 
	 * @since 2.0
	 */
	private function validate_connections( $wf_info ) {
		$error_messages = "";
		$wf_info = json_decode( $wf_info ) ;
		$success_connections_count = 0;
		$connections_count = 0 ;
		$fail_connections_count = 0 ;
		
		// loop through all the connections
		foreach ( $wf_info->conns as $conn ) {
			if ( $conn->connset->paintStyle->strokeStyle == "blue" ){ // success connections are represented in blue
				$success_connections_count++ ;
			} else {
				$fail_connections_count++ ;
			}
			$connections_count++ ;
		}
		
		if ( $connections_count == 0 ){
			$error_messages .= __( "No connections found.", "oasisworkflow" ) ;
			$error_messages .= "<br>"; // add new line for new messages to append on new line
		}
		
		if ( $fail_connections_count == 0 ){
			$error_messages .= __( "Please provide failure path for all steps except the first one.", "oasisworkflow" ) ;
			$error_messages .= "<br>"; // add new line for new messages to append on new line
		}	
		
		return $error_messages;
	}
	
	/*
	 * Validate if we have one and only one team per site for auto submit
	 * 
	 * @param mixed $auto_submit_site_teams_array array of teams specified in the workflow setup
	 * @return string $error_messages error messages, if any
	 *  
	 * @since 2.0
	 */
	
	/*
	private function validate_teams_for_auto_submit( $auto_submit_site_teams_array ) {
		// only one team per site is allowed for auto submit
		$error_messages = "";
		$site_array = array();
		if (!empty($auto_submit_site_teams_array )) {
			foreach ($auto_submit_site_teams_array  as $site_team) {
				$site_team_array = explode('@', $site_team);
				if (in_array($site_team_array[0], $site_array)) {
					// display error
					$error_messages .= __( "Only one team per site should be specified for auto submit team." , "oasisworkflow" );
					$error_messages .= "<br>"; // add new line for new messages to append on new line
				} else {
					array_push($site_array, $site_team_array[0]);
				} //end if
			} // end for
		} // end if
		
		return $error_messages;
	}
	*/
	
}

// construct an instance so that the actions get loaded
$ow_workflow_validator = new OW_Workflow_Validator();
?>