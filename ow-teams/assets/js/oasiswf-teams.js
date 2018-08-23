jQuery(document).ready(function ()
{
    // Save or Edit team
    jQuery('#workflow_team_save').click(function (e)
    {
        e.preventDefault();
        var $this = jQuery(this);
        var val = $this.val();
        var team_name = jQuery('#workflow_team_name').val();
        var team_desc = jQuery('#workflow_team_desc').val();
        var associated_workflows = jQuery('#add_workflow_to_team').val();
        var team_members = jQuery('#add_user_to_team').val();
        var secure_hash = jQuery('#_save_team').val();
        var team_action = jQuery('#team_action').val();
        var team_id = jQuery('#team_id').val();
        
        var validate_team_data = {
         action: 'validate_team',
         associated_workflows : associated_workflows,
         team_members: team_members,
         team_action: team_action,
         team_id : team_id,
         security: secure_hash
        };
        
        jQuery.post(ajaxurl, validate_team_data, function (response)
        {
            if (response == -1) {
               return false; // Invalid nonce
            }
            
            if ( ! response.success ) { // looks like there are validation errors
               jQuery( "#message" ).removeClass( "owf-hidden" );
               jQuery( "#message" ).html( response.data.errorMessage );
               // scroll to the top
               window.scrollTo(0,0);
               return false;
            }
            else { 
               // There is no validation error, Save the team data
               var data = {
                   "action": "create_or_update_team",
                   "team_name": team_name,
                   "team_desc": team_desc,
                   "associated_workflows" : associated_workflows,
                   "team_members": team_members,
                   "hash": secure_hash,
                   "team_action": team_action,
                   "team_id": team_id
               };
               
               jQuery( "#message" ).addClass( "owf-hidden" );
               $this.val("Processing...");

               jQuery.post(ajaxurl, data, function (response)
               {
                   if (response)
                   {
                       if (team_action == undefined) {
                           jQuery("#add_user_to_team").select2("val", "");
                           location.href = "?page=oasiswf-teams";
                       } else {
                           jQuery("#add_user_to_team").select2("val", "");
                           location.reload();
                       }
                   }
               });
            }                         
         });
    });


    // Delete teams memeber
    jQuery('#delete_members').click(function (event)
    {
        var members = []
        jQuery('.members-check:checkbox:checked').each(function () {
            members.push(jQuery(this).val());
        });
        var members_action = jQuery(this).prev().prev().val();
        var team_id = jQuery(this).attr('data-team');
        var hash = jQuery('#_bulk_remove_members').val();
        if (members_action == -1)
            return false;

        var data = {
            "action": "delete_members_from_team",
            "team_id": team_id,
            "members": members,
            "hash": hash
        };

        jQuery.post(ajaxurl, data, function (response)
        {
            if ( response.success )
            {
                jQuery(".members-check").prop("checked", false);
                location.reload();
            }
        });
        event.preventDefault();
    });
    
    // Delete teams associated workflows
    jQuery('#delete_workflows').click(function (event)
    {
        var workflows = []
        jQuery('.workflow-check:checkbox:checked').each(function () {
            workflows.push(jQuery(this).val());
        });
        var workflows_action = jQuery(this).prev().prev().val();
        var team_id = jQuery(this).attr('data-team');
        var hash = jQuery('#_bulk_remove_workflows').val();
        if ( workflows_action == -1 )
            return false;

        var data = {
            "action": "delete_workflows_from_team",
            "team_id": team_id,
            "workflows": workflows,
            "hash": hash
        };

        jQuery.post(ajaxurl, data, function (response)
        {
            if ( response.success )
            {
                jQuery(".workflow-check").prop("checked", false);
                location.reload();
            }
        });
        event.preventDefault();
    });
    
    jQuery(document).on("change", "#teams-list-select", function () {
      jQuery('.error').hide();
      
      var post_id = "";
      if ( jQuery("#post_ID").length ) {
         post_id = jQuery("#post_ID").val();
      } else {
         post_id = jQuery("#hi_post_id").val();
      }
      var get_team_members_by_id = {
         action: 'get_team_members_by_id',
         step_id: jQuery("#step-select").val(),
         team_id: jQuery(this).val(),
         post_id: post_id,
         security: jQuery('#owf_signoff_ajax_nonce').val()
      };
      jQuery.post(ajaxurl, get_team_members_by_id, function ( response ) {
         if (response == -1) {
            return false; // Invalid nonce
         }
         
         // if response is false then there are no users for given role..!
         if ( ! response.success ) {
            jQuery('#multiple-actors-div').addClass('owf-hidden');
            displayTeamErrorMessages( response.data.errorMessage );
            return false;
         }
         
         var is_team_assign_to_all = jQuery("#assign_to_all").val();
         if ( is_team_assign_to_all === 1 ) {
            jQuery('#multiple-actors-div').addClass('owf-hidden');
         } else {
            jQuery("#actors-set-select").find('option').remove();
               if ( response.data.users != "" ) {
                  if (typeof response.data.users[0] == 'object') {
                     users = response.data.users;
                  }
               }
            add_option_to_select("actors-list-select", users, 'name', 'ID');
         }
      });
    });

});

// Delete team
function owt_delete_team(team_id) {
    var is_in_workflow = {
        "action": "is_team_in_workflow",
        "team_id": team_id
    };

    jQuery.post(ajaxurl, is_in_workflow, function (response)
    {
        if ( response.success ) {
            alert(owf_teams_js_vars.teamInUse);
            return false;
        } 
        
        if ( ! response.success ) {
            var data = {
                "action": "delete_team",
                "team_id": team_id,
                "security": jQuery('#_delete_teams').val()
            };
            jQuery.post(ajaxurl, data, function (response)
            {
                if (response == -1) {
                    return false;
                }
                if ( response.success )
                {
                    location.reload();
                }
            });
     }
    });
}

function displayTeamErrorMessages( errorMessages ) {
      jQuery('.error').hide();
      jQuery('#ow-step-messages').html(errorMessages);
      jQuery('#ow-step-messages').removeClass('owf-hidden');

      // scroll to the top of the window to display the error messages
      jQuery(".simplemodal-wrap").css('overflow', 'hidden');
      jQuery(".simplemodal-wrap").animate({scrollTop: 0}, "slow");
      jQuery(".simplemodal-wrap").css('overflow', 'scroll');
      jQuery(".changed-data-set span").removeClass("loading");
      jQuery("#simplemodal-container").css("max-height", "90%");

      // call modal.setPosition, so that the window height can adjust automatically depending on the displayed fields.
      jQuery.modal.setPosition();
   }


