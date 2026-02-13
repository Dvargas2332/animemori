<?php

if (!defined('ABSPATH')) exit;



function animemori_scheduler_boot() {

  add_action('animemori_daily_refresh', 'animemori_daily_refresh_job');

  add_action('animemori_auto_import', 'animemori_auto_import_job');



  if (!wp_next_scheduled('animemori_daily_refresh')) {

    wp_schedule_event(time() + 60, 'daily', 'animemori_daily_refresh');

  }

  if (!wp_next_scheduled('animemori_auto_import')) {

    wp_schedule_event(time() + 120, 'daily', 'animemori_auto_import');

  }

}



function animemori_daily_refresh_job() {

  global $wpdb;

  $anime_table = $wpdb->prefix . 'am_anime';

  $rows = $wpdb->get_results("SELECT id, slug, title_english, title_romaji FROM $anime_table WHERE status IN ('AIRING','UPCOMING') ORDER BY updated_at DESC LIMIT 200", ARRAY_A);

  $report = [

    'ran_at' => current_time('mysql'),

    'errors' => [],

    'skipped' => [],

  ];



  foreach ($rows as $r) {

    $anime_id = intval($r['id']);

    $res = animemori_generate_episodes_for_anime($anime_id, 8); // keep 8-week buffer

    if (is_wp_error($res)) {

      $report['errors'][] = [

        'anime_id' => $anime_id,

        'slug' => $r['slug'] ?? null,

        'title' => $r['title_english'] ?: ($r['title_romaji'] ?: null),

        'reason' => $res->get_error_message(),

      ];

    } elseif (is_array($res) && !empty($res['skipped'])) {

      $report['skipped'][] = [

        'anime_id' => $anime_id,

        'slug' => $r['slug'] ?? null,

        'title' => $r['title_english'] ?: ($r['title_romaji'] ?: null),

        'reason' => $res['skipped'],

      ];

    }

  }



  update_option('animemori_last_refresh', $report, false);

}



function animemori_generate_episodes_for_anime($anime_id, $buffer_weeks = 8) {

  global $wpdb;

  $anime_id = intval($anime_id);

  if ($anime_id <= 0) return new WP_Error('bad_anime', 'Invalid anime_id');



  $anime = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}am_anime WHERE id=%d", $anime_id), ARRAY_A);

  if (!$anime) return new WP_Error('not_found', 'Anime not found');



  if ($anime['format'] === 'MOVIE') return ['skipped' => 'movie'];



  $broadcast = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}am_anime_broadcast WHERE anime_id=%d", $anime_id), ARRAY_A);

  $broadcast_active = ($broadcast && intval($broadcast['is_active']) === 1);



  $season_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}am_season WHERE anime_id=%d AND season_number=1", $anime_id));

  $season_id = $season_id ? intval($season_id) : null;



  $weekday = ($broadcast_active && $broadcast['weekday_jst'] !== null) ? intval($broadcast['weekday_jst']) : null;

  $time_jst = ($broadcast_active && !empty($broadcast['time_jst'])) ? $broadcast['time_jst'] : null;

  $first_air_jst = ($broadcast_active && !empty($broadcast['first_air_datetime_jst'])) ? $broadcast['first_air_datetime_jst'] : null;



  $start_date = $anime['start_date'] ?: null;

  $total_eps = $anime['total_episodes'] ? intval($anime['total_episodes']) : null;



  // Determine episode 1 datetime in JST

  $jst = new DateTimeZone('Asia/Tokyo');



  $ep1 = null;

  if ($first_air_jst) {

    try { $ep1 = new DateTime($first_air_jst, $jst); } catch (Exception $e) { $ep1 = null; }

  }



  if (!$ep1) {

    if (!$start_date) return new WP_Error('no_start', 'No start_date to estimate episodes');

    // If we have weekday+time, find first occurrence on/after start_date matching weekday

    $base = new DateTime($start_date . ' 00:00:00', $jst);

    $hhmmss = $time_jst ?: '00:00:00';

    $base->setTime(intval(substr($hhmmss,0,2)), intval(substr($hhmmss,3,2)), 0);



    if ($weekday) {

      // PHP: N = 1 (Mon) .. 7 (Sun)

      $currentN = intval($base->format('N'));

      $delta = ($weekday - $currentN + 7) % 7;

      $base->modify("+{$delta} day");

    }

    $ep1 = $base;

  }



  // Decide how many episodes to ensure exist

  $nowUtc = animemori_now_utc();

  $endUtc = clone $nowUtc;

  $endUtc->modify('+' . intval($buffer_weeks) . ' weeks');



  // If total episodes is known and reasonable, generate all.

  // Otherwise generate enough episodes to cover the buffer.

  $max_to_generate = $total_eps ?: 9999;



  // How many episodes needed to reach endUtc?

  $ep1Utc = clone $ep1;

  $ep1Utc->setTimezone(new DateTimeZone('UTC'));

  $diffSeconds = $endUtc->getTimestamp() - $ep1Utc->getTimestamp();

  if ($diffSeconds < 0) $diffSeconds = 0;

  $weeks = intdiv($diffSeconds, WEEK_IN_SECONDS);

  $needed_by_buffer = max(1, $weeks + 1);



  $to_generate = min($max_to_generate, $needed_by_buffer);



  $etable = $wpdb->prefix . 'am_episode';

  $now = current_time('mysql');



  for ($ep=1; $ep<=$to_generate; $ep++) {

    $dtJst = clone $ep1;

    if ($ep > 1) $dtJst->modify('+' . ($ep-1) . ' week');



    $dtUtc = clone $dtJst;

    $dtUtc->setTimezone(new DateTimeZone('UTC'));



    $air_jst = $dtJst->format('Y-m-d H:i:s');

    $air_utc = $dtUtc->format('Y-m-d H:i:s');



    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $etable WHERE anime_id=%d AND episode_number=%d", $anime_id, $ep));



    $row = [

      'anime_id' => $anime_id,

      'season_id' => $season_id,

      'episode_number' => $ep,

      'title' => null,

      'air_datetime_jst' => $air_jst,

      'air_datetime_utc' => $air_utc,

      'status' => ($dtUtc < $nowUtc ? 'AIRED' : 'SCHEDULED'),

      'is_estimated' => 1,

      'updated_at' => $now,

    ];



    if ($exists) {

      // Only update if still estimated and not manually edited later (future: add lock flag)

      $wpdb->update($etable, $row, ['id' => $exists]);

    } else {

      $row['created_at'] = $now;

      $wpdb->insert($etable, $row);

    }

  }



  return ['generated' => $to_generate];

}



