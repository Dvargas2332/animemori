<?php
if (!defined('ABSPATH')) exit;

function animemori_chars_role_map($role) {
  $r = strtoupper(trim((string)$role));
  if ($r === 'SUPPORTING') return 'SUPPORT';
  if ($r === 'BACKGROUND') return 'BACKGROUND';
  return 'MAIN';
}

function animemori_chars_fetch_anime_row($anime_id) {
  global $wpdb;
  $anime_id = intval($anime_id);
  if ($anime_id <= 0) return null;
  return $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}am_anime WHERE id=%d LIMIT 1",
    $anime_id
  ), ARRAY_A);
}

function animemori_chars_import_by_mal_id($mal_id, $anime_id = null, $settings = null) {
  global $wpdb;
  $mal_id = intval($mal_id);
  if ($mal_id <= 0) return new WP_Error('bad_id', 'Invalid MAL ID');

  $settings = $settings ?: animemori_chars_get_settings();
  $per_page = max(1, intval($settings['per_page'] ?? 50));
  $max_pages = max(1, intval($settings['max_pages'] ?? 2));
  $sleep_us = intval($settings['sleep_us'] ?? 200000);

  if (!$anime_id) {
    $anime_id = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM {$wpdb->prefix}am_anime WHERE source='MAL' AND source_id=%d LIMIT 1",
      $mal_id
    ));
  }
  $anime_id = intval($anime_id);
  if ($anime_id <= 0) return new WP_Error('no_anime', 'Anime not found in database');

  $query = <<<GQL
query ($idMal: Int, $page: Int, $perPage: Int) {
  Media(idMal: $idMal, type: ANIME) {
    id
    characters(page: $page, perPage: $perPage, sort: [ROLE, RELEVANCE, ID]) {
      pageInfo { hasNextPage }
      edges {
        role
        node {
          id
          name { full native }
          image { large }
          description
        }
      }
    }
  }
}
GQL;

  $imported = 0;
  $linked = 0;
  $page = 1;
  $has_next = true;

  while ($has_next && $page <= $max_pages) {
    $data = animemori_chars_anilist_request($query, [
      'idMal' => $mal_id,
      'page' => $page,
      'perPage' => $per_page,
    ], $settings);
    if (is_wp_error($data)) return $data;

    $media = $data['Media'] ?? null;
    if (!$media || empty($media['characters'])) break;

    $edges = $media['characters']['edges'] ?? [];
    $has_next = !empty($media['characters']['pageInfo']['hasNextPage']);

    foreach ($edges as $edge) {
      if (!is_array($edge)) continue;
      $node = $edge['node'] ?? null;
      if (!is_array($node)) continue;

      $char_id = intval($node['id'] ?? 0);
      if ($char_id <= 0) continue;

      $name_full = $node['name']['full'] ?? null;
      $name_native = $node['name']['native'] ?? null;
      $image = $node['image']['large'] ?? null;
      $desc = $node['description'] ?? null;
      $desc_short = $desc ? wp_strip_all_tags($desc) : null;
      if ($desc_short && strlen($desc_short) > 800) $desc_short = substr($desc_short, 0, 800) . '...';

      $slug_base = sanitize_title($name_full ?: ('character-' . $char_id));
      if (!$slug_base) $slug_base = 'character-' . $char_id;

      $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}am_character WHERE source='ANILIST' AND source_id=%d",
        $char_id
      ));

      $slug = $slug_base;
      $slug_conflict = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}am_character WHERE slug=%s AND source='ANILIST' AND source_id<>%d",
        $slug,
        $char_id
      ));
      if ($slug_conflict) $slug = $slug . '-' . $char_id;

      $payload = wp_json_encode($node);

      $row = [
        'source' => 'ANILIST',
        'source_id' => $char_id,
        'slug' => $slug,
        'name' => $name_full,
        'name_native' => $name_native,
        'description_short' => $desc_short,
        'image_url' => $image,
        'source_payload_json' => $payload,
        'updated_at' => current_time('mysql'),
      ];

      if ($existing) {
        unset($row['slug']);
        $wpdb->update($wpdb->prefix . 'am_character', $row, ['id' => $existing]);
      } else {
        $row['created_at'] = current_time('mysql');
        $wpdb->insert($wpdb->prefix . 'am_character', $row);
        $existing = $wpdb->insert_id;
      }

      if ($existing) $imported++;

      $role = animemori_chars_role_map($edge['role'] ?? '');
      $link_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT anime_id FROM {$wpdb->prefix}am_anime_character WHERE anime_id=%d AND character_id=%d",
        $anime_id,
        $existing
      ));
      if ($link_exists) {
        $wpdb->update($wpdb->prefix . 'am_anime_character', ['role' => $role], ['anime_id' => $anime_id, 'character_id' => $existing]);
      } else {
        $wpdb->insert($wpdb->prefix . 'am_anime_character', [
          'anime_id' => $anime_id,
          'character_id' => $existing,
          'role' => $role,
          'created_at' => current_time('mysql'),
        ]);
      }
      $linked++;
    }

    $page++;
    if ($sleep_us > 0) usleep($sleep_us);
  }

  return [
    'imported' => $imported,
    'linked' => $linked,
    'pages' => $page - 1,
  ];
}

function animemori_chars_auto_import_job() {
  global $wpdb;
  $settings = animemori_chars_get_settings();
  if (empty($settings['auto_enabled'])) return ['skipped' => 'disabled'];

  $max_per_run = max(1, intval($settings['max_per_run'] ?? 20));
  $rows = $wpdb->get_results($wpdb->prepare(
    "SELECT a.id, a.source_id
     FROM {$wpdb->prefix}am_anime a
     LEFT JOIN {$wpdb->prefix}am_anime_character ac ON ac.anime_id = a.id
     WHERE a.source='MAL'
     GROUP BY a.id
     HAVING COUNT(ac.character_id) = 0
     ORDER BY a.updated_at DESC
     LIMIT %d",
    $max_per_run
  ), ARRAY_A);

  $report = [
    'ran_at' => current_time('mysql'),
    'imported' => 0,
    'linked' => 0,
    'errors' => [],
  ];

  foreach ($rows as $r) {
    $anime_id = intval($r['id']);
    $mal_id = intval($r['source_id']);
    if ($mal_id <= 0) continue;
    $res = animemori_chars_import_by_mal_id($mal_id, $anime_id, $settings);
    if (is_wp_error($res)) {
      $report['errors'][] = ['anime_id' => $anime_id, 'mal_id' => $mal_id, 'error' => $res->get_error_message()];
    } else {
      $report['imported'] += intval($res['imported'] ?? 0);
      $report['linked'] += intval($res['linked'] ?? 0);
    }
  }

  update_option('animemori_chars_last_run', $report, false);
  return $report;
}

