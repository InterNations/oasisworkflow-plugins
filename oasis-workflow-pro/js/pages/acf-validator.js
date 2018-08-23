/*
 * function to invoke ACF validation, if ACF plugin is installed and activated.
 */

function workflowSubmitWithACF(fnParam)
{
	// If disabled, bail early on the validation check
	if( acf.validation.disabled )
	{
		normalWorkFlowSubmit(fnParam);
	}
	
	
	// do validation
	acf.validation.run();
	
	if( ! acf.validation.status ) {
		
		// vars
		var $form = jQuery('form#post');
		
		// show message
		$form.siblings('#message').remove();
		$form.before('<div id="message" class="error"><p>' + acf.l10n.validation.error + '</p></div>');
	} else {
		normalWorkFlowSubmit(fnParam);
	}	
}