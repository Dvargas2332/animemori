<?php
/**
 * Plugin Name: Animemori Core
 * Description: Core data model + automated anime ingest + episode scheduling + calendar pages for Animemori.
 * Version: 0.3.1
 * Author: Animemori
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

define('ANIMEMORI_CORE_VERSION', '0.3.1');
define('ANIMEMORI_CORE_PATH', plugin_dir_path(__FILE__));
define('ANIMEMORI_CORE_URL', plugin_dir_url(__FILE__));

require_once ANIMEMORI_CORE_PATH . 'includes/db.php';
require_once ANIMEMORI_CORE_PATH . 'includes/util.php';
require_once ANIMEMORI_CORE_PATH . 'includes/importers/jikan.php';
require_once ANIMEMORI_CORE_PATH . 'includes/scheduler.php';
require_once ANIMEMORI_CORE_PATH . 'includes/characters-api.php';
require_once ANIMEMORI_CORE_PATH . 'includes/characters-importer.php';
require_once ANIMEMORI_CORE_PATH . 'includes/characters-admin.php';
require_once ANIMEMORI_CORE_PATH . 'includes/characters-scheduler.php';
require_once ANIMEMORI_CORE_PATH . 'includes/admin.php';
require_once ANIMEMORI_CORE_PATH . 'includes/routes.php';
require_once ANIMEMORI_CORE_PATH . 'includes/shortcodes.php';
require_once ANIMEMORI_CORE_PATH . 'includes/seo.php';
require_once ANIMEMORI_CORE_PATH . 'includes/ratings.php';
require_once ANIMEMORI_CORE_PATH . 'includes/comments.php';

register_activation_hook(__FILE__, 'animemori_core_activate');
register_deactivation_hook(__FILE__, 'animemori_core_deactivate');

add_action('plugins_loaded', function () {
  $ver = get_option('animemori_core_version');
  if ($ver !== ANIMEMORI_CORE_VERSION) {
    update_option('animemori_core_version', ANIMEMORI_CORE_VERSION, false);
    if (function_exists('animemori_core_add_rewrite_rules')) {
      animemori_core_add_rewrite_rules();
    }
    flush_rewrite_rules();
  }
  if (function_exists('animemori_core_register_routes')) {
    animemori_core_register_routes();
  }
  if (function_exists('animemori_core_register_shortcodes')) {
    animemori_core_register_shortcodes();
  }
  if (function_exists('animemori_core_register_admin')) {
    animemori_core_register_admin();
  }
  if (function_exists('animemori_register_rating_ajax')) {
    animemori_register_rating_ajax();
  }
  if (function_exists('animemori_register_comment_handlers')) {
    animemori_register_comment_handlers();
  }
  if (function_exists('animemori_chars_register_admin')) {
    animemori_chars_register_admin();
  }
  if (function_exists('animemori_chars_scheduler_boot')) {
    animemori_chars_scheduler_boot();
  }
  if (function_exists('animemori_scheduler_boot')) {
    animemori_scheduler_boot();
  }
});
