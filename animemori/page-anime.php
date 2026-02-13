<?php
get_header();

global $wpdb;
$anime_table = $wpdb->prefix . 'am_anime';
$per_page = 48;
$paged = max(1, intval(get_query_var('paged') ?: get_query_var('page') ?: 1));
$offset = ($paged - 1) * $per_page;

$q = sanitize_text_field($_GET['q'] ?? '');
$genre = sanitize_text_field($_GET['genre'] ?? '');
$year = intval($_GET['year'] ?? 0);
$season = sanitize_text_field($_GET['season'] ?? '');

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
  $like_rel = '%\"name\":\"' . $wpdb->esc_like($q) . '%';
  $where[] = "(title_english LIKE %s OR title_romaji LIKE %s OR title_native LIKE %s OR slug LIKE %s OR source_payload_json LIKE %s)";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like_rel;
}

if ($genre) {
  $like_genre = '%\"name\":\"' . $wpdb->esc_like($genre) . '%';
  $where[] = "source_payload_json LIKE %s";
  $params[] = $like_genre;
}

if ($year > 0) {
  $where[] = "(YEAR(start_date) = %d OR year = %d)";
  $params[] = $year;
  $params[] = $year;
}

if ($season) {
  $months = implode(',', array_map('intval', $seasons[$season]));
  $where[] = "start_date IS NOT NULL AND MONTH(start_date) IN ({$months})";
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_sql = "SELECT COUNT(*) FROM {$anime_table} {$where_sql}";
if (!empty($params)) {
  $total_sql = $wpdb->prepare($total_sql, ...$params);
}
$total = intval($wpdb->get_var($total_sql));

$query_sql = "SELECT id, title_english, title_romaji, slug, cover_image_url, year FROM {$anime_table} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
$params_rows = $params;
$params_rows[] = $per_page;
$params_rows[] = $offset;
$query_sql = $wpdb->prepare($query_sql, ...$params_rows);
$rows = $wpdb->get_results($query_sql);

$total_pages = $per_page > 0 ? (int)ceil($total / $per_page) : 1;
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
        <div class="filter-actions">
          <button class="btn" type="submit">Apply</button>
          <a class="btn ghost" href="<?php echo esc_url(home_url('/anime/')); ?>">Reset</a>
        </div>
      </form>

      <div class="row">
        <div class="row-title">
          <h2>All titles</h2>
        </div>
        <?php if (!empty($rows)) : ?>
          <div class="grid-cards">
            <?php foreach ($rows as $a) :
              $title = $a->title_english ?: $a->title_romaji;
            ?>
              <a href="/anime/<?php echo esc_attr($a->slug); ?>" class="card">
                <img src="<?php echo esc_url($a->cover_image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
                <div class="card-meta">
                  <h3><?php echo esc_html($title); ?></h3>
                  <p><?php echo esc_html($a->year); ?></p>
                </div>
              </a>
            <?php endforeach; ?>
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

    <?php get_sidebar(); ?>
  </div>
</section>

<?php get_footer(); ?>
