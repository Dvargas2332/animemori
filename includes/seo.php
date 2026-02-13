<?php
if (!defined('ABSPATH')) exit;

add_filter('pre_get_document_title', 'animemori_pre_get_document_title');
add_action('wp_head', 'animemori_seo_head', 1);
add_filter('wp_robots', 'animemori_wp_robots_directives', 20);
add_filter('robots_txt', 'animemori_robots_txt_rules', 20, 2);

function animemori_normalize_day_slug($dayParam) {
  if (!$dayParam) return null;
  $key = strtolower(preg_replace('/[^a-z]/', '', $dayParam));
  $valid = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
  return in_array($key, $valid, true) ? $key : null;
}

function animemori_pre_get_document_title($title) {
  $page = get_query_var('animemori_page');
  if (!$page) return $title;

  $site = get_bloginfo('name');

  if ($page === 'schedule') {
    $dayParam = animemori_normalize_day_slug(get_query_var('animemori_day'));
    $win = animemori_schedule_window($dayParam, 7);
    if ($dayParam) {
      $base = 'Anime Schedule for ' . $win['start_local']->format('l, M j');
    } else {
      $base = 'Weekly Anime Schedule';
    }
    return $base . ' | ' . $site;
  }

  if ($page === 'upcoming') {
    $base = 'Upcoming Anime Episodes';
    return $base . ' | ' . $site;
  }

  if ($page === 'anime') {
    $slug = get_query_var('animemori_slug');
    $anime = animemori_fetch_anime_by_slug($slug);
    if ($anime) {
      $base = animemori_pick_title($anime['title_english'], $anime['title_romaji'], 'Anime');
      return $base . ' | ' . $site;
    }
  }

  if ($page === 'anime_list') {
    $base = 'All Anime Directory';
    return $base . ' | ' . $site;
  }

  if ($page === 'character') {
    return 'Character | ' . $site;
  }

  return $title;
}

function animemori_seo_head() {
  $page = get_query_var('animemori_page');
  if (!$page) return;

  $meta = animemori_build_meta_description($page);
  if ($meta) {
    echo '<meta name="description" content="' . esc_attr($meta) . '">' . "\n";
  }

  $canonical = animemori_build_canonical($page);
  if ($canonical) {
    echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
  }

  $doc_title = wp_get_document_title();
  if ($doc_title) {
    echo '<meta property="og:title" content="' . esc_attr($doc_title) . '">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($doc_title) . '">' . "\n";
  }
  if ($meta) {
    echo '<meta property="og:description" content="' . esc_attr($meta) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($meta) . '">' . "\n";
  }
  if ($canonical) {
    echo '<meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";
  }
  echo '<meta property="og:type" content="' . esc_attr($page === 'anime' ? 'article' : 'website') . '">' . "\n";
  echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
  echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

  $schema = animemori_build_json_ld($page);
  if ($schema) {
    echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
  }
}

function animemori_build_meta_description($page) {
  $tz = animemori_site_tz();

  if ($page === 'schedule') {
    $dayParam = animemori_normalize_day_slug(get_query_var('animemori_day'));
    $win = animemori_schedule_window($dayParam, 7);
    if ($dayParam) {
      return 'Anime schedule for ' . $win['start_local']->format('l, M j') . ' in your site timezone.';
    }
    $start = $win['start_local']->format('M j');
    $end = (clone $win['end_local'])->modify('-1 day')->format('M j');
    return 'Weekly anime schedule from ' . $start . ' to ' . $end . ' in your site timezone.';
  }

  if ($page === 'upcoming') {
    $hours = intval(apply_filters('animemori_upcoming_hours', 72));
    if ($hours < 1) $hours = 72;
    return 'Upcoming anime episodes for the next ' . $hours . ' hours in your site timezone.';
  }

  if ($page === 'anime') {
    $slug = get_query_var('animemori_slug');
    $anime = animemori_fetch_anime_by_slug($slug);
    if ($anime) {
      if (!empty($anime['synopsis_short'])) {
        return animemori_truncate_text($anime['synopsis_short'], 155);
      }
      $title = animemori_pick_title($anime['title_english'], $anime['title_romaji'], 'Anime');
      return 'Anime details, episodes, and air dates for ' . $title . '.';
    }
  }

  if ($page === 'anime_list') {
    return 'Browse all anime titles by genre, year, season, format, and status.';
  }

  return null;
}

