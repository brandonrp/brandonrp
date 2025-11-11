<?php
/**
 * Assets loader (Sage 8 / Gulp 4)
 * - jQuery first, never defer main bundle
 * - Supports dist/rev-manifest.json (hashed) and legacy dist/assets.json
 * - Local/dev: prefer non-hashed files with filemtime() version
 * - Production: prefer manifest, graceful fallback to plain filenames
 */

namespace Roots\Sage\Assets;

if (!defined('ABSPATH')) { exit; }

/**
 * Legacy Sage 8 manifest reader (assets.json)
 */
class JsonManifest {
  private $manifest;
  public function __construct($manifest_path) {
    if (file_exists($manifest_path)) {
      $this->manifest = json_decode(file_get_contents($manifest_path), true);
    } else {
      $this->manifest = [];
    }
  }
  public function get() { return $this->manifest; }
  public function getPath($key = '', $default = null) {
    $collection = $this->manifest;
    if (is_null($key)) return $collection;
    if (isset($collection[$key])) return $collection[$key];
    foreach (explode('.', $key) as $segment) {
      if (!isset($collection[$segment])) return $default;
      $collection = $collection[$segment];
    }
    return $collection;
  }
}

/**
 * Environment check
 */
function is_local_env() {
  $host = parse_url(home_url(), PHP_URL_HOST);
  $is_local = (defined('WP_ENV') && WP_ENV !== 'production')
           || preg_match('/\.(test|local)$/', (string) $host)
           || $host === 'localhost';
  return (bool) $is_local;
}

/**
 * Load both possible manifests once and cache them.
 * - rev-manifest.json maps logical paths (e.g. "scripts/main.js")
 * - assets.json maps basenames (e.g. "main.js")
 */
function brp_get_manifests() {
  static $loaded = false, $rev = [], $assets = [], $rev_mtime = 0;

  if ($loaded) return [$rev, $assets, $rev_mtime];

  $dist_dir    = get_stylesheet_directory() . (defined('DIST_DIR') ? DIST_DIR : '/dist/');
  $rev_path    = $dist_dir . 'rev-manifest.json';
  $assets_path = $dist_dir . 'assets.json';

  if (is_file($rev_path)) {
    $json = @file_get_contents($rev_path);
    $rev  = is_string($json) ? json_decode($json, true) : [];
    if (!is_array($rev)) $rev = [];
    $rev_mtime = @filemtime($rev_path) ?: 0;
  }

  if (is_file($assets_path)) {
    $manifest = new JsonManifest($assets_path);
    $assets   = $manifest->get();
    if (!is_array($assets)) $assets = [];
  }

  $loaded = true;
  return [$rev, $assets, $rev_mtime];
}

/**
 * Find a mapped filename in either manifest.
 * Accepts "styles/main.css" and "main.css".
 */
function brp_manifest_map($logical) {
  list($rev, $assets) = brp_get_manifests();

  if (isset($rev[$logical])) return $rev[$logical];

  $base = basename($logical);
  if (isset($assets[$base])) return dirname($logical) . '/' . ltrim($assets[$base], '/');

  if (isset($rev[$base])) return dirname($logical) . '/' . ltrim($rev[$base], '/');

  return null;
}

/**
 * Resolve a dist asset to [uri, version], with fallbacks.
 * $prefer_plain: true => try non-hashed first (dev); false => manifest first (prod)
 */
function brp_resolve_asset($logical, $prefer_plain = false) {
  $dist_uri = get_stylesheet_directory_uri() . (defined('DIST_DIR') ? DIST_DIR : '/dist/');
  $dist_dir = get_stylesheet_directory()     . (defined('DIST_DIR') ? DIST_DIR : '/dist/');

  $logical = ltrim($logical, '/');
  $plain_p = $dist_dir . $logical;
  $plain_u = $dist_uri . $logical;

  list($rev, $assets, $rev_mtime) = brp_get_manifests();
  $mapped_rel = brp_manifest_map($logical);
  $map_p = $mapped_rel ? $dist_dir . ltrim($mapped_rel, '/') : null;
  $map_u = $mapped_rel ? $dist_uri . ltrim($mapped_rel, '/') : null;

  if ($prefer_plain) {
    if (is_file($plain_p)) {
      return [$plain_u, @filemtime($plain_p) ?: null];
    }
    if ($map_p && is_file($map_p)) {
      $v = array_filter([@filemtime($map_p) ?: 0, $rev_mtime ?: 0]);
      return [$map_u, $v ? implode('.', $v) : null];
    }
  } else {
    if ($map_p && is_file($map_p)) {
      $v = array_filter([@filemtime($map_p) ?: 0, $rev_mtime ?: 0]);
      return [$map_u, $v ? implode('.', $v) : null];
    }
    if (is_file($plain_p)) {
      return [$plain_u, @filemtime($plain_p) ?: null];
    }
  }

  return ['', null];
}

