<?php // Get teaser image
$teaser_image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'blog-teaser-small' ); ?>

<a href="<?php the_permalink(); ?>" <?php post_class('post-teaser small'); ?>>
  <div class="post-thumbnail"  <?php post_class('post-teaser'); ?> <?php if($teaser_image_url): ?> style="background-image: url('<?php echo $teaser_image_url[0]; ?>');" <?php endif; ?>></div>
  <header>
    <h2 class="entry-title"><?php the_title(); ?></h2>
    <span class="date"><?php echo get_the_date(); ?></span>
  </header>
</a>
