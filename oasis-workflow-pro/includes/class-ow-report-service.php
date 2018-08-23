<?php

/*
 * Service class for Workflow Reports
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.2
 *
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/*
 * OW_Report_Service Class
 *
 * @since 4.2
 */

class OW_Report_Service {

   /*
	 * Set things up.
	 *
	 * @since 4.2
	 */
   public function __construct() {

   }

   /*
	 * generate the table header for the Current Assignment Report page
	 *
	 * @return mixed HTML for Current Assignment Report page
	 *
	 * @since 4.2
	 */
   public function get_current_assigment_table_header() {
      $sortby            = ( isset( $_GET['order'] ) && sanitize_text_field( $_GET["order"] ) == "desc" ) ? "asc" : "desc";
      $assign_user_class = $post_order_class = $wf_name_class = $step_name_class = $priority_class = $due_date_class = '';
      if ( isset( $_GET['orderby'] ) && isset( $_GET['order'] ) ) {
         $orderby = sanitize_text_field( $_GET['orderby'] );
         switch ( $orderby ) {
            case 'assigned_users' :
               $assign_user_class = $sortby;
            case 'post_title':
               $post_order_class = $sortby;
               break;
            case 'workflow_name':
               $wf_name_class = $sortby;
               break;
            case 'step_name':
               $step_name_class = $sortby;
               break;
            case 'priority':
               $priority_class = $sortby;
               break;
            case 'due_date':
               $due_date_class = $sortby;
               break;
         }
      }

      $option = get_option( 'oasiswf_custom_workflow_terminology' );
      $due_date_title = ! empty( $option['dueDateText'] ) ? $option['dueDateText'] : __( 'Due Date', 'oasisworkflow' );
      
      
      // Hide Assigned User column by roles
      $current_user_id = get_current_user_id();
      $current_user_role = OW_Utility::instance()->get_user_role( $current_user_id );

      $roles = array();
      $user_roles = apply_filters( 'owf_hide_attributes_by_role', $roles );

      if ( ! in_array( $current_user_role, $user_roles ) ) {
         
         $sorting_args = add_query_arg( array( 'orderby' => 'assigned_user', 'order' => $sortby ) );
         $report_column_headers['assigned_user'] = "<th width='150px' scope='col' class='sorted $assign_user_class'>
                           <a href='$sorting_args'>
                           <span>" . __( "Assigned User", "oasisworkflow" ) . "</span>
                           <span class='sorting-indicator'></span>
                           </a>
                        </th>";

      }
      
      $sorting_args = add_query_arg( array( 'orderby' => 'post_title', 'order' => $sortby ) );
      $report_column_headers['post_type'] =  "<th width='280px' scope='col' class='sorted $post_order_class'>
                        <a href='$sorting_args'>
                        <span>" . __( "Post/Page", "oasisworkflow" ) . "</span>
                        <span class='sorting-indicator'></span>
                        </a>
                     </th>";

      $sorting_args = add_query_arg( array( 'orderby' => 'workflow_name', 'order' => $sortby ) );
     $report_column_headers['workflow_name'] = "<th width='200px' scope='col' class='sorted $wf_name_class'>
                        <a href='$sorting_args'>
                        <span>" . __( "Workflow", "oasisworkflow" ) . "</span>
                        <span class='sorting-indicator'></span>
                        </a>
                     </th>";

      $sorting_args = add_query_arg( array( 'orderby' => 'step_name', 'order' => $sortby ) );
      $report_column_headers['workflow_step'] = "<th width='150px' scope='col' class='sorted $step_name_class'>
                        <a href='$sorting_args'>
                        <span>" . __( "Workflow Step", "oasisworkflow" ) . "</span>
                        <span class='sorting-indicator'></span>
                        </a>
                     </th>";
      
      if ( get_option( 'oasiswf_priority_setting' ) == 'enable_priority' ) {
         $sorting_args = add_query_arg( array( 'orderby' => 'priority', 'order' => $sortby ) );
         $report_column_headers['priority'] = "<th width='150px' scope='col' class='sorted $priority_class'>
                           <a href='$sorting_args'>
                           <span>" . __( "Priority", "oasisworkflow" ) . "</span>
                           <span class='sorting-indicator'></span>
                           </a>
                        </th>";
      }

      $sorting_args = add_query_arg( array( 'orderby' => 'due_date', 'order' => $sortby ) );
      $report_column_headers['due_date'] = "<th width='150px' scope='col' class='sorted $due_date_class'>
                        <a href='$sorting_args'>
                        <span>" . $due_date_title . "</span>
                        <span class='sorting-indicator'></span>
                        </a>
                     </th>";
      
      if ( has_filter( 'owf_report_column' ) ) {
			$report_column_headers = apply_filters( 'owf_report_column', $report_column_headers );
		}  
      
      return $report_column_headers;
   }
   
