<?php use Roots\Sage\Nav; ?>

<header class="brand main-header" role="banner">
    <a href="<?php bloginfo('url'); ?>" class="icon-logo"></a>
    <nav class="main-nav" role="navigation">
      <span class="nav-toggle"></span>
      <?php
      if (has_nav_menu('primary_navigation')) :
        wp_nav_menu(['theme_location' => 'primary_navigation', 'menu_class' => 'nav']);
      endif;
      ?>
    </nav>

    <?php
    if (has_nav_menu('social_links')) :
        wp_nav_menu(['theme_location' => 'social_links', 'menu_class' => 'social-links']);
    endif;
    ?>
</header>
