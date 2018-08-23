<?php
/*
 * Settings class for Groups License settings
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * OW_Groups_Plugin_License_Settings Class
 *
 * @since 1.0
 */

class OW_Groups_Plugin_License_Settings {
	
	// oasis workflow groups license key option name
	protected $ow_groups_license_key_option_name = "oasiswf_groups_license_key";
	
	// oasis workflow groups license status option name
	protected $ow_groups_license_status_option_name = "oasiswf_groups_license_status";
	
	/*
	 * Set things up.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		
		add_action( 'owf_add_license_settings', array( $this, 'add_license_settings' ) );
	
	}
	
	// White list our options using the Settings API
	public function init_settings() {
		register_setting('ow-settings-license', $this->ow_groups_license_key_option_name, array($this, 'validate_ow_groups_license_key'));
	}	
	
	/*
	 * sanitize and validate the input (if required)
	 *
	 * @since 1.0
	 */
	public function validate_ow_groups_license_key( $license_key ) {
		$license_key = sanitize_text_field( $license_key );
		$ow_license_service = new OW_License_Service();
	
		if( isset( $_POST['oasiswf_groups_license_deactivate'] ) ) { // user is trying to deactivate the license
			$status = $ow_license_service->deactivate_license( $license_key,
					$this->ow_groups_license_status_option_name, OW_GROUPS_PLUGIN_PRODUCT_NAME);
	
			if ( $status == OW_License_Service::FAILED ) {
				add_settings_error(
						'ow-settings-license',
						'ow-settings-groups-plugin-license-ow-license-key',
						"Oasis Workflow Groups Plugin " . __( "License cannot be deactivated. Either the license key is invalid or the licensing server cannot be reached." , "owgroups" ),
						'error'
				);
				// since there was an error, revert to the license key to the value from the DB
				$license_key = trim( get_option( $this->ow_groups_license_key_option_name ) );
	
			} else { // looks like we have a successful de-activation, so let's clear the license key
				$license_key = "";
			}
		} else if ( ! empty ($license_key ) ) { // user is trying to activate the license
			$status = $ow_license_service->activate_license( $license_key,
					$this->ow_groups_license_status_option_name, OW_GROUPS_PLUGIN_PRODUCT_NAME);
	
			if ( $status == OW_License_Service::INVALID ) {
				add_settings_error(
						'ow-settings-license',
						'ow-settings-groups-plugin-license-ow-license-key',
						"Oasis Workflow Groups Plugin " . __( "License cannot be activated. Either the license key is invalid or your activation limit is reached." , "owgroups" ),
						'error'
				);
			}
		}
	
		return $license_key;
	}
	
	/*
	 * generate the page
	 *
	 * @since 1.0
	 */
	public function add_license_settings() {
		$ow_groups_license_key = get_option( $this->ow_groups_license_key_option_name );
		$ow_groups_license_status = get_option( $this->ow_groups_license_status_option_name );
		?>
		<div class="select-info full-width">
			<div class="left quarter-width">
				<label class="settings-title" for="ow_groups_license_key"><?php echo("Oasis Workflow Groups ");?><?php _e('license key'); ?>:</label>
			</div>
			<div class="left three-fourth-width">
				<input type="text" class="regular-text" name="<?php echo $this->ow_groups_license_key_option_name; ?>" value="<?php echo $ow_groups_license_key; ?>" />
	         <?php if( $ow_groups_license_status !== false && $ow_groups_license_status == 'valid' ) { ?>
					<input type="submit" class="button-secondary" name="oasiswf_groups_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
				<?php }	?>
			</div>
			<br class="clear">
		</div>
	<?php 		
	}	
}

$ow_groups_license_settings = new OW_Groups_Plugin_License_Settings();
?>