<?php
/*
 * Settings class for License settings
*
* @copyright   Copyright (c) 2015, Nugget Solutions, Inc
* @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
* @since       2.0
*
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/*
 * OW_License_Settings Class
 *
 * @since 2.0
 */

class OW_License_Settings {

	// oasis workflow license key option name
	protected $ow_license_key_option_name = "oasiswf_license_key";

	// oasis workflow license status option name
	protected $ow_license_status_option_name = "oasiswf_license_status";

	/*
	 * Set things up.
	 *
	 * @since 2.0
	*/
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_settings' ) );
	}

	// White list our options using the Settings API
	public function init_settings() {
		register_setting( 'ow-settings-license', $this->ow_license_key_option_name, array( $this, 'validate_ow_license_key') );
	}

	/*
	 * sanitize and validate the input (if required)
	 *
	 * @since 2.0
	 */
	public function validate_ow_license_key( $license_key ) {
		$license_key = sanitize_text_field( $license_key );
		$ow_license_service = new OW_License_Service();

		if ( isset( $_POST['oasiswf_license_deactivate'] ) ) { // user is trying to deactivate the license
			$status = $ow_license_service->deactivate_license( $license_key,
					$this->ow_license_status_option_name, OASISWF_PRODUCT_NAME);

			if ( $status == OW_License_Service::FAILED ) {
				add_settings_error(
						'ow-settings-license',
						'ow-settings-license-ow-license-key',
						"Oasis Workflow Pro " . __( "License cannot be deactivated. Either the license key is invalid or the licensing server cannot be reached." , "oasisworkflow" ),
						'error'
				);
				// since there was an error, revert to the license key to the value from the DB
				$license_key = trim( get_option( $this->ow_license_key_option_name ) );

			} else { // looks like we have a successful de-activation, so let's clear the license key
				$license_key = "";
			}
		} elseif ( ! empty( $license_key ) ) { // user is trying to activate the license
			$status = $ow_license_service->activate_license( $license_key,
								$this->ow_license_status_option_name, OASISWF_PRODUCT_NAME );

			if ( $status == OW_License_Service::INVALID ) {
				add_settings_error(
						'ow-settings-license',
						'ow-settings-license-ow-license-key',
						"Oasis Workflow Pro " . __( "License cannot be activated. Either the license key is invalid or your activation limit is reached." , "oasisworkflow" ),
						'error'
				);
			}
		}

		return $license_key;
	}

	/*
	 * generate the page
	 *
	 * @since 2.0
	 */
	public function add_settings_page() {
		$ow_license_key = get_option( $this->ow_license_key_option_name );
		$ow_license_status = get_option( $this->ow_license_status_option_name );
		?>
		<form id="wf_settings_form" method="post" action="options.php">
    	<?php
    	// adds nonce and option_page fields for the settings page
    	settings_fields('ow-settings-license');
    	?>
			<div id="workflow-general-setting">
				<div id="license-setting">
					<div class="select-info full-width">
						<div class="left quarter-width">
							<label class="settings-title" for="oasis_workflow_license_key"><?php echo("Oasis Workflow Pro ");?><?php _e( 'license key', "oasisworkflow" ); ?>:</label>
						</div>
						<div class="left three-fourth-width">
							<input type="text" class="regular-text" name="<?php echo $this->ow_license_key_option_name; ?>" value="<?php echo $ow_license_key; ?>" />
				         <?php if ( $ow_license_status !== false && $ow_license_status == 'valid' ) { ?>
								<input type="submit" class="button-secondary" name="oasiswf_license_deactivate" value="<?php _e( 'Deactivate License', "oasisworkflow" ); ?>"/>
							<?php }	?>
						</div>
						<br class="clear">
					</div>
					<?php
					// action to add license settings for add-ons
					do_action( 'owf_add_license_settings' );
					?>
					<div class="select-info full-width">
						<input type="submit" class="button button-primary button-large" name="oasiswf_license_activate" value="<?php echo __( "Save", "oasisworkflow" ); ?>"/>
					</div>
					<br class="clear">
				</div>
			</div>
			<?php wp_nonce_field( 'owf_license_nonce', 'owf_license_nonce' ); ?>
		</form>
	<?php
	}
}

$ow_license_settings = new OW_License_Settings();
?>