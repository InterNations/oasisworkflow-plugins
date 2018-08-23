jQuery(document).ready(function ($) {
	jQuery("#chk_reminder_day").click(function () {
		if (jQuery(this).attr("checked") == "checked") {
			jQuery("#oasiswf_reminder_days").attr("disabled", false);
        } else {
        	jQuery("#oasiswf_reminder_days").val('');
            jQuery("#oasiswf_reminder_days").attr("disabled", true);
        }
	});

	jQuery("#chk_reminder_day_after").click(function () {
	   if (jQuery(this).attr("checked") == "checked") {
		   jQuery("#oasiswf_reminder_days_after").attr("disabled", false);
	   } else {
		   jQuery("#oasiswf_reminder_days_after").val('');
		   jQuery("#oasiswf_reminder_days_after").attr("disabled", true);
       }
	});

	jQuery("#emailSettingSave").click(function () {
		if (jQuery("#chk_reminder_day").attr("checked") == "checked") {
			if (!jQuery("#oasiswf_reminder_days").val()) {
				alert("Please enter the number of days for reminder email before due date.");
                return false;
			}
			if (isNaN(jQuery("#oasiswf_reminder_days").val())) {
				alert("Please enter a numeric value for reminder email before due date.");
                return false;
			}
		}

       if (jQuery("#chk_reminder_day_after").attr("checked") == "checked") {
           if (!jQuery("#oasiswf_reminder_days_after").val()) {
               alert("Please enter the number of days for reminder email after due date.");
               return false;
           }
           if (isNaN(jQuery("#oasiswf_reminder_days_after").val())) {
               alert("Please enter a numeric value for reminder email after due date.");
               return false;
           }
       }
   });
   
   // Email type select change show the template accordingly
   jQuery('.email-template').hide();
   jQuery('#post_publish').show();
   jQuery('#email-type-select').change(function () {
      jQuery('.email-template').hide();
      jQuery('#' + jQuery(this).val()).show();
   });


   // Initialize select2 plugin	
   jQuery("#post_publish_email_actors,#revised_post_email_actors,#unauthorized_update_email_actors,#task_claim_email_actors,#post_submit_email_actors,#workflow_abort_email_actors").select2({
      placeholder: "Select additional email recipients",
      allowClear: true,
      closeOnSelect: false
   });
   
});
