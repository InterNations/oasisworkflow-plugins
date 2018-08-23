 /**
    * Call to pre-publish checklist popup page when click on checklist count blurb
    * @since 1.4
    */
   jQuery(document).ready(function () {
      jQuery(".post-condition-count").click(function (e) {
        e.preventDefault();
        var inbox_obj = this;
        if (jQuery(this).children("span").html() == 0)
           return;
        data = {
           action: 'get_step_checklist_page',
           post_id: jQuery(this).attr("post_id"),
           action_id: jQuery(this).attr("action_id"),
           history_type: jQuery(this).attr("history_type"),
           security: jQuery('#owf_inbox_ajax_nonce').val()
        };

        jQuery(this).parent().children(".loading").show();
        jQuery(this).hide();
        jQuery.post(ajaxurl, data, function (response) {
           if (response == -1) {
              jQuery(inbox_obj).parent().children(".loading").hide(); // Remove Loader
              jQuery(document).find(".post-condition-count").show(); // Show Post Count
              return false;
           }
           jQuery(response.data).appendTo('body');
           jQuery('.ow-overlay').show(); // Enable background overlay
           jQuery('#ow-checklist-popup').show();
        });   
     }); 
   });   