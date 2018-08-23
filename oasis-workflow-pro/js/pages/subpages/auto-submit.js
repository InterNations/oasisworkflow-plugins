jQuery(document).ready(function () {
   /**
    * Add auto submit workflows
    * @since 4.9
    */
   jQuery(document).on('click', '.add-workflow', function (event) {
      event.preventDefault();
      jQuery('div.no-applicable-workflow').hide();

      // step 1. get the parent of button ie <div>
      var parent = jQuery(this).parent().parent();
      
      // <div>'s first child always be hidden so clone it.
      var copy_wrapper = parent.children(':first')
              .clone()
              .removeClass('owf-hidden')
              .addClass('owf-workflows')
              .fadeIn('slow', function () {
                 jQuery(this).delay(800);
              });

      // now append the data before the add button
      jQuery(copy_wrapper).insertBefore(parent.children(':last'));
   });
   
   /**
    * Remove auto submit workflows
    * @since 4.9
    */
   jQuery(document).on('click', '.remove-workflow', function (event) {
      event.preventDefault();

      // get parent of clicked icon
      var parent = jQuery(this).parent();

      parent.addClass('remove-workflow').fadeOut(1000, function () {
         jQuery(this).remove();
      });
   }); 
   
});