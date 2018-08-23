jQuery(document).ready(function () {
   function load_setting() {
      var allowed_post_types = jQuery.parseJSON(owf_make_revision_vars.allowedPostTypes);
      var make_revision_enabled = owf_make_revision_vars.enableDocumentRevisionProcess;
      var workflow_process_enabled = owf_make_revision_vars.enableWorkflowProcess;
      var current_post_type = jQuery('#post_type').val();
      var post_status = jQuery('#hidden_post_status').val(); // get post status 

      var get_original = {
         action: 'is_make_revision_allowed',
         post_id: jQuery("#hi_post_id").val(),
         security: jQuery('#owf_make_revision_ajax_nonce').val()
      };
      jQuery.post(ajaxurl, get_original, function (response) {
         jQuery(".loading").hide();
         // If not allowed post/page type then do not show
         if (response.success
                 && jQuery.inArray(current_post_type, allowed_post_types) != -1
                 && make_revision_enabled == "active"
                 && workflow_process_enabled == "active")
         {
            jQuery("#publishing-action").append("<input type='button' id='oasiswf_make_revision' class='button button-primary button-large'" +
                    " value='" + owf_make_revision_vars.makeRevisionButton + "' style='float:left;' /><span class='blank-space loading owf-hidden'></span>").css({"width": "100%"});

            jQuery("#publishing-action").css({"margin-top": "10px"});

            // show make revision overlay before making any changes to publish post
            if (post_status == 'publish') {
               var alert_make_revision_on_publish_post = {
                  action: 'alert_make_revision_on_publish_post',
                  post_id: jQuery("#hi_post_id").val(),
                  security: jQuery('#owf_make_revision').val()
               };
               jQuery.post(ajaxurl, alert_make_revision_on_publish_post, function (response) {
                  if (response == -1) {
                     return false; // Invalid nonce
                  }
                  if ( ! response.success ) {
                     return false; // user can skip the workflow so do nothing
                  }
                  var content = html_decode(response.data);
                  jQuery( content ).owfmodal();
                  jQuery('.simplemodal-close').hide(); // we do not want the user to close this modal window.
               });
            }
         }
      });
   }

   load_setting();

   jQuery(document).on("click", "#oasiswf_make_revision, #make_revision_overlay", function () {
      jQuery(".changed-data-set span").addClass("loading");
      jQuery(this).parent().children(':first-child .loading').addClass('owfe-loader'); //for front end add-on
      jQuery(this).hide();

      var post_id = '';

      // lets first see if the button/link has postId attribute.
      if (typeof jQuery(this).attr('postid') !== 'undefined') {
         post_id = jQuery(this).attr('postid');
      } else { // looks like the button doesn't define the postId attribute
         post_id = jQuery("#hi_post_id").val();
      }
      security = jQuery('#owf_make_revision').val();

      make_revision_if_not_exist(post_id, security);
   });

   jQuery(document).on('click', '.compare-post-revision', function (e) {
      e.preventDefault();
      var revision_link = jQuery(this).attr('href');
      // open a popup window with 90% height and width. We will use this window to show the revision compare results.
      var h = (screen.height * 90) / 100;
      var w = (screen.width * 90) / 100;

      // link to the revision compare page
      window.open(revision_link, "Revision_Compare", "height=" + h + ",width=" + w + ",scrollbars=yes");
   });

});

jQuery(document).ready(function () {
   jQuery(document).on('click', '.ow-make-revision', function () {
      jQuery(this).parent().children(':first-child .loading').show();
      var post_id = jQuery(this).attr('postid');
      var security = jQuery('#owf_make_revision_ajax_nonce').val();
      make_revision_if_not_exist(post_id, security);
   });
});

jQuery(document).ready(function () {
   jQuery(document).on('click', '.untrash > a', function (e) {
      e.preventDefault();
      // lets get the post_id of the post being restored
      var untrash_post_id = parseInt( get_given_query_string_value_from_url( 'post', jQuery(this).attr('href') ) );
      // now lets check if current post is in revision one or original
      var data = {
         action: 'is_post_a_revision',
         untrash_post_id: untrash_post_id
      };
      jQuery.post(ajaxurl, data, function (response) {
         if (response == -1) {
            return false; // Invalid nonce
         }

         if ( ! response.data.is_restored ) { // looks like we have an existing revision, so let's ask the user which one to keep as revision
            var content = html_decode(response.data);
            jQuery( content ).owfmodal();
            jQuery("#simplemodal-container").css({ "width": "652px",
                                                   "left": "335px", 
                                                   "top":"255px"
            });
            
            // keep trashed one as revised post and delete current revision
            jQuery(".revision-untrashed-ok").click(function () {
               jQuery(this).hide();
               jQuery(".changed-data-set span").addClass("loading");
               var keep_untrashed_revision = {
                  action: 'keep_untrashed_revision',
                  untrash_post_id: untrash_post_id,
               };
               jQuery.post(ajaxurl, keep_untrashed_revision, function (response) {
                  if ( response.success ) {
                     location.reload();
                  }
               });
            });

            // keep current revision and do not untrash the post
            jQuery('.revision-untrashed-cancel').click(function () {
            	location.reload();
            });
         } else {
            location.reload();
         }
      });
   });
});

function oasis_make_revision(post_id, security) {
   data = {
      action: 'save_as_new_post_draft',
      post: post_id,
      security: security
   };

   jQuery(this).parent().children(':first-child .loading').show();
   jQuery.post(ajaxurl, data, function (response) {
      if (response == -1) {
         return false; // Invalid nonce
      }

      var url = response.data;
      window.location.href = url;
   });
}

function make_revision_if_not_exist(post_id, security, action) {

   if (typeof action === 'undefined') {
      action = '';
   }
   var get_current_revision = {
      action: 'get_current_revision',
      post_id: post_id,
      security: security
   };
   jQuery.post(ajaxurl, get_current_revision, function (response) {
      if (response == -1) {
         return false;
      }
      if (response.success) {
         jQuery.modal.close();  // close any previous modal - like the revision overlay
         if (jQuery(".loading").hasClass('owfe-loader')) {
            jQuery(".loading").removeClass('owfe-loader');
         }
         jQuery(".loading").hide(); //for front end add-on
         var objResponse = response.data;
         jQuery("#make-revision-submit-div").owfmodal();
         jQuery(".revision-ok").click(function () {
            jQuery(this).hide();
            jQuery(".changed-data-set span").addClass("loading");
            var delete_current_revision = {
               action: 'oasiswf_delete_post',
               post_id: objResponse.revision_post_id,
               security: security
            };
            jQuery.post(ajaxurl, delete_current_revision, function (del_response) {
               if (del_response.trim() == "success") {
                  oasis_make_revision(post_id, security);
               }
            });
         });

         jQuery(".revision-no").click(function () {
            if( typeof owf_redirect_after_revision === 'function' ) {
               owf_redirect_after_revision( objResponse.revision_post_id );
               return false;
            }
            window.location.href = ow_admin_url + 'post.php?action=edit&post=' + objResponse.revision_post_id;
         });

         jQuery(".revision-cancel").click(function () {
            modal_close();
            jQuery('#oasiswf_make_revision, #make_revision_overlay').show();
            return false;
         });
         modal_close = function () {
            stepProcess = "";
            jQuery.modal.close();
         }
      } else {
         if ('restore_revision' === action) {
            return;
         }
         oasis_make_revision(post_id, security);
      }
   });
}