<form method="post">
    <div id="ow-editorial-comment-popup">
        <div id="ow-comment-popup" class="ow-modal-dialog">
            <a class="ow-modal-close" onclick="ow_modal_close( event );"></a>
            <div class="ow-modal-header">
                <h3 class="ow-modal-title" id="poststuff"><?php _e( 'Editorial Comments On', 'oweditorialcomments' ); ?></h3>
            </div>
            <div class="ow-modal-body">
                <blockquote class="ow-editorial-comments-blockquotes">
                   <label class="ow-contextual-text" id="ow-contextual-label"></label>
                </blockquote>
                <p>
                    <textarea cols="20" rows="5" id="ow-contextual-comment-textarea" placeholder="<?php _e( 'Contextual Comment', 'oweditorialcomments' ); ?>"></textarea>
                </p>
            </div>
            <div class="ow-modal-footer">
                <input type="hidden" id="ow_comment_id" />
                <ul class="ow-footer-button">
                    <li>
                        <input type="button" class="button-primary" id="saveComment" name="ow_save_contextual_comment" value="Save" />
                        <span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    </li>
                    <li>  
                        <a href="#" id="ow_modal_close" onclick="ow_modal_close( event );"><?php _e( 'Cancel', 'oweditorialcomments' ); ?></a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="ow-overlay"></div>
    </div>
</form>
<script>
   function ow_modal_close( event ) {
       event.preventDefault();
       jQuery( '#ow-editorial-comment-popup' ).remove();
   }
   
   
   jQuery( document ).ready( function () {
         jQuery( '#saveComment' ).off("click").on('click', function() {
            
            jQuery(".ow-footer-button span").addClass("loading");
            jQuery(this).hide();
            
           var data = {               
               'action': 'create_contextual_comment',
               'security': jQuery('#owf_tinymce_ajax_nonce').val(),
               'contextual_text': jQuery('#ow-contextual-label').text(),
               'contextual_comment': tinyMCE.get('ow-contextual-comment-textarea').getContent(),
               'post_id': jQuery('#post_ID').val()
           };

           // Send AJAX request
               jQuery.post(ajaxurl, data, function ( response ) { 
                  if (response == -1) {
                     return false;
                  }
                  
                  if ( response.success ) {
                     jQuery( '#ow_modal_close' ).click();

                     var widget = jQuery('#inner-' + response.data.row_id);
                     var layout = response.data.layout; // This will hold the new contextual comment HTML string
                     var fade = jQuery(layout).fadeIn('slow');

                        jQuery('.loading').hide(); // Hide loader
                        if (jQuery('.ow-no-comments-box').css('display') == 'block') {
                           jQuery('.ow-no-comments-box').hide(); // Hide no comment message
                        }                             
                        // Append data after the loading span
                        var box = jQuery('.ow-editorial-comment-box:first');
                        // if first comment box is set hidden then this box is for current logged in user and user has not make any comment so unhide it and show comments
                        box.removeClass('owf-hidden');
                        // Append data after the .accordion class
                        box.children().eq(1).after(fade);
                        jQuery( '#ow-editorial-comment-popup' ).remove();
                  } 
               });
         });   
   } );
</script>