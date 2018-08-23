jQuery(document).ready(function() {
	jQuery( document ).on( "click", "#owf-delete-history", function(){
		jQuery("#delete-history-div").owfmodal();
	});

	jQuery( document ).on( "click", "#deleteHistoryConfirm", function(){
		data = {
			action: 'purge_workflow_history',
			range: jQuery("#delete-history-range-select").val(),
            security: jQuery('#owf_workflow_history_nonce').val()
		};

		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			data: data,
			success: function( response ){
				jQuery.modal.close();
				window.location = "admin.php?page=oasiswf-history&trashed=" +  response.data.result;
			}
		});
	});

	jQuery( document ).on( "click", "#deleteHistoryCancel", function(){
		jQuery.modal.close();
	});
    
});