<?php
if (!defined('ABSPATH')) exit;

function animemori_chars_default_settings() {
  return [
    'endpoint' => 'https://graphql.anilist.co',
    'auth_token' => '',
    'per_page' => 50,
    'max_pages' => 2,
    'max_per_run' => 20,
    'sleep_us' => 200000,
    'auto_enabled' => 1,
  ];
}

function animemori_chars_get_settings() {
  $defaults = animemori_chars_default_settings();
  $saved = get_option('animemori_chars_settings');
  if (!is_array($saved)) return $defaults;
  return array_merge($defaults, $saved);
}

function animemori_chars_anilist_request($query, $variables = [], $settings = null) {
  $settings = $settings ?: animemori_chars_get_settings();
  $endpoint = trim($settings['endpoint'] ?? '');
  if (!$endpoint) return new WP_Error('missing_endpoint', 'Characters API endpoint missing');

  $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
  if (!empty($settings['auth_token'])) {
    $headers['Authorization'] = 'Bearer ' . trim($settings['auth_token']);
  }

  $body = wp_json_encode(['query' => $query, 'variables' => $variables]);
  $resp = wp_remote_post($endpoint, [
    'timeout' => 20,
    'headers' => $headers,
    'body' => $body,
  ]);
  if (is_wp_error($resp)) return $resp;

  $code = wp_remote_retrieve_response_code($resp);
  $raw = wp_remote_retrieve_body($resp);
  if ($code !== 200) {
    return new WP_Error('chars_http', 'Characters API request failed', ['status' => $code, 'body' => $raw]);
  }

  $json = json_decode($raw, true);
  if (!is_array($json)) {
    return new WP_Error('chars_parse', 'Invalid JSON from characters API', ['body' => $raw]);
  }
  if (!empty($json['errors'])) {
    return new WP_Error('chars_error', 'Characters API returned errors', ['errors' => $json['errors']]);
  }

  return $json['data'] ?? null;
}
