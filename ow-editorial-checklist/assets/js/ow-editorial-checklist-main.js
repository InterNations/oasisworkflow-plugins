jQuery(document).ready(function () {

   // called when "Add New Condition" is clicked
   jQuery(document).on('click', '.add-new-condition', function (event) {
      event.preventDefault();

      // step 1. get the parent of button ie <td>
      var parent = jQuery(this).parent();
      
      // <td>'s first child always be hidden so clone it and remove style ie display property and set background color light yellow and also set fadeIn effect
      var copy_wrapper = parent.children(':first')
              .clone()
              .removeClass('owcc-wrapper-hidden')
              .addClass('owcc-wrapper')
              .removeAttr('style')
              .css('background-color', '#feff99')
              .fadeIn('slow', function () {
                 jQuery(this).delay(800).css('background-color', '');
              });

      // now append the data before the add-new-condition button
      jQuery(copy_wrapper).insertBefore(parent.children(':last'));

   });

   // validate condition on publish/update
   // if any input fields is blank, display the error.
   jQuery(document).on('click', '#publish', function (event) {
      return validateConditions();
   });

   jQuery(document).on('click', '#save-post', function (event) {
      return validateConditions();
   });

   /**
    * Remove condition when trash icon is clicked
    * @since 1.0
    */
   jQuery(document).on('click', '.remove-condition', function (event) {
      event.preventDefault();

      // get parent of clicked icon ie div of the condition
      var parent = jQuery(this).parent();

      parent.addClass('remove-condition').fadeOut(1000, function () {
         jQuery(this).remove();
      });
   });   
   
   /**
    * validate and than trash the condition group 
    * @since 1.5
    */
   jQuery(document).on("click", ".submitdelete ", function (e) {
      e.preventDefault();
      // lets get the post_id of the post being trashed
      var trashed_condition_id = parseInt(get_given_query_string_value_from_url('post', jQuery(this).attr('href')));

      // now lets check if the condition is used in workflow
      var data = {
         action: 'validate_condition_group_delete',
         trashed_condition_id: trashed_condition_id,
         security: ow_editorial_checklist_js_vars.owf_editorial_checklist_nonce
      };
      jQuery.post(ajaxurl, data, function (response) {

         if ( ! response == -1 ) {
            return false; // Invalid nonce
         }
         
         if ( ! response.success ) {
            var content = html_decode(response.data);
            jQuery(content).owfmodal();
            jQuery("#simplemodal-container").css({"width": "652px",
               "left": "335px",
               "top": "255px"
            });

            jQuery(".condition-trash").click(function () {
               jQuery(this).hide();
               trash_condition_group(trashed_condition_id);
            });

            // keep condition group and don't trash
            jQuery(".condition-trash-cancel").click(function () {
               location.reload();
            });
         }

         if ( response.success ) {
            trash_condition_group(trashed_condition_id);
         }
      });
   });

});

function validateConditions() {
   var sel_value = '';
   var is_valid = true;
   jQuery('.owcc-wrapper').children('.attribute-name, .ow-word-count, .ow-containing-taxonomy-count, .ow-taxonomy, .checklist_condition, .attribute').each(function (index, element) {
      sel_value = jQuery(element).val();
      if (sel_value === '') {
         jQuery(element).css('border', '1px solid red');
         jQuery('html, body').animate({ 
            scrollTop: jQuery(element).offset().top - 120 }, 
         500);
         is_valid = false;

         // remove inline style attribute if exist
      } else {
         jQuery(element).removeAttr('style');
      }
   });

   if (is_valid === false) {
      return is_valid;
   } else {
      // this is the only way, we can send all the values to the backend. the user checked conditions with value "yes"
      // and the user un-checked values as "no".
      jQuery('.owcc-wrapper').children('.required-condition').each(function (index, element) {
         var is_checked = jQuery(element).is(":checked");
         // if the publish or save button is clicked twice, we do not want to set all the values to "yes",
         // since we checked in programmatically.
         if (is_checked && jQuery(element).val() == "yes") {
            jQuery(element).val("yes");
         } else {
            jQuery(element).val("no");
            jQuery(element).prop( "checked", true );
         }
      });

      return is_valid;
   }
}

function addConditionGroupToStep( step_id ) {

   var data = {
      action: 'add_condition_group_to_step',
      step_id: step_id,
      condition_group: jQuery('#condition_group').val()
   };
   jQuery.post(ajaxurl, data, function (response) {
      response = response.trim();
   });
}

function get_given_query_string_value_from_url(name, url) {
   url = url.toLowerCase(); // This is just to avoid case sensitiveness
   name = name.replace(/[\[\]]/g, "\\$&").toLowerCase();// This is just to avoid case sensitiveness for query parameter name
   var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
           results = regex.exec(url);
   if (!results)
      return null;
   if (!results[2])
      return '';
   return decodeURIComponent(results[2].replace(/\+/g, " "));
}

function  trash_condition_group(trashed_condition_id) {
   jQuery(".changed-data-set span").addClass("loading");

   var trash_condition_group = {
      action: 'trash_condition_group',
      trashed_condition_id: trashed_condition_id,
      security: ow_editorial_checklist_js_vars.owf_editorial_checklist_nonce
   };
   jQuery.post(ajaxurl, trash_condition_group, function (response) {
      var admin_url = response.data;
      jQuery(".changed-data-set span").removeClass("loading");
      if (response.success) {
         window.location.href = admin_url + 'edit.php?post_type=ow-condition-group';
      }
   });
}
