jQuery(document).ready(function() {
	var select_id = jQuery("#reassign_actors").val() ;

	modal_close = function(){
		jQuery(document).find("#reassign-div").html("") ;
		jQuery.modal.close();
	}
	jQuery( document ).on( "click", "#reassignCancel, .modalCloseImg", function() {
		modal_close() ;
	});

	jQuery( document ).on( "click", "#reassignSave", function(){
		if ( 0 === jQuery('#actors-set-select option').length ) {
			alert(owf_reassign_task_vars.selectUser);
			return false;
		}
		var actors = [];
		jQuery('#actors-set-select option').each(function() {
			actors.push(jQuery(this).val());
		});
      
		var obj = this ;
		jQuery(this).parent().children("span").addClass("loading") ;
		jQuery(this).hide();
      

		data = {
			action: 'reassign_process' ,
			oasiswf: jQuery("#action_history_id").val(),
			reassign_id: actors,
			reassign_comments: jQuery('#reassign_comments').val(),
			task_user: jQuery('#task_user_inbox').val(),
			security: jQuery('#owf_reassign_ajax_nonce').val()
		};
		jQuery.post(ajaxurl, data, function( response ) {
			if ( ! response.success ) {
            displayWorkflowReassignErrorMessages( response.data.errorMessage );
             
				jQuery(obj).parent().children("span").removeClass("loading") ;
				jQuery("#reassignSave").show();
				return false;
			} else {
				modal_close();
				location.reload();
			}
		});
	});
   
   
   function displayWorkflowReassignErrorMessages( errorMessages ) {
      jQuery('#ow-reassign-messages').html(errorMessages);
      jQuery('#ow-reassign-messages').removeClass('owf-hidden');

      // scroll to the top of the window to display the error messages
      jQuery(".simplemodal-wrap").css('overflow', 'hidden');
      jQuery(".simplemodal-wrap").animate({scrollTop: 0}, "slow");
      jQuery(".simplemodal-wrap").css('overflow', 'scroll');
      jQuery(".changed-data-set span").removeClass("loading");
      jQuery("#simplemodal-container").css("max-height", "90%");

      // call modal.setPosition, so that the window height can adjust automatically depending on the displayed fields.
      jQuery.modal.setPosition();
   }
   
});