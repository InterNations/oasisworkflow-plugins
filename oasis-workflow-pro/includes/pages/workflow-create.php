<?php
/*
 * Workflow Create Page
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

$wf_id = "";
$workflow = "";
$wf_editable = true;
if( isset( $_GET['wf_id'] ) && sanitize_text_field( $_GET["wf_id"] )) {
	$wf_id = intval( sanitize_text_field( $_GET["wf_id"] )) ;
	$workflow_service = new OW_Workflow_Service();
	$workflow = $workflow_service->get_workflow_by_id( $wf_id  );
	$wf_editable = $workflow_service->is_workflow_editable( $wf_id ) ; // check if editable.
}

$workflow_info = "";
if ( is_object( $workflow ) ) {
	$workflow_info = addslashes( $workflow->wf_info );
}

$title = "";
$workflow_description = "";
$start_date = "";
$end_date = "";
$wf_for_new_posts = 1;
$wf_for_revised_posts = 1;
$wf_for_roles = array();
$wf_for_post_types = array();
$wf_for_categories = array();
$selected_teams_array = array();
if( $workflow ) {
	$title = $workflow->name;
	$workflow_description = $workflow->description;
	$start_date = OW_Utility::instance()->format_date_for_display_and_edit( $workflow->start_date );
	$end_date = OW_Utility::instance()->format_date_for_display_and_edit( $workflow->end_date );
	$additional_info = @unserialize( $workflow->wf_additional_info );
	if ( is_array( $additional_info )) {
		$wf_for_new_posts = $additional_info['wf_for_new_posts'];
		$wf_for_revised_posts = $additional_info['wf_for_revised_posts'];
		if (array_key_exists ( 'wf_for_roles', $additional_info )) {
			$wf_for_roles = $additional_info['wf_for_roles'];
		}
		if (array_key_exists ( 'wf_for_post_types', $additional_info )) {
			$wf_for_post_types = $additional_info['wf_for_post_types'];
		}
	}
}

echo "<script type='text/javascript'>
		   wf_structure_data = '{$workflow_info}';
		   wfeditable = '{$wf_editable}' ;
	   </script>";
?>
<div class="wrap">
	<div id="workflow-edit-icon" class="icon32"><br></div>
	<?php
	if ( is_object( $workflow )) {?>
		<h2><label id="page_top_lbl"><?php echo $workflow->name . " (" . $workflow->version .")" ;?></label></h2>
	<?php }?>
	<form id="wf-form" method="post" action="<?php echo admin_url('admin.php?page=oasiswf-admin');?>" >
		<div>
			<div id="validation_error_message" class="updated ow-error-message owf-hidden"></div>
			<span class="description">
	         <?php add_thickbox(); ?>
				<?php echo __( 'If you want to know more about creating/modifying workflows ', "oasisworkflow") ;?>
	            <!-- TODO: if this looks great then lets delete the code from js part .help-popup -->
				<a href="http://www.youtube.com/embed/JbJJQMMnf5U?TB_iframe=true&width=800&height=600" class="thickbox">
					<?php echo __( 'watch this video.', "oasisworkflow") ;?>
				</a>
				<?php echo __( ' You can also look up for more tutorial videos about Oasis Workflow on ', "oasisworkflow") ;?>
				<a href="https://www.youtube.com/results?search_query=oasis+workflow" target="_blank">
					<?php echo __( 'YouTube.', "oasisworkflow") ;?>
				</a>
			</span>
			<br class="clear" />
		</div>
		<div class="fc_action">
			<div id="workflow-info-area">
				<div class="postbox-container"  id="process-info-div">
					<div class="postbox" >
						<div class="handlediv" title="Click to toggle"><br></div>
						<h3 style="padding:7px;">
							<span class="process-lbl">
								<?php echo __('Processes', "oasisworkflow");?>
                       	<a href="#" title="<?php echo __('Drag and Drop the processes into the Workflow Design Canvas to create new workflow steps.',
                       			"oasisworkflow") ;?>" class="tooltip">
                        	<span title="">
                        	<img src="<?php echo OASISWF_URL . '/img/help.png'; ?>" class="help-icon"/></span>
                        </a>
							</span>
						</h3>
						<div class="move-div">
							<?php
								if( $wf_editable ){
									echo '<ul id="wfsortable">';
									$fw_process = get_site_option('oasiswf_process'); // list all the processes/steps on the side
									foreach ($fw_process as $k => $v) {
										echo "<li class='widget'>
												<div class='widget-wf-process'>" . __($k, "oasisworkflow") . "</div>
											 </li>";
									}
									echo '</ul>';
								} else { // steps cannot be added or deleted
									echo "<ul class='wfeditable'><li class='widget wfmessage'><p>";
									echo __("Processes are not available, since there are items (post/pages) in the workflow.&nbsp;&nbsp;&nbsp;If you want to edit the workflow,&nbsp;&nbsp; please ",
											"oasisworkflow") . "&nbsp;
											<a href='#' id='save_as_link'>" . __("save it as a new version", "oasisworkflow");
									echo "</a></p></li><ul>";
								}
							?>
						</div>
					</div>
				</div>
				<div class="postbox-container">
					<div class="postbox" >
						<div class="handlediv" title="Click to toggle"><br></div>
						<h3 style="padding:7px;">
							<span class="workflow-lbl"><?php echo  __("Workflow Info", "oasisworkflow");?></span>
						</h3>
						<div class="move-div workflow-define-div">
							<table>
								<tr>
									<td>
										<label>
											<span class="space bold-label">
												<?php echo  __("Title : ", "oasisworkflow");?>
											</span>
										</label>
									</td>
								</tr>
								<tr>
									<td>
										<input type="text"
											id="define-workflow-title"
											name="define-workflow-title"
											style="width:100%;"
											value="<?php echo esc_attr( $title );?>"  />
									</td>
								</tr>
								<tr height="20px;"><td>&nbsp;</td><td>&nbsp;</td></tr>
								<tr>
									<td style="vertical-align: top;">
										<label>
											<span class="space bold-label">
												<?php echo  __("Description : ", "oasisworkflow");?>
											</span>
										</label>
									</td>
								</tr>
								<tr>
									<td>
										<textarea id="define-workflow-description"
											name="define-workflow-description"
											class="define-workflow-textarea"
                                 cols="20" rows="10"><?php echo esc_textarea( $workflow_description );?></textarea>
									</td>
								</tr>
							</table>
							<div class="div-line"></div>
							<table>
								<tr>
									<td>
										<label>
											<span class="space bold-label">
												<?php echo  __("Start Date :", "oasisworkflow");?>
                                     <span class="required-color">*</span>
                                     <a href="#"
                                     	title="<?php echo __('Specify a date from which this workflow will become available for use.',
                                     			"oasisworkflow") ;?>" class="tooltip">
                                       <span title="">
                                       <img src="<?php echo OASISWF_URL . '/img/help.png'; ?>" class="help-icon"/></span>
                                     </a>
											</span>
										</label>
									</td>
								</tr>
								<tr>
									<td>
										<input class="date_input"
											id="start-date"
											name="start-date" readonly value="<?php echo esc_attr( $start_date );?>" />
										<?php if( $wf_editable ):?>
											<button class="date-clear"><?php echo __("clear", "oasisworkflow") ;?></button>
										<?php endif;?>
									</td>
								</tr>
								<tr height="10px;"><td>&nbsp;</td><td>&nbsp;</td></tr>
								<tr>
									<td>
										<label>
											<span class="space bold-label">
												<?php echo  __("End date :", "oasisworkflow");?>
                                     <a href="#"
                                     	title="<?php echo __('End date is not required. If not specified, the workflow is valid for ever.
                                     			Specify an end date, if you want to retire the workflow.', "oasisworkflow") ;?>"
                                     			class="tooltip">
                                       <span title="">
                                       <img src="<?php echo OASISWF_URL . '/img/help.png'; ?>" class="help-icon"/></span>
                                    </a>
											</span>
										</label>
									</td>
								</tr>
								<tr>
									<td>
										<input class="date_input"
											id="end-date"
											name="end-date" readonly
											value="<?php echo esc_attr( $end_date );?>" />
										<button class="date-clear"><?php echo __("clear", "oasisworkflow") ;?></button>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</div>
				<div class="postbox-container">
					<div class="postbox" >
						<div class="handlediv" title="Click to toggle"><br></div>
						<h3 style="padding:7px;">
							<span class="workflow-lbl"><?php echo  __("Workflow Applicable To", "oasisworkflow");?></span>
						</h3>
						<div class="move-div workflow-define-div">
							<table>
								<tr>
									<td>
									<?php
									$for_new_posts="";
									$for_revised_posts="";
   				            if( $wf_for_new_posts == 1 ) {
   				            	$for_new_posts = "checked=true" ;
   				            }
								  	if( $wf_for_revised_posts == 1 ) {
								  		$for_revised_posts = "checked=true" ;
								  	}
								  	?>
										<span>
											<label><input type="checkbox" class="owf-checkbox" name="new_post_workflow"
	   											value="1"
	   											<?php echo $for_new_posts;?> />
	   											<?php echo __("new posts/pages", "oasisworkflow") ;?>
	   									</label><br/>
   									</span>
   									<span>
											<label><input type="checkbox" class="owf-checkbox" name="revised_post_workflow"
	   											value="1"
	   											<?php echo $for_revised_posts;?> />
	   											<?php echo __("revised posts/pages", "oasisworkflow") ;?>
	   									</label>
	   								</span>
									</td>
								</tr>
							</table>
							<div class="div-line"></div>
							<table>
								<tr>
									<td>
										<label>
											<span class="space bold-label">
											<?php echo  __("Roles (who can submit to this workflow) :", "oasisworkflow");?>
											</span>
											<span class="space">
											<?php echo  __(" (applicable to all, if none specified)", "oasisworkflow");?>
											</span>
										</label>
									</td>
								</tr>
								<tr>
									<td>
					    				<select name="wf_for_roles[]" id="wf_for_roles[]" size="6" multiple="multiple">
					    				   <?php OW_Utility::instance()->owf_dropdown_roles_multi( $wf_for_roles ); ?>
					    				</select>
									</td>
								</tr>
							</table>
							<div class="div-line"></div>
							<table>
								<tr>
									<td>
										<label>
											<span class="space bold-label">
											<?php echo  __("Post Types :", "oasisworkflow");?>
											</span>
											<span class="space">
											<?php echo  __(" (applicable to all, if none specified)", "oasisworkflow");?>
											</span>
										</label>
									</td>
								</tr>
								<tr>
									<td>
										<?php OW_Utility::instance()->owf_checkbox_post_types_multi( 'wf_for_post_types[]', $wf_for_post_types ) ?>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</div>
			</div>
			<div class="widget-holder dropable-area" id="workflow-area" style="position:relative;">
				<span id="workflow-design-area"><?php echo __("Workflow Design Canvas", "oasisworkflow") ;?></span>
            <span id="workflow-steps-loading-span" class="steps-loading"><?php echo __("Loading Step...", "oasisworkflow") ;?></span>
			</div>
			<br class="clear">
		</div>
		<div class="save-action-div">
         <?php wp_nonce_field( 'owf_workflow_create_nonce', 'owf_workflow_create_nonce' ); ?>
			<?php if( $wf_editable ){?>
				<?php
				if ( current_user_can( 'ow_edit_workflow' ) ) {
				?>
				<input type="button" value="<?php echo __("Save", "oasisworkflow") ?>" class="button-primary workflow-save-button" >
				<?php
				}
				?>
				<?php
				if ( current_user_can( 'ow_create_workflow' ) ) {
				?>
				<input type="button" value="<?php echo __("Copy") ?>" class="button-primary workflow-copy-button" >
				<?php
				}
				?>
				<span class="save_loading">&nbsp;</span>
				<a href="#" id="delete-form"><?php echo __("Clear Workflow", "oasisworkflow") ?></a>
			<?php } else {?>
				<?php
				if ( current_user_can( 'ow_create_workflow' ) ) {
				?>
				<input type="button" value="<?php echo __("Save as new version", "oasisworkflow") ?>"
					class="button-primary workflow-save-new-version-button" >
				<?php
				}
				if ( current_user_can( 'ow_edit_workflow' ) ) {
				?>
				<input type="button" value="<?php echo __("Save", "oasisworkflow") ?>"
					class="button-primary workflow-save-button" >
				<?php
				}
				if ( current_user_can( 'ow_create_workflow' ) ) {
				?>
				<input type="button" value="<?php echo __("Copy") ?>" class="button-primary workflow-copy-button" >
				<?php
				}
				?>
				<span class="save_loading">&nbsp;</span>
			<?php }?>
		</div>
		<br class="clear" />
		<input type="hidden" id="wf_graphic_data_hi" name="wf_graphic_data_hi" />
		<input type="hidden" id="wf_id" name="wf_id" value='<?php echo esc_attr( $wf_id ); ?>' />
		<input type="hidden" id="deleted_step_ids" name="deleted_step_ids" />
		<input type="hidden" id="first_step" name="first_step" value="" />
		<input type="hidden" id="wf_validate_result" name="wf_validate_result" value="active" />
		<input type="hidden" id="save_action" name="save_action" value="workflow_save" />
	</form>
</div>
<?php
	// include the file for the connection info setting
	include( OASISWF_PATH . 'includes/pages/subpages/connection-info-content.php' );
?>
<ul id="connectionMenu" class="contextMenu">
	<div>Conn Menu</div>
	<li class="edit" id="connEdit" ><a href="#edit"><?php echo __("Edit", "oasisworkflow") ?></a></li>
	<li class="delete" id="connDelete"><a href="#delete"><?php echo __("Delete", "oasisworkflow") ?></a></li>
	<li class="quit separator" id="connQuit"><a href="#quit"><?php echo __("Quit", "oasisworkflow") ?></a></li>
</ul>
<ul id="stepMenu" class="contextMenu">
	<div>Step Menu</div>
	<li class="edit" id="stepEdit">
		<a><?php echo __("Edit", "oasisworkflow") ?></a></li>
	<?php if( $wf_editable ):?>
		<li class="copy" id="stepCopy"><a href="#copy"><?php echo __("Copy") ?></a></li>
		<li class="delete" id="stepDelete"><a href="#delete"><?php echo __("Delete", "oasisworkflow") ?></a></li>
	<?php endif;?>
	<li class="quit separator" id="stepQuit"><a href="#quit"><?php echo __("Quit", "oasisworkflow") ?></a></li>
</ul>
<?php if( $wf_editable ):?>
   <ul id="pasteMenu" class="contextMenu">
   	<li class="paste" id="stepPaste"><a href="#paste"><?php echo __("Paste") ?></a></li>
   	<li class="quit separator" id="stepQuit"><a href="#quit"><?php echo __("Quit") ?></a></li>
   </ul>
<?php endif;?>
<span class="paste_loading">&nbsp;&nbsp;&nbsp;</span>

<?php
	// include the file for the workflow create popup
	include( OASISWF_PATH . 'includes/pages/subpages/workflow-create-popup.php' );
?>

<?php
	// include the file for the workflow copy popup
	include( OASISWF_PATH . 'includes/pages/subpages/workflow-copy-popup.php' );
?>
<div id="step-info-update" class="owf-hidden"></div>
<script type="text/javascript">
	jQuery(document).ready(function() {
		// loading workflow create modal
		if(!jQuery("#wf_id").val()){
			if(navigator.appName == "Netscape"){
				show_workflow_create_modal() ;
			}else{
				setTimeout("show_workflow_create_modal()", 500);
			}
		}
	});

	jQuery("#wpbody").css({"position":"inherit"});

	function call_modal(param){
		jQuery('.contextMenu').hide();
		jQuery('#'+param).owfmodal();
	}

	function show_workflow_create_modal(){
		jQuery('#new-workflow-create-popup').owfmodal();
		jQuery(".modalCloseImg").hide() ;
	}
</script>