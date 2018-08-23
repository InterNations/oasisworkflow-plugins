<?php
/*
 * Workflow Dashboard Widget
 *
 * @copyright   Copyright (c) 2016, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.1
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}
?>
<div class="dashboard-content">
   <?php
   $ow_process_flow = new OW_Process_Flow();

   // get date count of assigned tasks for selected user
   $tasks = $ow_process_flow->get_task_count_by_due_date( get_current_user_id() );

   // get priority count of assigned tasks for selected user
   $task_list_by_priority = $ow_process_flow->get_task_count_by_priority( get_current_user_id() );
   ?>

   <div class="main-content">
      <?php
      $task_count = 0;
      foreach ( $tasks as $task ) {
         $task_count += $task->row_count;
      }
      if ( $task_count > 0 ) {
         $task_count_message = sprintf( __( 'You currently have <a href="admin.php?page=oasiswf-inbox">%s assignment(s).</a>', 'oasisworkflow' ),
            $task_count );
         echo "<span>";
         echo $task_count_message;
         echo "</span>";
      } else {
         echo "<span>";
         echo __( 'Hurray! No assignments.', 'oasisworkflow' );
         echo "</span>";
      }
      ?>

      <?php
      $email_settings = get_option( 'oasiswf_email_settings' );
      $reminder_email_settings = $email_settings['reminder_emails'];
      if( (get_option( 'oasiswf_default_due_days' ) != '' ) || ( $reminder_email_settings == 'yes' ) ) : ?>
         <div class="main-content1">
            <span><?php echo __( "Tasks by Due Date", "oasisworkflow" ); ?></span>
            <ul>
               <?php
               $today = date( "Y-m-d" );
               $tomorrow = date( 'Y-m-d', strtotime( '+24 hours' ) );
               $week = date( 'Y-m-d', strtotime( '+7 days' ) );

               //set due date types and if we don't have tasks for the given due dates , show 0 count
               $due_types = array(
                  'overdue'           => array( 'label' => __( 'Overdue', 'oasisworkflow' ), 
                                                'count' => 0, 
                                                'color' => '' ),
                  'due_today'         => array( 'label' => __( 'Due Today', 'oasisworkflow' ), 
                                                'count' => 0, 
                                                'color' => '' ),
                  'due_tomorrow'      => array( 'label' => __( 'Due Tomorrow', 'oasisworkflow'), 
                                                'count' => 0, 
                                                'color' => '' ),
                  'due_in_seven_days' => array( 'label' => __( 'Due in 7 days', 'oasisworkflow'), 
                                                'count' => 0, 
                                                'color' => '' )
               );

               // compare dates to get counts for the given tasks by due dates
               foreach ( $tasks as $task ) {
                  $date = $task->date;
                  $key = '';
                  if( $date < $today ) {
                     $key = "overdue";
                     $due_types[$key]["count"] += $task->row_count;
                  }
                  if( $date == $today ) {
                     $key = "due_today";
                     $due_types[$key]["count"] += $task->row_count;
                  }
                  if( $date == $tomorrow ) {
                     $key = "due_tomorrow";
                     $due_types[$key]["count"] += $task->row_count;
                  }
                  if( $date <= $week ) {
                     $key = "due_in_seven_days";
                     $due_types[$key]["count"] += $task->row_count;
                  }
               }

               foreach ( $due_types as $key => $value ) {
                  
                  $css_class = "owf-li-normal";
                  
                  // If task count is zero change the css class
                  if ( $value['count'] !== 0 ) {
                     $css_class = "owf-li-bold";
                  }
                  
                  // If task is overdue than change text color to red
                  if ( $key == "overdue" && $value['count'] !== 0 ) {
                     $value['color'] = "red";
                  }
                  
                  echo "<li class='". $css_class ."' style='color:" . $value['color'] . "'>" . $value['count'] . " " . $value['label'] . "</li>";
                  
               }
               ?>
            </ul>
         </div>
      <?php endif; // check if default due date and reminder emails is enabled ?>

      <?php if( get_option( 'oasiswf_priority_setting' ) == 'enable_priority' ) : ?>
         <div class="main-content2">
            <span><?php echo __( "Tasks by Priority", "oasisworkflow" ); ?></span>
            <ul>
               <?php

               $priority_array = array_reverse( OW_Utility::instance()->get_priorities() );

               foreach ( $priority_array as $priority_key => $priority_name ) {
                  $has_tasks = false;
                  // the CSS is defined without the number part, example, "2normal" is defined as "normal-task-priority"
                  $css_class = substr( $priority_key, 1 );

                  foreach ( $task_list_by_priority as $priority_task ) {
                     if( $priority_key === $priority_task->priority ) { //looks like we found count for the given priority
                        echo "<li class=" . $css_class . "-task-priority>" . $priority_task->priority_count . " " . $priority_name . "</li>";
                        $has_tasks = true;
                        break;
                     }
                  }

                  // since we do not have tasks for the given priority, show 0 count
                  if( !$has_tasks ) {
                     echo "<li>" . "0 " . $priority_name . "</li>";
                  }
               }
               ?>
            </ul>
         </div>
      <?php endif; // enable_priority check  ?>
   </div> <!-- main-content end -->
</div> <!-- dashboard-content end -->