<?php
/*
 * Settings class for Editorial Checklist Add-on settings
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.1
 *
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) {
   exit();
}

/*
 * OW_Editorial_Checklist_Settings Class
 *
 * @since 1.1
 */

class OW_Editorial_Checklist_Settings {

   /**
    * @var string group name
    */
   protected $ow_editorial_checklist_group_name = 'ow-editorial-checklist-settings';

   /**
    * @var string checklist action option name, what should happen after checklist is run
    */
   protected $ow_checklist_action_option_name = 'oasiswf_checklist_action';

   /**
    * Set things up.
    *
    * @since 1.1
    */
   public function __construct() {
      add_action( 'admin_init', array( $this, 'init_settings' ) );

      // add the editorial checklist tab to settings page
      add_action( 'owf_add_settings_tab', array( $this, 'add_editorial_checklist_settings_tab' ) );

      // add the editorial checklist settings page to the tab content
      add_action( 'owf_display_settings_tab', array( $this, 'display_editorial_checklist_settings_tab' ), 10, 1 );
   }

   // White list our options using the Settings API
   public function init_settings() {
      register_setting( $this->ow_editorial_checklist_group_name, $this->ow_checklist_action_option_name, array( $this, 'validate_checklist_action_option' ) );
   }

   /**
    * Add our setting page to settting menu
    * @param array $tabs
    * @since 1.1
    */
   public function add_editorial_checklist_settings_tab( &$tabs ) {
      // add the checklist editorial tab
      $tabs['editorial_checklist'] = __( 'Checklist', 'oweditorialchecklist' );
   }

   /**
    * sanitize user data
    * @param array $checklist_action
    * @return string
    * @since 1.1
    */
   public function validate_checklist_action_option( $checklist_action ) {
      return sanitize_text_field( $checklist_action );
   }

   /**
    * Print the setting page
    * @since 1.1
    */
   public function display_editorial_checklist_settings_tab( $active_tab ) {
   	if ( $active_tab != 'editorial_checklist' ) {
   		return;
   	}
      $editorial_checklist_action = get_option( $this->ow_checklist_action_option_name );
      ?>
      <form id="editorial_checklist_form" method="post" action="options.php">
         <?php
         settings_fields( $this->ow_editorial_checklist_group_name ); // adds nonce for current settings page
         ?>
         <div class="select-info">
            <label for="prevent_signing_off" class="settings-title">
               <input type="radio" id="prevent_signing_off"
                      name="<?php esc_attr_e( $this->ow_checklist_action_option_name ); ?>"
                      value="prevent_signing_off" <?php checked( $editorial_checklist_action, 'prevent_signing_off' ); ?>/>
                      <?php _e( 'Prevent Signing off', 'oweditorialchecklist' ); ?>
            </label>
            <br />
            <span class="description-checklist">
               <?php _e( "(Prevent the user from signing off the task.)", "oweditorialchecklist" ); ?>
            </span>
            <br>
            <br>
            <label for="by_pass_the_checklist" class="settings-title">
               <input type="radio"
                      id="by_pass_the_checklist"
                      name="<?php esc_attr_e( $this->ow_checklist_action_option_name ); ?>"
                      value="by_pass_the_checklist" <?php checked( $editorial_checklist_action, 'by_pass_the_checklist' ); ?> />
                      <?php _e( 'By pass the checklist', 'oweditorialchecklist' ); ?>
            </label>
            <br />
            <span class="description-checklist">
               <?php _e( "(Let the user sign off without any warnings.)", "oweditorialchecklist" ); ?>
            </span>
         </div>

         <div id="owf_settings_button_bar">
            <input type="submit" name="save_editorial_checklist_settings" id="save_editorial_checklist_settings" value="<?php _e( 'Save', 'oweditorialchecklist' ); ?>" class="button button-primary button-large" />
         </div>

      </form>
      <?php
   }

}

$ow_editorial_checklist_settings = new OW_Editorial_Checklist_Settings();
?>