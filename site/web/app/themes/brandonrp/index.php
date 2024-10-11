<?php if(!have_posts()) : ?>
  <div class="alert alert-warning">
    <?php _e('Sorry, no results were found...', 'sage'); ?>
  </div>
<?php endif; ?>

<?php // Select teaser type based on count

  $images = '';

  while (have_posts()) : the_post();
    $images .= get_the_post_thumbnail($post->ID, 'medium');
    get_template_part('templates/teaser', 'large');
  endwhile;
?>

<?php the_posts_navigation(array(
  'mid_size' => 3,
  'prev_text' => __( 'Older', 'sage' ),
  'next_text' => __( 'Newer', 'sage' ),
) ); ?>

<?php if(have_posts()) : ?>
  <div class="preload">
    <?php echo $images; ?>
  </div>
<?php endif; ?>
