<?php
if (!defined('ABSPATH')) exit;

function animemori_core_tables() {
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $prefix = $wpdb->prefix;

  $tables = [];

  $tables['am_anime'] = "CREATE TABLE {$prefix}am_anime (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source VARCHAR(32) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    slug VARCHAR(190) NOT NULL,
    title_romaji VARCHAR(255) NULL,
    title_english VARCHAR(255) NULL,
    title_native VARCHAR(255) NULL,
    synopsis_short TEXT NULL,
    start_date DATE NULL,
    start_date_precision ENUM('YEAR','MONTH','DAY') NOT NULL DEFAULT 'DAY',
    year SMALLINT NULL,
    status ENUM('UPCOMING','AIRING','FINISHED','HIATUS','CANCELLED') NOT NULL DEFAULT 'UPCOMING',
    format ENUM('TV','MOVIE','OVA','ONA','SPECIAL','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
    total_episodes SMALLINT NULL,
    episode_duration_min SMALLINT NULL,
    cover_image_url TEXT NULL,
    banner_image_url TEXT NULL,
    rating VARCHAR(128) NULL,
    source_payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_source (source, source_id),
    UNIQUE KEY uq_slug (slug),
    KEY idx_status (status),
    KEY idx_year (year)
  ) $charset_collate;";

  $tables['am_anime_broadcast'] = "CREATE TABLE {$prefix}am_anime_broadcast (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    anime_id BIGINT UNSIGNED NOT NULL,
    timezone VARCHAR(128) NOT NULL DEFAULT 'Asia/Tokyo',
    weekday_jst TINYINT NULL,
    time_jst TIME NULL,
    first_air_datetime_jst DATETIME NULL,
    broadcast_source VARCHAR(64) NOT NULL DEFAULT 'import',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_anime (anime_id),
    KEY idx_weekday (weekday_jst),
    CONSTRAINT fk_broadcast_anime FOREIGN KEY (anime_id) REFERENCES {$prefix}am_anime(id) ON DELETE CASCADE
  ) $charset_collate;";

  $tables['am_season'] = "CREATE TABLE {$prefix}am_season (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    anime_id BIGINT UNSIGNED NOT NULL,
    season_number SMALLINT NOT NULL DEFAULT 1,
    title VARCHAR(255) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_anime_season (anime_id, season_number),
    CONSTRAINT fk_season_anime FOREIGN KEY (anime_id) REFERENCES {$prefix}am_anime(id) ON DELETE CASCADE
  ) $charset_collate;";

  $tables['am_episode'] = "CREATE TABLE {$prefix}am_episode (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    anime_id BIGINT UNSIGNED NOT NULL,
    season_id BIGINT UNSIGNED NULL,
    episode_number SMALLINT NOT NULL,
    title VARCHAR(255) NULL,
    air_datetime_jst DATETIME NULL,
    air_datetime_utc DATETIME NULL,
    status ENUM('SCHEDULED','AIRED','DELAYED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
    is_estimated TINYINT(1) NOT NULL DEFAULT 1,
    delay_reason VARCHAR(255) NULL,
    delay_shift_days SMALLINT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_anime_ep (anime_id, episode_number),
    KEY idx_air_utc (air_datetime_utc),
    KEY idx_status (status),
    CONSTRAINT fk_episode_anime FOREIGN KEY (anime_id) REFERENCES {$prefix}am_anime(id) ON DELETE CASCADE
  ) $charset_collate;";

  $tables['am_character'] = "CREATE TABLE {$prefix}am_character (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source VARCHAR(32) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    slug VARCHAR(190) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_native VARCHAR(255) NULL,
    description_short TEXT NULL,
    image_url TEXT NULL,
    source_payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_source (source, source_id),
    UNIQUE KEY uq_slug (slug),
    KEY idx_name (name)
  ) $charset_collate;";

  $tables['am_anime_character'] = "CREATE TABLE {$prefix}am_anime_character (
    anime_id BIGINT UNSIGNED NOT NULL,
    character_id BIGINT UNSIGNED NOT NULL,
    role ENUM('MAIN','SUPPORT','BACKGROUND') NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (anime_id, character_id),
    KEY idx_character (character_id),
    CONSTRAINT fk_ac_anime FOREIGN KEY (anime_id) REFERENCES {$prefix}am_anime(id) ON DELETE CASCADE,
    CONSTRAINT fk_ac_character FOREIGN KEY (character_id) REFERENCES {$prefix}am_character(id) ON DELETE CASCADE
  ) $charset_collate;";

  $tables['am_anime_rating'] = "CREATE TABLE {$prefix}am_anime_rating (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    anime_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_hash CHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_anime (anime_id),
    KEY idx_user (user_id),
    CONSTRAINT fk_rating_anime FOREIGN KEY (anime_id) REFERENCES {$prefix}am_anime(id) ON DELETE CASCADE
  ) $charset_collate;";

  $tables['am_anime_comment'] = "CREATE TABLE {$prefix}am_anime_comment (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    anime_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    author_name VARCHAR(100) NULL,
    author_email VARCHAR(190) NULL,
    content TEXT NOT NULL,
    status ENUM('approved','pending','spam') NOT NULL DEFAULT 'approved',
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_anime (anime_id),
    KEY idx_status (status),
    CONSTRAINT fk_comment_anime FOREIGN KEY (anime_id) REFERENCES {$prefix}am_anime(id) ON DELETE CASCADE
  ) $charset_collate;";

  return $tables;
}

function animemori_core_activate() {
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  $tables = animemori_core_tables();
  foreach ($tables as $sql) {
    dbDelta($sql);
  }
  // rewrite rules
  animemori_core_add_rewrite_rules();
  flush_rewrite_rules();

  // schedule cron
  if (!wp_next_scheduled('animemori_daily_refresh')) {
    wp_schedule_event(time() + 60, 'daily', 'animemori_daily_refresh');
  }
  if (!wp_next_scheduled('animemori_auto_import')) {
    wp_schedule_event(time() + 120, 'daily', 'animemori_auto_import');
  }
  if (!wp_next_scheduled('animemori_chars_auto_import')) {
    wp_schedule_event(time() + 180, 'daily', 'animemori_chars_auto_import');
  }
}

function animemori_core_deactivate() {
  flush_rewrite_rules();
  $timestamp = wp_next_scheduled('animemori_daily_refresh');
  if ($timestamp) {
    wp_unschedule_event($timestamp, 'animemori_daily_refresh');
  }
  $timestamp2 = wp_next_scheduled('animemori_auto_import');
  if ($timestamp2) {
    wp_unschedule_event($timestamp2, 'animemori_auto_import');
  }
  $timestamp3 = wp_next_scheduled('animemori_chars_auto_import');
  if ($timestamp3) {
    wp_unschedule_event($timestamp3, 'animemori_chars_auto_import');
  }
}
