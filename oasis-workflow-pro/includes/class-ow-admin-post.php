<?php
/*
 * Admin Post utility class for Oasis Workflow
 *
 * @copyright   Copyright (c) 2017, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       5.1
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit;
}

/*
 * Admin Post Utility class
 *
 * @since 5.1
 */
class OW_Admin_Post
{

   public static $post;

   public static function init()
   {
      $p_get = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT);
      $p_post = filter_input(INPUT_POST, 'post', FILTER_SANITIZE_NUMBER_INT);
      if ($p_get > 0 || $p_post > 0) {
         self::$post = $p_get > 0 ? get_post($p_get) : get_post($p_post);
      } elseif ($GLOBALS['pagenow'] === 'post-new.php') {
         add_action('new_to_auto-draft', function(\WP_Post $post) {
            if (is_null(OW_Admin_Post::$post)) {
               OW_Admin_Post::$post = $post;
            }
         }, 0);
      }
   }

   public function get()
   {
      return self::$post;
   }
}

add_action('admin_init', array('OW_Admin_Post', 'init'));
