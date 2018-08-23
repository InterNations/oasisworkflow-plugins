<?php
/*
 * Settings class for Teams Add-on settings
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

/*
 * OW_Team_Settings Class
 *
 * @since 2.0
 */

class OW_Team_Settings {

   /**
    * @var string group name
    */
   protected $ow_teams_group_name = 'ow-settings-teams';

   /**
    * @var string activate workflow option name
    */
   protected $ow_teams_enable = 'oasiswf_team_enable';

   /**
    * Set things up.
    *
    * @since 2.0
    */
   public function __construct() {
      add_action( 'admin_init', array( $this, 'init_settings' ) );

      // add the teams tab to settings page
      add_action( 'owf_add_settings_tab', array( $this, 'add_teams_settings_tab' ) );

      // add the teams settings page to the tab content
      add_action( 'owf_display_settings_tab', array( $this, 'display_teams_settings_tab' ), 10, 1 );
   }

   // White list our options using the Settings API
   public function init_settings() {
      register_setting( $this->ow_teams_group_name, $this->ow_teams_enable, array( $this, 'validate_teams_enable' ) );
   }

   public function add_teams_settings_tab( &$tabs ) {
      // add the teams tab
      $tabs['teams'] = __( 'Teams', 'owfteams' );
   }

   /**
    * sanitize user data
    * @param string $is_enabled
    * @return string
    */
   public function validate_teams_enable( $is_enabled ) {
      return sanitize_text_field( $is_enabled );
   }

   /**
    * generate the page
    *
    * @since 2.0
    */
   public function display_teams_settings_tab( $active_tab ) {
   	if ( $active_tab != 'teams' ) {
   		return;
   	}
      $enable_teams = get_option( 'oasiswf_team_enable' ) == 'yes' ? 'checked="checked"' : '';
      $oasiswf_teams_license_status = get_option( 'oasiswf_teams_license_status' );
      $is_disabled = "disabled";

      if ( $oasiswf_teams_license_status == 'valid' ) {
         $is_disabled = "";
      }
      ?>
      <form id="teams_settings_form" method="post" action="options.php">
          <?php
          settings_fields( $this->ow_teams_group_name ); // adds nonce for current settings page
          ?>
          <div class="container">
              <div class="select-info">
                  <label class="settings-title owt-padding-bottom"><input type="checkbox" <?php echo $is_disabled; ?> name="<?php echo $this->ow_teams_enable; ?>" id="<?php echo $this->ow_teams_enable; ?>" value="yes" <?php echo $enable_teams; ?> /> <strong><?php _e( 'Enable Workflow Teams ?', 'owfteams' ); ?></strong></label><br/>
                  <?php if ( $oasiswf_teams_license_status != 'valid' || empty( $oasiswf_teams_license_status ) ) { ?>
                     <span class="description"><?php echo __( "(The checkbox will be available, if the Oasis Workflow Teams license is valid)", "owfteams" ); ?> </span>
                  <?php } ?>
              </div>
          </div>

          <div id="owf_settings_button_bar">
              <input type="submit" name="save_team_settings" id="save_team_settings" value="<?php _e( 'Save', 'owfteams' ); ?>" class="button button-primary button-large" />
          </div>

      </form>
      <?php
   }

}

$ow_team_settings = new OW_Team_Settings();
?>