jQuery(document).ready(function () {
   var stepProcess = "";

   // setup the page
   function initWorkflowSubmit() {
      var allowed_post_types = jQuery.parseJSON(owf_submit_workflow_vars.allowedPostTypes);
      var current_post_type = jQuery('#post_type').val();

      // If not allowed post/page type then do not show
      if (jQuery.inArray(current_post_type, allowed_post_types) != -1)
      {
         jQuery("#publishing-action #publish").before("<span class='blank-space loading owf-hidden'></span>");
         jQuery(".loading").show();
         var get_original = {
            action: 'get_original',
            post_id: jQuery('#post_ID').val(),
            security: jQuery('#owf_workflow_abort_nonce').val()
         };
         jQuery.post(ajaxurl, get_original, function (response) {
            jQuery(".loading").hide();
            if (response.success && owf_submit_workflow_vars.hideCompareButton == "") {
               jQuery("#publishing-action #publish").before(
                       "<input type='button' id='workflow_revision' class='button button-primary button-large' value='" + owf_submit_workflow_vars.compareOriginal + "' style='float:left;display:block;margin-top:10px;margin-bottom:5px;width:100%;clear:both;' />");
            }
         });

         jQuery("#publishing-action").append(
                 "<input type='button' id='workflow_submit' class='button button-primary button-large'" + " value='" + owf_submit_workflow_vars.submitToWorkflowButton + "' style='float:left;clear:both;' />"
                 ).css({"width": "100%"});

         jQuery("#post").append(
                 "<input type='hidden' id='hi_workflow_id' name='hi_workflow_id' />" +
                 "<input type='hidden' id='hi_step_id' name='hi_step_id' />" +
                 "<input type='hidden' id='hi_priority_select' name='hi_priority_select' />" +
                 "<input type='hidden' id='hi_actor_ids' name='hi_actor_ids' />" +
                 "<input type='hidden' id='hi_is_team' name='hi_is_team' />" +
                 "<input type='hidden' id='hi_due_date' name='hi_due_date' />" +
                 "<input type='hidden' id='hi_publish_datetime' name='hi_publish_datetime' />" +
                 "<input type='hidden' id='hi_custom_condition' name='hi_custom_condition' />" +
                 "<input type='hidden' id='hi_comment' name='hi_comment' />" +
                 "<input type='hidden' id='save_action' name='save_action' />"
                 );
         jQuery("#publishing-action").css({"margin-top": "10px"});
      }
   }

   initWorkflowSubmit();

   // called when user clicks on "submit to workflow" on the edit page
   jQuery(document).on("click", "#workflow_submit", function () {

      // hook for custom validation before submitting to the workflow
      if (typeof owSubmitToWorkflowPre === 'function') {
         var pre_submit_to_workflow_result = owSubmitToWorkflowPre();
         if (pre_submit_to_workflow_result == false) {
            return false;
         }
      }

      // hook for running ACF or other third party plugin validations if needed before submitting to the workflow
      owThirdPartyValidation.run(workflowSubmit);
   });

   jQuery(".date-clear").click(function () {
      jQuery(this).parent().children(".date_input").val("");
   });

   jQuery(document).on("click", "#workflow_revision", function () {
      revision_compare_popup(owf_submit_workflow_vars.revisionPrepareMessage,
         owf_submit_workflow_vars.clickHereText,
         owf_submit_workflow_vars.absoluteURL);
   });

   jQuery(document).on("click", "#submitCancel, .modalCloseImg", function () {
      modalClose();
   });

   jQuery("#workflow-select").change(function () {
      workflowSelect(jQuery(this).val());
   });
   
   /* On Change of Workflow Step during Submit to Workflow */
   jQuery(document).on("change", "#step-select", function () {
      actionSetting("step", "pre");
      jQuery("#submitSave").prop('disabled', true);
      jQuery("#step-loading-span").addClass("loading");

      var get_submit_step_details_data = {
         action: 'get_submit_step_details',
         step_id: jQuery(this).val(),
         post_id: jQuery("#post_ID").val(),
         wf_id: jQuery("#workflow-select").val(),
         history_id: "",
         security: jQuery('#owf_signoff_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, get_submit_step_details_data, function (response) {
         if (response == -1) {
            jQuery("#step-loading-span").removeClass("loading");
            return false; // Invalid nonce
         }

         // if response is false then there are no users for given role..!
         if ( ! response.success ) {
            jQuery("#step-loading-span").removeClass("loading");
            displayWorkflowSubmitErrorMessages( response.data.errorMessage );
            return false;
         }

         // if teams is active, show teams drop down
         if ( response.data.teams != "" ) {
            var teams = Object.keys(response.data.teams).length;
            add_option_to_select("teams-list-select", response.data.teams, 'name', 'ID');
            jQuery('.select-teams-div').removeClass('owf-hidden');
            // If object length is 1 than trigger the change event
            if ( teams === 1 ) {
               jQuery("#teams-list-select").change();
            }
         }

         // if there is any custom data, like checklist conditions, display custom data
         if ( response.data.custom_data != "" ) {
            jQuery('#ow-step-custom-data').removeClass('owf-hidden');
            jQuery('#ow-step-custom-data').html(html_decode( response.data.custom_data ) );
         } else {
            jQuery('#ow-step-custom-data').addClass('owf-hidden');
            jQuery('#ow-step-custom-data').html("");
         }

         // update the stepProcess var
         // TODO : see if we can get rid of the stepProcess var
         if ( response.data.process != "" ) {
            stepProcess = response.data.process;
         }

         var users = "";

         // get assign to all value from the step
         var is_assign_to_all = "";
         if ( response.data.assign_to_all != "" ) {
            is_assign_to_all = parseInt(response.data.assign_to_all);
         }

         if ( jQuery("#assign_to_all").length ) { //if the field exists, update it
            jQuery("#assign_to_all").val(is_assign_to_all);
         } else { // add the field to the page
            jQuery('<input>').attr({
               type: 'hidden',
               id: 'assign_to_all',
               name: 'assign_to_all',
               value: is_assign_to_all
            }).appendTo('#post');
         }

         // if assign to all is checked, then hide the assignee selection.
         if ( is_assign_to_all === 1 ) {
            jQuery('#multiple-actors-div').addClass('owf-hidden');
         } else if ( is_assign_to_all !== 1 && response.data.teams != ""  ) {
            // If assign to all is false and team addon is enable
            jQuery('#multiple-actors-div').removeClass('owf-hidden');
         } else { // "assign to all" is false, show the user selection
            jQuery('#multiple-actors-div').removeClass('owf-hidden');
            if ( response.data.users != "" ) {
               if (typeof response.data.users[0] == 'object') {
                  users = response.data.users;                  
                  var i;
                  var postAuthor = "";
                  var substring = "Post Author";
                  for( i = 0; i < users.length; i++ ) {
                     if( users[i].name.indexOf(substring) !== -1 ) {
                        postAuthor = users[i];
                     }
                  }
                  users.sort(function(x,y){
                     return x===postAuthor?-1:y===postAuthor?1:0;
                  });
               }
            }
            add_option_to_select("actors-list-select", users, 'name', 'ID');
         }
         
         if ( response.data.due_date != ""  ) {
            jQuery('#due-date').val(response.data.due_date);
         } 

         actionSetting("step", "after");
         
         // Resize the popup based on show/hide of assignees
         jQuery("#simplemodal-container").css({
               "height": "663px",
         });

         jQuery("#step-loading-span").removeClass("loading");
         jQuery("#submitSave").prop('disabled', false);

      });
   });

   // user assign action
   jQuery("#assignee-set-point").click(function () {
      jQuery('#actors-list-select option:selected').each(function () {
         var v = jQuery(this).val();
         var t = jQuery(this).text();
         addRemoveOptions('actors-list-select', 'actors-set-select', v, t);
      });
      return false;
   });

   // user unassign action
   jQuery("#assignee-unset-point").click(function () {
      jQuery('#actors-set-select option:selected').each(function () {
         var v = jQuery(this).val();
         var t = jQuery(this).text();
         addRemoveOptions('actors-set-select', 'actors-list-select', v, t);
      });
      return false;
   });

   /*
    * Submit to Workflow
    */
   jQuery(document).on("click", "#submitSave", function () {

      // validate if all the required fields have data
      if ( ! validateRequiredFormFields() ) {
         return false;
      }

      // looks like we are good, lets show the loading icon and hide the "submit" button,
      // so that the user cannot click twice
      jQuery(".changed-data-set span").addClass("loading");
      jQuery(this).hide();

      // validate and get selected actors
      var actors = validateAndGetSelectedActors(); 

      // if no actors found, return
      if (! actors)
         return;

      // get all the form fields
      jQuery("#hi_workflow_id").val(jQuery("#workflow-select").val());
      jQuery("#hi_step_id").val(jQuery("#step-select").val());
      jQuery("#hi_priority_select").val(jQuery("#priority-select").val());
      jQuery("#hi_actor_ids").val(actors);
      jQuery("#hi_due_date").val(jQuery("#due-date").val());
      jQuery("#hi_comment").val(jQuery("#workflowComments").val());

      // set user specified publish date into hidden field
      if ( typeof jQuery("#publish-date").val() != 'undefined' && jQuery("#publish-date").val() != '' ) {
         user_specified_publish_date = jQuery("#publish-date").val() + " @ " + jQuery("#publish-hour").val() + ":" + jQuery("#publish-min").val();
         jQuery("#hi_publish_datetime").val(user_specified_publish_date);
      }

      // pre publish checklist - displayed from the checklist add-on
      var selected_pre_publish_conditions = [];
      jQuery('.ow-pre-publish-conditions input[type="checkbox"]').each(function () {
         var is_checked = jQuery(this).is(":checked");
         if (is_checked) {
            selected_pre_publish_conditions.push(jQuery(this).val());
         }
      });
      jQuery("#hi_custom_condition").val(selected_pre_publish_conditions);

      var validate_submit_to_workflow_data = {
         action: 'validate_submit_to_workflow',
         form: jQuery('form#post').serialize(),
         step_id: jQuery("#step-select").val(),
         security: jQuery('#owf_signoff_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, validate_submit_to_workflow_data, function (response) {
         if (response == -1) {
            return false; // Invalid nonce
         }

         // if response is false then there are no users for given role..!
         if ( ! response.success ) { 
            displayWorkflowSubmitErrorMessages( response.data.errorMessage );

            // display the "submit" button again
            jQuery("#submitSave").show();

            return false;
         }
         if ( response.success ) {
            modalClose();
            // no validation errors, proceed with submit to workflow
            // lets update the post status, with the post status from the first step
            jQuery("#post_status").val(response.data.post_status);
            if ( jQuery("#hidden_post_status").length ) {
               jQuery("#hidden_post_status").val(response.data.post_status);
            }

            jQuery("#save_action").val("submit_post_to_workflow");
            jQuery("#save-post").click();
         }
      });
   });


   function workflowSubmit() {
      // display the submit to workflow popup dialog
      jQuery("#new-workflow-submit-div").owfmodal({
         onShow: function (dlg) {
            jQuery("#simplemodal-container").css({
               "max-height": "90%",
               "top":"60px"
            });
            jQuery(dlg.wrap).css('overflow', 'auto'); // or try ;
            // commented out, so that the above CSS can take effect
            //jQuery.modal.update();
         }
      });

      // call workflow select
      if (jQuery('#workflow-select option:selected').length > 0) {
         workflowSelect(jQuery("#workflow-select").val());
      }
      calendarAction();
   }

   function calendarAction() {
      // set the datepicker for the due date
      jQuery("#due-date").attr("readonly", true);
      jQuery("#due-date").datepicker({
         autoSize: true,
         changeMonth: true,
         changeYear: true,
         yearRange: '1950:2050',
         dateFormat: owf_submit_workflow_vars.editDateFormat
      });
      
      if ( jQuery('body > #ui-datepicker-div').length > 0 )
      {
         jQuery('#ui-datepicker-div').wrap('<div class="ui-oasis" />');
      }
      
      // set the datepicker for the publish date
      // get the publish date from DB for the post
      jQuery(".publish-date-loading-span").addClass("loading");

      data = {
         action: 'get_post_publish_date_edit_format',
         post_id: jQuery('#post_ID').val()
      };

      jQuery.post(ajaxurl, data, function (response) {
         if ( response.success ) {
            jQuery("#publish-date").val(response.data.publish_date);

            // time
            jQuery("#publish-hour").val(response.data.publish_hour);
            jQuery("#publish-min").val(response.data.publish_min);

            jQuery(".publish-date-loading-span").removeClass("loading");
         }
      });

      jQuery("#publish-date").attr("readonly", true);
      // add jquery datepicker functionality to publish textbox
      jQuery("#publish-date").datepicker({
         autoSize: true,
         changeMonth: true,
         changeYear: true,
         yearRange: '1950:2050',
         dateFormat: owf_submit_workflow_vars.editDateFormat
      });
   }

   function modalClose() {
      // init the stepProcess and close the modal window
      stepProcess = "";
      jQuery.modal.close();
   }

   /* field settings when select */
   function actionSetting(inx, frm) {
      if (inx == "wf") {
         if (frm == "pre") {
            stepProcess = "";
            jQuery("#step-select").find('option').remove();
            jQuery("#actor-one-select").find('option').remove();
            jQuery("#actors-list-select").find('option').remove();
            jQuery("#actors-set-select").find('option').remove();

            jQuery("#actor-one-select").attr("disabled", true);
            jQuery("#actors-list-select").attr("disabled", true);
            jQuery("#actors-set-select").attr("disabled", true);

            jQuery("#step-loading-span").addClass("loading");
         }
      }
      if (inx == "step") {
         stepProcess = "";
         if (frm == "pre") {
            jQuery("#actor-one-select").find('option').remove();
            jQuery("#actors-list-select").find('option').remove();
            jQuery("#actors-set-select").find('option').remove();

            jQuery("#actor-one-select").attr("disabled", true);
            jQuery("#actors-list-select").attr("disabled", true);
            jQuery("#actors-set-select").attr("disabled", true);

            jQuery(".assign-loading-span").addClass("loading");
         } else {
            jQuery(".assign-loading-span").removeClass("loading");
            jQuery("#actor-one-select").removeAttr("disabled");
            jQuery("#actors-list-select").removeAttr("disabled");
            jQuery("#actors-set-select").removeAttr("disabled");

         }
      }
   }
   /* Action on workflow select */
   function workflowSelect( workflow_id ) {
      actionSetting("wf", "pre");

      jQuery("#step-loading-span").addClass("loading");

      if ( ! workflow_id ) {
         jQuery("#step-loading-span").removeClass("loading");
         return;
      }

      data = {
         action: 'get_first_step',
         wf_id: workflow_id,
         security: jQuery('#owf_signoff_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, data, function (response) {
         jQuery("#step-loading-span").removeClass("loading");

         if (response == -1) {
            return false; // Invalid Nonce
         }
         if (response.data == "nodefine") {
            alert(owf_submit_workflow_vars.allStepsNotDefined);
            jQuery("#workflow-select").val("");
            return;
         }
         if ( ! response.success ) {
            alert(owf_submit_workflow_vars.notValidWorkflow);
            jQuery("#workflow-select").val("");
            return;
         }
         var stepinfo = {};
         stepinfo = response.data;
         jQuery("#step-select").find('option').remove();
         jQuery("#step-select").append("<option value='" + stepinfo["first"][0][0] + "'>" + stepinfo["first"][0][1] + "</option>");
         jQuery("#step-select").change();
      });
   }

   function addRemoveOptions (removeSelector, appendSelector, val, text) {
      if (typeof val !== 'undefined') {
         jQuery("#" + removeSelector + " option[value='" + val + "']").remove();
         jQuery('#' + appendSelector).append('<option value=' + val + '>' + text + '</option>');
      }
   };

   function validateRequiredFormFields() {
      if (!jQuery("#workflow-select").val()) {
         alert(owf_submit_workflow_vars.selectWorkflow);
         return false;
      }

      if (!jQuery("#step-select").val()) {
         alert(owf_submit_workflow_vars.selectStep);
         return false;
      }

      if (jQuery("#step-select").val() == "nodefine") {
         alert(owf_submit_workflow_vars.stepNotDefined);
         return false;
      }

      /*  if reminder email checkbox is selected in workflow email settings.
       *  then Due Date is Required Else Not
       */
      if (owf_submit_workflow_vars.drdb != "" || owf_submit_workflow_vars.drda != "" || owf_submit_workflow_vars.defaultDueDays != "")
      {
         if (jQuery("#due-date").val() == '') {
            alert(owf_submit_workflow_vars.dueDateRequired);
            return false;
         }
      }

      return true;
   }

   function validateAndGetSelectedActors() {
      var is_assigned_to_all = parseInt(jQuery('#assign_to_all').val());

      // Case 1: teams add-on is active
      // Validate if team is selected
      // if not, display error message.      
      if ( owf_submit_workflow_vars.workflowTeamsAvailable == 'yes' ) {
         var optionNum = jQuery("#teams-list-select").val();
         if ( optionNum == '' ) {
            jQuery(".changed-data-set span").removeClass("loading");
            jQuery("#submitSave").show();
            alert(owf_submit_workflow_vars.noTeamSelected);
            return false;
         }
         
         // If assign to all is checked simply send team_id
         var team_id = jQuery('#teams-list-select').val();
         if ( team_id && is_assigned_to_all === 1 ) {
            jQuery("#hi_is_team").val("true");
            return team_id;
         } else if ( team_id && is_assigned_to_all !== 1 ) {
            // If assign to all is uncheck send multi actors
            var selectedOptionCount = jQuery("#actors-set-select option").length;
            if ( ! selectedOptionCount ) {
               alert(owf_submit_workflow_vars.noAssignedActors);
               jQuery(".changed-data-set span").removeClass("loading");
               jQuery("#submitSave").show();
               return false;
            }
            var multi_actors = "", i = 1;
            jQuery("#actors-set-select option").each(function () {
               if ( i == selectedOptionCount )
                  multi_actors += jQuery(this).val();
               else
                  multi_actors += jQuery(this).val() + "@";
               i++;
            });
            if ( multi_actors ) {
               jQuery("#hi_is_team").val(team_id);
               return multi_actors;
            } else {
               return false;
            }
         }
      }

      // Case 2: Assign to all is checked
      // nothing to validates, simply return true
      if ( is_assigned_to_all === 1 ) {
         return true;
      }

      // Case 3: Regular multi-actor selection
      // Validate if, at least one user is selected,
      // if not, display error message
      // if yes, return the selected actor(s)

      var selectedOptionCount = jQuery("#actors-set-select option").length;
      if ( ! selectedOptionCount && is_assigned_to_all !== 1 ) {
         alert(owf_submit_workflow_vars.noAssignedActors);
         jQuery( ".changed-data-set span" ).removeClass("loading");
         jQuery( "#submitSave" ).show();
         return false;
      }
      var multi_actors = "", i = 1;
      jQuery( "#actors-set-select option" ).each(function () {
         if ( i == selectedOptionCount )
            multi_actors += jQuery(this).val();
         else
            multi_actors += jQuery(this).val() + "@";
         i++;
      });
      if ( multi_actors && is_assigned_to_all !== 1 ) {
         return multi_actors;
      } else {
         return false;
      }
   }

   function displayWorkflowSubmitErrorMessages( errorMessages ) {
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
});