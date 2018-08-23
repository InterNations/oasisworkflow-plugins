jQuery( document ).ready( function () {

   // for non-assignees in a given workflow task, we should hide the "save action",
   // so that the non-assignee is not able to edit the post outside the workflow.
   jQuery('#save-action').hide();

} );

