<?php
if (!defined('ABSPATH')) exit;
get_header();

global $wpdb;

$dayParam = animemori_normalize_day_slug(get_query_var('animemori_day'));
$win = animemori_schedule_window($dayParam, 7);
$rows = animemori_fetch_schedule_rows($win['start_utc'], $win['end_utc'], 500, ['AIRING','UPCOMING']);
$userTz = $win['tz'];
$utc = $win['utc'];

$heading = $dayParam ? 'Schedule for ' . $win['start_local']->format('l') : 'Weekly Schedule';
$subheading = $dayParam
  ? 'Episodes airing on ' . $win['start_local']->format('l, M j') . ' in your local timezone.'
  : 'Fresh episode times based on the latest broadcast data, shown in your local timezone.';

$days = [
  'monday' => 'Mon',
  'tuesday' => 'Tue',
  'wednesday' => 'Wed',
  'thursday' => 'Thu',
  'friday' => 'Fri',
  'saturday' => 'Sat',
  'sunday' => 'Sun',
];

$grouped = [];
foreach ($rows as $r) {
  if (empty($r['air_datetime_utc'])) continue;
  $dt = new DateTime($r['air_datetime_utc'], $utc);
  $dt->setTimezone($userTz);
  $key = $dt->format('Y-m-d');
  $r['local_dt'] = $dt;
  if (!isset($grouped[$key])) $grouped[$key] = [];
  $grouped[$key][] = $r;
}
?>

<section class="hero">
  <div class="container">
    <?php animemori_render_breadcrumbs(animemori_breadcrumbs_schedule($dayParam, $win)); ?>
    <p class="hero-meta">Schedule</p>
    <h1 class="hero-title"><?php echo esc_html($heading); ?></h1>
    <p class="hero-sub"><?php echo esc_html($subheading); ?></p>
    <div class="schedule-nav">
      <a class="day-link<?php echo $dayParam ? '' : ' is-active'; ?>" href="<?php echo esc_url(home_url('/schedule/')); ?>">This week</a>
      <?php foreach ($days as $slug => $label): ?>
        <a class="day-link<?php echo ($dayParam === $slug) ? ' is-active' : ''; ?>" href="<?php echo esc_url(home_url('/schedule/' . $slug . '/')); ?>"><?php echo esc_html($label); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container layout">
    <div>
      <?php
        $render_day = function(DateTime $date) use ($grouped) {
          $key = $date->format('Y-m-d');
          $items = $grouped[$key] ?? [];
          echo '<div class="day-section">';
          echo '<div class="day-title"><h2>' . esc_html($date->format('l')) . '</h2><span>' . esc_html($date->format('M j')) . '</span></div>';
          if (empty($items)) {
            echo '<p class="day-empty">No episodes scheduled yet.</p>';
          } else {
            echo '<div class="carousel" data-carousel>';
            echo '<button class="carousel-arrow prev" type="button" data-carousel-prev><span>&lsaquo;</span></button>';
            echo '<div class="day-scroll" data-carousel-track>';
            foreach ($items as $r) {
              $dt = $r['local_dt'];
              $date = $dt instanceof DateTime ? $dt->format('M j') : '';
              $title = animemori_pick_title($r['title_english'], $r['title_romaji'], 'Anime');
              $animeUrl = home_url('/anime/' . $r['anime_slug'] . '/');
              $meta = 'Ep ' . intval($r['episode_number']);
              if ($date) $meta .= ' | ' . $date;

              echo '<a class="schedule-card" href="' . esc_url($animeUrl) . '">';
              if (!empty($r['cover_image_url'])) {
                echo '<img src="' . esc_url($r['cover_image_url']) . '" alt="" loading="lazy" decoding="async">';
              }
              echo '<div class="card-meta">';
              echo '<h3>' . esc_html($title) . '</h3>';
              echo '<p>' . esc_html($meta) . '</p>';
              echo '</div>';
              echo '</a>';
            }
            echo '</div>';
            echo '<button class="carousel-arrow next" type="button" data-carousel-next><span>&rsaquo;</span></button>';
            echo '</div>';
          }
          echo '</div>';
        };
      ?>

      <?php if (!$rows): ?>
        <p class="day-empty">No episodes scheduled for this range.</p>
      <?php else: ?>
        <?php if ($dayParam): ?>
          <?php $render_day($win['start_local']); ?>
        <?php else: ?>
          <?php
            $cursor = clone $win['start_local'];
            while ($cursor < $win['end_local']) {
              $render_day($cursor);
              $cursor->modify('+1 day');
            }
          ?>
        <?php endif; ?>
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
        <h3>Upcoming releases</h3>
        <?php echo do_shortcode('[animemori_upcoming hours="72"]'); ?>
      </div>
    </aside>
  </div>
</section>

<?php get_footer(); ?>
