<?php
/*
 * Action History Object, stores sign off action data from assignment, review and publish
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * OW_Action_History Class
 *
 * @since 2.0
 */

class OW_Action_History {
	
	/*
	 * Action History Id
	 *
	 * @since 2.0
	 */
	public $ID = 0;	
	
	/*
	 * action status - submitted, assignment etc
	 *
	 * @since 2.0
	 */	
	public $action_status;
	
	/*
	 * Sign off comments
	 *
	 * @since 2.0
	 */
	public $comment;
	
	/*
	 * Workflow Step Id
	 *
	 * @since 2.0
	 */
	public $step_id;
	
	/*
	 * Currently assigned actor_id, if -1, then it's a review process
	 *
	 * @since 2.0
	 */
	public $assign_actor_id;
	
	/*
	 * Post Id for this action history
	 *
	 * @since 2.0
	 */
	public $post_id;
	
	/*
	 * Previous Step Id
	 *
	 * @since 2.0
	 */
	public $from_id;
	
	/*
	 * due date to complete this step
	 *
	 * @since 2.0
	 */
	public $due_date;
	
	/*
	 * Reminder date, empty if due date feature is not turned on
	 *
	 * @since 2.0
	 */
	public $reminder_date;
	
	/*
	 * Reminder date after due date, empty if due date feature is not turned on
	 *
	 * @since 2.0
	 */
	public $reminder_date_after;
	
	/*
	 * create datetime for this record
	 *
	 * @since 2.0
	 */
	public $create_datetime;
	
}

?>