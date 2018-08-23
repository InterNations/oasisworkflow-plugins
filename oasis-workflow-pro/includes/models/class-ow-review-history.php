<?php
/*
 * Review Action History Object, Stores sign off action data from individual review action.
 * Review is a multi-user step, so we need to capture the sign off data from all users before moving 
 * to the next step
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * OW_Review_History Class
 *
 * @since 2.0
 */

class OW_Review_History {

	/*
	 * Action History Id
	 *
	 * @since 2.0
	 */
	public $ID = 0;

	/*
	 * review status - submitted, assignment etc
	 *
	 * @since 2.0
	 */
	public $review_status;

	/*
	 * Sign off comments
	 *
	 * @since 2.0
	 */
	public $comments;

	/*
	 * Workflow Step Id
	 *
	 * @since 2.0
	 */
	public $step_id;

	/*
	 * Currently assigned actor(s) for the next step
	 *
	 * @since 2.0
	 */
	public $next_assign_actors;

	/*
	 * actor id for this step
	 *
	 * @since 2.0
	 */
	public $actor_id;

	/*
	 * due date to complete this step
	 *
	 * @since 2.0
	 */
	public $due_date;

	/*
	 * Related action_history_id
	 *
	 * @since 2.0
	 */
	public $action_history_id;

	/*
	 * update datetime for this record
	 *
	 * @since 2.0
	 */
	public $update_datetime;


	/*
	 * meta data related to the history record
	 *
	 * @since 3.0
	 */
	public $history_meta;

}

?>