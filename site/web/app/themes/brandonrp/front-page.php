<?php while (have_posts()) : the_post(); ?>

  <?php // Portfolio block image URL

    // check if the repeater field has rows of data
    if( have_rows('home_block') ):
        $count_home = 0;
        $image_url = '';
        $image_url_mobile = '';

        // loop through the rows of data
        while ( have_rows('home_block') ) : the_row(); ?>

          <?php
          // Get Required Fields
          $title = get_sub_field('block_title');
          $link = get_sub_field('block_link');

          if( get_sub_field('block_image')) {
            $image = get_sub_field('block_image');
            $image_url = wp_get_attachment_image_src($image['id'], 'portfolio-block' );
          }

          if( get_sub_field('block_image_mobile')) {
            $image_mobile = get_sub_field('block_image_mobile');
            $image_url_mobile = wp_get_attachment_image_src($image_mobile['id'], 'portfolio-block-mobile' );
          }

          $count_home++;
          ?>

            <a class="home-block" href="<?php echo $link ?>">
              <div class="overlay">
                <h2 class="block-title"><?php echo $title ?></h2>
                <span class="button red">View</span>
              </div>

              <?php if( !empty($image)): ?>
              <div class="image-wrapper">
                <div class="featured-image" style="background-image: url('<?php echo $image_url[0]; ?>');"></div>
                <div class="featured-image-mobile" style="background-image: url('<?php echo $image_url_mobile[0]; ?>');"></div>
              </div>
              <?php endif; ?>
            </a>

        <?php endwhile;

  else :

    echo "Down for Maintenance";

  endif; ?>

<?php endwhile; ?>
