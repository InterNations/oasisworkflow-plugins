<?php
/*
 * Register Editorial Comments Widget for front end actions
 *
 * @copyright   Copyright (c) 2015, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

/**
 * OW_Front_End_Actions_Widget
 *
 * A widget to display editorial comments on the front end.
 *
 * @since 1.0
 */
class OW_Front_End_Actions_Widget extends WP_Widget {

   public function __construct() {
      parent::__construct(
              // Base ID of widget
              'ow_front_end_actions_widget',
              // Widget name
              __( 'Editorial Comments', 'owfrontendactions' ),
              // Widget description
              array( 'description' => __( 'To view editorial comments from the front end', 'owfrontendactions' ), )
      );
   }

   /**
    * Creating widget front-end
    * Apply for loop here
    * @since 1.0
    */
   public function widget( $args, $instance ) {
      echo $args['before_widget'];

      $title = apply_filters( 'widget_title', $instance['title'] );
      if ( ! empty( $title ) ) {
         echo $args['before_title'] . $title . $args['after_title'];
      }

      // display the output
      if ( ! class_exists( 'OW_Comments_Widget' ) ) {
         include_once(OW_EDITORIAL_COMMENTS_URL . 'includes/class-editorial-comments-widget.php');
      }
      $ow_comment_widget = new OW_Comments_Widget();

      $ow_comment_widget->editorial_comments_metabox();

      echo $args['after_widget'];
   }

   /**
    * Widget Backend - Display the form for contextual comment widget
    * @param array $instance saved form values ie. title
    * @since 1.0
    */
   public function form( $instance ) {
      $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Editorial Comments', 'owfrontendactions' );
      ?>
      <p>
         <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'owfrontendactions' ); ?></label>
         <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
         	name="<?php echo $this->get_field_name( 'title' ); ?>"
         	type="text"
         	value="<?php echo esc_attr( $title ); ?>">
      </p>
      <?php
   }

   /**
	 * Sanitize widget form values as they are saved.
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 * @return array Updated safe values to be saved.
    * @since 1.0
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		return $instance;
	}
}

// Register and load the widget
function register_contextual_comment_widget() {
   register_widget( 'OW_Front_End_Actions_Widget' );
}

add_action( 'widgets_init', 'register_contextual_comment_widget' );
