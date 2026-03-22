<?php
/**
 * Quick asset URL debug script
 * Access this via: https://brandonrp.test/app/themes/brandonrp/debug-assets.php
 */

// Load WordPress
require_once('../../../wp/wp-load.php');

use Roots\Sage\Assets;

echo "<h2>Asset Debug Info</h2>";

echo "<h3>Theme Info:</h3>";
echo "Active Theme: " . get_stylesheet() . "<br>";
echo "Theme Directory: " . get_stylesheet_directory() . "<br>";
echo "Theme URI: " . get_stylesheet_directory_uri() . "<br>";

echo "<h3>Environment:</h3>";
echo "WP_ENV: " . (defined('WP_ENV') ? WP_ENV : 'not defined') . "<br>";
echo "WP_HOME: " . (defined('WP_HOME') ? WP_HOME : 'not defined') . "<br>";
echo "WP_SITEURL: " . (defined('WP_SITEURL') ? WP_SITEURL : 'not defined') . "<br>";
echo "WP_CONTENT_URL: " . (defined('WP_CONTENT_URL') ? WP_CONTENT_URL : 'not defined') . "<br>";
echo "Home URL: " . home_url() . "<br>";
echo "Site URL: " . site_url() . "<br>";
echo "is_local_env(): " . (Assets\is_local_env() ? 'true' : 'false') . "<br>";

echo "<h3>Asset Paths (Direct):</h3>";
$dist_dir = get_stylesheet_directory() . '/dist/';
$dist_uri = get_stylesheet_directory_uri() . '/dist/';

$css_path = $dist_dir . 'styles/main.css';
$css_uri = $dist_uri . 'styles/main.css';
$js_path = $dist_dir . 'scripts/main.js';
$js_uri = $dist_uri . 'scripts/main.js';

echo "CSS Path: " . $css_path . "<br>";
echo "CSS Exists: " . (file_exists($css_path) ? 'YES' : 'NO') . "<br>";
echo "CSS URI: <a href='" . $css_uri . "' target='_blank'>" . $css_uri . "</a><br>";
// Test CSS accessibility
$css_accessible = 'Cannot test (cURL not available)';
if (function_exists('curl_init')) {
  $ch = curl_init($css_uri);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $css_accessible = ($code == 200) ? 'YES (HTTP ' . $code . ')' : 'NO (HTTP ' . ($code ?: 'unknown') . ')';
}
echo "CSS Accessible: " . $css_accessible . "<br><br>";

echo "JS Path: " . $js_path . "<br>";
echo "JS Exists: " . (file_exists($js_path) ? 'YES' : 'NO') . "<br>";
echo "JS URI: <a href='" . $js_uri . "' target='_blank'>" . $js_uri . "</a><br>";
// Test JS accessibility
$js_accessible = 'Cannot test (cURL not available)';
if (function_exists('curl_init')) {
  $ch = curl_init($js_uri);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $js_accessible = ($code == 200) ? 'YES (HTTP ' . $code . ')' : 'NO (HTTP ' . ($code ?: 'unknown') . ')';
}
echo "JS Accessible: " . $js_accessible . "<br>";

echo "<h3>Asset Resolution (Using Helper Functions):</h3>";
list($css_uri_resolved, $css_ver) = Assets\brp_resolve_asset('styles/main.css', Assets\is_local_env());
list($js_uri_resolved, $js_ver) = Assets\brp_resolve_asset('scripts/main.js', Assets\is_local_env());

echo "CSS URI (resolved): " . ($css_uri_resolved ?: 'FAILED') . "<br>";
echo "CSS Version: " . ($css_ver ?: 'none') . "<br>";
echo "JS URI (resolved): " . ($js_uri_resolved ?: 'FAILED') . "<br>";
echo "JS Version: " . ($js_ver ?: 'none') . "<br>";

echo "<h3>Asset Path Helper:</h3>";
echo "CSS (asset_path): " . Assets\asset_path('styles/main.css') . "<br>";
echo "JS (asset_path): " . Assets\asset_path('scripts/main.js') . "<br>";

