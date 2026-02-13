<?php
get_header();

global $wpdb;
$anime_table = $wpdb->prefix . 'am_anime';
$rating_table = $wpdb->prefix . 'am_anime_rating';

$now_year = intval(current_time('Y'));
$next_year = $now_year + 1;

$in_season = $wpdb->get_results("
  SELECT id, title_english, title_romaji, slug, cover_image_url, year, start_date, status
  FROM {$anime_table}
  WHERE status = 'AIRING'
  ORDER BY start_date DESC, id DESC
  LIMIT 24
");

$next_season = $wpdb->get_results("
  SELECT id, title_english, title_romaji, slug, cover_image_url, year, start_date, status
  FROM {$anime_table}
  WHERE status = 'UPCOMING'
    AND (start_date IS NULL OR start_date >= CURDATE())
  ORDER BY start_date ASC, id DESC
  LIMIT 24
");

$popular = $wpdb->get_results(
  "SELECT a.id, a.title_english, a.title_romaji, a.slug, a.cover_image_url, a.year,
          AVG(r.rating) as avg_rating, COUNT(r.id) as rating_count
   FROM {$anime_table} a
   LEFT JOIN {$rating_table} r ON r.anime_id = a.id
   WHERE a.status IN ('AIRING','UPCOMING','FINISHED')
   GROUP BY a.id
   ORDER BY avg_rating DESC, rating_count DESC, a.id DESC
   LIMIT 24"
);

if (empty($popular)) {
  $popular = $wpdb->get_results("
    SELECT id, title_english, title_romaji, slug, cover_image_url, year
    FROM {$anime_table}
    ORDER BY id DESC
    LIMIT 24
  ");
}

$next_year_list = $wpdb->get_results(
  $wpdb->prepare(
    "SELECT id, title_english, title_romaji, slug, cover_image_url, year, start_date
     FROM {$anime_table}
     WHERE (start_date IS NOT NULL AND YEAR(start_date) = %d)
        OR (start_date IS NULL AND year = %d)
     ORDER BY start_date ASC, id DESC
     LIMIT 24",
    $next_year,
    $next_year
  )
);

$all_ids = [];
foreach ([$in_season, $next_season, $popular, $next_year_list] as $list) {
  if (empty($list)) continue;
  foreach ($list as $a) {
    $all_ids[] = intval($a->id);
  }
}
$all_ids = array_values(array_unique(array_filter($all_ids)));
$rating_map = [];
if (!empty($all_ids)) {
  $placeholders = implode(',', array_fill(0, count($all_ids), '%d'));
  $rating_rows = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT anime_id, AVG(rating) as avg_rating, COUNT(*) as rating_count
       FROM {$rating_table}
       WHERE anime_id IN ($placeholders)
       GROUP BY anime_id",
      ...$all_ids
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

$featured = !empty($in_season) ? $in_season[0] : (!empty($next_season) ? $next_season[0] : null);
$featured_title = $featured ? ($featured->title_english ?: $featured->title_romaji) : get_bloginfo('name');
$featured_bg = ($featured && $featured->cover_image_url) ? $featured->cover_image_url : '';
$hero_style = $featured_bg ? 'style="background-image:url(' . esc_url($featured_bg) . ');"' : '';
?>

<section class="hero" <?php echo $hero_style; ?>>
  <div class="container">
    <p class="hero-meta">This season</p>
    <h1 class="hero-title"><?php echo esc_html($featured_title); ?></h1>
    <p class="hero-sub">News, releases, and calendar updates.</p>
    <div class="hero-actions">
      <a class="btn" href="<?php echo esc_url(home_url('/schedule/')); ?>">Schedule</a>
      <a class="btn ghost" href="<?php echo esc_url(home_url('/upcoming/')); ?>">Upcoming</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container layout">
    <div>
      <div class="row">
        <div class="row-title">
          <h2>In season now</h2>
        </div>
        <div class="carousel" data-carousel>
          <button class="carousel-arrow prev" type="button" data-carousel-prev><span>&lsaquo;</span></button>
          <div class="row-scroll" data-carousel-track>
          <?php if (!empty($in_season)) : ?>
            <?php foreach ($in_season as $a) :
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
          <?php else : ?>
            <p>No in-season anime found.</p>
          <?php endif; ?>
          </div>
          <button class="carousel-arrow next" type="button" data-carousel-next><span>&rsaquo;</span></button>
        </div>
      </div>

      <div class="row">
        <div class="row-title">
          <h2>Next season</h2>
        </div>
        <div class="carousel" data-carousel>
          <button class="carousel-arrow prev" type="button" data-carousel-prev><span>&lsaquo;</span></button>
          <div class="row-scroll" data-carousel-track>
          <?php if (!empty($next_season)) : ?>
            <?php foreach ($next_season as $a) :
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
          <?php else : ?>
            <p>No upcoming season anime found.</p>
          <?php endif; ?>
          </div>
          <button class="carousel-arrow next" type="button" data-carousel-next><span>&rsaquo;</span></button>
        </div>
      </div>

      <div class="row">
        <div class="row-title">
          <h2>Popular anime</h2>
        </div>
        <div class="carousel" data-carousel>
          <button class="carousel-arrow prev" type="button" data-carousel-prev><span>&lsaquo;</span></button>
          <div class="row-scroll" data-carousel-track>
          <?php if (!empty($popular)) : ?>
            <?php foreach ($popular as $a) :
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
          <?php else : ?>
            <p>No popular anime yet.</p>
          <?php endif; ?>
          </div>
          <button class="carousel-arrow next" type="button" data-carousel-next><span>&rsaquo;</span></button>
        </div>
      </div>

      <div class="row">
        <div class="row-title">
          <h2><?php echo esc_html($next_year); ?> releases</h2>
        </div>
        <div class="carousel" data-carousel>
          <button class="carousel-arrow prev" type="button" data-carousel-prev><span>&lsaquo;</span></button>
          <div class="row-scroll" data-carousel-track>
          <?php if (!empty($next_year_list)) : ?>
            <?php foreach ($next_year_list as $a) :
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
          <?php else : ?>
            <p>No anime announced for next year yet.</p>
          <?php endif; ?>
          </div>
          <button class="carousel-arrow next" type="button" data-carousel-next><span>&rsaquo;</span></button>
        </div>
      </div>
    </div>

    <?php get_sidebar(); ?>
  </div>
</section>

<?php get_footer(); ?>

