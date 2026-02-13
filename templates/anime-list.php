<?php
if (!defined('ABSPATH')) exit;
get_header();

global $wpdb;
$anime_table = $wpdb->prefix . 'am_anime';
$rating_table = $wpdb->prefix . 'am_anime_rating';
$per_page = 48;
$paged = max(1, intval(get_query_var('paged') ?: get_query_var('page') ?: 1));
$offset = ($paged - 1) * $per_page;

$q = sanitize_text_field($_GET['q'] ?? '');
$genre = sanitize_text_field($_GET['genre'] ?? '');
$year = intval($_GET['year'] ?? 0);
$season = sanitize_text_field($_GET['season'] ?? '');
$format = sanitize_text_field($_GET['format'] ?? '');
$order = sanitize_text_field($_GET['order'] ?? 'latest');
$status = sanitize_text_field($_GET['status'] ?? '');

$allowed_formats = ['TV','MOVIE','OVA','ONA','SPECIAL','UNKNOWN'];
if (!in_array($format, $allowed_formats, true)) {
  $format = '';
}
$allowed_status = ['AIRING','UPCOMING','FINISHED','HIATUS','CANCELLED'];
if (!in_array($status, $allowed_status, true)) {
  $status = '';
}
$allowed_orders = ['latest','popular'];
if (!in_array($order, $allowed_orders, true)) {
  $order = 'latest';
}

$seasons = [
  'winter' => [12, 1, 2],
  'spring' => [3, 4, 5],
  'summer' => [6, 7, 8],
  'fall' => [9, 10, 11],
];
if (!isset($seasons[$season])) {
  $season = '';
}

// Build genre list (cached)
$genre_list = get_transient('animemori_genre_list');
if (!is_array($genre_list)) {
  $genre_list = [];
  $rows = $wpdb->get_results("SELECT source_payload_json FROM {$anime_table} WHERE source_payload_json IS NOT NULL LIMIT 1500", ARRAY_A);
  foreach ($rows as $r) {
    $json = json_decode($r['source_payload_json'] ?? '', true);
    if (!is_array($json)) continue;
    $genres = $json['genres'] ?? [];
    if (!is_array($genres)) continue;
    foreach ($genres as $g) {
      if (!is_array($g)) continue;
      $name = $g['name'] ?? null;
      if ($name) $genre_list[$name] = true;
    }
  }
  $genre_list = array_keys($genre_list);
  sort($genre_list);
  set_transient('animemori_genre_list', $genre_list, 6 * HOUR_IN_SECONDS);
}

$year_list = $wpdb->get_col("SELECT DISTINCT COALESCE(year, YEAR(start_date)) as y FROM {$anime_table} WHERE year IS NOT NULL OR start_date IS NOT NULL ORDER BY y DESC");

$where = [];
$params = [];

if ($q) {
  $like = '%' . $wpdb->esc_like($q) . '%';
  $like_rel = '%"name":"' . $wpdb->esc_like($q) . '%';
  $where[] = "(a.title_english LIKE %s OR a.title_romaji LIKE %s OR a.title_native LIKE %s OR a.slug LIKE %s OR a.source_payload_json LIKE %s)";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like_rel;
}

if ($genre) {
  $like_genre = '%"name":"' . $wpdb->esc_like($genre) . '%';
  $where[] = "a.source_payload_json LIKE %s";
  $params[] = $like_genre;
}

if ($year > 0) {
  $where[] = "(YEAR(a.start_date) = %d OR a.year = %d)";
  $params[] = $year;
  $params[] = $year;
}

if ($season) {
  $months = implode(',', array_map('intval', $seasons[$season]));
  $where[] = "a.start_date IS NOT NULL AND MONTH(a.start_date) IN ({$months})";
}

if ($format) {
  $where[] = "a.format = %s";
  $params[] = $format;
}