   /**
    * generate the table rows for the assignment report
    * @return mixed HTML for the assignment report
    * @since 5.0
    */
   public function get_assignment_table_rows( $assigned_tasks, $report_column_header ) {
      if( $assigned_tasks ):
   		foreach ( $assigned_tasks as $assigned_task){   					
            $post_id = $assigned_task['post_id'];
   			echo "<tr id='post-{$assigned_task['post_id']}' class='post-{$assigned_task['post_id']} post type-post status-pending format-standard hentry category-uncategorized alternate iedit author-other'> " ;
            if ( array_key_exists( 'assigned_user', $report_column_header ) ) {
               echo "<td>" . $assigned_task['assigned_user'] . "</td>" ;
            }
            
            if ( array_key_exists( 'post_type', $report_column_header ) ) {
               echo "<td><strong><a href='post.php?post=" . $post_id . "&action=edit'>" . esc_html( $assigned_task['post_title'] ) . "</a> <span class='post-state'>" . __("- ", "oasisworkflow") . $assigned_task['post_status'] . "</span></strong></td>" ;
            }
            
            if ( array_key_exists( 'workflow_name', $report_column_header ) ) {
               $workflow_name = "<a href='admin.php?page=oasiswf-admin&wf_id=" . $assigned_task['workflow_id'] . "'><strong>" . $assigned_task['workflow_name'];
               $workflow_name .= "</strong></a>";

               echo "<td>{$workflow_name} </td>";
            }
            
            if ( array_key_exists( 'workflow_step', $report_column_header ) ) {
               echo "<td>{$assigned_task['step_name']} </td>";
            }
                     
            if ( array_key_exists( 'priority', $report_column_header ) ) {  
               if ( get_option( 'oasiswf_priority_setting' ) == 'enable_priority' ) {
                  //priority settings
                  $priority = get_post_meta( $post_id, '_oasis_task_priority', true );
                  if ( empty( $priority ) ) {
                     $priority = '2normal';
                  }
                  $priority_array = OW_Utility::instance()->get_priorities();
                  $priority_value = $priority_array[ $priority ];
                  // the CSS is defined without the number part
                  $css_class = substr( $priority, 1 );
                  echo "<td ><p class='post-priority $css_class-priority'>" . $priority_value . "</p></td>";
               }
            }
            if ( array_key_exists( 'due_date', $report_column_header ) ) {  
               $date = OW_Utility::instance()->format_date_for_display( $assigned_task['due_date'] );
               if ( ! empty( $date ) ) {
                  echo "<td>" . $date . "</td>" ;
               } else {
                  echo "<td>None</td>" ;
               }
            }
            
            if ( has_filter( 'owf_report_rows' ) ) {
               apply_filters( 'owf_report_rows', $post_id, $report_column_header );
            }
            
   			echo "</tr>" ;
   	}
   	else:
   		echo "<tr>" ;
   		echo "<td class='hurry-td' colspan='5'>
   				<label class='hurray-lbl'>";
   		echo __("No current assignments.", "oasisworkflow");
   		echo "</label></td>" ;
   		echo "</tr>" ;
   	endif;
   }

   /*
	 * generate the table header for Submission Report page 
	 *
	 * @return mixed HTML for Submission Report page
	 *
	 * @since 4.2
	 */

