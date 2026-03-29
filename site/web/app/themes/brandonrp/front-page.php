<?php while (have_posts()) : the_post(); ?>

  <?php
  $front_page_id = get_the_ID();

  // Shared pool: Front Page → home_gallery repeater → gallery_image
  // Use get_field() so looping home_block isn’t affected by a separate have_rows('home_gallery') pass.
  $brp_home_gallery_ids = [];
  $gallery_rows         = get_field('home_gallery', $front_page_id);

  if (! is_array($gallery_rows) || empty($gallery_rows)) {
      // If the repeater was added inside the first home block row instead of on the page
      $block_rows = get_field('home_block', $front_page_id);
      if (is_array($block_rows) && ! empty($block_rows[0]['home_gallery'])) {
          $gallery_rows = $block_rows[0]['home_gallery'];
      }
  }

  if (is_array($gallery_rows)) {
      foreach ($gallery_rows as $row) {
          $gimg = is_array($row) ? ( $row['gallery_image'] ?? null ) : null;
          if (is_numeric($gimg)) {
              $brp_home_gallery_ids[] = (int) $gimg;
          } elseif (is_array($gimg) && ! empty($gimg['ID'])) {
              $brp_home_gallery_ids[] = (int) $gimg['ID'];
          } elseif (is_array($gimg) && ! empty($gimg['id'])) {
              $brp_home_gallery_ids[] = (int) $gimg['id'];
          } elseif (is_string($gimg) && $gimg !== '') {
              $maybe_id = attachment_url_to_postid($gimg);
              if ($maybe_id) {
                  $brp_home_gallery_ids[] = $maybe_id;
              }
          }
      }
  }

  if (have_rows('home_block')) :

      while (have_rows('home_block')) : the_row(); ?>

        <?php
        $image_url = '';

        $title = get_sub_field('block_title');
        $link = get_sub_field('block_link');

        $attachment_id  = null;
        $is_first_block = ( (int) get_row_index() === 1 );

        if ($is_first_block) {
            if (! empty($brp_home_gallery_ids)) {
                $attachment_id = $brp_home_gallery_ids[ array_rand($brp_home_gallery_ids) ];
            } elseif (get_sub_field('block_image')) {
                $image = get_sub_field('block_image');
                if (! empty($image['id'])) {
                    $attachment_id = (int) $image['id'];
                } elseif (! empty($image['ID'])) {
                    $attachment_id = (int) $image['ID'];
                }
            }
        }

        if ($attachment_id) {
            $image_url = wp_get_attachment_image_src($attachment_id, 'home-gallery-hero');
            if (empty($image_url)) {
                $image_url = wp_get_attachment_image_src($attachment_id, 'full');
            }
        }
        ?>

          <a class="home-block" href="<?php echo esc_url($link); ?>">
            <div class="overlay">
              <h2 class="block-title"><?php echo esc_html($title); ?></h2>
              <span class="button red">View</span>
            </div>

            <div class="image-wrapper">
              <?php if (! empty($image_url)) : ?>
                <div class="featured-image" style="background-image: url('<?php echo esc_url($image_url[0]); ?>');"></div>
              <?php else : ?>
                <div class="featured-image"></div>
              <?php endif; ?>
            </div>
          </a>

      <?php endwhile;

  else :

      echo "Home blocks are not configured.";

  endif; ?>

<?php endwhile; ?>
