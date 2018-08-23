<?php
/*
 * Display System Info
 *
 * @copyright   Copyright (c) 2018, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       5.3
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

$ow_tools_service = new OW_Tools_Service();

?>

<div class="wrap">
   <textarea readonly="readonly" onclick="this.focus();this.select()" id="ow-system-info" name="ow-system-info" title="<?php echo __( 'To copy the system info, click below then press Ctrl + C (PC) or Cmd + C (Mac).', 'oasisworkflow' ); ?>">
<?php echo $ow_tools_service->get_owf_system_info(); ?>
   </textarea>
</div>      

