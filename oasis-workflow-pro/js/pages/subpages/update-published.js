jQuery(document).ready(function() {
    if( revision_post_id_for_update ){
        jQuery('#publishing-action>#publish').hide();
        jQuery("#publishing-action").append("<div class='update-published-article right'><a href='#' id='update-published-article'>" + owf_update_published_vars.updatePublishLinkText + "</a><span class='blank-space owf-hidden'></span></div>");
    }
    
    jQuery( document ).on("click", "#update-published-article", function(){
        data = {
            action: 'update_published_post' ,
            revision_post_id: revision_post_id_for_update,
            security: jQuery('#owf_update_published_nonce').val()
        };
        
        jQuery(this).hide();
        jQuery(".loading").show();
        
        jQuery.post(ajaxurl, data, function( response ) {
            if ( response == -1 ) { // incorrect nonce
                return false;
            }

            if ( response.success ) {
                window.location = response.data.redirect;
            }
        });
    });
});