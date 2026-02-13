<?php
get_header();

global $wpdb;
$term = get_search_query();
$results = [];
if (!empty($term)) {
  $like = '%' . $wpdb->esc_like($term) . '%';
  $like_rel = '%\"name\":\"' . $wpdb->esc_like($term) . '%';
  $results = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT id, title_english, title_romaji, slug, cover_image_url, year
       FROM {$wpdb->prefix}am_anime
       WHERE title_english LIKE %s OR title_romaji LIKE %s OR title_native LIKE %s OR slug LIKE %s OR source_payload_json LIKE %s
       ORDER BY id DESC
       LIMIT 48",
      $like,
      $like,
      $like,
      $like,
      $like_rel
    )
  );
}
?>

<section class="hero">
  <div class="container">
    <p class="hero-meta">Search</p>
    <h1 class="hero-title">Search anime</h1>
    <p class="hero-sub">Results for: <?php echo esc_html($term ?: '...'); ?></p>
  </div>
</section>

<section class="section">
  <div class="container layout">
    <div>
      <div class="row">
        <div class="row-title">
          <h2>Results</h2>
        </div>
        <?php if (!empty($results)) : ?>
          <div class="grid-cards">
            <?php foreach ($results as $a) :
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
    </div>

    <?php get_sidebar(); ?>
  </div>
</section>

<?php get_footer(); ?>

