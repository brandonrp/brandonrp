<?php

use Roots\Sage\Config;
use Roots\Sage\Wrapper;

?>

<?php get_template_part('templates/page', 'header'); ?>

<div class="entry-content">
  <div class="alert alert-warning">
    <?php _e('Sorry, but the page you were trying to view does not exist.', 'sage'); ?>
  </div>

  <?php get_search_form(); ?>
</div>

<?php get_template_part('templates/footer'); ?>
