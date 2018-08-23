jQuery(document).ready(function () {
   var stepProcess = ""; // process of selected step

   // initialize workflow sign off
   function initWorkflowSignoff() {
      jQuery("#publishing-action #publish").before("<span class='blank-space loading owf-hidden'></span>");
      // if current user can edit posts
      if (jQuery("#hi_editable").val()) {
         if (flag_compare_button) {
            jQuery(".loading").show();
            flag_compare_button = false;
            var get_original = {
               action: 'get_original',
               post_id: jQuery('#post_ID').val(),
               security: jQuery('#owf_workflow_abort_nonce').val()
            };
            jQuery.post(ajaxurl, get_original, function (response) {
               jQuery(".loading").hide();
               if (response.success && owf_submit_step_vars.hideCompareButton == "") {
                  jQuery("#publishing-action").append(
                          "<input type='button' id='step_revision' class='button button-primary button-large submit-step-compare'" +
                          " value='" + owf_submit_step_vars.compareOriginal + "' style='float:left;margin-top:10px;width:100%;' />");
               }
            });
         }
         jQuery(".loading").show();
         var check_claim = {
            action: 'check_for_claim_ajax',
            history_id: jQuery("#hi_oasiswf_id").val(),
            security: jQuery('#owf_check_claim_nonce').val()
         };
         jQuery.post(ajaxurl, check_claim, function (response) {
            if (response == -1) {
               return false; // Invalid nonce
            }
            jQuery(".loading").hide();
            if ( ! response.success ) {
               jQuery("#publishing-action").append(
                       "<input type='button' id='step_submit' class='button button-primary button-large'" +
                       " value='" + owf_submit_step_vars.signOffButton + "' style='float:left;margin-top:10px;clear:both' />" +
                       "<input type='hidden' name='hi_process_info' id='hi_process_info' />" +
                       "<input type='hidden' name='hi_oasiswf_redirect' id='hi_oasiswf_redirect' value=''/>").css({"width": "100%"});
            } else {
               jQuery("#publishing-action").append(
                       "<input type='button' id='claimButton' class='button button-primary button-large'" +
                       " value='" + owf_submit_step_vars.claimButton + "' style='float:left;margin-top:10px;clear:both' />" +
                       "<input type='hidden' name='hi_process_info' id='hi_process_info' />" +
                       "<input type='hidden' name='hi_oasiswf_redirect' id='hi_oasiswf_redirect' value=''/>").css({"width": "100%"});
            }
         });
      } else {
         jQuery("#publish").hide();
         jQuery(".loading").show();

         var check_claim = {
            action: 'check_for_claim_ajax',
            history_id: jQuery("#hi_oasiswf_id").val(),
            security: jQuery('#owf_check_claim_nonce').val()
         };

         jQuery.post(ajaxurl, check_claim, function (response) {
            if (response == -1) {
               return false; // Invalid nonce
            }
            jQuery(".loading").hide();
            if ( ! response.success ) {
               jQuery("#publishing-action").append("<input type='button' id='step_submit' class='button button-primary button-large' " +
                       "style='float:left;margin-top:10px;' value='" + owf_submit_step_vars.signOffButton + "' />");
            } else {
               jQuery("#publishing-action").append("<input type='button' id='claimButton' class='button button-primary button-large' " +
                       "style='float:left;margin-top:10px;' value='" + owf_submit_step_vars.claimButton + "' />");
            }
         });
      }

      jQuery("#publishing-action").append("<a style='float:right;margin-top:10px;' href='admin.php?page=oasiswf-inbox'>" +
              owf_submit_step_vars.inboxButton + "</a>");

      jQuery("#post").append(
         "<input type='hidden' id='save_action' name='save_action' />"
      );

      jQuery('.inline-edit-status').hide();
   }

   // When page is loaded, this function is processed
   if (jQuery("#hi_parrent_page").val() == "post_edit") {
      initWorkflowSignoff();
   }

   // called when user clicks on "sign off" on the edit page
   jQuery(document).on("click", "#step_submit", function () {

      // hook for custom validation before submitting to the workflow
      if (typeof owSignOffPre === 'function') {
         var sign_off_pre_result = owSignOffPre();
         if (sign_off_pre_result == false) {
            return false;
         }
      }

      // hook for running ACF or other third party plugin validation if needed prior to signing off on the workflow
      owThirdPartyValidation.run(workflowSignOff);

      return false;

   });

   jQuery(document).on("click", "#step_revision", function () {
      revision_compare_popup(owf_submit_step_vars.revisionPrepareMessage,
              owf_submit_step_vars.clickHereText,
              owf_submit_step_vars.absoluteURL);
   });

   // to clear the dates
   jQuery(document).on("click", ".date-clear", function () {
      jQuery(this).parent().children(".date_input").val("");
   });

   // close the step sign off popup
   jQuery(document).on("click", "#submitCancel, .modalCloseImg", function () {
      modalClose();
   });

   // called on decision select change on the sign off popup.
   jQuery(document).on("change", "#decision-select", function () {
      var get_action = "";
      var decision = "";
      if ( "complete" == jQuery(this).val() ) {
         decision = "success";
      }
      if ( "unable" == jQuery(this).val() ) {
         decision = "failure";
      }
      jQuery("#submitSave").prop('disabled', true);
      actionSetting();

      var execute_sign_off_decision_data = {
         action: 'execute_sign_off_decision',
         post_id: jQuery("#hi_post_id").val(),
         history_id: jQuery("#hi_oasiswf_id").val(),
         decision: decision,
         security: jQuery('#owf_signoff_ajax_nonce').val()
      };

      jQuery("#sum_step_info").css("opacity", 1);
      jQuery("#step-loading-span").addClass("loading");

      jQuery.post(ajaxurl, execute_sign_off_decision_data, function ( response ) {
         if (response == -1) {
            return false; // Invalid nonce
         }

         jQuery("#step-loading-span").removeClass("loading");

         // if there is any custom data, like checklist conditions, display custom data
         if ( response.data.custom_data != "" ) {
            jQuery('#ow-step-custom-data').removeClass('owf-hidden');
            jQuery('#ow-step-custom-data').html(html_decode( response.data.custom_data ) );
         } else {
            jQuery('#ow-step-custom-data').addClass('owf-hidden');
            jQuery('#ow-step-custom-data').html("");
         }

         // if there are next steps
         if ( response.data.steps != "" ) {
            var next_steps = response.data.steps;
            jQuery("#step-select").removeAttr("disabled");
            jQuery("#step-select").find('option').remove();
            add_option_to_select("step-select", next_steps, 'step_name', 'step_id');

            // if there is only one possible next step, auto select it
            if (next_steps.length == 1) {
               jQuery("#step-select").change();
            } else {
               jQuery("#submitSave").prop('disabled', false);
            }
         } else { // looks like we are on the last step of the workflow
            if ( "failure" == decision ) {
               showLastStepFailureMessage();
            } else if ( "success" == decision ) {
               var is_original_post = response.data.is_original_post;
               showLastStepSuccessMessage( is_original_post );

               jQuery("#completeSave").show();
               jQuery("#sum_step_info").hide();
            }
            // re-position the popup
            setPosition();
            jQuery("#submitSave").prop('disabled', false);
         }

      });
   });

   // called on change of Workflow Step during Sign off
   jQuery(document).on("change", "#step-select", function () {

      jQuery(".assign-loading-span").addClass("loading");
      jQuery("#step-loading-span").addClass("loading");

      // reset the error messages
      jQuery('#ow-step-messages').html("");
      jQuery('#ow-step-messages').addClass('owf-hidden');
      
      jQuery("#submitSave").prop('disabled', true);

      var get_sign_off_step_details_data = {
         action: 'get_sign_off_step_details',
         step_id: jQuery(this).val(),
         post_id: jQuery("#hi_post_id").val(),
         history_id: jQuery("#hi_oasiswf_id").val(),
         security: jQuery('#owf_signoff_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, get_sign_off_step_details_data, function (response) {
         if (response == -1) {
            return false; // Invalid nonce
         }

         jQuery(".assign-loading-span").removeClass("loading");
         jQuery("#step-loading-span").removeClass("loading");

         // if response is false then there are no users for given role..!
         if ( ! response.success ) {
            displayWorkflowSignOffErrorMessages( response.data.errorMessage );
            return false;
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
            }).appendTo('#new-step-submit-div');
         }
         
         if ( response.data.due_date != ""  ) {
            jQuery('#due-date').val(response.data.due_date);
         } 

         // if teams, then set teams id as hidden field
         // get assign to all value from the step
         var team_id = "";
         var team_name = "";
         if ( response.data.team_id !== "" ) {
            team_id = parseInt(response.data.team_id);
            team_name = response.data.team_name;
            jQuery("#teams-list-select").append("<option value='" + team_id + "'>" +team_name + "</option>");           
            jQuery("#teams-list-select").change();
         }

         // if assign to all is checked, then hide the assignee selection.
         if ( is_assign_to_all === 1 ) {
            jQuery('#multiple-actors-div').addClass('owf-hidden');
         } else if ( is_assign_to_all !== 1 && response.data.team_id !== ""  ) {
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
         
         // Resize the popup based on show/hide of assignees
         jQuery("#simplemodal-container").css({
               "height": "663px",
         });

         jQuery("#submitSave").prop('disabled', false);
         
      });
   });

   // assign users to the step
   jQuery(document).on("click", "#assignee-set-point", function () {

      jQuery('#actors-list-select option:selected').each(function () {
         var v = jQuery(this).val();
         var t = jQuery(this).text();
         addRemoveOptions('actors-list-select', 'actors-set-select', v, t);
      });
      return false;
   });

   //unassign users from the step
   jQuery(document).on("click", "#assignee-unset-point", function () {
      jQuery('#actors-set-select option:selected').each(function () {
         var v = jQuery(this).val();
         var t = jQuery(this).text();
         addRemoveOptions('actors-set-select', 'actors-list-select', v, t);
      });
   });

   // called when sign off a task - Sign off button click on the popup
   jQuery(document).on("click", "#submitSave", function () {
      var obj = this;

      // validate if all the required fields have data
      if ( ! validateRequiredFormFields() ) {
         return false;
      }

      // looks like we are good, lets show the loading icon and hide the "sign off" button,
      // so that the user cannot click twice
      jQuery(".changed-data-set span").addClass("loading");
      jQuery("#submitSave").hide();

      // validate and get selected actors
      var actors = validateAndGetSelectedActors();

      // if no actors found, return
      if (! actors)
         return;


      jQuery("#hi_custom_condition").val( getPrePublishChecklist() );

      var submit_post_to_step_data = {
         action:              'submit_post_to_step',
         post_id:             jQuery("#hi_post_id").val(),
         step_id:             jQuery("#step-select").val(),
         actors:              actors,
         due_date:            jQuery("#due-date").val(),
         sign_off_comments:   jQuery("#workflowComments").val(),
         task_user:           jQuery("#hi_task_user").val(),
         team:                jQuery("#hi_is_team").val(),
         history_id:          jQuery("#hi_oasiswf_id").val(),
         custom_condition:    jQuery("#hi_custom_condition").val(),
         step_decision:       jQuery("#decision-select").val(),
         priority:            jQuery("#priority-select").val(),
         form:                jQuery("form#post").serialize(),
         security:            jQuery("#owf_signoff_ajax_nonce").val()
      };

      jQuery.post(ajaxurl, submit_post_to_step_data, function (response) {
         if (response == -1) {
            return false; // Invalid nonce
         }

         // if response is false then there are no users for given role..!
         if ( ! response.success ) {
            displayWorkflowSignOffErrorMessages( response.data.errorMessage );

            // display the "submit" button again
            jQuery("#submitSave").show();

            return false;
         }

         jQuery( ".changed-data-set span" ).removeClass( "loading" );
         if ( jQuery( "#hi_parrent_page" ).val() == "inbox" ) {
            location.reload(); // simply reload the inbox page
         } else { // update the post status and save the post
            modalClose();
            jQuery( "#post_status" ).val( response.data.new_post_status );
            if ( jQuery("#hidden_post_status").length ) {
               jQuery("#hidden_post_status").val(response.data.new_post_status);
            }
            jQuery( "#save-post" ).click();
         }
      });
   });

   // show/hide the publish date selection depending on "publish immediately" checkbox value
   jQuery( document ).on( "click", "#immediately-chk", function () {
      if ( jQuery( this ).attr( "checked" ) == "checked" ) {
         jQuery( "#immediately-span" ).hide();
      } else {
         jQuery( "#immediately-span" ).show();
      }
   } );

   // called when signing off from the last step of the workflow
   jQuery( document ).on( "click", "#completeSave", function () {

      // show loading and hide the button
      jQuery( ".changed-data-set span" ).addClass( "loading" );
      jQuery( this ).hide();

      // get the user assigned publish date, if any
      var im_date = getImmediatelyDate();

      // get the custom data, in this case, pre publish checklist
      jQuery("#hi_custom_condition").val( getPrePublishChecklist() );

      var workflow_complete_data = {
         action:           'workflow_complete',
         history_id:       jQuery( "#hi_oasiswf_id" ).val(),
         post_id:          jQuery( "#hi_post_id" ).val(),
         task_user:        jQuery( "#hi_task_user") .val(),
         parent_page:      jQuery( "#hi_parrent_page" ).val(),
         custom_condition: jQuery( "#hi_custom_condition" ).val(),
         immediately:      im_date,
         form:             jQuery("form#post").serialize(),
         security:         jQuery( '#owf_signoff_ajax_nonce' ).val()
      };

      jQuery.post( ajaxurl, workflow_complete_data, function ( response ) {
         if ( response == -1 ) { // incorrect nonce
            return false;
         }

         // if response is false - looks like we have validation errors!
         if ( ! response.success ) {
            displayWorkflowSignOffErrorMessages( response.data.errorMessage );

            // display the "submit" button again
            jQuery( ".changed-data-set span" ).removeClass( "loading" );
            jQuery( "#completeSave" ).show();

            return false;
         }

         jQuery( document ).find( "#step_submit" ).remove();

         if ( jQuery( "#hi_parrent_page" ).val() == "inbox" ) {
            jQuery( ".changed-data-set span" ).removeClass( "loading" );
            // reload the inbox page
            location.reload();
         } else {
            jQuery( ".changed-data-set span" ).removeClass( "loading" );
            modalClose();
            jQuery( "#save_action" ).val("workflow_complete");
            jQuery( "#post_status" ).val(response.data.new_post_status);
            if ( jQuery("#hidden_post_status").length ) {
               jQuery("#hidden_post_status").val(response.data.new_post_status);
            }
            jQuery( "#save-post" ).click();
         }
      } );
   } );

   jQuery(".immediately").keydown(function () {

      jQuery(this).css("background-color", "#ffffff");
   });

   // called when cancelling the workflow
   jQuery(document).on("click", "#cancelSave", function () {
      var obj = this;

      // show loading and hide the button
      jQuery(".changed-data-set span").addClass("loading");
      jQuery(this).hide();

      var workflow_cancel_data = {
         action: 'workflow_cancel',
         history_id: jQuery("#hi_oasiswf_id").val(),
         post_id: jQuery("#hi_post_id").val(),
         comments: jQuery("#workflowComments").val(),
         review_result: jQuery("#decision-select").val(),
         security: jQuery('#owf_signoff_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, workflow_cancel_data, function (response) {
         if ( response == -1 ) { // incorrect nonce
            return false;
         }

         // if response is false - looks like we have validation errors!
         if ( ! response.success ) {
            displayWorkflowSignOffErrorMessages( response.data.errorMessage );

            // display the "submit" button again
            jQuery( ".changed-data-set span" ).removeClass( "loading" );
            jQuery( "#cancelSave" ).show();

            return false;
         }

         jQuery(".changed-data-set span").removeClass("loading");
         jQuery(document).find("#step_submit").remove();

         if (jQuery("#hi_parrent_page").val() == "inbox") {
            location.reload();
         } else {
            modalClose();
            location.reload();
         }
      });
   });

   // called when a task is being claimed
   jQuery(document).on("click", "#claimButton", function () {
      var claim = jQuery(this);
      var post_id = jQuery("#hi_post_id").val();
      data = {
         action: 'claim_process',
         actionid: jQuery("#hi_oasiswf_id").val().trim(),
         security: jQuery('#owf_claim_process_ajax_nonce').val()
      };

      jQuery(this).parent().children(".loading").show();
      jQuery.post(ajaxurl, data, function (response) {
         if ( response === -1 ) {
            claim.parent().children(".loading").hide();
            return false;
         }
         if ( response.success ) {
            var ow_admin_url = response.data.url;
            var new_history_id = response.data.new_history_id;
            window.location.href = ow_admin_url + 'post.php?post=' + post_id +'&action=edit&oasiswf=' + new_history_id;
         }
         if ( !response.success ) {
            claim.parent().children(".loading").hide();
            var content =  response.data.errorMessage ;
            jQuery( content ).owfmodal(); 
         }
      });
   });


   function workflowSignOff() {
      jQuery('#hi_oasiswf_redirect').val("step");
      jQuery("#new-step-submit-div").owfmodal({
         onShow: function (dlg) {
            jQuery("#simplemodal-container").css({
               "max-height": "90%",
               "top":"60px"
            });
            jQuery(dlg.wrap).css('overflow', 'auto'); // or try ;
            // commented out, so that the above CSS can take effect
            //jQuery.modal.update();y            
         }
      });
      stepProcess = "";
      calendarAction();
   }

   function calendarAction() {
      jQuery("#due-date").datepicker({
         autoSize: true,
         changeMonth: true,
         changeYear: true,
         yearRange: '1950:2050',
         dateFormat: owf_submit_step_vars.editDateFormat
      });
      if ( jQuery('body > #ui-datepicker-div').length > 0 )
      {
         jQuery('#ui-datepicker-div').wrap('<div class="ui-oasis" />');
      }
   }

   function modalClose() {
      stepProcess = "";
      jQuery.modal.close();
      if (jQuery("#hi_parrent_page").val() == "inbox")
         jQuery(document).find("#step_submit_content").html("");
   }

   function showLastStepFailureMessage() {
      var msg = owf_submit_step_vars.lastStepFailureMessage;
      jQuery("#message_div").html(msg).css({"background-color": "#fbd7f0", "border": "1px solid #f989d8"}).show();

      jQuery("#cancelSave").show();
      jQuery("#submitSave").hide();
      jQuery("#completeSave").hide();

      jQuery("#sum_step_info").hide();
   }

   function showLastStepSuccessMessage( is_original_post ) {
      var msg = owf_submit_step_vars.lastStepSuccessMessage;
      jQuery("#message_div").html(msg).css({"background-color": "#dcddfa", "border": "1px solid #b0b4fa"}).show();

      jQuery("#submitSave").hide();
      jQuery("#cancelSave").hide();
      jQuery("#comments-div").hide();

      jQuery("#immediately-div").show();
      jQuery("#update_publish_msg").hide();

      // If future date is set then uncheck the checkbox by default & show immediate span
      if (jQuery('#immediately-chk').attr("checked") == "checked")
      {
         jQuery("#immediately-span").hide();
      } else
      {
         jQuery("#immediately-span").show();
      }

      // show the update message for revisions
      if ( !is_original_post ) {
         jQuery("#update_publish_msg").show();
      }
   }

   function addRemoveOptions (removeSelector, appendSelector, val, text) {
      if (typeof val !== 'undefined') {
         jQuery("#" + removeSelector + " option[value='" + val + "']").remove();
         jQuery('#' + appendSelector).append('<option value=' + val + '>' + text + '</option>');
      }
   }

   function getImmediatelyDate() {
      var im_date = "";
      if ( jQuery( "#immediately-span" ).length > 0 && jQuery( "#immediately-span" ).is( ':visible' ) ) {
         if (isNaN(jQuery("#im-year").val())) {
            jQuery("#im-year").css("background-color", "#fadede");
            return im_date;
         }
         if (isNaN(jQuery("#im-day").val())) {
            jQuery("#im-day").css("background-color", "#fadede");
            return im_date;
         }
         if (isNaN(jQuery("#im-hh").val())) {
            jQuery("#im-hh").css("background-color", "#fadede");
            return im_date;
         }
         if (isNaN(jQuery("#im-mn").val())) {
            jQuery("#im-mn").css("background-color", "#fadede");
            return im_date;
         }

         im_date = jQuery("#im-year").val() + "-" +
            jQuery("#im-mon").val() + "-" +
            jQuery("#im-day").val() + " " +
            jQuery("#im-hh").val() + ":" +
            jQuery("#im-mn").val() + ":00";
      }

      return im_date;
   }

   function setPosition() {
      jQuery("#simplemodal-container").css({
         "max-height": "90%",
         "top":"60px"
      });

      // call modal.setPosition, so that the window height can adjust automatically depending on the displayed fields.
      // commented out, so that the above CSS can take effect
      //jQuery.modal.setPosition();
   }

   function actionSetting() {

      jQuery("#message_div").hide().html("");

      jQuery("#submitSave").show();
      jQuery("#comments-div").show();

      jQuery("#cancelSave").hide();
      jQuery("#completeSave").hide();
      jQuery("#immediately-div").hide();
      jQuery("#update_publish_msg").hide();

      jQuery("#sum_step_info").show();

      jQuery("#step-select").find('option').remove();
      jQuery("#actor-one-select").find('option').remove();
      jQuery("#actors-list-select").find('option').remove();
      jQuery("#actors-set-select").find('option').remove();

      jQuery("#step-select").attr("disabled", true);
      
      setPosition();
   }

   function validateAndGetSelectedActors() {

      var is_assigned_to_all = parseInt(jQuery('#assign_to_all').val());
      
      // Case 1: teams add-on is active
      if ( owf_submit_step_vars.workflowTeamsAvailable == 'yes' ) {
         // If assign to all is checked simply send team_id
         var team_id = jQuery('#teams-list-select').val();
         if ( team_id && is_assigned_to_all === 1 ) {
            jQuery("#hi_is_team").val("true");
            return team_id;
         } else if ( team_id && is_assigned_to_all !== 1 ) {
            // If assign to all is uncheck send multi actors
            var selectedOptionCount = jQuery("#actors-set-select option").length;
            if (! selectedOptionCount ) {
               alert(owf_submit_step_vars.noAssignedActors);
               jQuery( ".changed-data-set span" ).removeClass("loading");
               jQuery( "#submitSave" ).show();
               return false;
            }
            var multi_actors = "", i = 1;
            jQuery( "#actors-set-select option" ).each(function () {
               if (i == selectedOptionCount)
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
      if (! selectedOptionCount ) {
         alert(owf_submit_step_vars.noAssignedActors);
         jQuery( ".changed-data-set span" ).removeClass("loading");
         jQuery( "#submitSave" ).show();
         return false;
      }
      var multi_actors = "", i = 1;
      jQuery( "#actors-set-select option" ).each(function () {
         if (i == selectedOptionCount)
            multi_actors += jQuery(this).val();
         else
            multi_actors += jQuery(this).val() + "@";
         i++;
      });
      if (multi_actors)
         return multi_actors;
      else
         return false;
   }

   function validateRequiredFormFields() {
      if (!jQuery("#decision-select").val()) {
         alert(owf_submit_step_vars.decisionSelectMessage);
         return false;
      }

      if (!jQuery("#step-select").val()) {
         alert(owf_submit_step_vars.selectStep);
         return false;
      }
      /* This is for checking that reminder email checkbox is selected in workflow settings.
       If YES then Due Date is Required Else Not */
      if (owf_submit_step_vars.drdb != "" || owf_submit_step_vars.drda != "" || owf_submit_step_vars.defaultDueDays != "")
      {
         if (jQuery("#due-date").val() == '') {
            alert(owf_submit_step_vars.dueDateRequired);
            return false;
         }
      }

      return true;
   }

   // TODO: this function should go into the editorial checklist add-on
   function getPrePublishChecklist() {
      // pre publish checklist - displayed from the checklist add-on
      var selected_pre_publish_conditions = [];
      jQuery('.ow-pre-publish-conditions input[type="checkbox"]').each(function () {
         var is_checked = jQuery(this).is(":checked");
         if (is_checked) {
            selected_pre_publish_conditions.push(jQuery(this).val());
         }
      });

      return selected_pre_publish_conditions;
   }

   function displayWorkflowSignOffErrorMessages( errorMessages ) {
      jQuery('.error').hide();
      jQuery('#ow-step-messages').html(errorMessages);
      jQuery('#ow-step-messages').removeClass('owf-hidden');

      // scroll to the top of the window to display the error messages
      jQuery(".simplemodal-wrap").css('overflow', 'hidden');
      jQuery(".simplemodal-wrap").animate({scrollTop: 0}, "slow");
      jQuery(".simplemodal-wrap").css('overflow', 'scroll');
      jQuery(".changed-data-set span").removeClass("loading");

      // call setPosition, so that the window height can adjust automatically depending on the displayed fields.
      setPosition();
   }

});