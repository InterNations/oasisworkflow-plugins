<?php

/*
 * Comment Object
 */

if ( ! defined( 'ABSPATH' ) ) {
   exit; // Exit if accessed directly
}

class OW_Editorial_Comment {
   /**
    * Comment ID
    * 
    * @since 1.0
    */
   public $ID = 0;
   
   /**
    * Workflow history ID
    * 
    * @since 1.0
    */
   public $workflow_history_id;
   
   /**
    * Post ID
    * 
    * @since 1.0
    */
   public $post_id;
   
   /**
    * User ID
    * 
    * @since 1.0
    */
   public $user_id;
   
   /**
    * Comments
    * 
    * @since 1.0
    */
   public $comments;
}
?>