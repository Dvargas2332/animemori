<?php

if (!defined('ABSPATH')) exit;



function animemori_jikan_fetch_anime_full($mal_id) {

 $mal_id = intval($mal_id);

 if ($mal_id <= 0) return new WP_Error('bad_id', 'Invalid MAL ID');



 $url = "https://api.jikan.moe/v4/anime/{$mal_id}/full";

 $resp = wp_remote_get($url, [

 'timeout' => 20,

 'headers' => [

 'Accept' => 'application/json'

 ],

 ]);

 if (is_wp_error($resp)) return $resp;



 $code = wp_remote_retrieve_response_code($resp);

 $body = wp_remote_retrieve_body($resp);



 if ($code !== 200) {

 return new WP_Error('jikan_http', 'Jikan request failed', ['status' => $code, 'body' => $body]);

 }



 $json = json_decode($body, true);

 if (!is_array($json) || !isset($json['data'])) {

 return new WP_Error('jikan_parse', 'Invalid JSON from Jikan', ['body' => $body]);

 }



 return $json['data'];

}



function animemori_jikan_request($path, $params = []) {

 $path = ltrim($path, '/');

 $url = 'https://api.jikan.moe/v4/' . $path;

 if (!empty($params)) {

 $url = add_query_arg($params, $url);

 }



 $resp = wp_remote_get($url, [

 'timeout' => 20,

 'headers' => [

 'Accept' => 'application/json'

 ],

 ]);

 if (is_wp_error($resp)) return $resp;



 $code = wp_remote_retrieve_response_code($resp);

 $body = wp_remote_retrieve_body($resp);



 if ($code !== 200) {

 return new WP_Error('jikan_http', 'Jikan request failed', ['status' => $code, 'body' => $body]);

 }



 $json = json_decode($body, true);

 if (!is_array($json) || !isset($json['data'])) {

 return new WP_Error('jikan_parse', 'Invalid JSON from Jikan', ['body' => $body]);

 }



 return $json;

}



function animemori_jikan_fetch_list($path, $params = [], $max_pages = 1, $sleep_us = 350000) {

 $items = [];

 $page = 1;

 $max_pages = max(1, intval($max_pages));



 while ($page <= $max_pages) {

 $p = $params;

 $p['page'] = $page;

 $res = animemori_jikan_request($path, $p);

 if (is_wp_error($res)) return $res;



 $data = $res['data'] ?? [];

 if (!is_array($data)) {

 return new WP_Error('jikan_parse', 'Invalid list data from Jikan');

 }

 $items = array_merge($items, $data);



 $has_next = $res['pagination']['has_next_page'] ?? false;

 if (!$has_next) break;

 $page++;

 if ($sleep_us > 0) usleep($sleep_us);

 }



 return $items;

}



function animemori_anime_recently_updated($mal_id, $days = 7) {

 global $wpdb;

 $days = intval($days);

 if ($days <= 0) return false;



 $cutoff_ts = current_time('timestamp') - ($days * DAY_IN_SECONDS);

 $cutoff = date('Y-m-d H:i:s', $cutoff_ts);

 $table = $wpdb->prefix . 'am_anime';

 $exists = $wpdb->get_var($wpdb->prepare(

 "SELECT id FROM $table WHERE source='MAL' AND source_id=%d AND updated_at >= %s LIMIT 1",

 intval($mal_id),

 $cutoff

 ));

 return !empty($exists);

}



