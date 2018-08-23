jQuery(document).ready(function() 
{ 
	// Initialize select2 plugin	
	jQuery("#add_user_to_team").select2({
		placeholder: "Select team members",
		allowClear: true,
		closeOnSelect: false
	}); 
   jQuery("#add_workflow_to_team").select2({
		placeholder: "Select workflows",
		allowClear: true,
		closeOnSelect: false
	}); 
});