<div class="post-meta">
  <div class="wrapper">
    <div class="post-info">
      <span>Posted in</span>
      <ul class="category-list">
        <?php get_custom_tax($post->ID, true); ?>
      </ul>

      <?php if(get_post_type($post->ID) == 'post') : ?>
        <span>on</span>
        <time class="updated" datetime="<?= get_post_time('c', true); ?>"><?= get_the_date(); ?></time>
      <?php endif; ?>
    </div>

    <?php get_template_part('templates/post', 'nav'); ?>
  </div>
</div>
