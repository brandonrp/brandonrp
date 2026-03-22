<?php // Custom PHP functions for the theme

// Get post categories or terms
function get_custom_tax($post_id, $is_anchor) {
  $isCategory = false;

  if( get_post_type() == 'portfolio' ):
    $post_categories = wp_get_post_terms( $post_id, 'project' );
  else:
    $post_categories = wp_get_post_categories( $post_id);
    $isCategory = true;
  endif;

  $i = 0;
  $cat_count = 0;

  foreach($post_categories as $term){
    $cat_count++;
  }

  foreach($post_categories as $term){

    if($isCategory) {
      $cat = get_category( $term );
    } else {
      $cat = $term;
    }

    $i++;

    if($is_anchor) {
      if($i < $cat_count) {
        echo '<li><a href="/'.$cat->taxonomy.'/'.$cat->slug.'" title="'.$cat->name.'">'.$cat->name.'</a>, </li>';
      } else {
        echo '<li><a href="/'.$cat->taxonomy.'/'.$cat->slug.'" title="'.$cat->name.'">'.$cat->name.'</a></li>';
      }
    } else {
      if($i < $cat_count) {
        echo '<li>'.$cat->name.', </li>';
      } else {
        echo '<li>'.$cat->name.'</li>';
      }
    }
  }
}

// Get Breadcrumbs by Taxonomy
function breadcrumbs($post_id) {
  $term_list = wp_get_post_terms($post_id, 'project', array("fields" => "all"));
  $term = get_term_by('id', $term_list[0]->term_id, 'project');
  $term_id =  $term->term_taxonomy_id;
  echo '<div class="breadcrumbs">'.get_category_parents($term_id, true, "&nbsp;&#8811;&nbsp;", false, false)."</div>";
}

// Do not load contact form 7 stylesheet
add_filter( 'wpcf7_load_css', '__return_false' );

// Do not compress JPEG images.
//add_filter( 'jpeg_quality', create_function( '', 'return 100;' ) );

// Grab a thumbnail for the social sites
function insert_image_src_rel_in_head() {
  global $post;
  if ( !is_singular()) //if it is not a post or a page
    return;
  if(!has_post_thumbnail( $post->ID )) { //the post does not have featured image, use a default image
    $default_image="http://brandonrp.com/app/uploads/2015/09/screenshot.png"; //replace this with a default image on your server or an image in your media library
    echo '<meta property="og:image" content="' . $default_image . '"/>';
  }
  else{
    $thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'blog-teaser-large' );
    echo '<meta property="og:image" content="' . esc_attr( $thumbnail_src[0] ) . '"/>';
  }
  echo "
";
}
add_action( 'wp_head', 'insert_image_src_rel_in_head', 5 );

// Hide 'private' label in post template
// Taken from: https://wordpress.org/support/topic/how-to-remove-private-from-private-pages
function the_title_trim($title) {
  // Might aswell make use of this function to escape attributes
  $title = esc_attr($title);
  // What to find in the title
  $findthese = array(
    '#Protected:#', // # is just the delimeter
    '#Private:#'
  );
  // What to replace it with
  $replacewith = array(
    '', // What to replace protected with
    '' // What to replace private with
  );
  // Items replace by array key
  $title = preg_replace($findthese, $replacewith, $title);
  return $title;
}
add_filter('the_title', 'the_title_trim');
