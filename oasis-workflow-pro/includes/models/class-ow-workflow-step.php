<?php
/*
 * Workflow Step Object
 * 
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0 
 */
 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * OW_Workflow_Step Class
 * 
 * @since 2.0
 */

class OW_Workflow_Step {
	
	/*
	 * Workflow Step Id
	 * 
	 * @since 2.0
	 */
	public $ID = 0;
	
	/*
	 * Step Info
	 * 
	 * @since 2.0
	 */
	public $step_info;
	
	/*
	 * Process Info
	 * 
	 * @since 2.0
	 */
	public $process_info;
	
	/*
	 * Create Date & Time
	 * 
	 * @since 2.0
	 */
	public $create_datetime;
	
	/*
	 * Update Date & Time
	 * 
	 * @since 2.0
	 */
	public $update_datetime;
	
	/*
	 * Associated Workflow
	 * 
	 * @since 2.0
	 */
	public $workflow_id;
	
	/*
	 * sanitize the object
	 */
	public function sanitize_data( ) {
		$this->ID= intval( sanitize_text_field( $this->ID ) );
		$this->workflow_id = intval( sanitize_text_field ( $this->workflow_id ) );
		$this->process_info = stripcslashes( $this->process_info );
		$this->step_info = stripcslashes( $this->step_info );
		$this->create_datetime = sanitize_text_field( $this->create_datetime );
		$this->update_datetime = sanitize_text_field( $this->update_datetime );
	}
	
}
?>