function animemori_build_canonical($page) {
  if ($page === 'schedule') {
    $dayParam = animemori_normalize_day_slug(get_query_var('animemori_day'));
    if ($dayParam) return home_url('/schedule/' . $dayParam . '/');
    return home_url('/schedule/');
  }

  if ($page === 'upcoming') {
    return home_url('/upcoming/');
  }

  if ($page === 'anime') {
    $slug = sanitize_title(get_query_var('animemori_slug'));
    if ($slug) return home_url('/anime/' . $slug . '/');
  }

  if ($page === 'character') {
    $slug = sanitize_title(get_query_var('animemori_slug'));
    if ($slug) return home_url('/characters/' . $slug . '/');
  }

  if ($page === 'anime_list') {
    return home_url('/anime/');
  }

  return null;
}

function animemori_build_json_ld($page) {
  $graph = [];

  if ($page === 'schedule') {
    $dayParam = animemori_normalize_day_slug(get_query_var('animemori_day'));
    $win = animemori_schedule_window($dayParam, 7);
    $rows = animemori_fetch_schedule_rows($win['start_utc'], $win['end_utc'], 200, ['AIRING','UPCOMING']);
    $name = $dayParam ? 'Anime Schedule for ' . $win['start_local']->format('l, M j') : 'Weekly Anime Schedule';
    $graph[] = animemori_json_ld_item_list($rows, $name);
    $graph[] = animemori_breadcrumbs_json_ld(animemori_breadcrumbs_schedule($dayParam, $win));
  }

  if ($page === 'upcoming') {
    $hours = intval(apply_filters('animemori_upcoming_hours', 72));
    if ($hours < 1) $hours = 72;
    $win = animemori_upcoming_window($hours);
    $rows = animemori_fetch_schedule_rows($win['start_utc'], $win['end_utc'], 200, ['AIRING','UPCOMING']);
    $name = 'Upcoming Anime Episodes';
    $graph[] = animemori_json_ld_item_list($rows, $name);
    $graph[] = animemori_breadcrumbs_json_ld(animemori_breadcrumbs_upcoming($win));
  }

  if ($page === 'anime') {
    $slug = get_query_var('animemori_slug');
    $anime = animemori_fetch_anime_by_slug($slug);
    if ($anime) {
      $graph[] = animemori_json_ld_series($anime);
      $graph[] = animemori_breadcrumbs_json_ld(animemori_breadcrumbs_anime($anime));
    }
  }

  if ($page === 'anime_list') {
    $graph[] = animemori_breadcrumbs_json_ld([
      ['label' => 'Home', 'url' => home_url('/')],
      ['label' => 'Anime', 'url' => home_url('/anime/')],
    ]);
  }

  $graph = array_filter($graph);
  if (empty($graph)) return null;

  return [
    '@context' => 'https://schema.org',
    '@graph' => $graph,
  ];
}

