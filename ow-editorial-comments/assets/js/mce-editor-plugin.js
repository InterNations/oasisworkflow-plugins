(function () {
   tinymce.create('tinymce.plugins.owEditorialCommentPlugin', {
      // Initialize the plugin
      init: function (editor) {
         // Register button to toolbar
         editor.addButton('owEditorialCommentPlugin', {
            'title': 'Contextual Comment',
            'icon': 'custom-mce-icon', // Do not prepand mce-i prefix to icon
             onclick: function (e) {
               e.preventDefault();

               // get selected text
               var contextual_text = editor.selection.getContent({'format': 'html'});
               var post_title = jQuery('#title').val();
               
               if ( contextual_text == "" ) {
                  return;
               }

               var data = {
                  action: 'show_contextual_comment_popup',
                  security: jQuery('#owf_tinymce_ajax_nonce').val()
               }

               jQuery.post(ajaxurl, data, function (response) {
                  if (response === -1) {
                     return false;
                  }
                                  
                  if ( ! response.error ) {
                     jQuery( response.data ).appendTo('body'); // Append response to body tag

                     // Set popup title, label, textarea
                     jQuery('.ow-modal-title').append(' "' + post_title + '"');
                     jQuery('#ow-contextual-label').html(contextual_text);
                     tinymce.init({
                        menubar: false,
                        auto_focus: "ow-contextual-comment-textarea",
                        plugins: "textcolor colorpicker",
                        toolbar: ' undo redo bold italic underline strikethrough alignleft aligncenter alignright alignjustify bullist numlist outdent indent forecolor wp_help ',
                        setup :
                           function(ed) {
                              ed.on('init', function() 
                              {
                                  this.getDoc().body.style.fontSize = '14px';
                              });
                        }
                     });
                     tinymce.execCommand('mceRemoveEditor', true, 'ow-contextual-comment-textarea');
                     tinymce.execCommand('mceAddEditor', true, 'ow-contextual-comment-textarea'); 
                     jQuery('.ow-overlay').show(); // Enable background overlay
                     jQuery('#ow-comment-popup').show(); // Populate the modal
                  }
               });
            }
         });
      },
      getInfo: function () {
         return {
            longname: 'Contextual Text Comment Plugin',
            author: 'Nugget Solutions Inc.',
            authorurl: 'http://www.nuggetsolutions.com',
            infourl: 'http://www.nuggetsolutions.com',
            version: '1.0'
         };
      }
   });
   tinymce.PluginManager.add('owEditorialCommentPlugin', tinymce.plugins.owEditorialCommentPlugin);
})();