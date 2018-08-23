jQuery(document).ready(function () {

	jQuery(document).on("mouseover", "#step-setting a", function () {
        jQuery(this).css({"color": "red"});
    });

    jQuery(document).on("mouseout", "#step-setting a", function () {
        jQuery(this).css({"color": "blue"});
    });

    jQuery(document).on('click', '#step_email_content > .nav-tab', function (e) {
        e.preventDefault();
        var tab = jQuery(this).attr('href');
        if (tab == '#assignment_email') {
            jQuery(this).addClass('nav-tab-active').next().removeClass('nav-tab-active');
            jQuery('#reminder_email').hide();
        } else {
            jQuery(this).addClass('nav-tab-active').prev().removeClass('nav-tab-active');
            jQuery('#assignment_email').hide();
        }
        jQuery(tab).fadeIn('slow');

    });

    /*
     * Cancelling/Closing the step popup
     */
    jQuery(document).on("click", "#stepCancel,.simplemodal-close", function () {
        jQuery.modal.close();
    }); 
    
    jQuery( document ).on( 'click', '#first_step_check', function ( e ) {
      var $this = jQuery( this );

      if ( $this.is( ':checked' ) ) {
         jQuery( '.first-step-post-status' ).removeClass( 'owf-hidden' );
      } else {
         jQuery( '.first-step-post-status' ).addClass( 'owf-hidden' );
      }

      return true;
   } );

    /*
     * Saving a step information
     */
    jQuery(document).on("click", "#stepSave", function () {
        if (!validate_step_data()) {
            return;
    	}

        var savedata = new Array();
        var step_gpid = jQuery.trim( jQuery("#step_gpid-hi").val().escapeSpecialChars() ) ;
        savedata = get_step_data();
        jQuery(".step-set span").addClass("loading");
        data = {
            action: 'save_workflow_step',
            wf_id: jQuery.trim( jQuery(document).find("#wf_id").val().escapeSpecialChars() ),
            step_gp_id:  jQuery.trim( jQuery(document).find( "#" + step_gpid ).attr("id").escapeSpecialChars() ),
            step_id: jQuery.trim( jQuery(document).find( "#" + step_gpid ).attr("db-id").escapeSpecialChars() ),
            step_name: jQuery("#step-name").val(),
            act: jQuery(document).find("#" + step_gpid).attr("real"),
            step_info: savedata[0],
            process_info: savedata[1],
            security: jQuery('#owf_workflow_create_nonce').val()
        };
        jQuery.post(ajaxurl, data, function (response) {
            if(response == -1){ // nonce cannot be verified
            	jQuery(".changed-data-set span").removeClass("loading");
                return "0";
            }
            jQuery(".step-set span").removeClass("loading");
            var saved_step_id = response.data;
            jQuery(document).find("#" + step_gpid).attr({"db-id": saved_step_id});

            // hook for saving condition group with the step information
            owChecklistConditionGroup.addToStep( saved_step_id );

            return saved_step_id;
        });
    });

    // assignment subject
    jQuery(document).on("click", "#addPlaceholderAssignmentSubj", function () {
        if (jQuery(this).parent().children("select").val() == '') {
            alert( owf_workflow_step_info_vars.selectPlaceholder );
            return false;
        }
        var v = jQuery(this).parent().children("select").val() + " "; //add a space after the placeholder
        jQuery("#assignment-email-subject").insertAtCaret(v);
    });

    // assignment message
    jQuery(document).on("click", "#addPlaceholderAssignmentMsg", function () {
        if (jQuery(this).parent().children("select").val() == '') {
            alert( owf_workflow_step_info_vars.selectPlaceholder );
            return false;
        }
        var v = jQuery(this).parent().children("select").val() + " "; //add a space after the placeholder
        setCurrentWhizzy('assignment-email-content');
        insHTML(v);
        jQuery("#addPlaceholderAssignmentMsg").focus();
    });

    // reminder subject
    jQuery(document).on("click", "#addPlaceholderReminderSubj", function () {
        if (jQuery(this).parent().children("select").val() == '') {
            alert( owf_workflow_step_info_vars.selectPlaceholder );
            return false;
        }
        var v = jQuery(this).parent().children("select").val() + " "; //add a space after the placeholder
        jQuery("#reminder-email-subject").insertAtCaret(v);
    });

    // reminder message
    jQuery(document).on("click", "#addPlaceholderReminderMsg", function () {
        if (jQuery(this).parent().children("select").val() == '') {
            alert(owf_workflow_step_info_vars.selectPlaceholder);
            return false;
        }
        var v = jQuery(this).parent().children("select").val() + " "; //add a space after the placeholder
        setCurrentWhizzy('reminder-email-content');
        insHTML(v);
        jQuery("#addPlaceholderReminderMsg").focus();
    });

    // fix for cursor to stay in the place where user has clicked
    jQuery.fn.extend({
        insertAtCaret: function (myValue) {
            return this.each(function (i) {
                if (document.selection) {
                    //For browsers like Internet Explorer
                    this.focus();
                    sel = document.selection.createRange();
                    sel.text = myValue;
                    this.focus();
                }
                else if (this.selectionStart || this.selectionStart == '0') {
                    //For browsers like Firefox and Webkit based
                    var startPos = this.selectionStart;
                    var endPos = this.selectionEnd;
                    var scrollTop = this.scrollTop;
                    this.value = this.value.substring(0, startPos) + myValue + this.value.substring(endPos, this.value.length);
                    this.focus();
                    this.selectionStart = startPos + myValue.length;
                    this.selectionEnd = startPos + myValue.length;
                    this.scrollTop = scrollTop;
                } else {
                    this.value += myValue;
                    this.focus();
                }
            })
        }
    });

    /*
     * validate step data before saving
     */
    var validate_step_data = function () {
    	// step name cannot be empty
        if (!jQuery("#step-name").val()) {
            alert(owf_workflow_step_info_vars.stepNameRequired);
            return false;
        }

        // step name already exists
        var valid = true;
        var step_gpid = jQuery("#step_gpid-hi").val();
        jQuery(document).find(".fc_action .w").each(function () {
            var lbl = jQuery(this).children("label").html();
            if (jQuery(this).attr("id") != step_gpid) {
                var step_name = jQuery("#step-name").val();
                if (jQuery.trim(lbl) == jQuery.trim(step_name))
                    valid = false;
            }
        });

        if (!valid) {
            alert(owf_workflow_step_info_vars.stepNameAlreadyExists);
            return false;
        }

        // assignees not selected
        var optionNum = jQuery("#show_available_actors").val();
        if (!optionNum) {
            alert(owf_workflow_step_info_vars.selectAssignees);
            return false;
        }
        
        return true;
    }

    var get_step_data = function () {
        var step_info_array = {};
        var process_info_array = {};
        var assignee = {};

        // extract step_info
        var step_gpid = jQuery.trim( jQuery("#step_gpid-hi").val().escapeSpecialChars() ) ;
        step_info_array["process"] = jQuery(document).find("#" + step_gpid).attr("process-name");
        step_info_array["step_name"] = jQuery("#step-name").val();
        step_info_array["assign_to_all"] = jQuery('#assign_to_all:checked').prop('checked') === true ? 1 : 0;
        step_info_array["step_due_days"] = jQuery("#step_due_days").val();

        // get assignee list
        var show_assignee = jQuery('#show_available_actors').val();
        // now lets convert object to string and then explode it by comma(,)
        show_assignee = show_assignee.toString().split(",");

        var roles = [],
            users = [],
            groups = [];
        var assign_type, assigned_user;
        for( var i = 0; i < show_assignee.length; i++ ) {
           
           assign_type = show_assignee[i].slice(0, 2);
           assigned_user = show_assignee[i].substring(2);
           switch(assign_type) {
              case 'g@':
                 groups.push(assigned_user);
                 break;
              case 'u@':
                 users.push(assigned_user);
                 break;
              case 'r@':
                 roles.push(assigned_user);
                 break;
           }
        }
        step_info_array['task_assignee'] = {
           'roles': roles,
           'users': users,
           'groups': groups
        };

        if(step_info_array["process"]=== 'review') {
            //extract review approval settings
            step_info_array["review_approval"] = jQuery("input:radio[name=review_approval]:checked").val();
        }

        // extract process info
        syncTextarea();
        process_info_array["assign_subject"] = jQuery.trim( jQuery("#assignment-email-subject").val().escapeSpecialChars() );
        var assign_content = jQuery.trim( jQuery("#assignment-email-content").val().escapeSpecialChars() );

        // fixed issue with \\n getting added in the content text. Removed it used regex.
        process_info_array["assign_content"] = assign_content.replace(/\r?\n|\r/g, "");
        process_info_array["reminder_subject"] = jQuery.trim( jQuery("#reminder-email-subject").val().escapeSpecialChars() );

        // fixed issue with \\n getting added in the content text. Removed it used regex.
        var reminder_content = jQuery.trim( jQuery("#reminder-email-content").val().escapeSpecialChars() );
        process_info_array["reminder_content"] = reminder_content.replace(/\r?\n|\r/g, "");

        // convert to json
        step_info = jQuery.toJSON(step_info_array);
        process_info = jQuery.toJSON(process_info_array); // process_info_array
        var step_data = new Array(step_info, process_info);
        return step_data;
    }
});

