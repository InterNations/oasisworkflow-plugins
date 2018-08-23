jQuery( document ).ready( function () {

   /**
    * Hold the current status of single post
    * @var string current_status
    */
   var current_status = jQuery( '#original_post_status' ).val() || '';
   
   /**
    * Append the custom status dropdown in status field
    * @param string selector
    * @return void
    */
   var _post_status_dropdown = function ( selector ) {

      // get all custom statuses
      var postData = {
         action: 'get_all_custom_statuses',
      };

      jQuery.post( ajaxurl, postData, function ( response ) {
         var option;
         jQuery.each( response.data, function () {
            option = jQuery( '<option></option>' ).
                    text( this.name ).
                    attr( 'value', this.slug ).
                    attr( 'title', ( this.description ) ? this.description : '' );

            if ( current_status === this.slug ) {
               option.prop( 'selected', 'selected' );
            }

            option.appendTo( jQuery( selector ) );

         } );
         
         /**
          * FIXED: when we create brand new post and save the post as draft then
          * its saving the post as published..!
          * To produce this issue, we have to enter the post title, or anything
          * and click on save button and it will save the post as published
          */
         if( 'auto-draft' === current_status ) {
            option = jQuery( '<option></option>' ).
                    text( 'Draft' ).
                    attr( 'value', 'draft' ).
                    attr( 'title', '' ).
                    prop( 'selected', 'selected' );
            option.appendTo( jQuery( selector ) );
         }
         
      } );
   };

   /**
    * Get current post status of quick edit post
    * 
    * @since 2.1
    */
   jQuery( document ).on( 'click', '.editinline', function ( ) {
      var post_id = jQuery( this ).parents().closest( 'tr' ).children( ':first' ).children( 'input' ).val();
      current_status = jQuery( '#inline_' + post_id ).children( '._status' ).text();
   } );

   /**
    * Trigger - when user is on listing page and doing quick edit
    * 
    * @since 2.1
    */
   if ( jQuery( 'select[name="_status"]' ).length > 0 ) {
      
      var selector = 'select[name="_status"]';
      _post_status_dropdown( selector );
      
      // Clean up the bulk edit selector because it's non-standard
    
      jQuery( '#bulk-edit' ).find( 'select[name="_status"] option' ).removeAttr( 'selected' );
      jQuery( '#bulk-edit' ).find( 'select[name="_status"] option[value="future"]' ).remove();

   }

   /**
    * Update button "Save Draft" value
    * @param string text
    * @return void
    */
   var _update_save_button = function ( text ) {
      if ( typeof text === 'undefined' ) {
         text = 'Save as ' + jQuery( 'select[name="post_status"] option:selected' ).text();
      }
      jQuery( ':input#save-post' ).attr( 'value', text );
   };

   /**
    * Trigger if current page is sinle post/page
    * 
    * @since 2.1
    */
   if ( jQuery( 'select[name="post_status"]' ).length > 0 ) {

      // Set the Save button to generic text by default
      _update_save_button( 'Save' );

      // Bind event when OK button is clicked
      jQuery( '.save-post-status' ).bind( 'click', function () {
         _update_save_button();
      } );

      // Add custom statuses to Status dropdown
      _post_status_dropdown( 'select[name="post_status"]' );
   }

}
);