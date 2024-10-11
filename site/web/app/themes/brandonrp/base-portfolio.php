<?php

use Roots\Sage\Config;
use Roots\Sage\Wrapper;

?>

<!doctype html>
<html class="no-js" <?php language_attributes(); ?>>
  <?php get_template_part('templates/head'); ?>
  <body <?php body_class(); ?>>
    <!--[if lt IE 9]>
      <div class="alert alert-warning">
        <?php _e('You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.', 'sage'); ?>
      </div>
    <![endif]-->
    <?php
      do_action('get_header');
      get_template_part('templates/header');
    ?>
    <div class="main-wrapper" role="document">

      <?php if(
        have_posts() &&
        get_post_type() == 'portfolio' &&
        !is_single()):

        $filter_args = array(
          'type'                     => 'portfolio',
          'orderby'                  => 'name',
          'order'                    => 'ASC',
          'hide_empty'               => 1,
          'taxonomy'                 => 'project',
        );
      ?>

        <div class="project-filters">
          <h4 class="label">Filter</h4>
          <?php if (has_nav_menu('project_filters')) :
              wp_nav_menu(['theme_location' => 'project_filters', 'items_wrap' => '']);
            endif; ?>
        </div>
      <?php endif; ?>

        <main class="main" role="main">

          <?php if (!have_posts()) : ?>
            <div class="alert alert-warning">
              <?php _e('Sorry, no results were found...', 'sage'); ?>
            </div>
          <?php endif; ?>

          <?php include Wrapper\template_path(); ?>

        </main><!-- /.main -->
    </div><!-- /.wrap -->
    <?php
      do_action('get_footer');
      get_template_part('templates/modal', 'contact');
      wp_footer();
    ?>
  </body>
</html>
