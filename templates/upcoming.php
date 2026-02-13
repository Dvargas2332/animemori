<?php
if (!defined('ABSPATH')) exit;
get_header();

global $wpdb;

$hours = intval(apply_filters('animemori_upcoming_hours', 72));
if ($hours < 1) $hours = 72;

$win = animemori_upcoming_window($hours);
$rows = animemori_fetch_schedule_rows($win['start_utc'], $win['end_utc'], 200, ['AIRING','UPCOMING']);
$userTz = $win['tz'];
$utc = $win['utc'];
?>

<section class="hero">
  <div class="container">
    <?php animemori_render_breadcrumbs(animemori_breadcrumbs_upcoming($win)); ?>
    <p class="hero-meta">Upcoming</p>
    <h1 class="hero-title">Upcoming episodes</h1>
    <p class="hero-sub">Next <?php echo intval($hours); ?> hours in your local timezone.</p>
  </div>
</section>

<section class="section">
  <div class="container layout">
    <div>
      <div class="row">
        <div class="row-title">
          <h2>Next <?php echo intval($hours); ?> hours</h2>
        </div>
        <?php if (!$rows): ?>
          <p class="day-empty">No upcoming episodes in this window.</p>
        <?php else: ?>
          <ul class="schedule-list">
            <?php foreach ($rows as $r):
              $dt = new DateTime($r['air_datetime_utc'], $utc);
              $dt->setTimezone($userTz);
              $title = animemori_pick_title($r['title_english'], $r['title_romaji'], 'Anime');
              $animeUrl = home_url('/anime/' . $r['anime_slug'] . '/');
              $tags = [];
              if (!empty($r['status']) && $r['status'] === 'AIRED') $tags[] = 'Aired';
              if (!empty($r['is_estimated'])) $tags[] = 'Estimated';
            ?>
              <li class="schedule-item">
                <?php if (!empty($r['cover_image_url'])): ?>
                  <img class="schedule-thumb" src="<?php echo esc_url($r['cover_image_url']); ?>" alt="" loading="lazy" decoding="async">
                <?php endif; ?>
                <div>
                  <div class="schedule-time"><?php echo esc_html($dt->format('M j')); ?></div>
                  <div class="schedule-title">
                    <a href="<?php echo esc_url($animeUrl); ?>"><?php echo esc_html($title); ?></a>
                    <span class="schedule-ep">Episode <?php echo intval($r['episode_number']); ?></span>
                    <?php foreach ($tags as $tag): ?>
                      <span class="tag"><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
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
    </aside>
  </div>
</section>

<?php get_footer(); ?>
