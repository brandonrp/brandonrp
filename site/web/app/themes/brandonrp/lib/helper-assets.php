<?php
// lib/helpers-assets.php

namespace BrandonRP\Assets;

if (!defined('ABSPATH')) exit;

/**
 * Resolve an asset path.
 * Supports rev-manifest.json when present, otherwise falls back to plain dist paths.
 * Returns ['', null] on failure so callers can no-op.
 *
 * @param string $asset 'styles/main.css' or 'scripts/main.js' or 'images/foo.png'
 * @return array [$uri, $ver]
 */
function asset($asset) {
  static $manifest = null;
  static $manifest_mtime = 0;

  $theme_dir = get_template_directory();
  $theme_uri = get_template_directory_uri();
  $dist_dir  = $theme_dir . '/dist';
  $dist_uri  = $theme_uri . '/dist';
  $manifest_path = $dist_dir . '/rev-manifest.json';

  // Lazy load manifest once
  if ($manifest === null) {
    if (is_file($manifest_path)) {
      $json = @file_get_contents($manifest_path);
      $decoded = $json ? json_decode($json, true) : null;
      if (is_array($decoded)) {
        $manifest = $decoded;
        $manifest_mtime = @filemtime($manifest_path) ?: 0;
      } else {
        $manifest = [];
      }
    } else {
      $manifest = [];
    }
  }

  // Normalize input
  $asset = ltrim($asset, '/');

  // If manifest maps it, use mapped filename
  $mapped = isset($manifest[$asset]) ? $manifest[$asset] : $asset;

  $file_path = $dist_dir . '/' . $mapped;
  $file_uri  = $dist_uri . '/' . $mapped;

  if (is_file($file_path)) {
    // Version with file mtime. If using manifest, include manifest mtime too to refresh when it changes.
    $ver_parts = [ @filemtime($file_path) ?: 0 ];
    if (!empty($manifest)) $ver_parts[] = $manifest_mtime;
    $ver = implode('.', array_filter($ver_parts));
    return [$file_uri, $ver];
  }

  // Try original (unmapped) name as last resort
  $fallback_path = $dist_dir . '/' . $asset;
  $fallback_uri  = $dist_uri . '/' . $asset;
  if (is_file($fallback_path)) {
    $ver = @filemtime($fallback_path) ?: null;
    return [$fallback_uri, $ver];
  }

  return ['', null];
}
