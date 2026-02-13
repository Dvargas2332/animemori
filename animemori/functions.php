<?php
if (!defined('ABSPATH')) exit;

function animemori_theme_setup() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
}
add_action('after_setup_theme', 'animemori_theme_setup');

function animemori_theme_assets() {
  $theme = wp_get_theme();
  $ver = $theme->get('Version');
  wp_enqueue_script(
    'animemori-theme',
    get_template_directory_uri() . '/assets/theme.js',
    [],
    $ver,
    true
  );
}
add_action('wp_enqueue_scripts', 'animemori_theme_assets');

function animemori_capture_search_term() {
  if (is_admin()) return;
  $term = '';
  if (is_search()) {
    $term = get_search_query(false);
  } elseif (isset($_GET['s'])) {
    $term = sanitize_text_field(wp_unslash($_GET['s']));
  }
  $term = trim((string)$term);
  if ($term === '') return;
  if (strlen($term) > 80) $term = substr($term, 0, 80);
  $cookie_value = rawurlencode($term);
  $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
  $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
  setcookie('am_last_search', $cookie_value, time() + 30 * DAY_IN_SECONDS, $path, $domain, is_ssl(), true);
  $_COOKIE['am_last_search'] = $cookie_value;
}
add_action('template_redirect', 'animemori_capture_search_term');
