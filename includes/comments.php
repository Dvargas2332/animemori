<?php
if (!defined('ABSPATH')) exit;

function animemori_comment_ip_hash() {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (!$ip) return null;
  return hash('sha256', $ip . NONCE_SALT);
}

function animemori_get_comments($anime_id, $limit = 50) {
  global $wpdb;
  $anime_id = intval($anime_id);
  if ($anime_id <= 0) return [];
  $limit = max(1, intval($limit));
  $table = $wpdb->prefix . 'am_anime_comment';
  $rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT * FROM $table WHERE anime_id=%d AND status='approved' ORDER BY created_at DESC LIMIT {$limit}",
      $anime_id
    ),
    ARRAY_A
  );
  return $rows ?: [];
}

function animemori_insert_comment($anime_id, $author_name, $author_email, $content, $anonymous = false) {
  global $wpdb;
  $anime_id = intval($anime_id);
  if ($anime_id <= 0) return new WP_Error('bad_anime', 'Invalid anime');

  $content = trim(wp_strip_all_tags((string)$content));
  if ($content === '') return new WP_Error('bad_content', 'Empty comment');

  $author_name = trim((string)$author_name);
  $author_email = trim((string)$author_email);

  if ($anonymous || $author_name === '') {
    $author_name = 'Anonymous';
    $author_email = '';
  }

  $status = apply_filters('animemori_comment_status', 'approved');
  $status = in_array($status, ['approved','pending','spam'], true) ? $status : 'approved';

  $table = $wpdb->prefix . 'am_anime_comment';
  $row = [
    'anime_id' => $anime_id,
    'user_id' => get_current_user_id() ?: null,
    'author_name' => $author_name,
    'author_email' => $author_email,
    'content' => $content,
    'status' => $status,
    'ip_hash' => animemori_comment_ip_hash(),
    'created_at' => current_time('mysql'),
  ];

  $wpdb->insert($table, $row);
  return $wpdb->insert_id ? intval($wpdb->insert_id) : new WP_Error('insert_failed', 'Insert failed');
}

function animemori_handle_comment_submit() {
  if (!isset($_POST['anime_id'])) wp_die('Invalid request');
  check_admin_referer('animemori_comment');

  $anime_id = intval($_POST['anime_id']);
  $author_name = sanitize_text_field($_POST['author_name'] ?? '');
  $author_email = sanitize_email($_POST['author_email'] ?? '');
  $content = wp_unslash($_POST['content'] ?? '');
  $anonymous = !empty($_POST['anonymous']);

  $res = animemori_insert_comment($anime_id, $author_name, $author_email, $content, $anonymous);
  $redirect = esc_url_raw($_POST['redirect'] ?? home_url('/'));

  if (is_wp_error($res)) {
    wp_redirect(add_query_arg(['am_comment_err' => $res->get_error_message()], $redirect));
  } else {
    wp_redirect(add_query_arg(['am_comment_ok' => '1'], $redirect));
  }
  exit;
}

function animemori_register_comment_handlers() {
  add_action('admin_post_animemori_anime_comment', 'animemori_handle_comment_submit');
  add_action('admin_post_nopriv_animemori_anime_comment', 'animemori_handle_comment_submit');
}
