<form method="post">
    <div id="ow-editorial-comment-popup">
        <div id="ow-comment-popup" class="ow-modal-dialog">
            <a class="ow-modal-close" onclick="ow_modal_close( event );"></a>
            <div class="ow-modal-header">
                <h3 class="ow-modal-title" id="poststuff"><?php _e( 'Editorial Comments On', 'oweditorialcomments' ); ?></h3>
            </div>
            <div class="ow-modal-body">
                <blockquote class="ow-editorial-comments-blockquotes">
                    <p class="ow-contextual-text" id="ow-contextual-label"></p>
                </blockquote>
                <p>
                    <textarea cols="20" rows="5" id="ow-contextual-comment-textarea" placeholder="<?php _e( 'Contextual Comment', 'oweditorialcomments' ); ?>"></textarea>
                </p>
            </div>
            <div class="ow-modal-footer">
                <input type="hidden" id="ow_comment_id" />
                <ul class="ow-footer-button">
                    <li>
                        <input type="button" class="button-primary" id="ow_save_contextual_comment" name="ow_save_contextual_comment" value="Save" />
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
       jQuery( '#ow_save_contextual_comment' ).off("click").on('click', function() {
          
           var comment_id = jQuery( '#ow_comment_id' ).val();
           
           jQuery(".ow-footer-button span").addClass("loading");
           jQuery(this).hide();
            
           var data = {
               'action' : 'update_contextual_comment',
               'contextual_comment' : tinyMCE.get('ow-contextual-comment-textarea').getContent(),
               'comment_id' : comment_id,
               'security' : jQuery( '#edit_contextual_comment_ajax_nonce' ).val()
           };

           jQuery.post( ajaxurl, data, function ( response ) {
               if ( response == - 1 ) {
                   return false;
               }
               // Update comment to widget section
               jQuery( '#inner-' + comment_id + ' > .comment-contents > .contextual-comment' ).html( response.data );
               jQuery( '#ow_modal_close' ).click();
           } );

       } );
   } );
</script>