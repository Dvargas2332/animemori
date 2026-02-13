<?php
get_header();

global $wpdb;
$slug = get_query_var('animemori_slug');
if (!$slug) {
  $slug = get_query_var('name');
}

$anime = null;
if (!empty($slug)) {
  $anime = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT id, title_english, title_romaji, slug, cover_image_url, year, synopsis
       FROM {$wpdb->prefix}am_anime
       WHERE slug = %s
       LIMIT 1",
      $slug
    )
  );
}

$title = $anime ? ($anime->title_english ?: $anime->title_romaji) : get_the_title();
$hero_style = ($anime && $anime->cover_image_url) ? 'style="background-image:url(' . esc_url($anime->cover_image_url) . ');"' : '';
?>

<section class="hero" <?php echo $hero_style; ?>>
  <div class="container">
    <p class="hero-meta">Series</p>
    <h1 class="hero-title"><?php echo esc_html($title); ?></h1>
    <?php if ($anime && $anime->year) : ?>
      <p class="hero-sub">Premiered <?php echo esc_html($anime->year); ?></p>
    <?php else : ?>
      <p class="hero-sub">Anime details and episodes.</p>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="container layout">
    <div>
      <?php if ($anime) : ?>
        <div class="detail-card">
          <img src="<?php echo esc_url($anime->cover_image_url); ?>" alt="<?php echo esc_attr($title); ?>">
          <div>
            <h2 style="margin-top:0;">Synopsis</h2>
            <p><?php echo esc_html($anime->synopsis ?: 'Synopsis not available.'); ?></p>
          </div>
        </div>
      <?php elseif (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
          <article class="detail-card">
            <?php the_content(); ?>
          </article>
        <?php endwhile; ?>
      <?php else : ?>
        <p>Anime not found.</p>
      <?php endif; ?>
    </div>

    <?php get_sidebar(); ?>
  </div>
</section>

<?php get_footer(); ?>
