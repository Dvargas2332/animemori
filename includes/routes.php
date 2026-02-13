<?php
if (!defined('ABSPATH')) exit;

function animemori_core_add_rewrite_rules() {
  // /schedule or /schedule/monday
  add_rewrite_rule('^schedule/?$', 'index.php?animemori_page=schedule', 'top');
  add_rewrite_rule('^schedule/([a-zA-Z-]+)/?$', 'index.php?animemori_page=schedule&animemori_day=$matches[1]', 'top');

  // /upcoming
  add_rewrite_rule('^upcoming/?$', 'index.php?animemori_page=upcoming', 'top');

  // /anime (directory)
  add_rewrite_rule('^anime/?$', 'index.php?animemori_page=anime_list', 'top');

  // /anime/{slug}
  add_rewrite_rule('^anime/([a-z0-9-]+)/?$', 'index.php?animemori_page=anime&animemori_slug=$matches[1]', 'top');

  // /characters/{slug} (phase 2 template included)
  add_rewrite_rule('^characters/([a-z0-9-]+)/?$', 'index.php?animemori_page=character&animemori_slug=$matches[1]', 'top');

  // sitemap
  add_rewrite_rule('^animemori-sitemap\\.xml$', 'index.php?animemori_sitemap=1', 'top');
}

function animemori_core_register_routes() {
  add_action('init', 'animemori_core_add_rewrite_rules');

  add_filter('query_vars', function($vars) {
    $vars[] = 'animemori_page';
    $vars[] = 'animemori_slug';
    $vars[] = 'animemori_day';
    $vars[] = 'animemori_sitemap';
    return $vars;
  });

  add_action('template_redirect', function() {
    if (get_query_var('animemori_sitemap')) {
      animemori_render_sitemap();
      exit;
    }
  });

  add_filter('template_include', function($template) {
    $page = get_query_var('animemori_page');
    if (!$page) return $template;

    $file = null;
    if ($page === 'schedule') $file = ANIMEMORI_CORE_PATH . 'templates/schedule.php';
    if ($page === 'upcoming') $file = ANIMEMORI_CORE_PATH . 'templates/upcoming.php';
    if ($page === 'anime_list') $file = ANIMEMORI_CORE_PATH . 'templates/anime-list.php';
    if ($page === 'anime') $file = ANIMEMORI_CORE_PATH . 'templates/anime.php';
    if ($page === 'character') $file = ANIMEMORI_CORE_PATH . 'templates/character.php';

    if ($file && file_exists($file)) return $file;
    return $template;
  });
}
