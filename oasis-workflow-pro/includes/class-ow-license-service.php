<?php
/*
 * Service class for Oasis Workflow License operations
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
 * OW_License_Service Class
 *
 * @since 2.0
 */
 
class OW_License_Service {
	
	/*
	 * Valid license status, response when calling the EDD license API
	 */
	const VALID = "valid";
	
	/*
	 * Invalid license status, response when calling the EDD license API
	 */
	const INVALID = "invalid";
	
	/*
	 * Deactivated license status, response when calling the EDD license API
	 */
	const DEACTIVATED = "deactivated";
	
	/*
	 * Failed license status, response when calling the EDD license API
	 */
	const FAILED = "failed";	

	/*
	 * activate the license using EDD license API
	 * 
	 * @param string $license_key license key value
	 * @param string $license_status_option_name name of the wp_option to store the license status
	 * @param string $product_name product for which this license is applicable
	 * 
	 * @return string status of the license after activation
	 * 
	 * @since 2.0
	 */
	public function activate_license( $license_key, $license_status_option_name, $product_name ) {
      
		// run a quick security check
		if( ! check_admin_referer( 'owf_license_nonce', 'owf_license_nonce' ) )
			return; // get out if we didn't click the Save button		
		
		// sanitize the input
		$license_key = sanitize_text_field( $license_key );
		$license_status_option_name = sanitize_text_field( $license_status_option_name );
		$product_name = sanitize_text_field( $product_name );
		
		// data to send in our API request
		$api_params = array(
				'edd_action'=> 'activate_license',
				'license' 	=> $license_key,
				'item_name' => urlencode( $product_name ), // the name of our product in EDD
				'url'       => home_url()
		);
	
		// Call the custom API.
		$response = wp_remote_post( OASISWF_STORE_URL, array( 'timeout' => 15, 'body' => $api_params ) );
      
		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			OW_Utility::instance()->logger( $response );
			return false;
		}
	
		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

      // $license_data->license will be either "valid" or "invalid"
		update_option( $license_status_option_name, $license_data->license );
		
		return $license_data->license;
	}
	
	/*
	 * deactivate the license using EDD License API
	 * 
	 * @param string $license_key license key value
	 * @param string $license_status_option_name name of the wp_option to store the license status
	 * @param string $product_name product for which this license is applicable
	 * 
	 * @return string status of the license after de-activation
	 * 
	 * @since 2.0 
	 */
	public function deactivate_license( $license_key, $license_status_option_name, $product_name) {
	
		// run a quick security check
		
		if ( ! check_admin_referer( 'owf_license_nonce', 'owf_license_nonce' ) ) {
			return; // get out if we didn't click the Deactivate button
      }
	
		// data to send in our API request
		$api_params = array(
				'edd_action'=> 'deactivate_license',
				'license' 	=> $license_key,
				'item_name' => urlencode( $product_name ), // the name of our product in EDD
				'url'       => home_url()
		);
	
		// Call the custom API.
		$response = wp_remote_post( OASISWF_STORE_URL, array( 'timeout' => 15, 'body' => $api_params ) );
	
		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			OW_Utility::instance()->logger( $response );
			return false;
		}
	
		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
	
		// $license_data->license will be either "deactivated" or "failed"
		if ( $license_data->license == OW_License_Service::DEACTIVATED ) {
			update_option( $license_status_option_name, $license_data->license );
		}
		
		return $license_data->license;
	}	
	
}

?>