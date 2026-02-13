<?php
if (!defined('ABSPATH')) exit;

function animemori_now_utc() {
  return new DateTime('now', new DateTimeZone('UTC'));
}

function animemori_mysql_dt(DateTime $dt) {
  return $dt->format('Y-m-d H:i:s');
}

function animemori_sanitize_slug($text) {
  $slug = sanitize_title($text);
  if (!$slug) $slug = 'anime-' . wp_generate_password(8, false, false);
  return $slug;
}

function animemori_weekday_to_int($weekday) {
  // accepts: 'Mondays', 'Monday', 'Sundays', 'Saturday', etc.
  if (!$weekday) return null;
  $w = strtolower(trim($weekday));
  $w = preg_replace('/s$/', '', $w); // remove plural
  $map = [
    'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
    'friday' => 5, 'saturday' => 6, 'sunday' => 7
  ];
  return $map[$w] ?? null;
}

function animemori_parse_time_hhmm($timeStr) {
  // '17:00' -> '17:00:00'
  if (!$timeStr) return null;
  $t = trim($timeStr);
  if (!preg_match('/^\d{1,2}:\d{2}$/', $t)) return null;
  if (strlen($t) === 4) $t = '0' . $t;
  return $t . ':00';
}

function animemori_map_format_from_jikan($type) {
  $t = strtolower(trim((string)$type));
  if ($t === 'tv') return 'TV';
  if ($t === 'movie') return 'MOVIE';
  if ($t === 'ova') return 'OVA';
  if ($t === 'ona') return 'ONA';
  if ($t === 'special') return 'SPECIAL';
  return 'UNKNOWN';
}

function animemori_map_status_from_jikan($status) {
  $s = strtolower(trim((string)$status));
  // Status examples: "Currently Airing", "Finished Airing", "Not yet aired"
  if (str_contains($s, 'currently')) return 'AIRING';
  if (str_contains($s, 'finished')) return 'FINISHED';
  if (str_contains($s, 'not yet')) return 'UPCOMING';
  return 'UPCOMING';
}

function animemori_guess_precision($dateStr) {
  // API provides ISO datetime; precision usually DAY
  if (!$dateStr) return null;
  return 'DAY';
}

function animemori_dt_from_iso($iso, $tz = 'UTC') {
  if (!$iso) return null;
  try {
    return new DateTime($iso, new DateTimeZone($tz));
  } catch (Exception $e) {
    return null;
  }
}

function animemori_site_tz() {
  return wp_timezone();
}

function animemori_pick_title($english, $romaji, $fallback = 'Anime') {
  return $english ?: ($romaji ?: $fallback);
}

function animemori_truncate_text($text, $max = 155) {
  $text = wp_strip_all_tags((string)$text);
  $text = trim(preg_replace('/\s+/', ' ', $text));
  if ($max <= 0 || strlen($text) <= $max) return $text;
  return substr($text, 0, $max - 3) . '...';
}

function animemori_schedule_window($dayParam = null, $days = 7) {
  $tz = animemori_site_tz();
  $utc = new DateTimeZone('UTC');

  $days = intval($days);
  if ($days < 1) $days = 7;

  $startLocal = new DateTime('now', $tz);
  $startLocal->setTime(0, 0, 0);
  $endLocal = clone $startLocal;
  $endLocal->modify('+' . $days . ' day');

  if ($dayParam) {
    $map = [
      'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
      'friday' => 5, 'saturday' => 6, 'sunday' => 7
    ];
    $key = strtolower(preg_replace('/[^a-z]/', '', $dayParam));
    if (isset($map[$key])) {
      $todayN = intval($startLocal->format('N'));
      $delta = ($map[$key] - $todayN + 7) % 7;
      $startLocal->modify("+{$delta} day");
      $endLocal = clone $startLocal;
      $endLocal->modify('+1 day');
    }
  }

  $startUtc = clone $startLocal;
  $startUtc->setTimezone($utc);
  $endUtc = clone $endLocal;
  $endUtc->setTimezone($utc);

  return [
    'tz' => $tz,
    'utc' => $utc,
    'start_local' => $startLocal,
    'end_local' => $endLocal,
    'start_utc' => $startUtc,
    'end_utc' => $endUtc,
  ];
}

function animemori_upcoming_window($hours = 72) {
  $tz = animemori_site_tz();
  $utc = new DateTimeZone('UTC');
  $hours = intval($hours);
  if ($hours < 1) $hours = 72;

  $startUtc = new DateTime('now', $utc);
  $endUtc = clone $startUtc;
  $endUtc->modify('+' . $hours . ' hours');

  $startLocal = clone $startUtc;
  $startLocal->setTimezone($tz);
  $endLocal = clone $endUtc;
  $endLocal->setTimezone($tz);

  return [
    'tz' => $tz,
    'utc' => $utc,
    'start_local' => $startLocal,
    'end_local' => $endLocal,
    'start_utc' => $startUtc,
    'end_utc' => $endUtc,
  ];
}

function animemori_fetch_schedule_rows(DateTime $startUtc, DateTime $endUtc, $limit = 500, $status_filter = null) {
  global $wpdb;
  $limit = max(1, intval($limit));
  $status_sql = '';
  if (is_array($status_filter) && !empty($status_filter)) {
    $safe = array_map(function($s) { return "'" . esc_sql($s) . "'"; }, $status_filter);
    $status_sql = ' AND a.status IN (' . implode(',', $safe) . ')';
  }
  $sql = $wpdb->prepare(
    "SELECT e.*, a.slug as anime_slug, a.title_romaji, a.title_english, a.cover_image_url
     FROM {$wpdb->prefix}am_episode e
     JOIN {$wpdb->prefix}am_anime a ON a.id = e.anime_id
     WHERE e.air_datetime_utc >= %s AND e.air_datetime_utc < %s{$status_sql}
     ORDER BY e.air_datetime_utc ASC LIMIT {$limit}",
    $startUtc->format('Y-m-d H:i:s'),
    $endUtc->format('Y-m-d H:i:s')
  );
  return $wpdb->get_results($sql, ARRAY_A);
}

function animemori_fetch_anime_by_slug($slug) {
  global $wpdb;
  $slug = sanitize_title($slug);
  if (!$slug) return null;
  return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}am_anime WHERE slug=%s", $slug), ARRAY_A);
}