var jQuerySaveConditionGroup = jQuery.noConflict();
(function(jQuery){
	owChecklistConditionGroup = {
		addToStep : function ( step_id ) {
			if ( typeof addConditionGroupToStep !== 'undefined' ) {
				addConditionGroupToStep( step_id );
			}

            // TODO: if nonce is not correct then it returns always -1 not 0
            if ( step_id == "0" ) { // nonce verfication failed
            	return;
            }
    		var step_gpid = jQuery("#step_gpid-hi").val() ;
    		jQuery(document).find("#" + step_gpid + " label").html(jQuery("#step-name").val()) ;
    		if(jQuery("#first_step_check").attr("checked")){
    			jQuery(document).find("#" + step_gpid).attr("first_step", "yes") ;
            jQuery(document).find("#" + step_gpid).attr("post_status", jQuery( '#first_step_post_status' ).val() );
            if (typeof first_step_condition_group !== 'undefined') {
               jQuery(document).find("#" + step_gpid).attr("condition_group", jQuery( '#first_step_condition_group' ).val() );
            }
    			jQuery(document).find("#" + step_gpid).css("background-color", "#99CCFF");
    			jQuery(document).find("#" + step_gpid).children("label").css("color", "#000");
    		} else {
    			jQuery(document).find("#" + step_gpid).attr("first_step", "no") ;
            jQuery(document).find("#" + step_gpid).attr("post_status", 'draft' );
    			jQuery(document).find("#" + step_gpid).css("background-color", "#FFFFFF");
    			jQuery(document).find("#" + step_gpid).children("label").css("color", "#444444");
    		}

    		jQuery.modal.close();
		}
	};
} (jQuerySaveConditionGroup));