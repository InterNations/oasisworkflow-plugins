jQuery(document).ready(function ()
{
   // Save or Edit group
   jQuery(document).on('click', '#workflow_group_save', function (event) {
      event.preventDefault();

      var $this = jQuery(this);
      var val = $this.val();
      var group_name = jQuery('#user_group_name').val();
      var group_desc = jQuery('#workflow_group_desc').val();
      var group_members = jQuery('#add_user_to_group').val();
      var security = jQuery('#_save_group').val();
      var group_action = jQuery('#group_action').val();
      var group_id = jQuery('#group_id').val();

      var data = {
         action: "create_or_update_group",
         group_name: group_name,
         group_desc: group_desc,
         group_members: group_members,
         security: security,
         group_action: group_action,
         group_id: group_id
      };

      $this.val("Processing...");

      // FIXME: here we're getting action name in AJAX-RESPONSE
      jQuery.post(ajaxurl, data, function (response) {
         response = response.trim();
         if (response == -1) {
            return false; // nonce verification failed
         }

         jQuery("#add_user_to_group").select2("val", "");
         if (typeof group_action === 'undefined') {
            location.href = "?page=oasiswf-groups";
         } else {
            location.reload();
         }
      });
   });


   // Delete groups memeber
   jQuery(document).on('click', '#delete_members', function (event) {
      event.preventDefault();

      var members = [];
      jQuery('.members-check:checkbox:checked').each(function () {
         members.push(jQuery(this).val());
      });
      var members_action = jQuery(this).prev().prev().val();
      var group_id = jQuery(this).attr('data-group');
      var hash = jQuery('#_bulk_remove_members').val();
      if (members_action == -1)
         return false;

      var data = {
         action: "delete_members_from_group",
         group_id: group_id,
         members: members,
         security: hash
      };

      jQuery.post(ajaxurl, data, function (response) {
         response = response.trim();
         if (response == -1) {
            return false; // nonce verification failed
         }
         jQuery(".members-check").prop("checked", false);
         location.reload();
      });
   });

});

// Delete group
function ow_delete_group(group_id)
{
   var security = jQuery('#_delete_groups').val();
   var is_in_workflow = {
      action: 'is_group_in_workflow',
      security: security,
      group_id: group_id
   };

   jQuery.post(ajaxurl, is_in_workflow, function (response) {
      response = response.trim();
      if (response == -1) {
         return false; // nonce verification failed
      }
      if (response == "true") {
         alert(owf_groups_js_vars.groupInUse);
         return false;
      }
      var data = {
         action: 'delete_group',
         group_id: group_id,
         security: security
      };
      jQuery.post(ajaxurl, data, function (response) {
         response = response.trim();
         if (response == -1) {
            return false; // nonce verification failed
         }
         location.reload();
      });
   });
}