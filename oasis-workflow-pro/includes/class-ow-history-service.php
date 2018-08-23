<?php
/*
 * Service class for Workflow History
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
 * OW_History_Service Class
 *
 * @since 2.0
 */
class OW_History_Service {

	/*
	 * Set things up.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_purge_workflow_history', array( $this, 'purge_workflow_history' ) );
		add_action( 'admin_init', array( $this, 'download_history_report' ) );

		// TODO: this might cause issues with other plugins - compatibility issues - for the time being this feature is turned off.
		//add_action( 'admin_menu', array( $this, 'create_meta_box' ) );
	}
      
   /*
	 * AJAX function - Purge or Delete workflow history
	 * Given a timeframe till all history till that time.
	 */
   public function purge_workflow_history() {

      // nonce check
		check_ajax_referer( 'owf-workflow-history', 'security' );

		// capability check
		if ( ! current_user_can( 'ow_delete_workflow_history' ) ) {
			wp_die( __( 'You are not allowed to delete workflow history.' ) );
		}
      
      /* sanitize incoming data */
      $period = sanitize_text_field( $_POST["range"] );
      
      // call delete_history to delete the workflow history according to the period set
      $purge_result = $this->delete_history( $period );
      
	   wp_send_json_success( array( 'result' => $purge_result ) );
   }
   
   
   /*
	 * Hook - Purge or Delete Workflow History
    * 1) called via ajax function purge_workflow_history()
    * 2) Called via cron schedule event 
	 */
	public function delete_history( $period = "" ) {
		
      global $wpdb;
      
      $auto_delete_history = get_option('oasiswf_auto_delete_history_setting') ;
     
      if ( $auto_delete_history['enable'] == 'yes' && defined( 'DOING_CRON' ) ) {
         $period = $auto_delete_history['period'];
      }
      
		switch ( $period ){
			case 'one-month-ago' :
				$range = " AND posts.post_modified < DATE(curdate() - INTERVAL 1 MONTH) ";
				break;
			case 'three-month-ago' :
				$range = " AND posts.post_modified < DATE(curdate() - INTERVAL 3 MONTH) ";
				break;
			case 'six-month-ago' :
				$range = " AND posts.post_modified < DATE(curdate() - INTERVAL 6 MONTH) ";
				break;
			case 'twelve-month-ago' :
				$range = " AND posts.post_modified < DATE(curdate() - INTERVAL 12 MONTH) ";
				break;
			case 'everything' :
				$range = " ";
				break;
			default:
				return "not a valid period specified";
		}

		$sql = "SELECT distinct post_id FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE post_id IN
		(SELECT ID from
		(SELECT posts.ID from " . $wpdb->posts . " AS posts WHERE 1=1 " . $range .
		" AND posts.id NOT IN " .
		"(SELECT A.post_id FROM
		(SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE action_status = 'assignment') as A
		LEFT OUTER JOIN
		(SELECT * FROM " . OW_Utility::instance()->get_action_table_name() . " WHERE review_status = 'assignment') as B
		ON A.ID = B.action_history_id)) as wp_posts_temp )";

		$posts_not_in_workflow_array = array();
		$posts_not_in_workflow_results = $wpdb->get_results( $sql ) ;
		if ( empty( $posts_not_in_workflow_results ) ) {
         return "success_no_history_deleted";
		}
		foreach ( $posts_not_in_workflow_results as $post_not_in_workflow ) {
			array_push($posts_not_in_workflow_array, $post_not_in_workflow->post_id);
		}

		$int_place_holders = array_fill(0, count( $posts_not_in_workflow_array ), '%d');
		$place_holders_for_post_ids = implode(",", $int_place_holders);
		$sql = "SELECT ID FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE 1=1 AND post_id in (" . $place_holders_for_post_ids . ")";
		$history_results = $wpdb->get_results( $wpdb->prepare($sql, $posts_not_in_workflow_array ) );

		// first delete any records from fc_action table
		if ( empty( $history_results ) ) {
         return "success_no_history_deleted";
		}
		$history_id_array = array();
		foreach ( $history_results as $history ) {
			array_push($history_id_array, $history->ID);
		}

		$int_place_holders = array_fill(0, count($history_id_array), '%d');
		$place_holders_for_history_ids = implode(",", $int_place_holders);

