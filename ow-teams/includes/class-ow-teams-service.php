<?php
/*
 * CRUD actions for Teams
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
   exit;

/*
 * Teams Class - CRUD operations
 *
 * @since 2.0
 */

class OW_Teams_Service {
   /*
    * Set things up.
    *
    * @since 2.0
    */

   public function __construct() {

      //ajax actions
      add_action( 'wp_ajax_validate_team', array( $this, 'validate_team' ) );
      add_action( 'wp_ajax_create_or_update_team', array( $this, 'create_or_update_team' ) );
      
      add_action( 'wp_ajax_is_team_in_workflow', array( $this, 'is_team_in_workflow' ) );
      add_action( 'wp_ajax_delete_team', array( $this, 'delete_team' ) );
      add_action( 'wp_ajax_delete_teams', array( $this, 'delete_teams' ) );
      
      add_action( 'wp_ajax_delete_members_from_team', array( $this, 'delete_members_from_team' ) );
      add_action( 'wp_ajax_delete_workflows_from_team', array( $this, 'delete_workflows_from_team' ) );
      add_action( 'wp_ajax_get_team_members_by_id', array( $this, 'get_team_members_by_id' ) );
      
      add_action( 'get_teams_for_workflow', array( $this, 'get_teams_for_workflow' ), 10, 1 );
      
      add_action( 'delete_user', array( $this, 'delete_team_member' ) );
      add_action( 'owf_workflow_delete', array( $this, 'delete_associated_workflows' ), 10, 1 );
   }
   
   /**
    * AJAX function - Validate new/existing Team parameters 
    * In case of validation errors - display error messages on top of the page screen
    * @since 2.7
    */
   public function validate_team() {
      // nonce check
      check_ajax_referer( 'save_workflow_team', 'security' );
      
      // Sanitize incoming data
      $team_id = isset( $_POST['team_id'] ) ? intval( $_POST['team_id'] ) : '';
      $team_action = isset( $_POST['team_action'] ) ? sanitize_text_field( $_POST['team_action'] ) : '';
      $associated_workflows = isset( $_POST['associated_workflows'] ) ? $_POST['associated_workflows'] : '';
      $team_members = isset( $_POST['team_members'] ) ? $_POST['team_members'] : ''; 


      if ( ! empty ( $team_id ) ) { // essentially, team is being updated
         $team_info = $this->get_team( $team_id );
         $existing_associated_workflows = json_decode( $team_info[0]->associated_workflows );
         // Merge the selected associated workflows with existing associated workflows
         if ( ! empty( $existing_associated_workflows ) && ( ! empty( $associated_workflows ) ) ) {
            $merged_associated_workflows = array_merge( $existing_associated_workflows, $associated_workflows );
            $associated_workflows = array_unique( $merged_associated_workflows );
         }
         /*
          * Case: if selected associated workflows is empty but for existing 
          * associated workflow if any role is deleted or not exist than pass existing
          * associated workflow for validation
          */
         if ( empty ( $associated_workflows ) ) {
            $associated_workflows = $existing_associated_workflows; 
         }
         foreach( $team_info as $team_element ) {
            $team_members[] = $team_element->user_id . "@" . $team_element->role;
         }
      }
      
      // set the variables
      $validation_messages = array();

      //  For selected workflows, validate if the team has all roles as required by the workflow
      $validation_messages = $this->validate_team_workflow_roles( $associated_workflows, $validation_messages, $team_members );

      if (  count( $validation_messages ) > 0 ) {
         $messages  = "<div>";
         $messages .= '<p>' . implode( "<br>", $validation_messages ) . '</p>';
         $messages .= "</div>";
         wp_send_json_error( array( 'errorMessage' => $messages ) );
      }        
      
      wp_send_json_success();
   }
   
   /**
    * AJAX function - Create or update team
    * @since 1.0.0
    */
   public function create_or_update_team() {
      global $wpdb;
      if ( ! wp_verify_nonce( $_POST['hash'], 'save_workflow_team' ) )
         exit;

      $team_name = esc_attr( $_POST['team_name'] );
      $team_desc = esc_attr( $_POST['team_desc'] );
      $team_members = isset( $_POST['team_members'] ) && ! empty ( $_POST['team_members'] ) ? array_map( 'esc_attr', $_POST['team_members'] ) : '';
      $associated_workflows = isset( $_POST['associated_workflows'] ) && ! empty ( $_POST['associated_workflows'] ) ? array_map( 'esc_attr', $_POST['associated_workflows'] ) : '';

      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      
      // Unset "all" option if count of selected workflow is more than 1
      if ( ! empty( $associated_workflows ) && count( $associated_workflows ) > 1 ) {
         foreach( $associated_workflows as $key => $wf_id ) {
            if ( $wf_id == -1 ) {
               unset( $associated_workflows[ $key ] );
            }
         }
      }
      
      // Re-index the array after unset
      if ( ! empty( $associated_workflows ) ) {
         $associated_workflows = array_values( $associated_workflows );
      }
      
      // Update team
      if ( isset( $_POST['team_action'] ) && $_POST['team_action'] == "update_team" ) {
         $team_id = intval( $_POST['team_id'] );
         $data = array(
             'name' => $team_name,
             'description' => $team_desc,             
             'update_datetime' => current_time( 'mysql' )
         );
         
         if ( ! empty( $associated_workflows ) ) {
            /*
             * 1. Get existing associated workflow
             * 2. Merge with the selected workflows for team
             * 3. Find the unique workflow ids 
             * 4. update the associated workflows for the team
             */
            $final_result = array();
            $result = $wpdb->get_row( $wpdb->prepare( "SELECT associated_workflows from {$teams_table} WHERE ID = %d", $team_id ) );
            $result_workflows = json_decode( $result->associated_workflows );
            if ( ! empty( $result_workflows ) && count( $result_workflows ) !== 0 ) {
               /*
                * Unset "all workflows" option from result set 
                * if selected associated workflow not contain "all workflows" option
                */
               foreach( $result_workflows as $key => $wf_id ) {
                  if ( $wf_id == -1 ) {
                     unset( $result_workflows[ $key ] );
                  }
               }
               
               /*
                * If selected associated workflow just contain "all workflows" option
                * and return workflow result count is more than 1                * 
                * than unset "all workflows" option 
                */
               if ( count( $associated_workflows ) === 1 ) {
                  foreach( $associated_workflows as $key => $wf_id ) {
                     if ( $wf_id == -1 ) {
                        $result_workflows = array();
                     }
                  }
               }
               
               // Re-index the array after unset
               $reindexed_result_workflows       = array_values( $result_workflows );
               $reindexed_associated_workflows   = array_values( $associated_workflows );
               
               $merged_workflow_ids    = array_merge( $reindexed_result_workflows, $reindexed_associated_workflows );
               $associated_workflows   = array_unique( $merged_workflow_ids );
            }
           $data['associated_workflows'] = json_encode( $associated_workflows );
         }

         $wpdb->update( $teams_table, $data, array( 'id' => $team_id ) );

         $this->add_members( $team_id, $team_members );
      } else {
         // Insert new team
         $team_data = array(
             'name' => $team_name,
             'description' => $team_desc,
             'associated_workflows' => json_encode( $associated_workflows ),
             'create_datetime' => current_time( 'mysql' )
         );

         $team_id = OW_Utility::instance()->insert_to_table( $teams_table, $team_data );
         if ( $team_id && ! empty( $team_members ) ) {
            $this->add_members( $team_id, $team_members );
         }
      }
      wp_send_json_success();
   }
   
   /**
    * AJAX function - Checks whether team is associated as an assignee with any workflow
    * @since 1.0.0
    */
   public function is_team_in_workflow() {
      $team_id = intval( $_POST['team_id'] );
      $value = OW_Teams_Utility::instance()->get_meta_data_by_key( "_oasis_is_in_team", $team_id, 1 );
      if ( empty( $value ) ) {
         wp_send_json_error();
      } else {
         wp_send_json_success();
      }
   }
   
   /**
    * AJAX function - Delete individual team via team listing page
    * @since 1.0.0
    */
   public function delete_team() {
      global $wpdb;
      
      // nonce check
      check_ajax_referer( 'bulk_delete_teams', 'security' );
      
      // sanitize incoming data
      $team_id = intval( $_POST['team_id'] );

      if ( empty( $team_id ) )
         exit;

      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();

      $wpdb->delete( $teams_table, array( 'ID' => $team_id ), array( '%d' ) );
      $wpdb->delete( $team_members_table, array( 'team_id' => $team_id ), array( '%d' ) );
      
      wp_send_json_success();
   }
   
