<?php while (have_posts()) : the_post(); ?>

  <?php // Portfolio block image URL

    // check if the repeater field has rows of data
    if( have_rows('home_block') ):
        $count_home = 0;

        // loop through the rows of data
        while ( have_rows('home_block') ) : the_row(); ?>

          <?php
          // Reset per-block image variables so they don't leak between iterations
          $image = null;
          $image_mobile = null;
          $image_url = '';
          $image_url_mobile = '';

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

              <div class="image-wrapper">
                <?php if ( ! empty( $image_url ) ) : ?>
                  <div class="featured-image" style="background-image: url('<?php echo esc_url( $image_url[0] ); ?>');"></div>
                <?php else : ?>
                  <div class="featured-image"></div>
                <?php endif; ?>

                <?php if ( ! empty( $image_url_mobile ) ) : ?>
                  <div class="featured-image-mobile" style="background-image: url('<?php echo esc_url( $image_url_mobile[0] ); ?>');"></div>
                <?php else : ?>
                  <div class="featured-image-mobile"></div>
                <?php endif; ?>
              </div>
            </a>

        <?php endwhile;

  else :

    echo "Home blocks are not configured.";

  endif; ?>

<?php endwhile; ?>
