jQuery( document ).ready( function () {

    //unbind events from the core plugin, so that we can override those here.
    jQuery( ".post-com-count" ).off( "click" );

    // To delete contextual comment from widget
    jQuery( document ).on( 'click', '.delete-contextual-comment-div', function ( event ) {
        // FIXED: To prevent Uncaught SyntaxError: Unexpected end of input
        event.preventDefault();

        var $this = jQuery( this );
        var id = $this.attr( 'id' ); // get comment id
        var parent = $this.parents().eq( 4 );
        parent.addClass( 'delete-contextual-comment' ); // change background of selected comment
        var data = {
            action : 'delete_contextual_comment',
            comment_id : id,
            security : jQuery( '#delete_contextual_comment_ajax_nonce' ).val()
        };

        jQuery.post( ajaxurl, data, function ( response ) {
            // Nonce check + check response is greater than 0 then we are getting success else do nothing
            if ( response == -1 ) {
                  return false;
            }
                  
            if ( ! response.error ) {
                parent.fadeOut( 'slow', function () {
                    jQuery( this ).remove().delay;
                } );

                // TODO: find a better solution to hide comment box if there are no present
                var box = jQuery( '.ow-editorial-comment-box:first' );
                if ( box.children().length == 3 ) {
                    box.addClass( 'owf-hidden' );
                    jQuery( '.ow-no-comments-box' ).fadeIn( 'slow' );
                }
            }
        } );
    } );

    // Edit contextual comment
    jQuery( document ).on( 'click', '.edit-contextual-comment', function ( event ) {
        event.preventDefault();

        // Remove HTML if it exist
        jQuery( '#ow-editorial-comment-popup' ).remove();

        var $this = jQuery( this );
        var comment_id = $this.attr( 'id' );
        var post_title = jQuery('#title').val();
        var parent = $this.parents().eq(4);
        parent.addClass('bg-edit-contextual-comment'); // change background of selected comment
        var contextual_text = $this.parents().eq( 4 ).children().first().children().first().text();
        var contextual_comment = $this.parents().eq( 3 ).children().first().html();

        var data = {
            action : 'edit_contextual_comment_popup',
            security : jQuery( '#edit_contextual_comment_ajax_nonce' ).val()
        }

        jQuery.post( ajaxurl, data, function ( response ) {
            if (response == -1) {
                     return false;
                  }
            
            if ( ! response.error ) {
               jQuery( response.data ).appendTo( 'body' ); // Append response to body tag

               // Set value of label, textarea
               jQuery('.ow-modal-title').append( ' "' + post_title + '"');
               jQuery( '#ow-contextual-label' ).text( contextual_text );
               jQuery( '#ow_comment_id' ).val( comment_id );

               tinymce.init({
                  menubar: false,
                  auto_focus: "ow-contextual-comment-textarea",
                  plugins: "textcolor colorpicker",
                  toolbar: ' undo redo bold italic underline strikethrough alignleft aligncenter alignright alignjustify bullist numlist outdent indent forecolor wp_help ',
                  setup :
                     function(ed) {
                        ed.on('init', function() {
                           this.getDoc().body.style.fontSize = '14px';
                           tinyMCE.get('ow-contextual-comment-textarea').setContent( contextual_comment , { format: 'html' });

                        });
                     }
               });
               tinymce.execCommand( 'mceRemoveEditor', true, 'ow-contextual-comment-textarea' );
               tinymce.execCommand( 'mceAddEditor', true, 'ow-contextual-comment-textarea' );
               // Hide background class
               parent.removeClass('bg-edit-contextual-comment');
               show_popup();
            }
        } );
    } );

    var show_popup = function () {
        jQuery( '.ow-overlay' ).show(); // Enable background overlay
        jQuery( '#ow-comment-popup' ).show(); // Populate the modal
    }

    jQuery( document ).on( 'click', '.post-com-count', function ( event ) {
        // To prevent backscreen to go top
        event.preventDefault();

        var inbox_obj = this;

        // Do not show comment if there is 0 comments
        if ( jQuery( this ).children().eq( 0 ).text() < 1 ) {
            return false;
        }
        var page_chk = jQuery( this ).attr( "real" );

        // if page is workflow inbox then we will not get real attribute so go with data-comment attribute
        if ( typeof page_chk === 'undefined' ) {
            page_chk = jQuery( this ).attr( 'data-comment' );
        }
        var data = {
            action : 'get_step_comment_page',
            actionid : jQuery( this ).attr( "actionid" ),
            actionstatus: jQuery( this ).attr( "actionstatus" ),
            comment : jQuery( this ).attr( 'data-comment' ),
            page_action : page_chk,
            post_id : jQuery( this ).attr( 'post_id' ),
            security : jQuery( '#owf_inbox_ajax_nonce' ).val()
        };

        jQuery( this ).parent().children( ".loading" ).show();
        jQuery( this ).hide();

        jQuery.post( ajaxurl, data, function ( response ) {
            jQuery( inbox_obj ).parent().children( ".loading" ).hide(); // Remove Loader
            jQuery( document ).find( ".post-com-count" ).show(); // Show Post Count
            if ( response == - 1 ) {
                return false;
            }
            if ( response.success ) {
            jQuery( response.data ).appendTo( 'body' ); // Append response to body tag
            show_popup();
            }
        } );
    } );

    // For accordion js
    jQuery( document ).on( 'click', '.ow-expand', '.ow-collapse', function () {
        var $this = jQuery( this );
        $this.removeClass( 'ow-expand' ).addClass( 'ow-collapse' );
        $this.parent().children( '.ow-editorial-comment-box, .ow-signed-off' ).slideToggle( 'slow' );
    } );

    jQuery( document ).on( 'click', '.ow-collapse', function () {
        var $this = jQuery( this );
        $this.removeClass( 'ow-collapse' ).addClass( 'ow-expand' );
        $this.parent().children( '.ow-editorial-comment-box, .ow-signed-off' ).slideToggle( 'slow' );
    } );
} );