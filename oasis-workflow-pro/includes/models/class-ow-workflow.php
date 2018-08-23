<?php
/*
 * Workflow Object
 * 
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0 
 */
 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * OW_Workflow Class
 * 
 * @since 2.0
 */
 
class OW_Workflow {
	
	/*
	 * Workflow ID
	 *
	 * @since 2.0
	 */	
	public $ID = 0;
  	
	/*
	 * Workflow Name
	 *
	 * @since 2.0
	 */
	public $name;
	
	/*
	 * Workflow Description
	 *
	 * @since 2.0
	 */
	public $description;
	
	/*
	 * Workflow version
	 *
	 * @since 2.0
	 */	
	public $version;
	
	/*
	 * Workflow parent (in case of multiple versions of the workflow)
	 *
	 * @since 2.0
	 */	
	public $parent_id;
	
	/*
	 * Workflow Start date - workflow will become available for use on this date
	 *
	 * @since 2.0
	 */	
	public $start_date;
	
	
	/*
	 * Workflow End date - workflow will be retired/not available for use after this date.
	 *
	 * @since 2.0
	 */	
	public $end_date;
	
	/*
	 * Workflow info - carries information about steps and connections
	 *
	 * @since 2.0
	 */	
	public $wf_info;
	
	/*
	 * Is auto submit enabled on this workflow.
	 * If enabled auto submit engine will consider this workflow for submit
	 *
	 * @since 2.0
	 */	
	public $is_auto_submit;
	
	/*
	 * auto submit info relevant to the workflow
	 *
	 * @since 2.0
	 */	
	public $auto_submit_info;
	
	/*
	 * is workflow valid, depending on various conditions workflow may or may not be valid.
	 *
	 * @since 2.0
	 */	
	public $is_valid;
	
	/*
	 * create date/time
	 *
	 * @since 2.0
	 */
	public $create_datetime;	
	
	/*
	 * update date/time
	 *
	 * @since 2.0
	 */	
	public $update_datetime;
	
	/*
	 * additional info, like 
	 * is workflow applicable to new or revised posts
	 * what post types are allowed for this workflow
	 *
	 * @since 2.0
	 */	
	public $wf_additional_info;

}

?>