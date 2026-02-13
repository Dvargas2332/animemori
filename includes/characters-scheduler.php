<?php
if (!defined('ABSPATH')) exit;

function animemori_chars_scheduler_boot() {
  add_action('animemori_chars_auto_import', 'animemori_chars_auto_import_job');
  if (!wp_next_scheduled('animemori_chars_auto_import')) {
    wp_schedule_event(time() + 300, 'daily', 'animemori_chars_auto_import');
  }
}