		// delete workflow history from action table
		$sql = "DELETE from " . OW_Utility::instance()->get_action_table_name() . " WHERE action_history_id in (" . $place_holders_for_history_ids . ")";
		$wpdb->get_results( $wpdb->prepare($sql, $history_id_array ) );

		// delete workflow history from action history table
		$sql = "DELETE from " . OW_Utility::instance()->get_action_history_table_name() . " WHERE id in (" . $place_holders_for_history_ids . ")";
		$wpdb->get_results( $wpdb->prepare( $sql, $history_id_array ) );
      
      return "success_history_deleted";
	}
   
   /*
	 * Hook - This action handles download history report AJAX request
	 * And returns generated CSV file path for download
	 *
	 * @since 2.0
	 */
   public function download_history_report() {
      if ( isset( $_POST[ "download_history" ] ) ) {
         check_admin_referer( 'owf-workflow-history', 'security' );

         if ( ! current_user_can( 'ow_download_workflow_history' ) ) {
            wp_die( __( 'You are not allowed to download workflow history.' ) );
         }

         $post_id = (int) isset( $_POST[ 'post_filter' ] ) ? $_POST[ "post_filter" ] : "";

         $workflow_service = new OW_Workflow_Service();
         $ow_process_flow = new OW_Process_Flow();

         $histories = $this->get_workflow_history_all( $post_id, null, "download" );

			// Hide/Show actor column using the filter
			$current_user_id = get_current_user_id();
			$current_user_role = OW_Utility::instance()->get_user_role( $current_user_id );

			$roles = array();
			$user_roles = apply_filters( 'owf_hide_attributes_by_role', $roles );

			if ( ! in_array( $current_user_role, $user_roles ) ) {
            $data[] = array( __( "Title", "oasisworkflow" ),
               __( "Actor", "oasisworkflow" ),
               __( "Workflow (version)", "oasisworkflow" ),
               __( "Step", "oasisworkflow" ),
               __( "Assigned Date", "oasisworkflow" ),
               __( "Sign off date", "oasisworkflow" ),
               __( "Result", "oasisworkflow" ),
               __( "Comments", "oasisworkflow" ),
               __( "Workflow Meta", "oasisworkflow" ) );

			} else  {
            $data[] = array( __( "Title", "oasisworkflow" ),
               __( "Workflow (version)", "oasisworkflow" ),
               __( "Step", "oasisworkflow" ),
               __( "Assigned Date", "oasisworkflow" ),
               __( "Sign off date", "oasisworkflow" ),
               __( "Result", "oasisworkflow" ),
               __( "Comments", "oasisworkflow" ),
               __( "Workflow Meta", "oasisworkflow" ) );
         }

         if ( $histories ):
            foreach ( $histories as $key => $row ) {
               if ( $row->assign_actor_id != -1 ) { //assignment and/or publish steps
                  $post_title = $row->post_title;
                  if ( $row->userid == 0 ) {
                     $actor = "System";
                  } else {
                     $actor = OW_Utility::instance()->get_user_name( $row->userid );
                  }
                  $workflow = $row->wf_name;
                  if ( ! empty( $row->version ) ) {
                     $workflow .= "(" . $row->version . ")";
                  }
                  $step = $workflow_service->get_step_name( $row );
                  $assigned_date = OW_Utility::instance()->format_date_for_display( $row->create_datetime, '-', 'datetime' );
                  $sign_off_date = OW_Utility::instance()->format_date_for_display( $ow_process_flow->get_sign_off_date( $row ), '-', 'datetime' );
                  $results = $ow_process_flow->get_sign_off_status( $row );
                  if ( $ow_process_flow->get_sign_off_comment_count( $row ) != 0 ) {
                     $comments = $ow_process_flow->get_sign_off_comments( $row->ID, 'history' );
                  } else {
                     $comments = __( "No comments found.", "oasisworkflow" );
                  }

						$pre_publish_checklist = "";
                  if ( $ow_process_flow->get_checklist_count_by_history( $row ) != 0 ) {
                     $selected_checklists = $ow_process_flow->get_selected_checklist_conditions( $row->post_id, $row->ID, "history" );
							$pre_publish_checklist .= __( "Pre Publish Checklist: ", "oasisworkflow" );
                     $pre_publish_checklist .= implode(",\n", $selected_checklists );
                     
                  }
						// hide/show actor field depending on the filter
						if ( ! in_array( $current_user_role, $user_roles ) ) {
							$data[] = array( $post_title, $actor, $workflow, $step, $assigned_date, $sign_off_date, $results, $comments, $pre_publish_checklist );
						} else {
							$data[] = array( $post_title, $workflow, $step, $assigned_date, $sign_off_date, $results, $comments, $pre_publish_checklist );
						}

               }

               if ( $row->assign_actor_id == -1 ) { //review step
                  $review_rows = $this->get_review_action_by_history_id( $row->ID, "update_datetime" );
                  if ( $review_rows ) {
                     foreach ( $review_rows as $review_row ) {
                        $post_title =  $row->post_title;
                        if ( $review_row->actor_id == 0 ) {
                           $actor = "System";
                        } else {
                           $actor = OW_Utility::instance()->get_user_name( $review_row->actor_id );
                        }
                        $workflow = $row->wf_name . "(" . $row->version . ")";
                        $step = $workflow_service->get_step_name( $row );
                        $assigned_date = OW_Utility::instance()->format_date_for_display( $row->create_datetime, '-', 'datetime' );
                        $sign_off_date = $review_row->update_datetime;

                        // If editors' review status is "no_action" (Not acted upon) then set user status as "No action taken"
                        if ( $review_row->review_status == "no_action" || $review_row->review_status == "abort_no_action" ) {
                           $review_signoff_status = __( "No Action Taken", "oasisworkflow" );
                        } else {
                           if ( $ow_process_flow->get_next_step_sign_off_status( $row ) == "complete" ) {
                              $review_signoff_status = __( "Workflow completed", "oasisworkflow" );
									} elseif ( $ow_process_flow->get_next_step_sign_off_status( $row ) == "cancelled" ) {
										$review_signoff_status = __( "Cancelled", "oasisworkflow" );
									} else {
                              $review_signoff_status = $ow_process_flow->get_review_sign_off_status( $row, $review_row );
                           }
                        }
                        $sign_off_date = OW_Utility::instance()->format_date_for_display( $sign_off_date, '-', 'datetime' );
                        if ( $ow_process_flow->get_review_sign_off_comment_count( $review_row, $row->post_id, $review_row->actor_id ) != 0 ) {
                           $comments = $ow_process_flow->get_sign_off_comments( $review_row->ID, 'review' );
                        } else {
                           $comments = __( "No comments found.", "oasisworkflow" );
                        }

								$pre_publish_checklist = "";
                        if ( $ow_process_flow->get_checklist_count_by_history( $row ) != 0 ) {
                           $selected_checklists = $ow_process_flow->get_selected_checklist_conditions( $row->post_id, $row->ID, "review_history" );
									$pre_publish_checklist .= __( "Pre Publish Checklist: ", "oasisworkflow" );
									$pre_publish_checklist .= implode(",\n", $selected_checklists );
                        }

								// hide/show actor field depending on the filter
								if ( ! in_array( $current_user_role, $user_roles ) ) {
									$data[] = array( $post_title, $actor, $workflow, $step, $assigned_date, $sign_off_date, $review_signoff_status, $comments, $pre_publish_checklist );
								} else {
									$data[] = array( $post_title, $workflow, $step, $assigned_date, $sign_off_date, $review_signoff_status, $comments, $pre_publish_checklist );
								}

                     }
                  }
               }
            }
         endif;

         $today = date( "Ymd-His" );
         $fileName = "oasis-workflow-history-" . $today . ".csv";

         // output headers so that the file is downloaded rather than displayed
         header( 'Content-Type: text/csv; charset=UTF-8' );
         header( "Content-Disposition: attachment; filename={$fileName}" );

         $fh = @fopen( 'php://output', 'w' );

         foreach ( $data as $key => $val ) {
            @fputcsv( $fh, $val ); // Put the data into stream
         }

         @fclose( $fh );
         exit();
      }
   }
      
	public function create_meta_box(){

		global $chkResult ;

		$selected_user = isset( $_GET['user'] ) ? intval( sanitize_text_field( $_GET['user'] ) ) : get_current_user_id();
		$ow_process_flow = new OW_Process_Flow();
		$chkResult = $ow_process_flow->workflow_submit_check( $selected_user );

		if ( $chkResult && $chkResult != "submit" && $chkResult != "inbox" && $chkResult != "makerevision" ) {
			$post = get_post( $_GET["post"] ) ;
			$meta_box = array(
					'id' => 'graphic',
					'title' => 'Workflow',
					'page' => $post->post_type,
					'context' => 'normal',
					'priority' => 'high'
			);
			add_meta_box($meta_box['id'], $meta_box['title'], array($this,'history_graphic_box'), $meta_box['page'], $meta_box['context'], $meta_box['priority']);
		}

	} 
   
	public function history_graphic_box(){
		include( OASISWF_PATH . "includes/pages/subpages/history-graphic.php" ) ;
	}

   
   /*
	 * get all the workflow history
	 *
	 * @param null|int $post_id, if particular post id is provided, get history only for that post
	 * @return mixed $history result set
	 *
	 * @since 2.0
	 */
	public function get_workflow_history_all( $post_id = null, $page_number, $report_type )
	{
		global $wpdb;      
      $limit = OASIS_PER_PAGE;    
      
      // check capability
		if ( ! current_user_can( 'ow_view_workflow_history' ) ) {
			wp_die( __( 'You are not allowed to view workflow history.' ) );
		}

		// use white list approach to set order by clause
		$order_by = array(
				'post_title' => 'post_title',
				'wf_name' => 'wf_name',
				'create_datetime' => 'create_datetime'
		);

		$sort_order = array(
				'asc' => 'ASC',
				'desc' => 'DESC',
		);

		$limit_search = "";

      // sanitize the input
		if ( ! empty ( $post_id ) ) {
			$post_id = intval( sanitize_text_field( $post_id ) );
		}
      $page_number = intval( sanitize_text_field( $page_number ) );
      $report_type = sanitize_text_field( $report_type );
      
      // If report type is "download" we don't ned the limit search
      if ( $report_type == "download" ) {
         $limit_search = "";
      }
     
      // Set the limit and offset to view the history list as per limit search .
      if ( $report_type == "online" ) {    
         
         $offset = 0; 
         // limit search if page is 1
         $limit_search = "LIMIT " . $offset. "," . $limit ;
                 
         if ( $page_number !== 1 ) {
            $offset = $limit * ( $page_number - 1 );
            $limit_search = "LIMIT " . $offset. "," . $limit ;
         }
      }

		// default order by
		$order_by_columns = " ORDER BY A.ID DESC"; // default order by column
		if ( isset($_GET['orderby']) && $_GET['orderby'] ) {
			// sanitize data
			$user_provided_order_by = sanitize_text_field( $_GET['orderby'] );
			$user_provided_order = sanitize_text_field( $_GET['order'] );
			if (  array_key_exists ( $user_provided_order_by, $order_by )) {
				$order_by_columns = " ORDER BY " . $order_by[$user_provided_order_by] . " " . $sort_order[$user_provided_order] . ", A.ID DESC";
			}
		}

		$where_clause = "action_status != 'complete' AND action_status != 'cancelled'" ;

		// if post id is provided, filter by post_id
		if ( $post_id ) {
			$where_clause .= " AND post_id= %d ";
		}
		$sql = "SELECT A.* , B.post_title, C.ID as userid, C.display_name as assign_actor, D.step_info, D.workflow_id, D.wf_name, D.version
					FROM
					((SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE $where_clause) AS A
					LEFT JOIN
					{$wpdb->posts} AS B
					ON  A.post_id = B.ID
					LEFT JOIN
					{$wpdb->users} AS C
					ON A.assign_actor_id = C.ID
					LEFT JOIN
					(SELECT AA.*, BB.name as wf_name, BB.version FROM " . OW_Utility::instance()->get_workflow_steps_table_name() . " AS AA LEFT JOIN " . OW_Utility::instance()->get_workflows_table_name() . " AS BB ON AA.workflow_id = BB.ID) AS D
					ON A.step_id = D.ID)
               {$order_by_columns} {$limit_search}";

      if ( $post_id ) {
         $results = $wpdb->get_results( $wpdb->prepare( $sql, $post_id )) ;
      } else {
         $results = $wpdb->get_results( $sql ) ;
      }
		return $results;
	}
   
	/*
	 * get the count of history records
	 * @param int|null $post_id, if post id is provided, get count for the particular post only
	 * @return int count of history records
	 *
	 * @since 2.0
	 */
	public function get_workflow_history_count( $post_id = null )
	{
		global $wpdb;

		$where_clause = "action_status != 'complete' AND action_status != 'cancelled'" ;

		// if post id is provided, filter by post_id
		if ( $post_id ) {
			$where_clause .= " AND post_id= %d ";
		}
		$sql = "SELECT A.*
					FROM
						((SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE $where_clause) AS A
							LEFT JOIN " . OW_Utility::instance()->get_action_table_name() . " AS C
						ON A.ID = C.action_history_id)
					" ;

      if ( $post_id ) {
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $post_id )) ;
		} else {
			$results = $wpdb->get_results( $sql ) ;
		}

		$final_results = array();
		foreach ($results as $result) {
			$final_results[] = $result;
		}
		return count( $final_results );
	}
   
   /*
	 * Get Review History object by action history id
	 *
	 * @param int $action_history_id
	 * @param string|null $order_by
	 * @return mixed OW_Review_History array
	 *
	 * @since 2.0
	 */
	public function get_review_action_by_history_id( $action_history_id, $order_by = null ) {
		global $wpdb;
      // sanitize the input
		$action_history_id = intval( sanitize_text_field( $action_history_id ) );
		if ( ! empty ( $order_by ) ) {
			$order_by = sanitize_text_field( $order_by );
		}

		$review_histories = array();

		if ( empty( $order_by ) ) {
			$order_by_clause = " ORDER BY ID DESC";
		} elseif ( $order_by == 'update_datetime' ) {
			$order_by_clause = "ORDER BY (update_datetime ='0000-00-00 00:00:00') DESC, " . $order_by . " DESC ";
		} else {
			$order_by_clause = " ORDER BY " . $order_by . " DESC ";
		}
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_table_name() . " WHERE action_history_id = %d " . $order_by_clause, $action_history_id ) );
		foreach ( $results as $result ) {
			$review_history = $this->get_review_history_from_result_set( $result );
			array_push( $review_histories, $review_history );
		}
		return $review_histories;
	}

	/*
	 * Get Workflow History object from ID
	 *
	 * @param int $action_history_id
	 * @return OW_Action_History $action_history object
	 *
	 * @since 2.0
	 */
	public function get_action_history_by_id( $action_history_id ) {
		global $wpdb;

		// sanitize the input
		$action_history_id = intval( sanitize_text_field( $action_history_id ) );

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE ID = %d ORDER BY create_datetime DESC", $action_history_id ) );

		$action_history = $this->get_action_history_from_result_set( $result );
		return $action_history;

	}

	/*
	 * Get Workflow History object from "from_id" - the previous step_id
	 *
	 * @param int $from_id - previous step id
	 * @return OW_Action_History $action_history object
	 *
	 * @since 2.0
	 */
	public function get_action_history_by_from_id( $from_id ) {
		global $wpdb;

		// sanitize the input
		$from_id = intval( sanitize_text_field( $from_id ) );

		// try to get it from cache
		$cache_key = md5( 'ow_worklow_history_from_id' . $from_id );
		$action_history = wp_cache_get( $cache_key, 'ow-cache-workflows' );

		if( $action_history === false ) {
			$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE from_id = %d", $from_id ) );
			$action_history = $this->get_action_history_from_result_set( $result );

			// non existent cache, so, lets add/set it to cache
			wp_cache_set( $cache_key, $action_history, 'ow-cache-workflows' );
		}

		return $action_history;
	}

	/*
	 * Get Workflow History object from action status for a post
	 *
	 * @param int $action_status
	 * @param int|null $post_id
	 * @return mixed OW_Action_History array $action_history object
	 *
	 * @since 2.0
	 */
	public function get_action_history_by_status( $action_status, $post_id ) {
		global $wpdb;
      $action_histories = array();
		if ( ! empty( $post_id ) ) {
			// sanitize the input
			$action_status = sanitize_text_field( $action_status ) ;
			$post_id = intval( sanitize_text_field( $post_id ) );			
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE action_status = %s AND post_id = %d ORDER BY create_datetime DESC", $action_status, $post_id ) );
			foreach ( $results as $result ) {
				$action_history = $this->get_action_history_from_result_set( $result );
				array_push( $action_histories, $action_history );
			}
			return $action_histories;
		}
		return $action_histories;
	}

	/*
	 * Get Workflow History object from post
	 *
	 * @param int $post_id
	 * @return OW_Action_History $action_history object
	 *
	 * @since 2.0
	 */
	public function get_action_history_by_post( $post_id ) {
		global $wpdb;

		$post_id = intval( sanitize_text_field( $post_id ) );

		$action_histories = array();
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE post_id = %d ORDER BY create_datetime DESC", $post_id ) );
		foreach ( $results as $result ) {
			$action_history = $this->get_action_history_from_result_set( $result );
			array_push( $action_histories, $action_history );
		}
		return $action_histories;

	}

	/*
	 * Get Workflow History object from multiple parameters
	 *
	 * @param int $from_id - previous step id
	 * @return OW_Action_History $action_history object
	 *
	 * @since 2.0
	 */
	public function get_action_history_by_parameters( $workflow_history_params ) {
		global $wpdb;

		// sanitize the data
		$action_status = sanitize_text_field( $workflow_history_params["action_status"] ) ;
		$step_id = intval( $workflow_history_params["step_id"] ) ;
		$post_id = intval( $workflow_history_params["post_id"] );
		$from_id = intval( $workflow_history_params["from_history_id"] );

      // try to get it from cache
      $cache_key = md5( 'ow_worklow_history_parameters_' . serialize( $workflow_history_params ) );
      $action_history = wp_cache_get( $cache_key, 'ow-cache-workflows' );

      if ( $action_history === false ) {
         $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_history_table_name() . " WHERE action_status = %s AND step_id = %d AND post_id = %d AND from_id = %d",
            $action_status, $step_id, $post_id, $from_id ) );
         $action_history = $this->get_action_history_from_result_set( $result );

         // non existent cache, so, lets add/set it to cache
         wp_cache_set( $cache_key, $action_history, 'ow-cache-workflows' );
      }

		return $action_history;
	}

	/*
	 * Get Review History object by ID
	 *
	 * @param int $review_history_id
	 * @return OW_Review_History $review_history object
	 *
	 * @since 2.0
	 */
	public function get_review_action_by_id( $review_history_id ) {
		global $wpdb;

		$review_history_id = intval( sanitize_text_field( $review_history_id ) );

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_table_name() . " WHERE ID = %d", $review_history_id ) );

		$review_history = $this->get_review_history_from_result_set( $result );
		return $review_history;
	}

	/*
	 * Get Review History object by review status
	 *
	 * @param string $review_status
	 * @param int $action_history_id
	 * @return mixed OW_Review_History array
	 *
	 * @since 2.0
	 */
	public function get_review_action_by_status( $review_status, $action_history_id ) {
		global $wpdb;

		$action_history_id = intval( sanitize_text_field( $action_history_id ) );
		$review_status = sanitize_text_field( $review_status );

		$review_histories = array();

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_table_name() . " WHERE review_status = %s AND action_history_id = %d ORDER BY ID DESC", $review_status, $action_history_id ) );
		foreach ( $results as $result ) {
			$review_history = $this->get_review_history_from_result_set( $result );
			array_push( $review_histories, $review_history );
		}
		return $review_histories;
	}

	/*
	 * Get Review History object by actor id
	 *
	 * @param int $review_history_id
	 * @return OW_Review_History $review_history object
	 *
	 * @since 2.0
	 */
	public function get_review_action_by_actor( $actor_id, $review_status, $action_history_id ) {
		global $wpdb;

		$actor_id = intval( sanitize_text_field( $actor_id ) );
		$action_history_id = intval( sanitize_text_field( $action_history_id ) );
		$review_status = sanitize_text_field( $review_status );

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_action_table_name() . " WHERE review_status = %s AND actor_id = %d AND action_history_id = %d", $review_status, $actor_id, $action_history_id ) );
		$review_history = $this->get_review_history_from_result_set( $result );
		return $review_history;
	}
   
   public function get_table_header()
	{
		$order = ( isset( $_GET['order'] ) && sanitize_text_field( $_GET["order"] ) == "desc" ) ? "asc" : "desc" ;

		if ( isset( $_GET['orderby'] ) && sanitize_text_field( $_GET["orderby"] ) == "post_title" ) {
			$post_order_class = $order;
      } else {
			$post_order_class = "" ;
      }
      
		if ( isset( $_GET['orderby'] ) && sanitize_text_field( $_GET["orderby"] ) == "wf_name" ) {
			$wf_order_class = $order;
      } else {
			$wf_order_class = "" ;
      }
      
		if ( isset( $_GET['orderby'] ) && sanitize_text_field( $_GET["orderby"] ) == "create_datetime" ) {
			$create_date_order_class = $order;
      } else {
			$create_date_order_class = "" ;
      }
      
		$where_post = ( isset( $_GET['post'] ) && sanitize_text_field( $_GET["post"] ) ) ? "&post=" . intval( sanitize_text_field( $_GET["post"] ) ) : "" ;

		echo "<tr>";
		echo "<td scope='col' class='manage-column column-cb check-column'><input type='checkbox'></td>";
		echo "<th scope='col' class='history-title sorted $post_order_class'>
		<a href='admin.php?page=oasiswf-history&orderby=post_title&order=$order" . $where_post . "'>
					<span>". __("Title", "oasisworkflow") . "</span>
					<span class='sorting-indicator'></span>
				</a>
			</th>" ;
         
      // Hide actor column by roles
      $current_user_id = get_current_user_id();
      $current_user_role = OW_Utility::instance()->get_user_role( $current_user_id );

      $roles = array();

      $user_roles = apply_filters( 'owf_hide_attributes_by_role', $roles );

      if( ! in_array( $current_user_role, $user_roles ) ) {
         echo "<th class='history-header' >" . __("Actor", "oasisworkflow") . "</th>" ;
      }
      
      
		echo "<th scope='col' class='sorted $wf_order_class'>
		<a href='admin.php?page=oasiswf-history&orderby=wf_name&order=$order" . $where_post . "'>
					<span>". __("Workflow [Step]", "oasisworkflow") . "</span>
					<span class='sorting-indicator'></span>
				</a>
			</th>" ;
		echo "<th scope='col' class='sorted $create_date_order_class'>
		<a href='admin.php?page=oasiswf-history&orderby=create_datetime&order=$order" . $where_post . "'>
					<span>". __("Assigned date", "oasisworkflow") . "</span>
					<span class='sorting-indicator'></span>
				</a>
			</th>" ;
		echo "<th scope='col'>". __("Sign off date", "oasisworkflow") . "</th>" ;
		echo "<th scope='col' class='history-header' >". __("Result", "oasisworkflow") . "</th>" ;
		echo "<td scope='col' class='history-comment' >". __("Comments", "oasisworkflow") . "</td>" ;
      // for add-ons to add new column to history page 
      apply_filters( 'display_history_column_header', $result_html = '' );
		echo "</tr>";
	}

	/*
	 * function to result the action history object from the DB result set
	 *
	 * @since 2.0
	 */
	private function get_action_history_from_result_set( $result ) {
		if (! $result ) {
			return "";
		}
		$action_history = new OW_Action_History( );
		$action_history->ID = $result->ID;
		$action_history->action_status = $result->action_status;
		$action_history->comment = $result->comment;
		$action_history->step_id = $result->step_id;
		$action_history->assign_actor_id = $result->assign_actor_id;
		$action_history->post_id = $result->post_id;
		$action_history->from_id = $result->from_id;
		$action_history->due_date = $result->due_date;
		$action_history->reminder_date = $result->reminder_date;
		$action_history->reminder_date_after = $result->reminder_date_after;
		$action_history->create_datetime = $result->create_datetime;
      $action_history->history_meta = $result->history_meta;

		return $action_history;
	}

	/*
	 * function to result the review history object from the DB result set
	 *
	 * @since 2.0
	 */
	private function get_review_history_from_result_set( $result ) {
		if ( ! $result ) {
			return "";
		}
		$review_history = new OW_Review_History( );
		$review_history->ID = $result->ID;
		$review_history->review_status = $result->review_status;
		$review_history->comments = $result->comments;
		$review_history->step_id = $result->step_id;
		$review_history->next_assign_actors = $result->next_assign_actors;
		$review_history->actor_id = $result->actor_id;
		$review_history->due_date = $result->due_date;
		$review_history->action_history_id = $result->action_history_id;
		$review_history->update_datetime = $result->update_datetime;
		$review_history->history_meta = $result->history_meta;

		return $review_history;
	}
   
}

// construct an instance so that the actions get loaded
$ow_history_service = new OW_History_Service();
add_action( 'oasiswf_auto_delete_history_schedule', array( $ow_history_service, 'delete_history' ) );
?>