jQuery(document).ready(function () {
   jQuery(document).on('click', '.ow-duplicate-post', function () {
      jQuery(this).parent().children(':first-child .loading').show();
      var post_id = jQuery(this).attr('postid');
      var security = jQuery(this).attr('duplicatenonce');
      
      data = {
        action: 'save_duplicate_post_as_draft',
        post: post_id,
        security: security
      };
      
      jQuery.post(ajaxurl, data, function (response) {
        if (response == -1) {
           return false; // Invalid nonce
        }

        var url = response.data;
        window.location.href = url;
      });
   
   });
});