function animemori_import_anime_list($items, $max_imports = 25, $opts = []) {

 $max_imports = max(1, intval($max_imports));

 $sleep_us = isset($opts['sleep_us']) ? intval($opts['sleep_us']) : 350000;

 $skip_days = isset($opts['skip_recent_days']) ? intval($opts['skip_recent_days']) : 7;

 $log_limit = isset($opts['log_limit']) ? intval($opts['log_limit']) : 200;

 $log_imported = !empty($opts['log_imported']);



 $imported = 0;
 $imported_ids = [];

 $errors = 0;

 $skipped = 0;

 $log = [];

 $cut = [];

 $cut_total = 0;



 $idx = 0;

 foreach ($items as $item) {

 if ($imported >= $max_imports) {

 $remaining = array_slice($items, $idx);

 $cut_total = count($remaining);

 if ($cut_total > 0) {

 foreach ($remaining as $r) {

 if ($log_limit > 0 && count($cut) >= $log_limit) break;

 if (!is_array($r)) continue;

 $mid = $r['mal_id'] ?? null;

 $title = $r['title'] ?? ($r['title_english'] ?? ($r['title_japanese'] ?? null));

 $cut[] = [

 'mal_id' => $mid ? intval($mid) : null,

 'title' => $title,

 ];

 }

 }

 if ($log_limit > 0 && count($log) < $log_limit) {

 $log[] = [

 'reason' => 'limit_reached',

 'cut_total' => $cut_total,

 ];

 }

 break;

 }



 if (!is_array($item)) {

 if ($log_limit > 0 && count($log) < $log_limit) {

 $log[] = ['reason' => 'invalid_item'];

 }

 $idx++;

 continue;

 }



 $mal_id = $item['mal_id'] ?? null;

 if (!$mal_id) {

 if ($log_limit > 0 && count($log) < $log_limit) {

 $log[] = ['reason' => 'missing_mal_id'];

 }

 $idx++;

 continue;

 }



 if ($skip_days > 0 && animemori_anime_recently_updated($mal_id, $skip_days)) {

 $skipped++;

 if ($log_limit > 0 && count($log) < $log_limit) {

 $log[] = ['mal_id' => intval($mal_id), 'reason' => 'recently_updated'];

 }

 $idx++;

 continue;

 }



 $res = animemori_import_anime_from_mal($mal_id);

 if (is_wp_error($res)) {

 $errors++;

 if ($log_limit > 0 && count($log) < $log_limit) {

 $log[] = [

 'mal_id' => intval($mal_id),

 'reason' => 'import_error',

 'error' => $res->get_error_message(),

 ];

 }

 $idx++;

 continue;

 }



 $imported++;
 if (!empty($res['anime_id'])) {
 $imported_ids[] = intval($res['anime_id']);
 }

 if ($log_imported && $log_limit > 0 && count($log) < $log_limit) {

 $log[] = ['mal_id' => intval($mal_id), 'reason' => 'imported'];

 }

 if ($sleep_us > 0) usleep($sleep_us);

 $idx++;

 }



 return [

 'imported' => $imported,
 'imported_ids' => $imported_ids,
 'skipped' => $skipped,

 'errors' => $errors,

 'cut_total' => $cut_total,

 'cut' => $cut,

 'log' => $log,

 ];

}