   public function get_submission_report_table_header( $action, $post_type ) {
      // sanitize data
      $report_action = sanitize_text_field( $action );
      $post_type     = sanitize_text_field( $post_type );

      $sortby = ( isset( $_GET['order'] ) && sanitize_text_field( $_GET["order"] ) == "desc" ) ? "asc" : "desc";
      $post_title_class = $post_author_class = $post_type_class = $post_date_class = '';
      if ( isset( $_GET['orderby'] ) && isset( $_GET['order'] ) ) {
         $orderby = sanitize_text_field( $_GET['orderby'] );
         switch ( $orderby ) {
            case 'post_title':
               $post_title_class = $sortby;
               break;
            case 'post_type':
               $post_type_class = $sortby;
               break;
            case 'post_author':
               $post_author_class = $sortby;
               break;
            case 'post_date':
               $post_date_class = $sortby;
               break;
         }
      }

      if ( $report_action == 'in-workflow' ) {
      $report_column_headers['checkbox'] = "<td scope='col' class='manage-column column-cb check-column'><input type='checkbox' name='abort-all'  /></td>";
      }
      $sorting_args = add_query_arg( array( 'action'  => $report_action,
                                            'type'    => $post_type,
                                            'orderby' => 'post_title',
                                            'order'   => $sortby
      ) );
      $report_column_headers['post_title'] = "<th width='300px' scope='col' class='sorted $post_title_class'>
                <a href='$sorting_args'>
                   <span>" . __( "Title", "oasisworkflow" ) . "</span>
                   <span class='sorting-indicator'></span>
                </a>
            </th>";
      $sorting_args = add_query_arg( array( 'action'  => $report_action,
                                            'type'    => $post_type,
                                            'orderby' => 'post_type',
                                            'order'   => $sortby
      ) );
      $report_column_headers['post_type'] = "<th scope='col' class='report-header sorted $post_type_class'>
                <a href='$sorting_args'>
                   <span>" . __( "Type", "oasisworkflow" ) . "</span>
                   <span class='sorting-indicator'></span>
                </a>
            </th>";
      $sorting_args = add_query_arg( array( 'action'  => $report_action,
                                            'type'    => $post_type,
                                            'orderby' => 'post_author',
                                            'order'   => $sortby
      ) );
      $report_column_headers['post_author'] = "<th scope='col' class='report-header sorted $post_author_class'>
                <a href='$sorting_args'>
                   <span>" . __( "Author", "oasisworkflow" ) . "</span>
                   <span class='sorting-indicator'></span>
                </a>
            </th>";
      $sorting_args = add_query_arg( array( 'action'  => $report_action,
                                            'type'    => $post_type,
                                            'orderby' => 'post_date',
                                            'order'   => $sortby
      ) );
      $report_column_headers['update_date'] = "<th scope='col' class='report-header sorted $post_date_class'>
                <a href='$sorting_args'>
                   <span>" . __( "Last Update Date", "oasisworkflow" ) . "</span>
                   <span class='sorting-indicator'></span>
                </a>
            </th>";
      if ( has_filter( 'owf_report_column' ) ) {
         if ( $report_action == 'in-workflow' ) {
            $report_column_headers = apply_filters( 'owf_report_column', $report_column_headers );
         }
		}  
      
      return $report_column_headers;
   }
   
   /**
    * generate the table rows for the submission report
    * @return mixed HTML for the submission report
    * @since 5.0
    */
   public function get_submission_report_table_rows( $posts, $action, $report_column_header ) {
      if ( $posts ):                  
         foreach ( $posts as $post ) { 
            $post_id = $post->ID;
            $user = get_userdata( $post->post_author );
            echo "<tr>";
            if ( array_key_exists( 'checkbox', $report_column_header ) ) {
               if ( $action == 'in-workflow' ) {
                  echo "<td><input type='checkbox' id='abort-" . $post_id . "' value='" . esc_attr( $post_id ) . "' name='abort' /></td>";
               }
            }
            if ( array_key_exists( 'post_title', $report_column_header ) ) {
               echo "<td><a href='post.php?post=" . $post_id . "&action=edit'>" . esc_html( $post->post_title ) . "</a></td>";
            }
            if ( array_key_exists( 'post_type', $report_column_header ) ) {
               echo "<td>{$post->post_type}</td>";
            }
            if ( array_key_exists( 'post_author', $report_column_header ) ) {
               echo "<td>{$user->data->display_name}</td>";
            }
            if ( array_key_exists( 'update_date', $report_column_header ) ) {
               echo "<td>" . OW_Utility::instance()->format_date_for_display( $post->post_date, "-", "datetime" ) . "</td>";
            }
            if ( has_filter( 'owf_report_rows' ) ) {
               if ( $action == 'in-workflow' ) {
                  apply_filters( 'owf_report_rows', $post_id, $report_column_header );
               }
            }
            echo "</tr>";                      
         }
         else:
            echo "<tr>";
            echo "<td class='hurry-td' colspan='4'>
						<label class='hurray-lbl'>";
            echo __( "No Posts/Pages found in any workflows." );
            echo "</label></td>";
            echo "</tr>";
         endif;
   }
   
