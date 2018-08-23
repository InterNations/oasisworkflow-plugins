jQuery(document).ready(function () {
   jQuery(document).on('click', '.workflow-delete', function (e) {
      e.preventDefault();
      var workflow_id = parseInt( get_given_query_string_value_from_url( 'wf_id', jQuery(this).attr('href') ) );
      var data = {
         action: 'delete_workflow_confirmation',
         security: owf_workflow_delete_vars.workflow_delete_nonce
      };
      jQuery.post(ajaxurl, data, function (response) {
         if (response == -1) {
            return false; // Invalid nonce
         }
         if( response.success ) {
            var content = html_decode(response.data);
            jQuery( content ).owfmodal();
            jQuery("#simplemodal-container").css({ "width": "625px",
                                                   "left": "335px", 
                                                   "top":"255px"
            });
            
            jQuery(".delete-workflow").click(function () {
               jQuery(this).hide();
               jQuery(".changed-data-set span").addClass("loading");  
               var delete_workflow = {
                  action: 'delete_workflow',
                  workflow_id: workflow_id,
                  security: owf_workflow_delete_vars.workflow_delete_nonce
               };
               jQuery.post(ajaxurl, delete_workflow, function (response) {
                  if ( response.success ) {
                     location.reload();
                  }
               });
            });

            jQuery('.delete-workflow-cancel').click(function () {
               location.reload();
            });
         } 
      });
   });
});