/**
 * Back-compat: original Sage 8 helper name; now uses the resolver.
 */
function asset_path($filename) {
  $prefer_plain = is_local_env(); // dev: prefer non-hashed
  list($uri) = brp_resolve_asset(ltrim($filename, '/'), $prefer_plain);
  if ($uri) return $uri;

  // final fallback to raw dist path
  $dist_path = get_stylesheet_directory_uri() . (defined('DIST_DIR') ? DIST_DIR : '/dist/');
  $directory = dirname($filename) . '/';
  $file      = basename($filename);
  return $dist_path . $directory . $file;
}

/**
 * Main enqueue: jQuery first, never defer main bundle.
 */
function assets() {
  $is_local = is_local_env();

  // Threaded comments
  if (is_single() && comments_open() && get_option('thread_comments')) {
    wp_enqueue_script('comment-reply');
  }

  // Always load WP jQuery (and its migrate) before your script
  wp_enqueue_script('jquery');

  $prefer_plain = $is_local;

  // Use the robust asset resolution helper which handles environment differences
  // This ensures consistent behavior across test and production environments
  list($css_uri, $css_version) = brp_resolve_asset('styles/main.css', $prefer_plain);
  list($js_uri, $js_version) = brp_resolve_asset('scripts/main.js', $prefer_plain);

  // Diagnostic logging for development environment only
  if ($is_local && defined('WP_DEBUG') && WP_DEBUG) {
    $dist_uri = get_stylesheet_directory_uri() . '/dist/';
    $dist_dir = get_stylesheet_directory() . '/dist/';
    error_log('BRP Assets Debug - Environment: ' . (defined('WP_ENV') ? WP_ENV : 'not set'));
    error_log('BRP Assets Debug - is_local_env: ' . ($is_local ? 'true' : 'false'));
    error_log('BRP Assets Debug - Theme URI: ' . get_stylesheet_directory_uri());
    error_log('BRP Assets Debug - CSS URI resolved: ' . ($css_uri ?: 'FAILED'));
    error_log('BRP Assets Debug - JS URI resolved: ' . ($js_uri ?: 'FAILED'));
    error_log('BRP Assets Debug - WP_HOME: ' . (defined('WP_HOME') ? WP_HOME : 'not defined'));
    error_log('BRP Assets Debug - WP_CONTENT_URL: ' . (defined('WP_CONTENT_URL') ? WP_CONTENT_URL : 'not defined'));
  }

  // CSS - Use resolved URI or fallback to asset_path helper
  if ($css_uri) {
    wp_enqueue_style('brandonrp_style', $css_uri, [], $css_version ?: filemtime(get_stylesheet_directory() . '/dist/styles/main.css') ?: '1.0');
  } else {
    // Fallback to asset_path helper
    wp_enqueue_style('brandonrp_style', asset_path('styles/main.css'), [], null);
  }

  // JS - Use resolved URI or fallback to asset_path helper
  if ($js_uri) {
    wp_enqueue_script('sage_js', $js_uri, ['jquery'], $js_version ?: filemtime(get_stylesheet_directory() . '/dist/scripts/main.js') ?: '1.0', true);
  } else {
    // Fallback to asset_path helper
    wp_enqueue_script('sage_js', asset_path('scripts/main.js'), ['jquery'], null, true);
  }
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\assets', 100);

/**
 * IMPORTANT: Do NOT defer jquery or your main bundle.
 * Keep this filter only if you plan to defer other non-core scripts.
 */
add_filter('script_loader_tag', function($tag, $handle, $src) {
  if (is_admin()) return $tag;

  // Never defer these (prevents "nav is not defined" / jQuery race)
  if (in_array($handle, ['jquery', 'jquery-core', 'jquery-migrate', 'sage_js'], true)) {
    return $tag;
  }

  // Example: defer everything else (optional)
  // return sprintf('<script src="%s" defer></script>' . "\n", esc_url($src));

  return $tag;
}, 10, 3);