   /**
    * Counts the total number of current assignments in workflow
    * @return int $count
    * @since 4.8
    */
   public function count_workflow_assignments() {
      global $wpdb;
      
      $sql = "SELECT 
                  COUNT( history.ID ) as history_id
              FROM " . OW_Utility::instance()->get_action_history_table_name() . " history
              LEFT OUTER JOIN  " . OW_Utility::instance()->get_action_table_name() . " review_history ON history.ID = review_history.action_history_id 
                  AND review_history.review_status = 'assignment'
              WHERE 1 = 1 
              AND history.action_status = 'assignment'";
      
      $results = $wpdb->get_results( $sql ) ;
      
      $count = $results[0]->history_id;                 
      
      return $count;
      
   }


   /*
    * get the assigned posts to a Current Assignment Report
    *
    * @param int|null $post_id
    * @param int|null $user_id
    * @param mixed $return_format it could be rows or just a single row
    *
    * @since 4.2
    */

   public function generate_workflow_assignment_report( $page_number ) {
      global $wpdb;
      $report               = array();
      $sorted_assigned_task = array();
      $offset = 0;
      $limit = OASIS_PER_PAGE;
      
      $page_number = intval( sanitize_text_field( $page_number ) );
      
      if ( $page_number !== 1 ) {
         $offset = $limit * ( $page_number - 1 );
      }

      // use white list approach to set order by clause
      $order_by = array(
         'post_title'    => 'post_title',
         'post_type'     => 'post_type',
         'priority'      => 'priority',
         'due_date'      => 'due_date',
         'workflow_name' => 'workflow_name'
      );

      $sort_order = array(
         'asc'  => 'ASC',
         'desc' => 'DESC',
      );

      $multisort_order = array(
         'asc'  => SORT_ASC,
         'desc' => SORT_DESC,
      );

      // default order by
      $order_by_column = " ORDER BY posts.post_title"; // default order by column
      // if user provided any order by and order input, use that
      if ( isset( $_GET['orderby'] ) && $_GET['orderby'] ) {
         // sanitize data
         $user_provided_order_by = sanitize_text_field( $_GET['orderby'] );
         $user_provided_order    = sanitize_text_field( $_GET['order'] );
         if ( array_key_exists( $user_provided_order_by, $order_by ) ) {
            $order_by_column = " ORDER BY " . $order_by[ $user_provided_order_by ] . " " . $sort_order[ $user_provided_order ];
         }
      }

      $sql = " SELECT 
                  posts.ID as post_id, 
                  posts.post_title, 
                  posts.post_type,
                  posts.post_status,
                  history.ID as history_id, 
                  history.step_id,
                  steps.step_info,
                  IF( history.assign_actor_id = -1, review_history.actor_id, history.assign_actor_id ) as actor_id,
                  history.due_date,
                  postmeta.meta_value AS priority,
                  workflows.ID as workflow_id,
                  CONCAT( workflows.name, '(' , workflows.version, ')' ) as workflow_name
              FROM " . OW_Utility::instance()->get_action_history_table_name() . " history
              LEFT OUTER JOIN  " . OW_Utility::instance()->get_action_table_name() . " review_history ON history.ID = review_history.action_history_id 
                  AND review_history.review_status = 'assignment'
              JOIN {$wpdb->posts} AS posts ON posts.ID = history.post_id
              LEFT OUTER JOIN {$wpdb->postmeta} AS postmeta ON postmeta.post_id = history.post_id
                  AND postmeta.meta_key = '_oasis_task_priority'
              LEFT JOIN " . OW_Utility::instance()->get_workflow_steps_table_name() . " as steps ON steps.ID = history.step_id
              LEFT JOIN " . OW_Utility::instance()->get_workflows_table_name() . " as workflows ON workflows.ID = steps.workflow_id
              WHERE 1 = 1 
              AND history.action_status = 'assignment' " . $order_by_column . " LIMIT {$offset}, {$limit}" ;

      $results = $wpdb->get_results( $sql );

      // Get the array of stdClass object results
      $report = $this->generate_workflow_assignment_report_data( $results );

      // If orderby is assigned_user/step_name than sort the array using array_multisort
      if ( isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && ( $_GET['orderby'] == 'assigned_user' || $_GET['orderby'] == 'step_name' ) ) {

         $sort_by = sanitize_text_field( $_GET['orderby'] );
         $order   = sanitize_text_field( $_GET['order'] );

         foreach ( $report as $key => $value ) {
            $sorted_assigned_task[ $key ] = $value[ $sort_by ];
         }

         array_multisort( $sorted_assigned_task, $multisort_order[ $order ], $report );
      }


      return $report;
   }