function animemori_auto_import_job() {

  $settings = apply_filters('animemori_auto_import_settings', [

    'max_pages' => 1,

    'max_per_category' => 20,

    'sleep_us' => 350000,

    'skip_recent_days' => 7,

    'log_limit' => 200,

    'sfw' => true,

  ]);



  $max_pages = max(1, intval($settings['max_pages'] ?? 1));

  $max_per_category = max(1, intval($settings['max_per_category'] ?? 20));

  $sleep_us = intval($settings['sleep_us'] ?? 350000);

  $skip_recent_days = intval($settings['skip_recent_days'] ?? 7);

  $log_limit = intval($settings['log_limit'] ?? 200);



  $params_base = [];

  if (!empty($settings['sfw'])) {

    $params_base['sfw'] = true;

  }



  $results = [];

  $generate_for = function($res) {
    if (!is_array($res)) return;
    if (empty($res['imported_ids']) || !is_array($res['imported_ids'])) return;
    foreach ($res['imported_ids'] as $anime_id) {
      if (function_exists('animemori_generate_episodes_for_anime')) {
        animemori_generate_episodes_for_anime(intval($anime_id), 8);
      }
    }
  };



  // In season

  $now_items = animemori_jikan_fetch_list('seasons/now', $params_base, $max_pages, $sleep_us);

  if (!is_wp_error($now_items)) {

    $results['in_season'] = animemori_import_anime_list($now_items, $max_per_category, [

      'sleep_us' => $sleep_us,

      'skip_recent_days' => $skip_recent_days,

      'log_limit' => $log_limit,

    ]);
    $generate_for($results['in_season']);

  } else {

    $results['in_season'] = ['error' => $now_items->get_error_message()];

  }



  // Upcoming

  $upcoming_items = animemori_jikan_fetch_list('seasons/upcoming', $params_base, $max_pages, $sleep_us);

  if (!is_wp_error($upcoming_items)) {

    $results['upcoming'] = animemori_import_anime_list($upcoming_items, $max_per_category, [

      'sleep_us' => $sleep_us,

      'skip_recent_days' => $skip_recent_days,

      'log_limit' => $log_limit,

    ]);
    $generate_for($results['upcoming']);

  } else {

    $results['upcoming'] = ['error' => $upcoming_items->get_error_message()];

  }



  // Finished

  $complete_params = array_merge($params_base, [

    'status' => 'complete',

    'order_by' => 'start_date',

    'sort' => 'desc',

  ]);

  $complete_items = animemori_jikan_fetch_list('anime', $complete_params, $max_pages, $sleep_us);

  if (!is_wp_error($complete_items)) {

    $results['finished'] = animemori_import_anime_list($complete_items, $max_per_category, [

      'sleep_us' => $sleep_us,

      'skip_recent_days' => $skip_recent_days,

      'log_limit' => $log_limit,

    ]);
    $generate_for($results['finished']);

  } else {

    $results['finished'] = ['error' => $complete_items->get_error_message()];

  }



  // Cancelled (intenta status=cancelled, si no, filtra desde complete)

  $cancelled_items = animemori_jikan_fetch_list('anime', array_merge($params_base, [

    'status' => 'cancelled',

    'order_by' => 'start_date',

    'sort' => 'desc',

  ]), $max_pages, $sleep_us);

  if (is_wp_error($cancelled_items)) {

    $cancelled_items = [];

  }

  if (empty($cancelled_items) && !is_wp_error($complete_items)) {

    $cancelled_items = array_filter($complete_items, function($it) {

      $s = strtolower($it['status'] ?? '');

      return $s && str_contains($s, 'cancel');

    });

  }



  if (!empty($cancelled_items)) {

    $results['cancelled'] = animemori_import_anime_list($cancelled_items, $max_per_category, [

      'sleep_us' => $sleep_us,

      'skip_recent_days' => $skip_recent_days,

      'log_limit' => $log_limit,

    ]);
    $generate_for($results['cancelled']);

  } else {

    $results['cancelled'] = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

  }



  update_option('animemori_last_auto_import', [

    'ran_at' => current_time('mysql'),

    'results' => $results,

  ], false);



  return $results;

}

