<?php
get_header(); ?>
<div class="wrap">
	<div class="content-area">
		<main id="main" class="site-main" role="main">
		<?php
		// Start the loop.
		while ( have_posts() ) : the_post(); ?>
         <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>    
            <header class="entry-header">
               <?php
                  if ( is_single() ) :
                     the_title( '<h1 class="entry-title">', '</h1>' );
                  else :
                     the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' );
                  endif;
               ?>
            </header><!-- .entry-header -->

            <div class="entry-content">
               <?php  
               $post_id = get_the_ID();               
               $ow_comments_service = new OW_Comments_Service();
               $contextual_comments = $ow_comments_service->get_contextual_comments_by_post_id( $post_id ); 
               
               echo $contextual_comments;
               
               ?>
            </div><!-- .entry-content -->

         </article><!-- #post-## -->			
      <?php
		// End the loop.
		endwhile;
		?>

		</main><!-- .site-main -->
	</div><!-- .content-area -->
</div><!-- .wrap -->

<?php get_footer(); ?>
