<?php
if (!defined('ABSPATH')) exit;

function animemori_rating_ip_hash() {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!$ip) return null;
  return hash('sha256', $ip . NONCE_SALT);
}

function animemori_get_rating_stats($anime_id) {
  $anime_id = intval($anime_id);
  if ($anime_id <= 0) return ['avg' => 0, 'count' => 0];

  $cache_key = 'animemori_rating_' . $anime_id;
  $cached = get_transient($cache_key);
  if (is_array($cached)) return $cached;

  global $wpdb;
  $row = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM {$wpdb->prefix}am_anime_rating WHERE anime_id=%d",
      $anime_id
    ),
    ARRAY_A
  );

  $avg = ($row && $row['avg_rating'] !== null) ? round(floatval($row['avg_rating']), 2) : 0;
  $cnt = ($row && $row['cnt'] !== null) ? intval($row['cnt']) : 0;

  $data = ['avg' => $avg, 'count' => $cnt];
  set_transient($cache_key, $data, HOUR_IN_SECONDS);
  return $data;
}

function animemori_rate_anime($anime_id, $rating) {
  global $wpdb;
  $anime_id = intval($anime_id);
  $rating = intval($rating);
  if ($anime_id <= 0) return new WP_Error('bad_anime', 'Invalid anime');
  if ($rating < 1 || $rating > 10) return new WP_Error('bad_rating', 'Invalid rating');

  $table = $wpdb->prefix . 'am_anime_rating';
  $user_id = get_current_user_id();
  $ip_hash = animemori_rating_ip_hash();
  $now = current_time('mysql');

  $existing_id = null;
  if ($user_id) {
    $existing_id = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $table WHERE anime_id=%d AND user_id=%d LIMIT 1",
      $anime_id,
      $user_id
    ));
  } elseif ($ip_hash) {
    $existing_id = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $table WHERE anime_id=%d AND ip_hash=%s LIMIT 1",
      $anime_id,
      $ip_hash
    ));
  }

  $row = [
    'anime_id' => $anime_id,
    'rating' => $rating,
    'user_id' => $user_id ? $user_id : null,
    'ip_hash' => $ip_hash,
    'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    'created_at' => $now,
  ];

  if ($existing_id) {
    $wpdb->update($table, $row, ['id' => $existing_id]);
  } else {
    $wpdb->insert($table, $row);
  }

  delete_transient('animemori_rating_' . $anime_id);
  return animemori_get_rating_stats($anime_id);
}

function animemori_ajax_rate_anime() {
  check_ajax_referer('animemori_rate', 'nonce');
  $anime_id = isset($_POST['anime_id']) ? intval($_POST['anime_id']) : 0;
  $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

  $res = animemori_rate_anime($anime_id, $rating);
  if (is_wp_error($res)) {
    wp_send_json_error(['message' => $res->get_error_message()]);
  }

  wp_send_json_success($res);
}

function animemori_register_rating_ajax() {
  add_action('wp_ajax_animemori_rate', 'animemori_ajax_rate_anime');
  add_action('wp_ajax_nopriv_animemori_rate', 'animemori_ajax_rate_anime');
}
