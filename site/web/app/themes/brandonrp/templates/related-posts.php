<?php
$isCategory = false;
$catList = '';
$termList = array();
$count = 0;
$i = 0;

// Get categories or terms appropriately
if( get_post_type() == 'portfolio' ):
  $post_categories = wp_get_post_terms( $post->ID, 'project' );
else:
  $post_categories = wp_get_post_categories( $post->ID);
  $isCategory = true;
endif;

// Get total # of terms
foreach($post_categories as $term) {
  $count++;
}

// Add the terms to a string
foreach($post_categories as $term) {

  if($isCategory) {
    $cat = get_category( $term );
    $catList .= $cat->slug;
    $i++;

    if($i < $count) {
      $catList .= ',';
    }
  } else {
    $cat = $term;
    $i++;

    if($i < $count) {
      $termList[$i] = $cat->slug;
    }
  }
}

// Set arguments based on post type
if(!$isCategory) {
  $args = array(
    'posts_per_page' => '3',
    'orderby' > 'rand',
    'post__not_in' => array($post->ID),
    'tax_query' => array(
      array(
        'post_type' => 'portfolio',
        'taxonomy' => 'project',
        'field'    => 'slug',
        'terms'    => array('ui-design', 'web-design', 'branding', 'concept-art'),
      ),
    ),
  );
} else {
  $args = array(
    'posts_per_page' => '3',
    'category_name' => $catList,
    'orderby' > 'rand',
    'post__not_in' => array($post->ID)
  );
}

$query = new WP_Query( $args ); ?>

<?php if ( $query->have_posts() ) : ?>

  <?php while ( $query->have_posts() ) : $query->the_post(); ?>
    <?php get_template_part('templates/teaser', 'small'); ?>
  <?php endwhile; ?>

  <?php wp_reset_postdata(); ?>

<?php else : ?>
  <p><?php _e( 'Sorry, no posts matched your criteria.' ); ?></p>
<?php endif; ?>