function animemori_json_ld_item_list($rows, $name) {
  $items = [];
  $pos = 1;
  foreach ($rows as $r) {
    if (empty($r['air_datetime_utc'])) continue;
    $title = animemori_pick_title($r['title_english'], $r['title_romaji'], 'Anime');
    $seriesUrl = home_url('/anime/' . $r['anime_slug'] . '/');
    $epNum = intval($r['episode_number']);
    $epName = $title . ' Episode ' . $epNum;
    $dt = gmdate('c', strtotime($r['air_datetime_utc']));
    $status = ($r['status'] === 'AIRED') ? 'https://schema.org/EventCompleted' : 'https://schema.org/EventScheduled';

    $items[] = [
      '@type' => 'ListItem',
      'position' => $pos,
      'item' => [
        '@type' => 'BroadcastEvent',
        'name' => $epName,
        'startDate' => $dt,
        'eventStatus' => $status,
        'workPerformed' => [
          '@type' => 'TVEpisode',
          'name' => $epName,
          'episodeNumber' => $epNum,
          'partOfSeries' => [
            '@type' => 'TVSeries',
            'name' => $title,
            'url' => $seriesUrl,
          ],
        ],
      ],
    ];
    $pos++;
  }

  return [
    '@type' => 'ItemList',
    'name' => $name,
    'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
    'itemListElement' => $items,
  ];
}

function animemori_json_ld_series($anime) {
  global $wpdb;
  $title = animemori_pick_title($anime['title_english'], $anime['title_romaji'], 'Anime');
  $url = home_url('/anime/' . $anime['slug'] . '/');
  $desc = !empty($anime['synopsis_short']) ? animemori_truncate_text($anime['synopsis_short'], 300) : null;

  $series = [
    '@type' => 'TVSeries',
    'name' => $title,
    'url' => $url,
  ];

  if (!empty($anime['cover_image_url'])) $series['image'] = $anime['cover_image_url'];
  if ($desc) $series['description'] = $desc;
  if (!empty($anime['start_date'])) $series['startDate'] = $anime['start_date'];
  if (!empty($anime['total_episodes'])) $series['numberOfEpisodes'] = intval($anime['total_episodes']);

  // Attach a small list of upcoming episodes
  $nowUtc = gmdate('Y-m-d H:i:s');
  $eps = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}am_episode WHERE anime_id=%d AND air_datetime_utc >= %s ORDER BY air_datetime_utc ASC LIMIT 20",
    intval($anime['id']),
    $nowUtc
  ), ARRAY_A);

  if (empty($eps)) {
    $eps = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}am_episode WHERE anime_id=%d ORDER BY episode_number ASC LIMIT 5",
      intval($anime['id'])
    ), ARRAY_A);
  }

  if (!empty($eps)) {
    $series['episode'] = [];
    foreach ($eps as $e) {
      $epNum = intval($e['episode_number']);
      $epName = $title . ' Episode ' . $epNum;
      $ep = [
        '@type' => 'TVEpisode',
        'name' => $epName,
        'episodeNumber' => $epNum,
      ];
      if (!empty($e['air_datetime_utc'])) {
        $ep['datePublished'] = gmdate('c', strtotime($e['air_datetime_utc']));
      }
      $series['episode'][] = $ep;
    }
  }

  return $series;
}

function animemori_breadcrumbs_schedule($dayParam, $win) {
  $crumbs = [
    ['label' => 'Home', 'url' => home_url('/')],
    ['label' => 'Schedule', 'url' => home_url('/schedule/')],
  ];
  $daySlug = animemori_normalize_day_slug($dayParam);
  if ($daySlug) {
    $crumbs[] = ['label' => $win['start_local']->format('l'), 'url' => home_url('/schedule/' . $daySlug . '/')];
  }
  return $crumbs;
}

function animemori_breadcrumbs_upcoming($win) {
  return [
    ['label' => 'Home', 'url' => home_url('/')],
    ['label' => 'Upcoming', 'url' => home_url('/upcoming/')],
  ];
}

function animemori_breadcrumbs_anime($anime) {
  $title = animemori_pick_title($anime['title_english'], $anime['title_romaji'], 'Anime');
  return [
    ['label' => 'Home', 'url' => home_url('/')],
    ['label' => 'Anime', 'url' => home_url('/anime/')],
    ['label' => $title, 'url' => home_url('/anime/' . $anime['slug'] . '/')],
  ];
}

