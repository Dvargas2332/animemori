<?php
/**
 * Plugin Name: Animemori Characters
 * Description: Automatic character importer for Animemori using a public API endpoint.
 * Version: 0.1.1
 * Author: Animemori
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

define('ANIMEMORI_CHARS_VERSION', '0.1.1');
define('ANIMEMORI_CHARS_PATH', plugin_dir_path(__FILE__));

function animemori_chars_admin_notice_core_missing() {
  if (!current_user_can('manage_options')) return;
  echo '<div class="notice notice-error"><p>Animemori Characters requires Animemori Core to be active.</p></div>';
}

function animemori_chars_admin_notice_core_has_chars() {
  if (!current_user_can('manage_options')) return;
  echo '<div class="notice notice-warning"><p>Animemori Core already includes Characters. This plugin is not needed.</p></div>';
}

function animemori_chars_activate() {
  if (!defined('ANIMEMORI_CORE_VERSION')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die('Animemori Characters requires Animemori Core to be active.');
  }
  if (version_compare(ANIMEMORI_CORE_VERSION, '0.3.0', '>=')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die('Animemori Core already includes Characters. Deactivate this plugin.');
  }
  if (!wp_next_scheduled('animemori_chars_auto_import')) {
    wp_schedule_event(time() + 300, 'daily', 'animemori_chars_auto_import');
  }
}

function animemori_chars_deactivate() {
  $timestamp = wp_next_scheduled('animemori_chars_auto_import');
  if ($timestamp) {
    wp_unschedule_event($timestamp, 'animemori_chars_auto_import');
  }
}

register_activation_hook(__FILE__, 'animemori_chars_activate');
register_deactivation_hook(__FILE__, 'animemori_chars_deactivate');

if (!defined('ANIMEMORI_CORE_VERSION')) {
  add_action('admin_notices', 'animemori_chars_admin_notice_core_missing');
  return;
}

if (version_compare(ANIMEMORI_CORE_VERSION, '0.3.0', '>=')) {
  add_action('admin_notices', 'animemori_chars_admin_notice_core_has_chars');
  return;
}

require_once ANIMEMORI_CHARS_PATH . 'includes/characters-api.php';
require_once ANIMEMORI_CHARS_PATH . 'includes/characters-importer.php';
require_once ANIMEMORI_CHARS_PATH . 'includes/characters-admin.php';
require_once ANIMEMORI_CHARS_PATH . 'includes/characters-scheduler.php';

add_action('plugins_loaded', function () {
  if (function_exists('animemori_chars_register_admin')) {
    animemori_chars_register_admin();
  }
  if (function_exists('animemori_chars_scheduler_boot')) {
    animemori_chars_scheduler_boot();
  }
});
