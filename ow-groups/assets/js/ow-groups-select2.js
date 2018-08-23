jQuery(document).ready(function() 
{ 
	// Initialize select2 plugin	
	jQuery("#add_user_to_group").select2({
		placeholder: "Select Group members",
		allowClear: true,
		closeOnSelect: false
	}); 
});