function animemori_wp_robots_directives($robots) {
  $page = get_query_var('animemori_page');

  // Keep internal search and filtered directory pages out of index to reduce duplicates.
  if (is_search()) {
    $robots['noindex'] = true;
    $robots['nofollow'] = true;
    return $robots;
  }

  if ($page === 'anime_list') {
    $filtered = false;
    $keys = ['q', 'genre', 'year', 'season', 'format', 'status', 'order'];
    foreach ($keys as $k) {
      if (!empty($_GET[$k])) {
        $filtered = true;
        break;
      }
    }

    if ($filtered) {
      $robots['noindex'] = true;
      $robots['nofollow'] = true;
    }
  }

  return $robots;
}

function animemori_robots_txt_rules($output, $public) {
  if ('0' === (string)$public) return $output;

  $rules = "\n# Animemori SEO rules\n";
  $rules .= "Disallow: /?s=\n";
  $rules .= "Disallow: /anime/?q=\n";
  $rules .= "Disallow: /anime/?genre=\n";
  $rules .= "Disallow: /anime/?year=\n";
  $rules .= "Disallow: /anime/?season=\n";
  $rules .= "Disallow: /anime/?format=\n";
  $rules .= "Disallow: /anime/?status=\n";
  $rules .= "Disallow: /anime/?order=\n";

  if (strpos($output, '# Animemori SEO rules') === false) {
    $output .= $rules;
  }

  return $output;
}

function animemori_render_breadcrumbs($crumbs) {
  if (empty($crumbs) || !is_array($crumbs)) return;
  echo '<nav class="animemori-breadcrumbs" aria-label="Breadcrumbs" style="font-size:13px; margin-bottom:12px; opacity:.8;">';
  echo '<ol style="list-style:none; padding:0; margin:0; display:flex; gap:8px; flex-wrap:wrap;">';
  foreach ($crumbs as $i => $c) {
    if ($i > 0) {
      echo '<li aria-hidden="true">/</li>';
    }
    $label = esc_html($c['label']);
    $url = esc_url($c['url']);
    echo '<li><a href="' . $url . '">' . $label . '</a></li>';
  }
  echo '</ol></nav>';
}

function animemori_breadcrumbs_json_ld($crumbs) {
  if (empty($crumbs) || !is_array($crumbs)) return null;
  $items = [];
  $pos = 1;
  foreach ($crumbs as $c) {
    $items[] = [
      '@type' => 'ListItem',
      'position' => $pos,
      'name' => $c['label'],
      'item' => $c['url'],
    ];
    $pos++;
  }
  return [
    '@type' => 'BreadcrumbList',
    'itemListElement' => $items,
  ];
}

function animemori_render_sitemap() {
  global $wpdb;
  $limit = apply_filters('animemori_sitemap_limit', 50000);
  $limit = max(1, intval($limit));
  $now = gmdate('c');

  $urls = [];
  $urls[] = ['loc' => home_url('/schedule/'), 'lastmod' => $now];
  $urls[] = ['loc' => home_url('/upcoming/'), 'lastmod' => $now];

  $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
  foreach ($days as $d) {
    $urls[] = ['loc' => home_url('/schedule/' . $d . '/'), 'lastmod' => $now];
  }

  $rows = $wpdb->get_results("SELECT slug, updated_at FROM {$wpdb->prefix}am_anime ORDER BY updated_at DESC LIMIT {$limit}", ARRAY_A);
  foreach ($rows as $r) {
    $lastmod = !empty($r['updated_at']) ? gmdate('c', strtotime($r['updated_at'])) : $now;
    $urls[] = [
      'loc' => home_url('/anime/' . $r['slug'] . '/'),
      'lastmod' => $lastmod,
    ];
  }

  header('Content-Type: application/xml; charset=UTF-8');
  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
  foreach ($urls as $u) {
    echo "  <url>\n";
    echo "    <loc>" . esc_url($u['loc']) . "</loc>\n";
    if (!empty($u['lastmod'])) {
      echo "    <lastmod>" . esc_html($u['lastmod']) . "</lastmod>\n";
    }
    echo "  </url>\n";
  }
  echo "</urlset>\n";
}
