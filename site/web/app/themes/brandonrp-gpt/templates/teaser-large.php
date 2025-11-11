<?php // Get teaser image
$teaser_image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'large' ); ?>

<a href="<?php the_permalink(); ?>" <?php post_class('post-teaser large'); ?>>
  <header class="overlay">
    <h2 class="entry-title label"><?php the_title(); ?></h2>

    <?php if(get_post_type( $post ) == 'post'): ?>
      <span class="date"><?php echo get_the_date(); ?></span>
    <?php else: ?>
      <ul class="category-list">
        <?php get_custom_tax($post->ID, false); ?>
      </ul>
    <?php endif; ?>

    <span class="button red">View</span>
  </header>

  <div class="post-thumbnail" <?php if($teaser_image_url): ?> style="background-image: url('<?php echo $teaser_image_url[0]; ?>');" <?php endif; ?>></div>
</a>
