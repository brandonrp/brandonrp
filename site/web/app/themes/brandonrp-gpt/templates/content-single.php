<?php while (have_posts()) : the_post(); ?>
  <article <?php post_class(); ?>>
    <header class="post-header">
      <h1 class="entry-title"><?php the_title(); ?></h1>
        <?php if( get_post_type(get_the_ID()) == 'portfolio'): ?>
            <?php get_template_part('templates/client', 'info'); ?>
        <?php endif; ?>
      <?php get_template_part('templates/entry-meta'); ?>
    </header>

    <div class="entry-content">
      <?php the_content(); ?>
    </div>

    <footer>
      <?php get_template_part('templates/post', 'nav'); ?>

      <aside class="related-posts">
        <?php get_template_part('templates/related', 'posts'); ?>
      </aside>
      <?php wp_link_pages(['before' => '<nav class="page-nav"><p>' . __('Pages:', 'sage'), 'after' => '</p></nav>']); ?>
    </footer>
  </article>
<?php endwhile; ?>