   /**
    * AJAX function - Bulk delete team via team listing 
    * @since 1.0.0
    */
   public function delete_teams() {
      global $wpdb;

      // nonce check
      if ( ! wp_verify_nonce( $_REQUEST['_delete_teams'], 'bulk_delete_teams' ) OR sanitize_text_field( $_REQUEST['action2'] ) == -1 )
         return false;

      $teams_array = array();
      $teams = $_REQUEST['teams'];
      // sanitize the values
      $teams = array_map( 'esc_attr', $teams );

      foreach ( $teams as $team ) {
         array_push( $teams_array, $team );
      }


      if ( empty( $teams_array ) )
         exit;

      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();

      foreach ( $teams_array as $team ) {
         $wpdb->delete( $teams_table, array( 'ID' => $team ), array( '%d' ) );
         $wpdb->delete( $team_members_table, array( 'team_id' => $team ), array( '%d' ) );
      }

      if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
         echo "success";
         exit;
      }
   }

   /**
    * AJAX function - Delete members from the team
    * @since 1.3
    */
   public function delete_members_from_team() {
      global $wpdb;
      
      // nonce check
      if ( ! wp_verify_nonce( $_POST['hash'], 'delete_selected_team_members' ) ) {
         exit;
      }
      
      // sanitize incoming data
      $team_id = intval( $_POST['team_id'] );
      if ( empty( $_POST['members'] ) ) {
         exit;
      }

      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();

      if ( ! empty( $_POST['members'] ) ) {
         $members = array_map( 'esc_attr', $_POST['members'] );
         foreach ( $members as $member ) :
            $team_member = explode( "@", $member );
            $wpdb->delete( $team_members_table, array( 'user_id'   => $team_member[0],
                                                       'role_name' => $team_member[1],
                                                       'team_id'   => $team_id
            ), array( '%d', '%s', '%d' ) );
         endforeach;
         wp_send_json_success();
      }
   }
   
   /**
    * AJAX function - Delete associated workflows from the team
    * @since 2.7
    */
   public function delete_workflows_from_team() {
      global $wpdb;
      $team_id = intval( $_POST['team_id'] );
      
      if ( ! wp_verify_nonce( $_POST['hash'], 'delete_selected_workflows' ) ) {
         exit;
      }
      
      if ( empty( $_POST['workflows'] ) ) {
         exit;
      }
      
      $team_table = OW_Teams_Utility::instance()->get_teams_table_name();
      $team_info = $this->get_team( $team_id );
      $associated_workflows = json_decode( $team_info[0]->associated_workflows );
      
      if ( ! empty( $_POST['workflows'] ) ) {
         $delete_workflows = array_map( 'esc_attr', $_POST['workflows'] );
         $workflows        = array_diff( $associated_workflows, $delete_workflows );
         $data             = array(
            'associated_workflows' => json_encode( $workflows )
         );
         $wpdb->update( $team_table, $data, array( 'id' => $team_id ) );
         wp_send_json_success();
      }
   }
   
   /**
    * AJAX function - Get the team members on workflow submit popup 
    * @return string $actors
    * @since 2.8
    */
   public function get_team_members_by_id() {
      // nonce check
      check_ajax_referer( 'owf_signoff_ajax_nonce', 'security' );
      
      /* sanitize incoming data */
      $step_id = intval( $_POST["step_id"] );
      $team_id = intval( $_POST["team_id"] );
      $post_id = intval( $_POST["post_id"] );
      
      if ( $post_id != null ) {
         $post = get_post( $post_id );
         $post_author_id = $post->post_author;
      }
            
      $members = $this->get_team_members_by_step_id( $team_id, $step_id, $post_id );
      if ( $members ) {
      $team_members  = explode( '@', $members );
      }
      $users         = null;
      
      if ( empty( $team_members ) ) {
         //something is wrong, we didn't get any team members
         $messages  = "<div id='message' class='error error-message-background '>";
         $messages .= '<p>' . __( 'No users found for the given Team and Workflow assignee(s). Please check the team.', 'owfteams' ) . '</p>';
         $messages .= "</div>";
         wp_send_json_error( array( 'errorMessage' => $messages ) );
      }
      
      foreach ( $team_members as $user_id ) {
         $get_user_info = get_user_by( 'ID', $user_id );
         $display_name  = $get_user_info->display_name;
         
         $part["ID"]    = $user_id;
         
         if ( $user_id == $post_author_id ) {
            $part["name"] = $display_name . ' (' . __( "Post Author", "owfteams" ) . ')';
         } else {
            $part["name"] = $display_name;
         }
         $user_string[] = (object) $part;
      }
      $users["users"] = (object) $user_string;
      wp_send_json_success( $users );
   }


   /**
    * Hook - get_teams_for_workflow
    * Get all the associated teams for the given workflow.
    *
    * @param $wf_id
    *
    * @return array of associated teams Or all teams, if associated teams if empty
    * @since 2.7
    */
   public function get_teams_for_workflow( $wf_id ) {
      global $wpdb;
      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      $team_results = $wpdb->get_results( "SELECT ID, name, associated_workflows from {$teams_table}" );
      $associated_teams = array();
      foreach( $team_results as $team ) {
         $associated_workflows = json_decode( $team->associated_workflows, true );
         if( ! empty( $associated_workflows ) && ( in_array( $wf_id , $associated_workflows ) || in_array( -1 , $associated_workflows ) ) ) {
            array_push( $associated_teams, $team );
         }
      }
      
      return $associated_teams;
   }

   
   /**
    * Hook - delete_user
    * Delete team members from the team    *
    * @since 1.3    *
    */
   public function delete_team_member( $user_id ) {
      global $wpdb;

      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();
      $teams = $this->get_all_teams();
      foreach ( $teams as $team ) :
         if ( $team->user_id == $user_id ) {
            $wpdb->delete( $team_members_table, array( 'user_id' => $user_id, 'team_id' => $team->team_id ), array( '%d', '%d' ) );
         }
      endforeach;
   }
   
   /**
    * Hook - owf_workflow_delete
    * Delete associated workflows from all teams if workflow is deleted
    *
    * @param int $deleted_wf_id workflow id
    * @since 2.7
    */
   public function delete_associated_workflows( $deleted_wf_id ) {
      global $wpdb;
      // sanitize incoming data
      $deleted_wf_id = intval( $deleted_wf_id );
      
      $all_teams = $this->get_all_teams_basic_info();
      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      foreach ( $all_teams as $team ) {
         $team_id = $team->ID;
         $associated_workflows = json_decode( $team->associated_workflows );
         if ( ! empty ( $associated_workflows )  && in_array( $deleted_wf_id, $associated_workflows ) ) {
            $key = array_search( $deleted_wf_id, $associated_workflows );
            unset( $associated_workflows[ $key ] );
            $data = array(
               'associated_workflows' => json_encode( array_values( $associated_workflows ) )
            );
         $wpdb->update( $teams_table, $data, array( 'ID' => $team_id ) );
         }
      }
   }

   /**
    * Retrieve all teams with all user details
    * @global object $wpdb
    * @return array $teams
    */
   public function get_all_teams() {
      global $wpdb;
      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();

      $teams = $wpdb->get_results( "SELECT A.id AS team_id, A.name, A.description, A.associated_workflows, B.user_id AS user_id, B.role_name AS role
			FROM {$teams_table} AS A
			LEFT OUTER JOIN {$team_members_table} AS B ON A.id = B.team_id order by A.name" );
      return $teams;
   }

   /**
    * Returns an array/map with all the teams from all the sites
    * @global object $wpdb
    * @return array $all_site_teams
    */
   public function get_all_site_teams() {
      global $wpdb;

      $all_site_teams = array();
      $teams = $this->get_all_teams_basic_info();
      if ( ! empty( $teams ) ) {
         $all_site_teams[strval( $GLOBALS['blog_id'] )] = $teams;
      }
      return $all_site_teams;
   }

   /**
    * Retrives team by team id
    * @global object $wpdb
    * @param int $team_id
    * @return array $teams
    */
   public function get_team( $team_id ) {
      global $wpdb;
      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();

      $teams = $wpdb->get_results( $wpdb->prepare( "SELECT A.id AS team_id, A.name, A.description, A.associated_workflows, B.user_id AS user_id, B.role_name AS role
			FROM {$teams_table} AS A
			LEFT OUTER JOIN {$team_members_table} AS B ON A.id = B.team_id WHERE A.id = %d order by B.role_name", $team_id ) );

      return $teams;
   }
   
   /**
    * Retrives team name by team id
    * @global object $wpdb
    * @param int $team_id
    * @return array $team_name
    */
   public function get_team_name_by_id( $team_id ) {
      global $wpdb;
      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      $team_name = $wpdb->get_results( $wpdb->prepare( "SELECT name
			FROM {$teams_table} WHERE ID = %d ", $team_id ) );

      return $team_name;
   }

   /**
    * Add members to the team
    * @param int $team_id
    * @param array $team_members
    */
   public function add_members( $team_id, $team_members ) {
      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();

      if ( $team_id && ! empty( $team_members ) ) {
         foreach ( $team_members as $member ) :
            if ( $this->member_is_in_team( $team_id, $member ) ) {
               continue;
            }
            $team_member = explode( "@", $member );
            $team_member_data = array(
                'team_id' => $team_id,
                'user_id' => $team_member[0],
                'role_name' => $team_member[1],
                'create_datetime' => current_time( 'mysql' )
            );
            OW_Utility::instance()->insert_to_table( $team_members_table, $team_member_data );
         endforeach;
      }
   }

   /**
    * Check member is in team
    * @global object $wpdb
    * @param int $team_id
    * @param array $team_member
    * @return boolean
    */
   public function member_is_in_team( $team_id, $team_member ) {
      global $wpdb;
      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();

      if ( $team_id && $team_member ) {
         $member = explode( "@", $team_member );
         $sql = "SELECT DISTINCT user_id FROM {$team_members_table} WHERE team_id = %d AND user_id = %d AND role_name = %s";
         $query_result = $wpdb->get_var( $wpdb->prepare( $sql, trim( $team_id ), $member[0], $member[1] ) );
         if ( $query_result != null ) {
            return true;
         }

         return false;
      }

      return false;
   }

   /**
    * List the team information 
    * @global object $wp_roles
    */
   public function list_teams() {
      global $wp_roles;

      // Bulk action delete teams
      if ( isset( $_REQUEST['owt_bulk_action'] ) && sanitize_text_field( $_REQUEST['owt_bulk_action'] ) == "Apply" ) {
         $this->delete_teams();
      }

      // Get all the team information
      $teams = $this->get_all_teams();
      $all_teams = array();
      foreach ( $teams as $team ) {
         $all_teams[$team->team_id][] = array( "team_id" => $team->team_id, "team_name" => $team->name,"associated_workflow" => $team->associated_workflows, "user_id" => $team->user_id, "user_role" => $team->role );
      }
      $teams_with_members = array();
      $teams_without_members = array();
      foreach ( $all_teams as $k => $data ) {
         if ( $data[0]['user_id'] == '' ) {
            $teams_without_members[$k] = $data;
         } else {
            $teams_with_members[$k] = $data;
         }
      }
      ?>
      <div class="wrap">
         <h2>
            <?php echo __( "Teams", "owfteams" )?> 
               <?php if ( current_user_can( 'ow_create_teams' ) ) { ?> 
                  <a class="add-new-h2" href="<?php echo esc_url( add_query_arg( array( "page" => "add-new-team" ) ) ); ?>"><?php echo __( "Add New Team", "owfteams" )?></a>
               <?php } ?> 
         </h2>

         <ul class="subsubsub">
            <li class="all">
               <a <?php echo ( isset( $_GET['filter'] ) == '') ? "class='current'" : ''; ?> 
                  href="<?php echo admin_url( 'admin.php?page=oasiswf-teams' ); ?>"><?php _e( 'All', 'owfteams' ); ?>
                  <span class="count">(<?php echo count( $all_teams ); ?>)</span>
               </a> |
            </li>
            <li class="has_users">
               <a <?php echo ( isset( $_GET['filter'] ) && $_GET['filter'] == 'has_users') ? "class='current'" : ''; ?>
                  href="<?php echo esc_url( add_query_arg( array( "page" => "oasiswf-teams", "filter" => "has_users" ) ) ); ?>"><?php _e( 'Has Users', 'owfteams' ); ?>
                  <span class="count">(<?php echo count( $teams_with_members ); ?>)</span>
               </a> | 
            </li>
            <li class="no_users">
               <a <?php echo ( isset( $_GET['filter'] ) && $_GET['filter'] == 'no_users') ? "class='current'" : ''; ?>
                  href="<?php echo esc_url( add_query_arg( array( "page" => "oasiswf-teams", "filter" => "no_users" ) ) ); ?>"><?php _e( 'No Users', 'owfteams' ); ?>
                  <span class="count">(<?php echo count( $teams_without_members ); ?>)</span>
               </a>
            </li>
         </ul>

         <form method="post" action="" id="posts-filter">
            <br class="clear">
               <table class="wp-list-table widefat fixed posts">
                  <thead>
                     <tr>
                        <th style="" class="manage-column column-cb check-column" id="cb" scope="col">
                           <label for="cb-select-all-1" class="screen-reader-text"><?php _e( 'Select All', 'owfteams' ); ?></label>
                           <input type="checkbox" id="cb-select-all-1">
                        </th>
                        <th class="manage-column column-title" id="title" scope="col">
                           <?php _e( 'Team Name', 'owfteams' ); ?>
                        </th>
                        <th class="manage-column column-author" id="author" scope="col">
                           <?php _e( 'Users', 'owfteams' ); ?>
                        </th>
                        <th class="manage-column column-author" id="author" scope="col">
                           <?php _e( 'Associated Workflows', 'owfteams' ); ?>
                        </th>
                     </tr>
                  </thead>

                  <tfoot>
                     <tr>
                        <th style="" class="manage-column column-cb check-column" id="cb" scope="col">
                           <label for="cb-select-all-1" class="screen-reader-text"><?php _e( 'Select All', 'owfteams' ); ?></label>
                           <input type="checkbox" id="cb-select-all-1">
                        </th>
                        <th class="manage-column column-title" id="title" scope="col">
                           <?php _e( 'Team Name', 'owfteams' ); ?>
                        </th>
                        <th class="manage-column column-author" id="author" scope="col">
                           <?php _e( 'Users', 'owfteams' ); ?>
                        </th>
                        <th class="manage-column column-author" id="author" scope="col">
                           <?php _e( 'Associated Workflows', 'owfteams' ); ?>
                        </th>
                     </tr>
                  </tfoot>

                  <tbody id="the-list">
                     <?php
                     $num = 1;
                     if ( isset( $_GET['filter'] ) && $_GET['filter'] == "has_users" ) {
                        $all_teams = $teams_with_members;
                     } else if ( isset( $_GET['filter'] ) && $_GET['filter'] == "no_users" ) {
                        $all_teams = $teams_without_members;
                     }
                     if ( ! empty( $all_teams ) ) :
                        foreach ( $all_teams as $team_id => $team_data ):
                           $teams_members = array();
                           $post_count = $this->get_post_count_in_team( $team_id ); 
                           ?>
                           <tr class="<?php echo ($num % 2 != 0 ) ? 'alternate' : ''; ?>">
                              <th class="check-column" scope="row">
                                 <label for="cb-select-<?php echo $team_data[0]['team_id']; ?>"
                                        class="screen-reader-text">
                                        <?php printf( __( 'Select', 'owfteams' ) . '%s', $team_id ); ?>
                                 </label>
                                 <input type="checkbox" 
                                        value="<?php echo $team_data[0]['team_id']; ?>"
                                        name="teams[]" 
                                        class="teams-check" 
                                        id="cb-select-<?php echo $team_data[0]['team_id']; ?>"/>
                                 <div class="locked-indicator"></div>
                              </th>
                              
                              <?php
                              for ( $i = 0; $i < count( $team_data ); $i ++ ) {

                                 $teams_members[$team_data[$i]["user_role"]][] = array( "user_id" => $team_data[$i]["user_id"] );
                              }
                              ?> 
                              <td class="post-title page-title column-title">
                                 <strong>
                                    <a title="View/Edit"
                                       href="<?php echo esc_url( add_query_arg( array( "page" => "edit-team", "team" => $team_data[0]['team_id'], "action" => "edit" ) ) ); ?>"
                                       class="row-title">
                                       <?php echo $team_data[0]['team_name']; ?>
                                    </a>
                                 </strong>
                                 <div class='row-actions'>
                                    <span>
                                       <?php $current_team_id = $team_data[0]['team_id']; ?>
                                        <a href="<?php echo esc_url( add_query_arg( array( "page" => "view-team", "team" => $team_data[0]['team_id'], "action" => "view" ) ) ); ?>">
                                          <?php echo __( "View", 'owfteams' ) ?>
                                       </a>
                                    </span>
                                    <?php if ( current_user_can( 'ow_edit_teams' ) ) { ?>
                                    &nbsp;|&nbsp;        
                                    <span>
                                       <a href="<?php echo esc_url( add_query_arg( array( "page" => "edit-team", "team" => $team_data[0]['team_id'], "action" => "edit" ) ) ); ?>">
                                       <?php echo __( "Edit", 'owfteams' ) ?></a>
                                    </span> <?php } ?>                            
                                    <?php
                                    if ( current_user_can( 'ow_delete_teams' ) && $post_count == 0  ) {
                                    ?>
                                    &nbsp;|&nbsp;
                                    <span>
                                       <?php $current_team_id = $team_data[0]['team_id']; ?>
                                       <a href=<?php echo "javascript:void(0); onclick=\"owt_delete_team('" . $current_team_id . "')\""; ?>>
                                          <?php echo __( "Delete", 'owfteams' ) ?>
                                       </a>
                                    </span>
                                    <?php } ?>
                                  </div>
                              </td>
                              <td class="author column-author">
                                 <?php
                                 $user_list = '';
                                 foreach ( $teams_members as $role => $users ) {
                                    if ( $role == "" ) {
                                       _e( 'No Users', 'owfteams' );
                                       break;
                                    } else {
                                       $user_list .= count( $users ) . " " . $wp_roles->role_names[$role] . "(s) | ";
                                    }
                                 }
                                 echo substr( $user_list, 0, -2 );
                                 ?>
                              </td>
                              <td>
                                 <?php $workflows = json_decode( $team_data[0]['associated_workflow'], true ) ;
                                 if ( ! empty( $workflows ) ) {
                                    $all_workflows_associated = false;
                                    foreach( $workflows as $workflow_id ) {
                                       if ( $workflow_id == "-1") { // all workflows
                                          echo __( 'All Workflows' , 'owfteams' );
                                          $all_workflows_associated = true;
                                          break;
                                       }
                                    }
                                    if ( ! $all_workflows_associated ) {
                                       $total_associated_workflow = count( $workflows );
                                       echo $total_associated_workflow . " " . __( 'Associated Workflow(s)', 'owfteams' );
                                    }
                                 } else {
                                     _e( 'No Associated Workflow(s)', 'owfteams' );
                                 }
?>
                              </td>                                 
                           </tr>
                              <?php
                              $num ++;
                           endforeach; // Outer loop
                           else:
                           ?>
                           <tr>
                              <td colspan="3"><?php _e( 'No teams found.', 'owfteams' ); ?></td>
                           </tr>     
            <?php endif; ?>
                  </tbody>
              </table>

               <div class="tablenav bottom">
                  <div class="alignleft actions bulkactions">
                     <select name="action2">
                        <option selected="selected" value="-1"><?php _e( 'Bulk Actions', 'owfteams' ); ?></option>
                        <?php  if ( current_user_can( 'ow_delete_teams' ) ) { ?>
                        <option class="hide-if-no-js" value="delete"><?php _e( 'Delete', 'owfteams' ); ?></option>
                        <?php } ?>
                     </select>
                     <input type="submit" value="Apply" class="button action" id="owt_bulk_action2" name="owt_bulk_action">
                  </div>
                  <div class="alignleft actions"></div>
                  <br class="clear">
               </div>
               <?php wp_nonce_field( 'bulk_delete_teams', '_delete_teams', false ); ?>
         </form>
          <br class="clear">
      </div>
      <?php
   }

   /**
    * Renders Team add/edit page
    * @global object $wp_roles
    */
   public function add_or_edit_team() {
      global $wp_roles;

      if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' ) {
         $team_id = intval( $_GET['team'] );
         $team_info = $this->get_team( $team_id );
         $associated_workflows = json_decode( $team_info[0]->associated_workflows );
      }
      ?>
      <div class="wrap" style="margin: 1em 3em;">
         <?php
         echo (isset( $_GET['action'] ) && $_GET['action'] == 'edit') ? '<h2>' . __( 'Edit Workflow Team', 'owfteams' ) . '</h2>' : '<h2>' . __( 'New Workflow Team', 'owfteams' ) . '</h2>';
          ?>
         <div id="message" class="error owf-error owf-hidden"></div>
            <div class="container">             
               <form method="post">
                  <div class="select-info">
                     <div class="list-section-heading">
                        <label class="bold-label"><?php _e( 'Name', 'owfteams' ); ?></label>
                     </div>
                     <input type="text" 
                            class="form-element" 
                            name="workflow_team_name" 
                            id="workflow_team_name" 
                            value="<?php echo ! empty( $team_info ) ? $team_info[0]->name : ''; ?>">
                     <br/>
                     <span class="description">
                        <?php _e( 'Assign a name for the team to recognize it.', 'owfteams' ); ?>
                     </span>
                  </div>
                  <br>
                  <div class="select-info">
                     <div class="list-section-heading">
                        <label class="bold-label"><?php _e( 'Description', 'owfteams' ); ?></label>
                     </div>
                     <textarea name="workflow_team_desc" 
                               id="workflow_team_desc" 
                               rows="4" cols="85" class="form-element"><?php echo ! empty( $team_info ) ? $team_info[0]->description : ''; ?></textarea>
                     <br/>
                     <span class="description">
                        <?php _e( 'Add description about the team.', 'owfteams' ); ?>
                     </span>
                  </div>
                  <br>
                  <div class="select-info">
                     <div class="list-section-heading">
                        <label class="bold-label"><?php _e( 'Add Member(s) to the Team', 'owfteams' ); ?></label>
                     </div>
                     <select name="add_user_to_team[]" id="add_user_to_team" class="" multiple="multiple">
                        <?php
                           // Get the users according to the selected participants on the workflow settings tab.
                           $participants = get_option( 'oasiswf_participating_roles_setting' );
                           
                              foreach ( $participants as $role => $name ) {
                                 echo "<optgroup label='$name'>";
                                 $users = OW_Teams_Utility::instance()->get_users_by_roles( array( $role => $name ) );
                                 if ( !empty( $users ) ) {
                                    foreach ( $users as $user ) {
                                       echo "<option value='" . $user->ID . '@' . $role . "'>$user->name</option>";
                                    }
                                 }
                                       echo "</optgroup>";
                              }
                        ?>
                     </select>
                     <br/><span class="description"><?php _e( 'Select users to add them to the team.', 'owfteams' ); ?></span>
                  </div>

                  <fieldset>
                     <legend><?php _e( 'Existing Team Member(s)', 'owfteams' ); ?></legend>
                      <?php
                      if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' ) {
                         foreach ( $team_info as $team_user ) :
                            $user_object = new WP_User( $team_user->user_id );

                            $team_members[] = array(
                                "user_id" => $user_object->ID,
                                "user_login" => $user_object->user_login,
                                "user_name" => $user_object->display_name,
                                "user_email" => $user_object->user_email,
                                "user_role" => $team_user->role
                            );
                         endforeach;
                      }
                      ?>

                        <table class="wp-list-table widefat fixed members">
                           <thead>
                              <tr>
                                 <td class="manage-column column-cb check-column" 
                                     id="cb" scope="col">
                                    <label for="cb-select-all-1" 
                                           class="screen-reader-text">
                                           <?php _e( 'Select All', 'owfteams' ); ?>
                                    </label>
                                    <input type="checkbox" id="cb-select-all-1">
                                 </td>
                                 <th class="manage-column column-username" 
                                     scope="col">
                                    <span><?php _e( 'User Name', 'owfteams' ); ?></span>
                                    <span class="sorting-indicator"></span>
                                 </th>
                                 <th class="manage-column column-name" 
                                     scope="col">
                                    <span><?php _e( 'Name', 'owfteams' ); ?></span>
                                    <span class="sorting-indicator"></span>
                                 </th>
                                 <th class="manage-column column-email" 
                                     scope="col">
                                    <span><?php _e( 'Email', 'owfteams' ); ?></span>
                                    <span class="sorting-indicator"></span>
                                 </th>
                                 <th class="manage-column column-role" 
                                     scope="col">
                                    <span><?php _e( 'Role', 'owfteams' ); ?></span>
                                    <span class="sorting-indicator"></span>
                                 </th>
                              </tr>
                           </thead>

                           <tfoot>
                              <tr>
                                 <td class="manage-column column-cb check-column" 
                                      id="cb" scope="col">
                                    <label for="cb-select-all-1" 
                                           class="screen-reader-text">
                                           <?php _e( 'Select All', 'owfteams' ); ?>
                                    </label>
                                    <input type="checkbox" id="cb-select-all-1">
                                 </td>
                                 <th class="manage-column column-username" 
                                     scope="col">
                                    <span><?php _e( 'User Name', 'owfteams' ); ?></span>
                                    <span class="sorting-indicator"></span>
                                 </th>
                                 <th class="manage-column column-name" 
                                     scope="col">
                                    <span><?php _e( 'Name', 'owfteams' ); ?></span>
                                    <span class="sorting-indicator"></span></th>
                                 <th class="manage-column column-email" 
                                     scope="col">
                                    <span><?php _e( 'Email', 'owfteams' ); ?></span>
                                    <span class="sorting-indicator"></span>
                                 </th>
                                 <th class="manage-column column-role" 
                                     scope="col">
                                    <span><?php _e( 'Role', 'owfteams' ); ?></span>
                                    <span class="sorting-indicator"></span>
                                 </th>
                              </tr>
                           </tfoot>

                           <tbody id="the-list">
                              <?php
                              if ( ! empty( $team_members ) && $team_members[0]['user_id'] ) :
                                 $num = 0;
                                 foreach ( $team_members as $member ):
                                    ?>
                                    <tr id="user-<?php echo $member['user_id']; ?>"
                                        class="<?php echo ($num % 2 != 0 ) ? 'alternate' : ''; ?>">
                                       <th class="check-column" scope="row">
                                          <label for="cb-select-<?php echo $member['user_id'] . "-" . $member['user_role']; ?>"
                                                 class="screen-reader-text">Select test
                                          </label>
                                          <input type="checkbox" 
                                                 value="<?php echo $member['user_id'] . "@" . $member['user_role']; ?>" 
                                                 class="members-check" 
                                                 name="user[]" 
                                                 id="cb-select-<?php echo $member['user_id'] . "-" . $member['user_role']; ?>">
                                          <div class="locked-indicator"></div>
                                       </th>
                                       <td class="username column-username">
                                          <strong>
                                             <?php echo get_avatar( $member['user_id'], 32, '', $member['user_name'] ); ?>
                                                <a title="View Profle of <?php echo $member['user_name']; ?>"
                                                  href="<?php echo admin_url( 'user-edit.php?user_id=' . $member['user_id'] ); ?>" 
                                                  class="row-title">
                                                <?php echo $member['user_login']; ?></a></strong>
                                       </td>
                                       <td class="name column-name">
                                          <strong><?php echo $member['user_name']; ?></strong>
                                       </td>
                                       <td class="email column-email">
                                          <strong>
                                             <a title="mail to <?php echo $member['user_name']; ?>" 
                                                     href="mailto:<?php echo $member['user_email']; ?>" class="row-title"><?php echo $member['user_email']; ?></a>
                                          </strong>
                                       </td>
                                       <td class="role column-role">
                                          <?php echo ($wp_roles->role_names[$member['user_role']]); ?>
                                       </td>
                                    </tr>
                                 <?php
                                 $num ++;
                                 endforeach;
                              else:
                              ?>
                              <tr>
                                 <td colspan="5"><?php _e( 'No team members found.', 'owfteams' ); ?></td>
                              </tr>
                              <?php
                              endif;
                              ?>

                           </tbody>
                        </table>
                        <?php                        
                        if ( ! empty( $team_id ) && ( current_user_can( 'ow_edit_teams' ) || current_user_can( 'ow_create_teams' ) ) ) :

                        ?>
                           <div class="tablenav bottom">
                              <div class="alignleft actions bulkactions">
                                 <select name="delete_members_action">
                                    <option selected="selected" value="-1"><?php _e( 'Bulk Actions', 'owfteams' ); ?></option>
                                    <option class="hide-if-no-js" value="delete"><?php _e( 'Delete', 'owfteams' ); ?></option>
                                 </select>
                                 <?php wp_nonce_field( 'delete_selected_team_members', '_bulk_remove_members', false ); ?>
                                 <input type="submit" value="Apply" class="button action" id="delete_members" name="delete_members" data-team = "<?php echo $team_id; ?>">
                              </div>
                              <div class="alignleft actions"></div>
                              <br class="clear">
                           </div>
                        <?php
                        endif;
                        ?>
                  </fieldset>
                  <br/>
                  <?php
                  $ow_workflow_service = new OW_Workflow_Service();
                  $workflows = $ow_workflow_service->get_workflow_list( "active" );
                  ?>
                  <div class="select-info">
                     <div class="list-section-heading">
                        <label class="bold-label"><?php _e( 'Assign Workflow(s) to the Team', 'owfteams' ); ?></label>
                     </div>
                     <?php
                     if ( ! empty( $team_id ) ) {
                        $selected = "";                       
                     } else { 
                        $selected = " ' selected='selected' ";
                     }
                     ?>
                     <select name="add_workflow_to_team[]" id="add_workflow_to_team" class="" multiple="multiple">
                        <option value="-1" <?php echo $selected; ?> ><?php _e( 'All Workflows', 'owfteams' ); ?></option>
                        <?php
                        foreach ( $workflows as $workflow ) {
                           if ( $workflow->version == 1 )
                              echo "<option value={$workflow->ID}>" . $workflow->name . "</option>";
                           else
                              echo "<option value={$workflow->ID}>" . $workflow->name . " (" . $workflow->version . ")" . "</option>";
                        }
                        ?>
                     </select>
                     <br/>
                     <span class="description">
                        <?php _e( 'Select Workflows to associate with team.', 'owfteams' ); ?>
                     </span>
                     <fieldset>
                        <legend><?php _e( 'Associated Workflow(s)', 'owfteams' ); ?></legend>
                        <table class="wp-list-table widefat fixed members">
                           <thead>
                           <tr>
                              <td class="manage-column column-cb check-column"
                                  id="cb" scope="col">
                                 <label for="cb-select-all-1"
                                        class="screen-reader-text">
                                    <?php _e( 'Select All', 'owfteams' ); ?>
                                 </label>
                                 <input type="checkbox" id="cb-select-all-1">
                              </td>
                              <th class="manage-column"
                                  scope="col">
                                 <span><?php _e( 'Workflow Name', 'owfteams' ); ?></span>
                                 <span class="sorting-indicator"></span>
                              </th>
                              <th class="manage-column"
                                  scope="col">
                                 <span><?php _e( 'Version', 'owfteams' ); ?></span>
                                 <span class="sorting-indicator"></span>
                              </th>
                              <th class="manage-column"
                                  scope="col">
                                 <span><?php _e( 'Start Date', 'owfteams' ); ?></span>
                                 <span class="sorting-indicator"></span>
                              </th>
                              <th class="manage-column"
                                  scope="col">
                                 <span><?php _e( 'End Date', 'owfteams' ); ?></span>
                                 <span class="sorting-indicator"></span>
                              </th>
                           </tr>
                           </thead>
                           <tfoot>
                           <tr>
                              <td class="manage-column column-cb check-column"
                                  id="cb" scope="col">
                                 <label for="cb-select-all-1"
                                        class="screen-reader-text">
                                    <?php _e( 'Select All', 'owfteams' ); ?>
                                 </label>
                                 <input type="checkbox" id="cb-select-all-1">
                              </td>
                              <th class="manage-column"
                                  scope="col">
                                 <span><?php _e( 'Workflow Name', 'owfteams' ); ?></span>
                                 <span class="sorting-indicator"></span>
                              </th>
                              <th class="manage-column"
                                  scope="col">
                                 <span><?php _e( 'Version', 'owfteams' ); ?></span>
                                 <span class="sorting-indicator"></span>
                              </th>
                              <th class="manage-column"
                                  scope="col">
                                 <span><?php _e( 'Start Date', 'owfteams' ); ?></span>
                                 <span class="sorting-indicator"></span>
                              </th>
                              <th class="manage-column"
                                  scope="col">
                                 <span><?php _e( 'End Date', 'owfteams' ); ?></span>
                                 <span class="sorting-indicator"></span>
                              </th>
                           </tr>
                           </tfoot>
                           <tbody>
                           <?php
                           if ( ! empty( $associated_workflows ) ) {
                              foreach ( $associated_workflows as $workflow_id ) { 
                                 // If workflow id is -1 set default values
                                 if ( $workflow_id == -1 ) {
                                    $workflow_parameters[] = array(
                                    "workflow_id"   => $workflow_id,
                                    "workflow_name" => __( 'All Workflows', 'owfteams' ),
                                    "version"       => "-",
                                    "start_date"    => "-",
                                    "end_date"      => "-"
                                    );
                                 } else {
                                    $workflows = $ow_workflow_service->get_workflow_by_id( $workflow_id );
                                    $workflow_parameters[] = array(
                                       "workflow_id"   => $workflows->ID,
                                       "workflow_name" => $workflows->name,
                                       "version"       => $workflows->version,
                                       "start_date"    => $workflows->start_date,
                                       "end_date"      => $workflows->end_date
                                    );
                                 }
                              }
                           }
                           
                           if ( ! empty( $workflow_parameters ) && $workflow_parameters[0]['workflow_id'] ) :
                              $num = 0;
                              foreach ( $workflow_parameters as $workflow ) { ?>
                                 <tr id="workflow-<?php echo $workflow['workflow_id']; ?>"
                                     class="<?php echo ($num % 2 != 0 ) ? 'alternate' : ''; ?>">
                                    <th class="check-column" scope="row">
                                       <input type="checkbox"
                                              value="<?php echo $workflow['workflow_id']; ?>"
                                              class="workflow-check"
                                              name="workflow[]"
                                              id="cb-select-wf-<?php echo $workflow['workflow_id']; ?>">
                                       <div class="locked-indicator"></div>
                                    </th>
                                    <td>
                                       <a href="<?php echo admin_url( 'admin.php?page=oasiswf-admin&wf_id=' . $workflow['workflow_id'] ); ?>"
                                          class="row-title">
                                          <?php echo $workflow['workflow_name']; ?>
                                       </a>
                                    </td>
                                    <td>
                                       <strong><?php echo $workflow['version']; ?></strong>
                                    </td>
                                    <?php
                                    if ( $workflow['workflow_id'] == -1 ) {
                                       $start_date = $workflow['start_date'] ;
                                       $end_date   = $workflow['end_date'];
                                    } else {
                                       $start_date = OW_Utility::instance()->format_date_for_display( $workflow['start_date'] ) ;
                                       $end_date   = OW_Utility::instance()->format_date_for_display( $workflow['end_date'] );
                                    }
                                    ?>
                                    <td>
                                       <strong><?php echo $start_date; ?></strong>
                                    </td>
                                    <td>
                                       <strong><?php echo $end_date; ?></strong>
                                    </td>
                                 </tr>
                                 <?php
                                 $num ++;
                              }
                           else :
                              ?>
                              <tr>
                                 <td colspan="5"><?php _e( 'No associated workflow found.', 'owfteams' ); ?></td>
                              </tr>
                              <?php
                           endif;
                           ?>
                           </tbody>
                        </table>
                        <?php
                        if ( ! empty( $team_id ) && ( current_user_can( 'ow_edit_teams' ) || current_user_can( 'ow_create_teams' ) ) ) :
                           ?>
                           <div class="tablenav bottom">
                              <div class="alignleft actions bulkactions">
                                 <select name="delete_workflow_action">
                                    <option selected="selected" value="-1">
                                       <?php _e( 'Bulk Actions', 'owfteams' ); ?>
                                    </option>
                                    <option class="hide-if-no-js" value="delete">
                                       <?php _e( 'Delete', 'owfteams' ); ?>
                                    </option>
                                 </select>
                                 <?php wp_nonce_field( 'delete_selected_workflows', '_bulk_remove_workflows', false ); ?>
                                 <input type="submit" value="Apply" class="button action" id="delete_workflows" name="delete_workflows" data-team = "<?php echo $team_id; ?>">
                              </div>
                              <div class="alignleft actions"></div>
                              <br class="clear">
                           </div>
                           <?php
                        endif;
                        ?>
                     </fieldset>

                  </div>
                  <br/>
                  <?php
                     if ( ( ! empty( $team_id ) && ( current_user_can( 'ow_edit_teams' ) ) || current_user_can( 'ow_create_teams' ) ) ) :
                  ?>
                  <div class="select-info">
                     <?php
                     wp_nonce_field( 'save_workflow_team', '_save_team', false );
                     if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' ) {
                        $team_id = intval( $_GET['team'] );
                        echo '<input type="hidden" name="team_action" id="team_action" value="update_team">';
                        echo '<input type="hidden" name="team_id" id="team_id" value="' . $team_id . '">';
                     }
                     ?>
                     <input type="submit" name="workflow_team_save" id="workflow_team_save" value="Save" class="button button-primary button-large"/>
                  </div>
                  <?php
                     endif;
                  ?>
               </form>
            </div>
         </div>
      <?php
   }
   
   /**
    * Renders Team view page
    * Display the list of users and workflow associated with particualr team
    * @global object $wp_roles
    */
   public function view_team_associates() {
      global $wp_roles;
      if ( isset( $_GET['action'] ) && $_GET['action'] == 'view' ) {
         $team_id = intval( $_GET['team'] );
         $team_info = $this->get_team( $team_id );
         $associated_workflows = json_decode( $team_info[0]->associated_workflows );
      } 
      $ow_workflow_service = new OW_Workflow_Service();
      ?>
      <div class="wrap" style="margin: 1em 3em;">
         <?php
         echo '<h2>' . $team_info[0]->name . '</h2>';
          ?>
         <div class="container">
            <fieldset>
               <legend><?php _e( 'Existing Team Member(s)', 'owfteams' ); ?></legend>
               <?php
               if ( isset( $_GET['action'] ) && $_GET['action'] == 'view' ) {
                  foreach ( $team_info as $team_user ) :
                     $user_object = new WP_User( $team_user->user_id );

                     $team_members[] = array(
                         "user_id" => $user_object->ID,
                         "user_login" => $user_object->user_login,
                         "user_name" => $user_object->display_name,
                         "user_email" => $user_object->user_email,
                         "user_role" => $team_user->role
                     );
                  endforeach;
               }
               ?>

               <table class="wp-list-table widefat fixed members">
                  <thead>
                     <tr>                                
                        <th class="manage-column column-username" 
                            scope="col">
                           <span><?php _e( 'User Name', 'owfteams' ); ?></span>
                           <span class="sorting-indicator"></span>
                        </th>
                        <th class="manage-column column-name" 
                            scope="col">
                           <span><?php _e( 'Name', 'owfteams' ); ?></span>
                           <span class="sorting-indicator"></span>
                        </th>
                        <th class="manage-column column-email" 
                            scope="col">
                           <span><?php _e( 'Email', 'owfteams' ); ?></span>
                           <span class="sorting-indicator"></span>
                        </th>
                        <th class="manage-column column-role" 
                            scope="col">
                           <span><?php _e( 'Role', 'owfteams' ); ?></span>
                           <span class="sorting-indicator"></span>
                        </th>
                     </tr>
                  </thead>

                  <tfoot>
                     <tr>                                
                        <th class="manage-column column-username" 
                            scope="col">
                           <span><?php _e( 'User Name', 'owfteams' ); ?></span>
                           <span class="sorting-indicator"></span>
                        </th>
                        <th class="manage-column column-name" 
                            scope="col">
                           <span><?php _e( 'Name', 'owfteams' ); ?></span>
                           <span class="sorting-indicator"></span></th>
                        <th class="manage-column column-email" 
                            scope="col">
                           <span><?php _e( 'Email', 'owfteams' ); ?></span>
                           <span class="sorting-indicator"></span>
                        </th>
                        <th class="manage-column column-role" 
                            scope="col">
                           <span><?php _e( 'Role', 'owfteams' ); ?></span>
                           <span class="sorting-indicator"></span>
                        </th>
                     </tr>
                  </tfoot>

                  <tbody id="the-list">
                     <?php
                     if ( ! empty( $team_members ) && $team_members[0]['user_id'] ) :
                        $num = 0;
                        foreach ( $team_members as $member ):
                           ?>
                           <tr id="user-<?php echo $member['user_id']; ?>"
                               class="<?php echo ($num % 2 != 0 ) ? 'alternate' : ''; ?>">                              
                              <td class="username column-username">
                                 <strong>
                                    <?php echo get_avatar( $member['user_id'], 32, '', $member['user_name'] ); ?>
                                       
                                       <?php echo $member['user_login']; ?></strong>
                              </td>
                              <td class="name column-name">
                                 <strong><?php echo $member['user_name']; ?></strong>
                              </td>
                              <td class="email column-email">
                                 <strong>
                                    <a title="mail to <?php echo $member['user_name']; ?>" 
                                            href="mailto:<?php echo $member['user_email']; ?>" class="row-title"><?php echo $member['user_email']; ?></a>
                                 </strong>
                              </td>
                              <td class="role column-role">
                                 <?php echo ($wp_roles->role_names[$member['user_role']]); ?>
                              </td>
                           </tr>
                        <?php
                        $num ++;
                        endforeach;
                     else:
                     ?>
                     <tr>
                        <td colspan="5"><?php _e( 'No team members found.', 'owfteams' ); ?></td>
                     </tr>
                     <?php
                     endif;
                     ?>

                  </tbody>
               </table>                       
            </fieldset>            
             
            <fieldset>
               <legend><?php _e( 'Associated Workflow(s)', 'owfteams' ); ?></legend>
                        
            <table class="wp-list-table widefat fixed members">
               <thead>
               <tr>                  
                  <th class="manage-column"
                      scope="col">
                     <span><?php _e( 'Workflow Name', 'owfteams' ); ?></span>
                     <span class="sorting-indicator"></span>
                  </th>
                  <th class="manage-column"
                      scope="col">
                     <span><?php _e( 'Version', 'owfteams' ); ?></span>
                     <span class="sorting-indicator"></span>
                  </th>
                  <th class="manage-column"
                      scope="col">
                     <span><?php _e( 'Start Date', 'owfteams' ); ?></span>
                     <span class="sorting-indicator"></span>
                  </th>
                  <th class="manage-column"
                      scope="col">
                     <span><?php _e( 'End Date', 'owfteams' ); ?></span>
                     <span class="sorting-indicator"></span>
                  </th>
               </tr>
               </thead>
               <tfoot>
               <tr>                  
                  <th class="manage-column"
                      scope="col">
                     <span><?php _e( 'Workflow Name', 'owfteams' ); ?></span>
                     <span class="sorting-indicator"></span>
                  </th>
                  <th class="manage-column"
                      scope="col">
                     <span><?php _e( 'Version', 'owfteams' ); ?></span>
                     <span class="sorting-indicator"></span>
                  </th>
                  <th class="manage-column"
                      scope="col">
                     <span><?php _e( 'Start Date', 'owfteams' ); ?></span>
                     <span class="sorting-indicator"></span>
                  </th>
                  <th class="manage-column"
                      scope="col">
                     <span><?php _e( 'End Date', 'owfteams' ); ?></span>
                     <span class="sorting-indicator"></span>
                  </th>
               </tr>
               </tfoot>
               <tbody>
               <?php
               if ( ! empty( $associated_workflows ) ) {
                  foreach ( $associated_workflows as $workflow_id ) { 
                     // If workflow id is -1 set default values
                     if ( $workflow_id == -1 ) {
                        $workflow_parameters[] = array(
                        "workflow_id"   => $workflow_id,
                        "workflow_name" => __( 'All Workflows', 'owfteams' ),
                        "version"       => "-",
                        "start_date"    => "-",
                        "end_date"      => "-"
                        );
                     } else {
                        $workflows = $ow_workflow_service->get_workflow_by_id( $workflow_id );
                        $workflow_parameters[] = array(
                           "workflow_id"   => $workflows->ID,
                           "workflow_name" => $workflows->name,
                           "version"       => $workflows->version,
                           "start_date"    => $workflows->start_date,
                           "end_date"      => $workflows->end_date
                        );
                     }
                  }
               }

               if ( ! empty( $workflow_parameters ) && $workflow_parameters[0]['workflow_id'] ) :
                  $num = 0;
                  foreach ( $workflow_parameters as $workflow ) { ?>
                     <tr id="workflow-<?php echo $workflow['workflow_id']; ?>"
                         class="<?php echo ($num % 2 != 0 ) ? 'alternate' : ''; ?>">                        
                        <td>
                            <?php if ( current_user_can( 'ow_edit_workflow' ) ) { ?>
                              <a href="<?php echo admin_url( 'admin.php?page=oasiswf-admin&wf_id=' . $workflow['workflow_id'] ); ?>"
                                 class="row-title">
                                 <?php echo $workflow['workflow_name']; ?>
                              </a>
                            <?php } else {
                              echo $workflow['workflow_name'];
                            }?>
                        </td>
                        <td>
                           <strong><?php echo $workflow['version']; ?></strong>
                        </td>
                        <?php
                        if ( $workflow['workflow_id'] == -1 ) {
                           $start_date = $workflow['start_date'] ;
                           $end_date   = $workflow['end_date'];
                        } else {
                           $start_date = OW_Utility::instance()->format_date_for_display( $workflow['start_date'] ) ;
                           $end_date   = OW_Utility::instance()->format_date_for_display( $workflow['end_date'] );
                        }
                        ?>
                        <td>
                           <strong><?php echo $start_date; ?></strong>
                        </td>
                        <td>
                           <strong><?php echo $end_date; ?></strong>
                        </td>
                     </tr>
                     <?php
                     $num ++;
                  }
               else :
                  ?>
                  <tr>
                     <td colspan="5"><?php _e( 'No associated workflow found.', 'owfteams' ); ?></td>
                  </tr>
                  <?php
               endif;
               ?>
               </tbody>
            </table>
         </fieldset>
         </div>
      </div>    
   <?php }


   /**
    * Get the team members
    * @global object $wpdb
    * @param int $team_id
    * @param array $assignee_roles
    * @param int $post_id
    * @return array $members
    */
   public function get_team_members( $team_id, $assignee_roles, $post_id ) {
      global $wpdb;
      $teams_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();
      $post_author_id = "";

      if ( $post_id != null ) {
         $post = get_post( $post_id );
         $post_author_id = $post->post_author;
      }
      $members = $user_roles = array();

      if ( ! empty( $assignee_roles ) ) {
         foreach ( $assignee_roles as $k => $ar ) :
            if ( $k == 'owfpostauthor' ) {
               $members[] = $post_author_id;
               continue;
            }
            $user_roles[] = $k;
         endforeach;
      }

      if ( ! empty( $user_roles ) ) {
         $role_name_query = " ( ";
         for ( $i = 1; $i <= count( $user_roles ); $i ++ ) {
            $user_role = $user_roles[$i - 1];
            $role_name_query = $role_name_query . " role_name = '" . $user_role . "' ";
            if ( $i != count( $user_roles ) ) {
               $role_name_query = $role_name_query . " OR ";
            }
         }
         $role_name_query = $role_name_query . " ) ";
         $sql = "SELECT DISTINCT user_id FROM {$teams_members_table} WHERE team_id = %d AND " . $role_name_query;
         $team_users = $wpdb->get_results( $wpdb->prepare( $sql, trim( $team_id ) ) );
         if ( ! empty( $team_users ) ) {
            foreach ( $team_users as $user ) :
               if ( ! in_array( $user->user_id, $members ) ) {
                  $members[] = $user->user_id;
               }
            endforeach;
         }
      }
      return $members;
   }
   
   /**
    * Build option list for teams and corresponding blog IDs
    * @param array $site_teams
    * @param array $selected_teams_array
    * @param int $blog_id
    * @return html
    */
   public function build_auto_submit_teams_options( $site_teams, $selected_teams_array, $blog_id ) {
      // TODO : blog id, might be redundant information, since teams and workflows are site specific
      $selected = " selected = selected";
      if ( ! empty( $site_teams ) ) {
      	echo "<option value=''>" . __("--Select a Team--", "owfteams") . "</option>";
         foreach ( $site_teams as $team ) {
            $option_value = $blog_id . "@" . $team->ID;
            if ( in_array( $option_value, $selected_teams_array ) ) {
               echo "<option value='" . $option_value . "'" . $selected . ">" . $team->name . "</option>";
            } else {
               echo "<option value='" . $option_value . "'>" . $team->name . "</option>";
            }
         }
      }
   }
   
   /**
    * Validate Workflow roles for task assignee
    * @global type $wpdb
    * @param array $associated_workflows
    * @param string $team_action 
    * @return array $validation_message
    * @since 2.7
    */
   private function validate_team_workflow_roles( $associated_workflows, $validation_messages, $team_members ) {
      global $wpdb;
      global $wp_roles;

      if ( empty( $associated_workflows ) ) { //nothing to validate, so return
         return $validation_messages;
      }

      // let's create a list of unique roles in the team
      $team_roles = array();

      if ( ! empty( $team_members ) ) {
         foreach ( $team_members as $member ) :
            $team_member = explode( "@", $member );
            $associated_roles_for_team[] = $team_member[1];
         endforeach;

         $team_roles = array_unique( $associated_roles_for_team );
      }

      $ow_workflow_service = new OW_Workflow_Service();
      $associated_roles_for_workflow = array();

      // if $associated_workflows not empty, loop through the workflow and get all the roles
      foreach ( $associated_workflows as $key => $workflow_id ) { 
         if ( $workflow_id == -1 ) {
            continue;
         }
         // Get workflow parameters by workflow id
         $workflow = $ow_workflow_service->get_workflow_by_id( $workflow_id );
         $workflow_name = $workflow->name;

         // Get all workflow steps by workflow id
         $workflow_steps = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . OW_Utility::instance()->get_workflow_steps_table_name() . " WHERE workflow_id = %d", $workflow_id ) );

         // For each step get the associated assignee
         foreach ( $workflow_steps as $step ) {
            $step_info = json_decode( $step->step_info );
            $task_assignee = $step_info->task_assignee;

            // if step is assigned to a role, add the role to $workflow_roles
            if ( ! empty( $task_assignee->roles ) ) {
               foreach( $task_assignee->roles as $role ) {
                  $associated_roles_for_workflow[] = $role;
               }

            }
         }
      }

      $workflow_roles = array_unique( $associated_roles_for_workflow );

      $required_roles = array();
      // lets find out if the team has all the roles from the workflow
      foreach ( $workflow_roles as $role ) {
         if ( $role == 'owfpostauthor' ) { // this is a dynamic role
            continue;
         }
         if ( ! in_array( $role, $team_roles ) ) {
            $required_roles[] = $wp_roles->role_names[ $role ];
         }
      }

      if ( ! empty( $required_roles ) ) {
         $validation_message = __( "Team doesn't have sufficient members to satisfy all the roles in the workflow. ", "owfteams" );
         $validation_message .= __( "Please add member(s) with the following roles: ", "owfteams" );
         $validation_message .= implode(', ', $required_roles );
         $validation_messages[] = $validation_message;
      }

      return $validation_messages;
   }
   
   /**
    * Retrives team member roles
    * @global object $wpdb
    * @param int $user_id
    * @param string $old_role
    * @return array $user_roles
    */
   public function get_team_member_user_roles( $user_id, $old_role ) {
      global $wpdb;
      $team_members_table = OW_Teams_Utility::instance()->get_teams_members_table_name();
      $sql = "SELECT ID, role_name FROM ". $team_members_table . " WHERE user_id = %d AND role_name = %s ";
      $user_roles = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $old_role ) );
      return $user_roles;
   }   
   
   /**
    * Retrieve all teams with basic info
    * @global object $wpdb
    * @return array|null|object
    */
   private function get_all_teams_basic_info() {
      global $wpdb;
      $teams_table = OW_Teams_Utility::instance()->get_teams_table_name();
      $teams = $wpdb->get_results( "SELECT ID, name, associated_workflows from {$teams_table}" );
      return $teams;
   }
   
   /**
    * Hook - set_user_role
    * Update team member roles when admin changes the user role
    * @param int $user_id
    * @param int $new_role 
    * @param array $old_roles
    * @since 2.7
    */
   public function update_team_member_role( $user_id, $new_role, $old_roles ) {
      global $wpdb;
      
      /* If additional roles are provided to the user role than $old_roles
       * returns all the user roles where index 0 is the original user role
       */
      if( ! empty( $old_roles ) ) {
         $old_role            = $old_roles[0];
         $user_roles          = $this->get_team_member_user_roles( $user_id, $old_role );
         $team_members_table  = OW_Teams_Utility::instance()->get_teams_members_table_name();
         foreach ( $user_roles as $values ) {
            $member_id  = $values->ID;
            $role_name  = $values->role_name;         
               $data = array( 'role_name' => $new_role );
               $wpdb->update( $team_members_table, $data, array( 'ID' => $member_id,
                  'user_id' => $user_id ) );
         }
      }
   }
   
   /**
    * Hook - owf_get_team_members
    * Get the team members using step id
    * @param int $team_id
    * @param int $step_id 
    * @param int $post_id
    * @return string $actors
    * @since 2.0
    */
   public function get_team_members_by_step_id( $team_id, $step_id, $post_id ) {
      if ( $team_id != '' ) {
         $team_id = intval( $team_id );
         $step_id = intval( $step_id );
         $post_id = intval( $post_id );
         $ow_workflow_service = new OW_Workflow_Service();
         $step = $ow_workflow_service->get_step_by_id( $step_id );
         $step_info = json_decode( $step->step_info );
         $assignee_roles = isset( $step_info->task_assignee->roles ) ? array_flip( $step_info->task_assignee->roles ) : null;
         $actors = $this->get_team_members( $team_id, $assignee_roles, $post_id );
         $actors = implode( "@", $actors );
         return $actors;
      }
   }
   
   public function get_post_count_in_team( $team_id ) {
      global $wpdb;
      $team_assigned = $wpdb->get_results( "SELECT count(meta_id)as Count FROM {$wpdb->postmeta} WHERE meta_key='_oasis_is_in_team' and meta_value=".$team_id );      
      $post_count = $team_assigned[0]->Count;      
      return $post_count;
   }
   
   /**
    * Hook - owf_report_column
    * Display Assigned Team to reports column
    * @param array $report_column_headers
    * @return array $report_column_headers
    * @since 2.8
    */
   public function add_team_column( $report_column_headers ) {
      $report_column_headers['assigned_team'] = "<th width='150px' scope='col'>". __("Assigned Team", "owfteams") . "</th>";
      return $report_column_headers;
   }
   
   /**
    * Hook - owf_report_rows
    * Display team name to report rows
    * @param array $report_column_headers
    * @return html
    * @since 2.8
    */
   public function add_team_row( $post_id, $report_column_header ) {
      if ( array_key_exists( 'assigned_team', $report_column_header ) ) {
         $previous_post_id = "";  
         $post_id = intval( $post_id );    
         if ( $previous_post_id !== $post_id || $previous_post_id == "" ) {      
            $team_id = get_post_meta( $post_id, '_oasis_is_in_team', true );
            if ( ! empty( $team_id ) ) {
               $teams = $this->get_team_name_by_id( $team_id );
               $team_name = $teams[0]->name;
               echo "<td>$team_name</td>";
            } else {
               echo "<td>". __( 'None', 'owfteams' ) ."</td>";
            } 
            $previous_post_id = $post_id;
         } 
      }
   }
   
   /**
    * Hook - add_report_filter
    * Display the "All Teams" filter dropdown for submission reports
    * @param int $team_filter
    * @since 2.8
    */
   public function add_team_with_report_filter( $team_filter ) {
      $row = '';
      $all_teams = $this->get_all_teams_basic_info();
      foreach ( $all_teams as $team ) {
         $team_id    = $team->ID;
         $team_name  = $team->name;
         $selected   = "";
         if ( $team_filter ==  $team_id ) {
            $selected = " selected='selected' ";
         }
         $row .= "\n\t<option value='" . esc_attr( $team_id ) . "' $selected >$team_name</option>";
      }  
       return $row;
   }
   
   /**
    * Hook - owf_assignee_list
    * Display team dropdown on submit popup
    * @param array $args
    * @return array
    * @since 1.0.0
    */
   public function create_teams_list() { ?>   
      <div class="select-teams-div owf-hidden">
         <label><?php echo __( "Assign to Team :", "owfteams" ); ?></label>
            <div class="oasis-team-list" >
               <p>
                  <select id="teams-list-select" name="teams-list-select" style="width:200px;" real="assign-loading-span"></select>
                  <span class="assign-loading-span">&nbsp;</span>
               </p>
            </div>
      </div>
      <br class="clear">
   <?php
   }
   
   
}

// construct an instance so that the actions get loaded
$ow_teams_service = new OW_Teams_Service();

// Show assign to team drop down on submit popup
add_action( 'owf_assignee_list', array( $ow_teams_service, 'create_teams_list' ) );

// filters for the reports
add_filter( 'owf_report_column', array( $ow_teams_service, 'add_team_column' ), 10, 1 );
add_filter( 'owf_report_rows', array( $ow_teams_service, 'add_team_row' ), 10, 2 );
add_filter( 'owf_report_team_filter', array( $ow_teams_service, 'add_team_with_report_filter' ), 10, 2 );

// get actors - in this case it's team members
add_filter( 'owf_get_team_members', array( $ow_teams_service, 'get_team_members_by_step_id' ), 10, 3 );

 add_action( 'set_user_role', array( $ow_teams_service, 'update_team_member_role' ), 10,3 );
?>