function animemori_import_anime_from_mal($mal_id) {

 global $wpdb;

 $data = animemori_jikan_fetch_anime_full($mal_id);

 if (is_wp_error($data)) return $data;



 $now = current_time('mysql');



 $titles = $data['titles'] ?? [];

 $title_romaji = $data['title'] ?? null;

 $title_english = $data['title_english'] ?? null;

 $title_native = $data['title_japanese'] ?? null;



 // Better title extraction from titles[] if present

 if (is_array($titles)) {

 foreach ($titles as $t) {

 if (!is_array($t)) continue;

 $type = strtolower($t['type'] ?? '');

 $val = $t['title'] ?? null;

 if ($type === 'romaji' && $val) $title_romaji = $val;

 if ($type === 'english' && $val) $title_english = $val;

 if (($type === 'japanese' || $type === 'native') && $val) $title_native = $val;

 }

 }



 $synopsis = $data['synopsis'] ?? null;

 $synopsis_short = $synopsis ? wp_strip_all_tags($synopsis) : null;

 if ($synopsis_short && strlen($synopsis_short) > 800) {

 $synopsis_short = mb_substr($synopsis_short, 0, 800) . '...';

 }



 $aired_from = $data['aired']['from'] ?? null; // ISO datetime

 $start_dt = animemori_dt_from_iso($aired_from, 'UTC');

 $start_date = $start_dt ? $start_dt->format('Y-m-d') : null;



 $year = $data['year'] ?? null;

 if (!$year && $start_dt) $year = intval($start_dt->format('Y'));



 $status = animemori_map_status_from_jikan($data['status'] ?? null);

 $format = animemori_map_format_from_jikan($data['type'] ?? null);



 $total_eps = $data['episodes'] ?? null;

 $duration = $data['duration'] ?? null; // string like "24 min per ep"

 $duration_min = null;

 if (is_string($duration) && preg_match('/(\d+)\s*min/', $duration, $m)) $duration_min = intval($m[1]);



 $rating = $data['rating'] ?? null;

 if (is_string($rating) && mb_strlen($rating) > 128) {

 $rating = mb_substr($rating, 0, 128);

 }



 $cover = $data['images']['webp']['large_image_url']
 ?? $data['images']['jpg']['large_image_url']
 ?? $data['images']['webp']['image_url']
 ?? $data['images']['jpg']['image_url']
 ?? null;

 $banner = $data['images']['webp']['large_image_url']
 ?? $data['images']['jpg']['large_image_url']
 ?? null;



 // broadcast: Jikan often provides:

 // data['broadcast']['day'] e.g. "Sundays"

 // data['broadcast']['time'] e.g. "17:00"

 // data['broadcast']['timezone'] e.g. "Asia/Tokyo"

 $bday = $data['broadcast']['day'] ?? null;

 $btime = $data['broadcast']['time'] ?? null;

 $btz = $data['broadcast']['timezone'] ?? 'Asia/Tokyo';



 $weekday_jst = animemori_weekday_to_int($bday);

 $time_jst = animemori_parse_time_hhmm($btime);



 // slug strategy: stable slug based on romaji/english + source id fallback

 $base_title = $title_romaji ?: ($title_english ?: ("anime-{$mal_id}"));

 $slug = animemori_sanitize_slug($base_title);

 // make slug deterministic with mal id if collision

 $slug_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}am_anime WHERE slug=%s LIMIT 1", $slug));

 if ($slug_exists) $slug = $slug . '-' . intval($mal_id);



 $payload_json = wp_json_encode($data);



 // Upsert anime

 $table = $wpdb->prefix . 'am_anime';

 $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE source='MAL' AND source_id=%d", intval($mal_id)));



 $anime_row = [

 'source' => 'MAL',

 'source_id' => intval($mal_id),

 'slug' => $slug,

 'title_romaji' => $title_romaji,

 'title_english' => $title_english,

 'title_native' => $title_native,

 'synopsis_short' => $synopsis_short,

 'start_date' => $start_date,

 'start_date_precision' => 'DAY',

 'year' => $year ? intval($year) : null,

 'status' => $status,

 'format' => $format,

 'total_episodes' => $total_eps ? intval($total_eps) : null,

 'episode_duration_min' => $duration_min,

 'cover_image_url' => $cover,

 'banner_image_url' => $banner,

 'rating' => $rating,

 'source_payload_json' => $payload_json,

 'updated_at' => $now,

 ];



 if ($existing_id) {

 // do not overwrite slug if already set (SEO stability)

 unset($anime_row['slug']);

 $wpdb->update($table, $anime_row, ['id' => $existing_id]);

 $anime_id = intval($existing_id);

 } else {

 $anime_row['created_at'] = $now;

 $wpdb->insert($table, $anime_row);

 $anime_id = intval($wpdb->insert_id);

 // create default season 1

 $wpdb->insert($wpdb->prefix . 'am_season', [

 'anime_id' => $anime_id,

 'season_number' => 1,

 'title' => 'Season 1',

 'start_date' => $start_date,

 'end_date' => null,

 'created_at' => $now,

 'updated_at' => $now,

 ]);

 }



 // Upsert broadcast

 $btable = $wpdb->prefix . 'am_anime_broadcast';

 $bexists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $btable WHERE anime_id=%d", $anime_id));

 $first_air_jst = null;

 if ($aired_from) {

 // aired_from is UTC-ish; store as JST for broadcast first air

 try {

 $dt = new DateTime($aired_from);

 $dt->setTimezone(new DateTimeZone('Asia/Tokyo'));

 $first_air_jst = $dt->format('Y-m-d H:i:s');

 } catch (Exception $e) { /* ignore */ }

 }



 $brow = [

 'anime_id' => $anime_id,

 'timezone' => $btz ?: 'Asia/Tokyo',

 'weekday_jst' => $weekday_jst,

 'time_jst' => $time_jst,

 'first_air_datetime_jst' => $first_air_jst,

 'broadcast_source' => 'jikan',

 'is_active' => 1,

 'updated_at' => $now,

 ];



 if ($bexists) {

 $wpdb->update($btable, $brow, ['id' => $bexists]);

 } else {

 $brow['created_at'] = $now;

 $wpdb->insert($btable, $brow);

 }



 return [

 'anime_id' => $anime_id,

 'slug' => $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$wpdb->prefix}am_anime WHERE id=%d", $anime_id)),

 'status' => $status,

 'format' => $format

 ];

}

