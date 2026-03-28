<?php
$show_category_back = (bool) get_query_var('brp_show_post_nav_category_back', false);

$category_back_markup = '';
if ($show_category_back) {
    $post_id = get_the_ID();
    $post_type = get_post_type($post_id);

    // Blog posts: "Back to …" goes to the Posts page (Settings → Reading), e.g. Sketchbook index.
    // Portfolio and other types still use their primary taxonomy term archive.
    if ($post_type === 'post') {
        $posts_page_id = (int) get_option('page_for_posts');
        if ($posts_page_id > 0) {
            $url = get_permalink($posts_page_id);
            if ($url) {
                $label = sprintf(
                    /* translators: %s: blog posts index page title (e.g. Sketchbook) */
                    __('Back to %s', 'sage'),
                    get_the_title($posts_page_id)
                );
                $category_back_markup = sprintf(
                    '<span class="nav-category-back"><a href="%s">%s</a></span>',
                    esc_url($url),
                    esc_html($label)
                );
            }
        }
    }

    if ($category_back_markup === '') {
        // `get_the_category()` only applies to the default "post" type + category taxonomy.
        // Portfolio uses the `project` taxonomy; other CPTs may use their own.
        $taxonomy = null;
        if ($post_type === 'post') {
            $taxonomy = 'category';
        } elseif ($post_type === 'portfolio') {
            $taxonomy = 'project';
        } else {
            foreach (get_object_taxonomies($post_type, 'objects') as $tax) {
                if (!$tax->public) {
                    continue;
                }
                $try_terms = get_the_terms($post_id, $tax->name);
                if (!empty($try_terms) && !is_wp_error($try_terms)) {
                    $taxonomy = $tax->name;
                    break;
                }
            }
        }

        $terms = $taxonomy ? get_the_terms($post_id, $taxonomy) : [];
        if (!empty($terms) && !is_wp_error($terms)) {
            $term = $terms[0];
            $url = get_term_link($term);
            if (!is_wp_error($url)) {
                $label = sprintf(
                    /* translators: %s: category or taxonomy term name */
                    __('Back to %s', 'sage'),
                    $term->name
                );
                $category_back_markup = sprintf(
                    '<span class="nav-category-back"><a href="%s">%s</a></span>',
                    esc_url($url),
                    esc_html($label)
                );
            }
        }
    }
}
?>
<div class="post-nav posts-navigation">
  <?php
  echo '<span class="nav-previous">' . get_next_post_link('%link', 'Prev') . '</span>';
  echo $category_back_markup;
  echo '<span class="nav-next">' . get_previous_post_link('%link', 'Next') . '</span>';
  ?>
</div>