if ($status) {
  $where[] = "a.status = %s";
  $params[] = $status;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_sql = "SELECT COUNT(*) FROM {$anime_table} a {$where_sql}";
if (!empty($params)) {
  $total_sql = $wpdb->prepare($total_sql, ...$params);
}
$total = intval($wpdb->get_var($total_sql));

$join = ($order === 'popular') ? "LEFT JOIN {$rating_table} r ON r.anime_id = a.id" : '';
$select = ($order === 'popular')
  ? "SELECT a.id, a.title_english, a.title_romaji, a.slug, a.cover_image_url, a.year, AVG(r.rating) as avg_rating, COUNT(r.id) as rating_count"
  : "SELECT a.id, a.title_english, a.title_romaji, a.slug, a.cover_image_url, a.year";

$group = ($order === 'popular') ? "GROUP BY a.id" : '';
$order_sql = ($order === 'popular') ? "ORDER BY avg_rating DESC, rating_count DESC, a.id DESC" : "ORDER BY a.id DESC";

$query_sql = "{$select} FROM {$anime_table} a {$join} {$where_sql} {$group} {$order_sql} LIMIT %d OFFSET %d";
$params_rows = $params;
$params_rows[] = $per_page;
$params_rows[] = $offset;
$query_sql = $wpdb->prepare($query_sql, ...$params_rows);
$rows = $wpdb->get_results($query_sql);

$total_pages = $per_page > 0 ? (int)ceil($total / $per_page) : 1;

$rating_map = [];
if (!empty($rows)) {
  $ids = [];
  foreach ($rows as $a) $ids[] = intval($a->id);
  $ids = array_values(array_unique(array_filter($ids)));
  if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $rating_rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT anime_id, AVG(rating) as avg_rating, COUNT(*) as rating_count
         FROM {$rating_table}
         WHERE anime_id IN ($placeholders)
         GROUP BY anime_id",
        ...$ids
      ),
      ARRAY_A
    );
    foreach ($rating_rows as $row) {
      $rating_map[intval($row['anime_id'])] = [
        'avg' => round(floatval($row['avg_rating']), 1),
        'count' => intval($row['rating_count']),
      ];
    }
  }
}
?>

<?php
  $query_args = [];
  if ($q) $query_args['q'] = $q;
  if ($genre) $query_args['genre'] = $genre;
  if ($year) $query_args['year'] = $year;
  if ($season) $query_args['season'] = $season;
  if ($format) $query_args['format'] = $format;
  if ($order) $query_args['order'] = $order;
  if ($status) $query_args['status'] = $status;
  $query_str = http_build_query($query_args);
  $next_page = $paged + 1;
?>

<section class="hero">
  <div class="container">
    <p class="hero-meta">Directory</p>
    <h1 class="hero-title">All anime</h1>
    <p class="hero-sub">Filter by genre, year, season, or name.</p>
  </div>
</section>

