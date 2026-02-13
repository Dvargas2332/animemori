<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$days = intval($atts['days'] ?? 7);
if ($days < 1) $days = 7;
if ($days > 31) $days = 31;

$win = animemori_schedule_window(null, $days);
$rows = animemori_fetch_schedule_rows($win['start_utc'], $win['end_utc'], 200, ['AIRING','UPCOMING']);
$userTz = $win['tz'];
$utc = $win['utc'];
?>
<div class="animemori-schedule-block">
  <h3>Schedule (next <?php echo intval($days); ?> days)</h3>
  <?php if (!$rows): ?>
    <p>No scheduled episodes found.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($rows as $r):
        $dt = new DateTime($r['air_datetime_utc'], $utc);
        $dt->setTimezone($userTz);
        $title = animemori_pick_title($r['title_english'], $r['title_romaji'], 'Anime');
        $animeUrl = home_url('/anime/' . $r['anime_slug'] . '/');
      ?>
        <li>
          <?php echo esc_html($dt->format('M j')); ?> -
          <a href="<?php echo esc_url($animeUrl); ?>"><?php echo esc_html($title); ?></a>
          (Ep <?php echo intval($r['episode_number']); ?>)
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
