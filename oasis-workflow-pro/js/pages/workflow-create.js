var jQueryCgmp = jQuery.noConflict();
( function ( jQuery ) {
   jQuery( document ).ready( function () {
      var changed_step_chk = false; // set it to false as default, once the user changes any thing related to workflow, we will set the value to true
      var deleted_step = new Array(); // IDs of Deleted step

      /*
       * called when clicked "Save" on Create New Workflow" popup
       * if everything works, goes to the workflow builder canvas
       */
      jQuery( "#new-wf-save" ).click( function () {
         // validate workflow title cannot be blank
         if ( !jQuery( "#new-workflow-title" ).val() ) {
            jQuery( "#new-workflow-title" ).css( {"background-color": "#fbf3f3"} ).focus();
            return;
         }

         jQuery( ".changed-data-set span" ).addClass( "loading" );
         create_data = {
            action: 'create_new_workflow',
            name: jQuery( "#new-workflow-title" ).val(),
            description: jQuery( "#new-workflow-description" ).val(),
            security: jQuery( '#owf_workflow_create_nonce' ).val()
         };

         // Post the data to see if we can create the workflow
         jQuery.post( ajaxurl, create_data, function ( response ) {
            if ( response == -1 ) { // nonce cannot be verified
               jQuery( ".changed-data-set span" ).removeClass( "loading" );
               return false;
            }
            if ( ! response.success ) { // found existing workflow with the same name, so display error message.
               alert( owf_workflow_create_vars.alreadyExistWorkflow );
               jQuery( ".changed-data-set span" ).removeClass( "loading" );
               return false;
            }

            // everything looks good, the workflow is created. hurray!!!!
            jQuery( ".changed-data-set span" ).removeClass( "loading" );
            jQuery( "#wf_id" ).val( response.data );
            jQuery( "#define-workflow-title" ).val( jQuery( "#new-workflow-title" ).val() );
            jQuery( "#define-workflow-description" ).val( jQuery( "#new-workflow-description" ).val() );
            jQuery( "#page_top_lbl" ).html( jQuery( "#new-workflow-title" ).val() + " (1)" ); // it's version 1 of the workflow
            jQuery.modal.close();
         } );
      } );

      /*
       * setup date picker for start date
       */
      jQuery( "#start-date" ).datepicker( {
         autoSize: true,
         changeMonth: true,
         changeYear: true,
         yearRange: '1950:2050',
         dateFormat: owf_workflow_create_vars.editDateFormat,
         onSelect: function ( dateText, inst ) {
            jQuery( this ).addClass( "ow-date-picker" );
         }
      } );

      /*
       * setup date picker for end date
       */
      jQuery( "#end-date" ).datepicker( {
         autoSize: true,
         changeMonth: true,
         changeYear: true,
         yearRange: '1950:2050',
         dateFormat: owf_workflow_create_vars.editDateFormat,
         onSelect: function ( dateText, inst ) {
            jQuery( this ).addClass( "ow-date-picker" );
         }
      } );
      
      if (jQuery('body > #ui-datepicker-div').length > 0)
      {
         jQuery('#ui-datepicker-div').wrap('<div class="ui-oasis" />');
      }

      /*
       * since the workflow has been changed, set the changed_step_chk to true, so that we can act accordingly
       */
      set_step_changed_status = function () {
         changed_step_chk = true;
      }

      //window closed
      window.onbeforeunload = function () {
         if ( changed_step_chk ) { // if true, we have some unsaved changes, so display the error message.
            return owf_workflow_create_vars.unsavedChanges;
         }
      }

      jQuery( ".workflow-save-button" ).click( function () {
         jQuery( "#wf_graphic_data_hi" ).val( "" );
         // validate title field is not empty
         if ( !jQuery( "#define-workflow-title" ).val() ) { // title is blank, so focus on the title field.
            jQuery( ".workflow-define-div" ).show();
            jQuery( "#define-workflow-title" ).css( {"background-color": "#fbf3f3"} ).focus();
            return;
         }

         var wf_info = _get_workflow_info();
         if ( !wf_info ) { // no workflow info found, something is wrong
            return false;
         }

         jQuery( "#wf_graphic_data_hi" ).val( wf_info ); // set the graphical data in the hidden field

         // if there are any deleted steps, now is the time to add those steps to the hidden field, so that we can delete it
         if ( deleted_step.length > 0 ) {
            var del_id_str = "";
            for ( var i = 0; i < deleted_step.length; i++ ) {
               del_id_str += deleted_step[i] + "@";
            }
            jQuery( "#deleted_step_ids" ).val( del_id_str );
         }

         // set it back to false, since we have captured all the changes
         changed_step_chk = false;

         // lets validate the workflow before saving it
         validate_workflow = {
            action: 'validate_workflow',
            wf_id: jQuery( "#wf_id" ).val(),
            start_date: jQuery( "#start-date" ).val(),
            end_date: jQuery( "#end-date" ).val(),
            wf_info: jQuery( "#wf_graphic_data_hi" ).val(),
            auto_submit_team: jQuery( "#auto-submit-team" ).val(),
            security: jQuery( '#owf_workflow_create_nonce' ).val()
         };

         jQuery.post( ajaxurl, validate_workflow, function ( response ) {
            if ( response.trim() == -1 ) { // nonce cannot be verified
               jQuery( ".changed-data-set span" ).removeClass( "loading" );
               return false;
            }
            if ( response.trim() != "" ) { // looks like there are validation errors
               jQuery( "#validation_error_message" ).removeClass( "owf-hidden" );
               jQuery( "#validation_error_message" ).html( response.trim() );
               jQuery( ".changed-data-set span" ).removeClass( "loading" );
               // scroll to the top
               jQuery( "html, body" ).animate( {
                  scrollTop: 0
               }, "slow" );
               return false;
            }
            // everything looks good, lets submit the form
            // set the action url and submit the form
            jQuery( "#validation_error_message" ).addClass( "owf-hidden" );
            var action_url = jQuery( "#wf-form" ).attr( "action" );
            jQuery( "#wf-form" ).attr( "action", action_url + "&wf_id=" + jQuery( "#wf_id" ).val() );
            jQuery( "#wf-form" ).submit();
         } );
      } );

      //------------copy workflow------------
      jQuery( ".workflow-copy-button" ).click( function () {
         var wf_id = jQuery( "#wf_id" ).val();
         var wf_name = jQuery( '#define-workflow-title' ).val();
         jQuery( '#copy-workflow-title' ).val( 'Copy-' + wf_name );
         jQuery( '#hi_wf_id' ).val( wf_id );
         jQuery( '#copy-workflow-popup' ).owfmodal();
         jQuery( ".modalCloseImg" ).hide();
      } );

      jQuery( ".duplicate_workflow" ).click( function () {
         var wf_id = jQuery( this ).attr( 'wf_id' );
         // get the name of the workflow
         var wf_name = jQuery( '#workflow-name-' + wf_id ).html();
         // append Copy- to the workflow name
         jQuery( '#copy-workflow-title' ).val( 'Copy-' + wf_name );
         jQuery( '#hi_wf_id' ).val( wf_id );
         jQuery( '#copy-workflow-popup' ).owfmodal();
         jQuery( ".modalCloseImg" ).hide();
      } );

      var chk_date_input = function () {
         if ( !jQuery( "#start-date" ).val() ) {
            jQuery( "#start-date" ).css( {"background-color": "#fbf3f3"} ).focus();
            return false;
         }

         if ( !jQuery( "#end-date" ).val() ) {
            jQuery( "#end-date" ).css( {"background-color": "#fbf3f3"} ).focus();
            return false;
         }

         return true;
      }
      //------------modal-------------------------
      showConnectionDialog = function ( linkObj, connset ) {
         call_modal( "connection-setting" );
         jQuery( "#source_name_lbl" ).html( jQuery( "#" + linkObj.sourceId + " label" ).html() );
         jQuery( "#target_name_lbl" ).html( jQuery( "#" + linkObj.targetId + " label" ).html() );
         jQuery( "#path-opt-" + connset["path"] ).attr( 'checked', true );
         jQuery( "#link-rdo-" + connset["connector"] ).attr( 'checked', true );

         // FIXED: jsPlumb: fire failed for event jsPlumbConnection
         // show selected post status on connection settings modal
         if ( typeof linkObj.getParameter === 'function' ) {
            jQuery( '#step-status-select option[value="' + linkObj.getParameter( 'post_status' ) + '"]' ).
                    prop( 'selected', 'selected' );

         }
      }

      //----------calculator-----------
      jQuery( ".date-clear" ).click( function () {
         jQuery( this ).parent().children( ".date_input" ).val( "" );
         return false;
      } );

      //----------------Menu------------------
      jQuery( ".wrap" ).click( function () {
         jQuery( ".contextMenu" ).hide();
      } );
      jQuery( ".contextMenu li a" ).mouseover( function () {
         var obj = this;
         jQuery( ".contextMenu li a" ).each( function () {
            jQuery( this ).removeClass( 'menu_hover' ).addClass( 'menu_out' );
         } );
         jQuery( obj ).addClass( 'menu_hover' );
      } );

      jQuery( "#connQuit, #stepQuit" ).click( function () {
         jQuery( ".contextMenu" ).hide();
      } )

      //------------Saving stepid after deleting step ----------------

      set_deleted_step = function ( stepdbid ) {
         deleted_step[deleted_step.length] = stepdbid;
      }
      //-------------as save----------------
      jQuery( "#save_as_link, .workflow-save-new-version-button" ).click( function () {
         jQuery( "#save_action" ).val( "workflow_save_as_new_version" );
         jQuery( "#wf-form" ).submit();
      } );


      jQuery( "#copy-wf-submit" ).click( function () {
         if ( !jQuery( "#copy-workflow-title" ).val() ) {
            jQuery( "#copy-workflow-title" ).css( {"background-color": "#fbf3f3"} ).focus();
            return;
         }
         jQuery( ".changed-data-set span" ).addClass( "loading" );
         check_for_duplicate_workflow_name = {
            action: 'validate_workflow_name',
            name: jQuery( "#copy-workflow-title" ).val(),
            security: jQuery( '#owf_workflow_create_nonce' ).val()
         };

         jQuery.post( ajaxurl, check_for_duplicate_workflow_name, function ( response ) {
            jQuery( ".changed-data-set span" ).removeClass( "loading" );
            if ( response == -1 ) { // nonce failed
               jQuery( ".changed-data-set span" ).removeClass( "loading" );
               return false;
            }
            if ( ! response.success ) { // found existing workflow with the same name, so display error message.
               alert( owf_workflow_create_vars.alreadyExistWorkflow );
               jQuery( ".changed-data-set span" ).removeClass( "loading" );
               return false;
            }

            jQuery( "#save_action" ).val( "workflow_copy" );
            jQuery( "#define-workflow-title" ).val( jQuery( "#copy-workflow-title" ).val() );
            jQuery( "#define-workflow-description" ).val( jQuery( "#copy-workflow-description" ).val() );
            jQuery( "#wf-form" ).submit();
         } );
      } );

   } );
}( jQueryCgmp ) );