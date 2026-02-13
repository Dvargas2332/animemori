<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$hours = intval($atts['hours'] ?? 72);
if ($hours < 1) $hours = 72;
if ($hours > 168) $hours = 168;

$win = animemori_upcoming_window($hours);
$rows = animemori_fetch_schedule_rows($win['start_utc'], $win['end_utc'], 200, ['AIRING','UPCOMING']);
$userTz = $win['tz'];
$utc = $win['utc'];
?>
<div class="animemori-upcoming-block">
  <h3>Upcoming (next <?php echo intval($hours); ?> hours)</h3>
  <?php if (!$rows): ?>
    <p>No upcoming episodes found.</p>
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
