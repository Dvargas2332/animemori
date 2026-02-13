<?php
if (!defined('ABSPATH')) exit;

function animemori_core_register_shortcodes() {
  add_shortcode('animemori_schedule', 'animemori_shortcode_schedule');
  add_shortcode('animemori_anime', 'animemori_shortcode_anime');
  add_shortcode('animemori_upcoming', 'animemori_shortcode_upcoming');
}

function animemori_shortcode_schedule($atts) {
  $atts = shortcode_atts([
    'days' => 7,
  ], $atts);

  ob_start();
  include ANIMEMORI_CORE_PATH . 'templates/partials/schedule-block.php';
  return ob_get_clean();
}

function animemori_shortcode_anime($atts) {
  $atts = shortcode_atts([
    'slug' => '',
  ], $atts);

  if (!$atts['slug']) return '<p>Missing anime slug.</p>';

  $slug = sanitize_title($atts['slug']);
  $_GET['animemori_embed_slug'] = $slug;

  ob_start();
  include ANIMEMORI_CORE_PATH . 'templates/partials/anime-block.php';
  return ob_get_clean();
}

function animemori_shortcode_upcoming($atts) {
  $atts = shortcode_atts([
    'hours' => 72,
  ], $atts);

  ob_start();
  include ANIMEMORI_CORE_PATH . 'templates/partials/upcoming-block.php';
  return ob_get_clean();
}