   /**
    * generate report data from SQL results
    *
    * @param stdClass object $results
    *
    * @return array $report
    * @since 4.8
    */
   private function generate_workflow_assignment_report_data( $results ) {
      $report_data = array();
      $users = array();
      $user_display_name_array = array();
      $post_status_array = array();
      
      // Get the assigned user display names by actor id
      // 1. Loop through results and fetch the actor ids
      foreach ( $results as $result ) {
         $users[] = $result->actor_id;
      }

      // 2. Get the unique actor ids
      $unique_users = array_unique( $users );
      
      $args = array( 
          'include' => $unique_users , 
          'fields' => array( 'ID', 'display_name' )
      );
      
      // 3. Get the display name for all users
      $all_users = get_users( $args );           
      
      // 4. Map it into an array by ID and display name.
      foreach( $all_users as $users ) {
         $user_id = $users->ID;
         $user_display_name_array[ $user_id ] = $users->display_name ;
      }
      
      // Get the post status
      // 1. Get all the post status 
      $status_array = get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' );
      // 2. Map it into an array by slug and label
      foreach ( $status_array as $status_slug => $status_object ) {
         $slug = $status_slug;
         $post_status_array[ $slug ] = $status_object->label;
      }

      // Create report data array
      foreach ( $results as $result ) {
         $post_id    = $result->post_id;
         $post_title = $result->post_title;
         $post_type  = $result->post_type;
         $history_id = $result->history_id;
         $step_id    = $result->step_id;

         // Get step name
         $info      = $result->step_info;
         $stepinfo  = json_decode( $info );
         $step_name = $stepinfo->step_name;

         // get assigned user display name
         if ( array_key_exists( $result->actor_id, $user_display_name_array ) ) {
            $actor_id = $result->actor_id;
            $assigned_user = $user_display_name_array[ $actor_id ];
         }
         
         // Get post status label
         if ( array_key_exists( $result->post_status, $post_status_array ) ) {
            $post_status_slug = $result->post_status;
            $post_status = $post_status_array[ $post_status_slug ];
         }
         
         $due_date      = $result->due_date;
         $priority      = $result->priority;
         $workflow_id   = $result->workflow_id;
         $workflow_name = $result->workflow_name;

         $report_data[] = array(
            'post_id'       => $post_id,
            'post_title'    => $post_title,
            'post_type'     => $post_type,
            'post_status'   => $post_status,
            'history_id'    => $history_id,
            'step_id'       => $step_id,
            'step_name'     => $step_name,
            'assigned_user' => $assigned_user,
            'due_date'      => $due_date,
            'priority'      => $priority,
            'workflow_id'   => $workflow_id,
            'workflow_name' => $workflow_name
         );

      }

      return $report_data;

   }

}

// construct an instance so that the actions get loaded
$ow_report_service = new OW_Report_Service();

?>