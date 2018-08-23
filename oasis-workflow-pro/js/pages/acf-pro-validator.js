/*
 * function to invoke ACF validation, if ACF Pro plugin is installed and activated, [and validation is active].
 */
function workflowSubmitWithACF(fnParam)
{
	/*
	 * if ACf validation is not active, then by passs the ACF validation
	 */
	if( acf.validation.active == 0) {
		normalWorkFlowSubmit(fnParam);
		return;
	}

	var $form = jQuery('#post');
	acf.do_action('submit', $form);
	var data = acf.serialize_form($form);

	// append AJAX action
	data.action = 'acf/validate_save_post';

   // prepare
   data = acf.prepare_for_ajax(data);

	// set busy
	$form.busy = 1;

	jQuery.ajax({
		async: false,
		url: acf.get('ajaxurl'),
		data: data,
		type: 'post',
		dataType: 'json',
		success: function (json) {
			// bail early if not json success
			if (!acf.is_ajax_success(json)) {
				return;
			}

			var json = json.data;

			// filter for 3rd party customization
			json = acf.apply_filters('validation_complete', json, $form);


			// validate json
			if (!json || json.valid || !json.errors) {
				// set valid (allows fetch_complete to run)
				acf.validation.valid = true;

				// end function
				return;
			}

			// set valid (prevents fetch_complete from running)
			acf.validation.valid = false;

			// reset trigger
			acf.validation.$trigger = null;

			// vars
			var $scrollTo = null,
				count = 0,
				message = acf._e('validation_failed');

			// show field error messages
			if (json.errors && json.errors.length > 0) {
				for (var i in json.errors) {
					// get error
					var error = json.errors[i];

					// is error for a specific field?
					if (!error.input) {

						// update message
						message += '. ' + error.message;

						// ignore following functionality
						continue;
					}
					// get input
					var $input = $form.find('[name="' + error.input + '"]').first();

					// if $_POST value was an array, this $input may not exist
					if (!$input.exists()) {
						$input = $form.find('[name^="' + error.input + '"]').first();
					}

					// bail early if input doesn't exist
					if (!$input.exists()) {
						continue;
					}
					// increase
					count++;

					// now get field
					var $field = acf.get_field_wrap($input);

					// add error
					acf.validation.add_error($field, error.message);

					// set $scrollTo
					if ($scrollTo === null) {
						$scrollTo = $field;
					}
				}
				// message
				if (count == 1) {

					message += '. ' + acf._e('validation_failed_1');

				} else if (count > 1) {
					message += '. ' + acf._e('validation_failed_2').replace('%d', count);
				}
			}
			// get $message
			var $message = $form.children('.acf-error-message');

			if (!$message.exists()) {
				$message = jQuery('<div class="acf-error-message"><p></p><a href="#" class="acf-icon acf-icon-cancel small"></a></div>');
				$form.prepend($message);
			}

			// update message
			$message.children('p').html(message);

			// if no $scrollTo, set to message
			if ($scrollTo === null) {
				$scrollTo = $message;
			}

			// timeout avoids flicker jump
			setTimeout(function () {
				jQuery("html, body").animate({ scrollTop: $scrollTo.offset().top - (jQuery(window).height() / 2) }, 500);

			}, 1);
		},
		complete: function () {
			if (acf.validation.valid) {
				normalWorkFlowSubmit(fnParam);
			}
		}
	});
}