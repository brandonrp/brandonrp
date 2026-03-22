<?php
/**
 * Custom Post Type Registration
 */

// Create project post type
add_action('init', function() {

  $labels = array(
    'name'              => _x( 'Project Types', 'taxonomy general name' ),
    'singular_name'     => _x( 'Project Type', 'taxonomy singular name' ),
    'search_items'      => __( 'Search Types' ),
    'all_items'         => __( 'All Types' ),
    'parent_item'       => __( 'Parent Type' ),
    'parent_item_colon' => __( 'Parent Type:' ),
    'edit_item'         => __( 'Edit Type' ),
    'update_item'       => __( 'Update Type' ),
    'add_new_item'      => __( 'Add New Type' ),
    'new_item_name'     => __( 'New Type Name' ),
    'menu_name'         => __( 'Type' ),
  );

  $args = array(
    'hierarchical'      => true,
    'labels'            => $labels,
    'show_ui'           => true,
    'show_admin_column' => true,
    'show_in_nav_menus' => true,
    'query_var'         => true,
    'rewrite'           => array('with_front' => false)
  );
  register_taxonomy('project', array( 'portfolio' ), $args);

  register_post_type( 'portfolio',
    array(
      'labels'=> array(
        'name'               => __( 'Projects' ),
        'singular_name'      => __( 'Project' ),
        'add_new_item'       => 'Add New Project',
        'edit_item'          => 'Edit Project',
        'new_item'           => 'New Project',
        'all_items'          => 'All Projects',
        'view_item'          => 'View Projects',
        'search_items'       => 'Search Projects',
        'not_found'          => 'No Projects found',
        'not_found_in_trash' => 'No Projects found in Trash',
        'menu_name'          => 'Projects'
      ),
      'menu_position'          => 5,
      'public'               => true,
      'has_archive'          => true,
      'hierarchical'         => true,
      'rewrite'              => array( 'slug' => 'portfolio', 'pages' => true ),
      'supports'             => array( 'title', 'thumbnail', 'editor', 'slug', 'page-attributes', 'revisions' ),
      'taxonomies'           => array( 'project' )
    )
  );

  flush_rewrite_rules();
});
