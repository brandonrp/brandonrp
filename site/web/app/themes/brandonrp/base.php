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
        <main class="main" role="main">
          <?php include Wrapper\template_path(); ?>
        </main>
    </div>
    <?php
      do_action('get_footer');
      get_template_part('templates/modal', 'contact');

      wp_footer();
    ?>
  </body>
</html>