<section class="section">
  <div class="container layout">
    <div>
      <form class="filters" method="get" action="<?php echo esc_url(home_url('/anime/')); ?>">
        <div class="filter-field">
          <label for="filter-name">Name</label>
          <input id="filter-name" type="search" name="q" placeholder="Search" value="<?php echo esc_attr($q); ?>">
        </div>
        <div class="filter-field">
          <label for="filter-genre">Genre</label>
          <select id="filter-genre" name="genre">
            <option value="">All</option>
            <?php foreach ($genre_list as $g): ?>
              <option value="<?php echo esc_attr($g); ?>" <?php selected($genre, $g); ?>><?php echo esc_html($g); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-field">
          <label for="filter-year">Year</label>
          <select id="filter-year" name="year">
            <option value="">All</option>
            <?php foreach ($year_list as $y): ?>
              <?php if (!$y) continue; ?>
              <option value="<?php echo esc_attr($y); ?>" <?php selected($year, intval($y)); ?>><?php echo esc_html($y); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-field">
          <label for="filter-season">Season</label>
          <select id="filter-season" name="season">
            <option value="">All</option>
            <option value="winter" <?php selected($season, 'winter'); ?>>Winter</option>
            <option value="spring" <?php selected($season, 'spring'); ?>>Spring</option>
            <option value="summer" <?php selected($season, 'summer'); ?>>Summer</option>
            <option value="fall" <?php selected($season, 'fall'); ?>>Fall</option>
          </select>
        </div>
        <div class="filter-field">
          <label for="filter-format">Format</label>
          <select id="filter-format" name="format">
            <option value="">All</option>
            <option value="TV" <?php selected($format, 'TV'); ?>>TV</option>
            <option value="MOVIE" <?php selected($format, 'MOVIE'); ?>>Movie</option>
            <option value="OVA" <?php selected($format, 'OVA'); ?>>OVA</option>
            <option value="ONA" <?php selected($format, 'ONA'); ?>>ONA</option>
            <option value="SPECIAL" <?php selected($format, 'SPECIAL'); ?>>Special</option>
            <option value="UNKNOWN" <?php selected($format, 'UNKNOWN'); ?>>Unknown</option>
          </select>
        </div>
        <div class="filter-field">
          <label for="filter-order">Order</label>
          <select id="filter-order" name="order">
            <option value="latest" <?php selected($order, 'latest'); ?>>Latest</option>
            <option value="popular" <?php selected($order, 'popular'); ?>>Most voted</option>
          </select>
        </div>
        <div class="filter-field">
          <label for="filter-status">Status</label>
          <select id="filter-status" name="status">
            <option value="">All</option>
            <option value="AIRING" <?php selected($status, 'AIRING'); ?>>Airing</option>
            <option value="UPCOMING" <?php selected($status, 'UPCOMING'); ?>>Upcoming</option>
            <option value="FINISHED" <?php selected($status, 'FINISHED'); ?>>Finished</option>
            <option value="HIATUS" <?php selected($status, 'HIATUS'); ?>>Hiatus</option>
            <option value="CANCELLED" <?php selected($status, 'CANCELLED'); ?>>Cancelled</option>
          </select>
        </div>
        <div class="filter-actions">
          <button class="btn" type="submit">Apply</button>
          <a class="btn ghost" href="<?php echo esc_url(home_url('/anime/')); ?>">Reset</a>
        </div>
      </form>

      <div class="row">
        <div class="row-title">
          <h2>All titles</h2>
          <span class="results-meta"><?php echo intval($total); ?> results</span>
        </div>
        <?php if (!empty($rows)) : ?>
          <div class="anime-directory" data-anime-directory data-base-url="<?php echo esc_url(home_url('/anime/')); ?>" data-query="<?php echo esc_attr($query_str); ?>" data-next-page="<?php echo esc_attr($next_page); ?>" data-total-pages="<?php echo esc_attr($total_pages); ?>">
            <div class="grid-cards" data-anime-grid>
            <?php foreach ($rows as $a) :
              $title = $a->title_english ?: $a->title_romaji;
            ?>
              <a href="/anime/<?php echo esc_attr($a->slug); ?>" class="card">
                <img src="<?php echo esc_url($a->cover_image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
                <div class="card-meta">
                  <h3><?php echo esc_html($title); ?></h3>
                  <p><?php echo esc_html($a->year); ?></p>
                  <?php if (!empty($rating_map[$a->id]) && $rating_map[$a->id]['count'] > 0): ?>
                    <p class="card-rating">&#9733; <?php echo esc_html($rating_map[$a->id]['avg']); ?> (<?php echo esc_html($rating_map[$a->id]['count']); ?>)</p>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
            </div>
            <?php if ($total_pages > 1 && $paged < $total_pages): ?>
              <div class="load-more">
                <button class="btn ghost" type="button" data-anime-load>Load more</button>
              </div>
            <?php endif; ?>
          </div>
        <?php else : ?>
          <p>No anime found.</p>
        <?php endif; ?>
      </div>

      <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php if ($paged > 1): ?>
            <a class="btn ghost" href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>">Previous</a>
          <?php endif; ?>
          <span class="pagination-meta">Page <?php echo intval($paged); ?> of <?php echo intval($total_pages); ?></span>
          <?php if ($paged < $total_pages): ?>
            <a class="btn ghost" href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>">Next</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="sidebar">
      <div class="widget">
        <h3>Search</h3>
        <form method="get" action="<?php echo esc_url(home_url('/')); ?>">
          <input type="search" name="s" placeholder="Search anime" value="<?php echo esc_attr(get_search_query()); ?>">
          <button type="submit">Search</button>
        </form>
      </div>
      <div class="widget">
        <h3>Weekly schedule</h3>
        <?php echo do_shortcode('[animemori_schedule days="7"]'); ?>
      </div>
      <div class="widget">
        <h3>Upcoming releases</h3>
        <?php echo do_shortcode('[animemori_upcoming hours="72"]'); ?>
      </div>
    </aside>
  </div>
</section>

<?php get_footer(); ?>
