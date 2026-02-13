<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$slug = sanitize_title($_GET['animemori_embed_slug'] ?? '');
$anime = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}am_anime WHERE slug=%s", $slug), ARRAY_A);
if (!$anime) { echo '<p>Anime not found.</p>'; return; }

$title = animemori_pick_title($anime['title_english'], $anime['title_romaji'], 'Anime');
$url = home_url('/anime/' . $anime['slug'] . '/');
?>
<div class="animemori-anime-block" style="border:1px solid #e5e7eb; border-radius:12px; padding:12px;">
  <div style="font-weight:600;"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a></div>
  <div style="opacity:.8; font-size:14px;">Status: <?php echo esc_html($anime['status']); ?> - Year: <?php echo esc_html($anime['year'] ?: '-'); ?></div>
</div>
