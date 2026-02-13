<?php
if (!defined('ABSPATH')) exit;
get_header();

global $wpdb;

$slug = get_query_var('animemori_slug');
$anime = animemori_fetch_anime_by_slug($slug);
?>

<?php if (!$anime): ?>
  <section class="hero">
    <div class="container">
      <p class="hero-meta">Series</p>
      <h1 class="hero-title">Anime not found</h1>
      <p class="hero-sub">We could not find this series.</p>
    </div>
  </section>
<?php else:
  $broadcast = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}am_anime_broadcast WHERE anime_id=%d", $anime['id']), ARRAY_A);
  $eps = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}am_episode WHERE anime_id=%d ORDER BY episode_number ASC LIMIT 200",
    $anime['id']
  ), ARRAY_A);

  $title = animemori_pick_title($anime['title_english'], $anime['title_romaji'], 'Anime');
  $sub = $anime['title_romaji'] && $anime['title_english'] ? $anime['title_romaji'] : ($anime['title_native'] ?: '');
  $userTz = wp_timezone();
  $jst = new DateTimeZone('Asia/Tokyo');
  $hero_style = !empty($anime['cover_image_url']) ? 'style="background-image:url(' . esc_url($anime['cover_image_url']) . ');"' : '';

  $genre_names = [];
  $payload = !empty($anime['source_payload_json']) ? json_decode($anime['source_payload_json'], true) : null;
  if (is_array($payload)) {
    $genres = $payload['genres'] ?? [];
    if (is_array($genres)) {
      foreach ($genres as $g) {
        if (!is_array($g)) continue;
        $name = $g['name'] ?? null;
        if ($name) $genre_names[] = $name;
      }
    }
  }
  $genre_names = array_values(array_unique(array_filter($genre_names)));

  $search_term = '';
  if (!empty($_COOKIE['am_last_search'])) {
    $search_term = sanitize_text_field(urldecode($_COOKIE['am_last_search']));
  }

  $recommendations = [];
  $reco_label = 'Recommended for you';

  if (!empty($search_term)) {
    $reco_label = 'Based on your searches';
    $cache_key = 'animemori_reco_search_' . md5($search_term);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
      $recommendations = $cached;
    } else {
      $like = '%' . $wpdb->esc_like($search_term) . '%';
      $like_rel = '%"name":"' . $wpdb->esc_like($search_term) . '%';
      $recommendations = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title_english, title_romaji, slug, cover_image_url, year
         FROM {$wpdb->prefix}am_anime
         WHERE id <> %d AND (title_english LIKE %s OR title_romaji LIKE %s OR title_native LIKE %s OR slug LIKE %s OR source_payload_json LIKE %s)
         ORDER BY updated_at DESC LIMIT 8",
        $anime['id'],
        $like,
        $like,
        $like,
        $like,
        $like_rel
      ), ARRAY_A);
      set_transient($cache_key, $recommendations, 6 * HOUR_IN_SECONDS);
    }
  }

  if (empty($recommendations) && !empty($genre_names)) {
    $reco_label = 'Recommended by genre';
    $cache_key = 'animemori_reco_genre_' . md5(implode('|', $genre_names));
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
      $recommendations = $cached;
    } else {
      $use = array_slice($genre_names, 0, 3);
      $likes = [];
      $params = [intval($anime['id'])];
      foreach ($use as $g) {
        $likes[] = "source_payload_json LIKE %s";
        $params[] = '%"name":"' . $wpdb->esc_like($g) . '"%';
      }
      $sql = "SELECT id, title_english, title_romaji, slug, cover_image_url, year
              FROM {$wpdb->prefix}am_anime
              WHERE id <> %d AND (" . implode(' OR ', $likes) . ")
              ORDER BY updated_at DESC LIMIT 8";
      $recommendations = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
      set_transient($cache_key, $recommendations, 6 * HOUR_IN_SECONDS);
    }
  }

  if (empty($recommendations) && !empty($anime['year'])) {
    $recommendations = $wpdb->get_results($wpdb->prepare(
      "SELECT id, title_english, title_romaji, slug, cover_image_url, year
       FROM {$wpdb->prefix}am_anime
       WHERE id <> %d AND year = %d
       ORDER BY updated_at DESC LIMIT 8",
      $anime['id'],
      $anime['year']
    ), ARRAY_A);
  }

  if (empty($recommendations)) {
    $recommendations = $wpdb->get_results($wpdb->prepare(
      "SELECT id, title_english, title_romaji, slug, cover_image_url, year
       FROM {$wpdb->prefix}am_anime
       WHERE id <> %d
       ORDER BY updated_at DESC LIMIT 8",
      $anime['id']
    ), ARRAY_A);
  }
?>
  <section class="hero" <?php echo $hero_style; ?>>
    <div class="container">
      <?php animemori_render_breadcrumbs(animemori_breadcrumbs_anime($anime)); ?>
      <p class="hero-meta">Series</p>
      <h1 class="hero-title"><?php echo esc_html($title); ?></h1>
      <?php if ($sub): ?>
        <p class="hero-sub"><?php echo esc_html($sub); ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="section">
    <div class="container layout">
      <div>
        <div class="detail-card">
          <?php if (!empty($anime['cover_image_url'])): ?>
            <img src="<?php echo esc_url($anime['cover_image_url']); ?>" alt="<?php echo esc_attr($title); ?>" loading="eager" fetchpriority="high" decoding="async">
          <?php endif; ?>
          <div>
            <h2 style="margin-top:0;">Overview</h2>
            <p><?php echo esc_html($anime['synopsis_short'] ?: ($anime['synopsis'] ?: 'Synopsis not available.')); ?></p>
            <p style="margin-top:12px; color: var(--muted); font-size: 13px;">
              Year: <?php echo esc_html($anime['year'] ?: '-'); ?> | 
              Status: <?php echo esc_html($anime['status']); ?> | 
              Format: <?php echo esc_html($anime['format']); ?> | 
              Episodes: <?php echo esc_html($anime['total_episodes'] ?: '-'); ?>
            </p>

            <?php if ($broadcast): ?>
              <p style="margin-top:12px; font-size: 13px; color: var(--muted);">
                Broadcast (JST):
                <?php
                  $wd = $broadcast['weekday_jst'] ? intval($broadcast['weekday_jst']) : null;
                  $time = $broadcast['time_jst'] ?: null;
                  $first = $broadcast['first_air_datetime_jst'] ?: null;
                  $wdName = $wd ? ['','Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$wd] : '-';
                  echo esc_html($wdName . ($time ? (' ' . substr($time,0,5)) : ''));
                ?>
                <?php if ($first): ?>
                  <?php
                    try {
                      $dt = new DateTime($first, $jst);
                      $dtUser = clone $dt; $dtUser->setTimezone($userTz);
                      echo '<br><span style="opacity:.8;">First air: ' . esc_html($dtUser->format('Y-m-d')) . ' (site TZ)</span>';
                    } catch (Exception $e) {}
                  ?>
                <?php endif; ?>
              </p>
            <?php endif; ?>
          </div>
        </div>

        <?php
          $rating_stats = function_exists('animemori_get_rating_stats') ? animemori_get_rating_stats($anime['id']) : ['avg' => 0, 'count' => 0];
          $rating_nonce = wp_create_nonce('animemori_rate');
        ?>
        <div class="rating-block" data-anime-id="<?php echo intval($anime['id']); ?>" data-nonce="<?php echo esc_attr($rating_nonce); ?>" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
          <div class="rating-title">Rate this anime</div>
          <div class="rating-stars" aria-label="Star rating">
            <?php for ($i=1; $i<=10; $i++): ?>
              <button type="button" class="rating-star" data-value="<?php echo $i; ?>" aria-label="<?php echo $i; ?> stars">&#9733;</button>
            <?php endfor; ?>
          </div>
          <div class="rating-meta">Average: <span class="rating-avg"><?php echo esc_html($rating_stats['avg']); ?></span> / 10 (<?php echo esc_html($rating_stats['count']); ?> votes)</div>
        </div>

        <?php
          $guide_items = [];
          $relations_map = [];
          $payload = !empty($anime['source_payload_json']) ? json_decode($anime['source_payload_json'], true) : null;
          $relations = is_array($payload) ? ($payload['relations'] ?? []) : [];
          $related_ids = [];

          if (is_array($relations)) {
            foreach ($relations as $rel) {
              if (!is_array($rel)) continue;
              $rel_name = $rel['relation'] ?? 'Related';
              $entries = $rel['entry'] ?? [];
              if (!is_array($entries)) continue;
              foreach ($entries as $entry) {
                if (!is_array($entry)) continue;
                $mal_id = $entry['mal_id'] ?? null;
                $name = $entry['name'] ?? null;
                $type = $entry['type'] ?? null;
                if (!$name) continue;
                $relations_map[$rel_name][] = [
                  'mal_id' => $mal_id ? intval($mal_id) : null,
                  'name' => $name,
                  'type' => $type,
                ];
                if ($mal_id) $related_ids[] = intval($mal_id);
              }
            }
          }

          $related_links = [];
          if (!empty($related_ids)) {
            $related_ids = array_values(array_unique($related_ids));
            $placeholders = implode(',', array_fill(0, count($related_ids), '%d'));
            $query = $wpdb->prepare(
              "SELECT source_id, slug, title_english, title_romaji FROM {$wpdb->prefix}am_anime WHERE source='MAL' AND source_id IN ($placeholders)",
              ...$related_ids
            );
            $rows = $wpdb->get_results($query, ARRAY_A);
            foreach ($rows as $row) {
              $related_links[intval($row['source_id'])] = $row;
            }
          }

          $order = ['Prequel','Sequel','Side story','Spin-off','Alternative version','Alternative setting','Summary','Other'];
        ?>

        <div class="guide-block">
          <div class="row-title">
            <h2>Watch guide</h2>
          </div>
          <?php if (empty($relations_map)): ?>
            <p class="guide-sub">Watch in release order. OVAs and movies can be watched after the main series unless marked as prequels.</p>
          <?php else: ?>
            <p class="guide-sub">Suggested order based on official MAL relations.</p>
            <ol class="guide-list">
              <?php if (!empty($relations_map['Prequel'])): ?>
                <li><strong>Prequel</strong>: 
                  <?php
                    $parts = [];
                    foreach ($relations_map['Prequel'] as $rel) {
                      $label = $rel['name'];
                      if (!empty($rel['type'])) $label .= ' (' . $rel['type'] . ')';
                      if (!empty($rel['mal_id']) && isset($related_links[$rel['mal_id']])) {
                        $slug = $related_links[$rel['mal_id']]['slug'];
                        $parts[] = '<a href="' . esc_url(home_url('/anime/' . $slug . '/')) . '">' . esc_html($label) . '</a>';
                      } else {
                        $parts[] = esc_html($label);
                      }
                    }
                    echo implode(', ', $parts);
                  ?>
                </li>
              <?php endif; ?>
              <li><strong>Main series</strong>: <?php echo esc_html($title); ?></li>
              <?php foreach ($order as $rel_label): ?>
                <?php if ($rel_label === 'Prequel') continue; ?>
                <?php if (!empty($relations_map[$rel_label])): ?>
                  <li><strong><?php echo esc_html($rel_label); ?></strong>:
                    <?php
                      $parts = [];
                      foreach ($relations_map[$rel_label] as $rel) {
                        $label = $rel['name'];
                        if (!empty($rel['type'])) $label .= ' (' . $rel['type'] . ')';
                        if (!empty($rel['mal_id']) && isset($related_links[$rel['mal_id']])) {
                          $slug = $related_links[$rel['mal_id']]['slug'];
                          $parts[] = '<a href="' . esc_url(home_url('/anime/' . $slug . '/')) . '">' . esc_html($label) . '</a>';
                        } else {
                          $parts[] = esc_html($label);
                        }
                      }
                      echo implode(', ', $parts);
                    ?>
                  </li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </div>

        <div class="row" style="margin-top:24px;">
          <div class="row-title">
            <h2>Episodes</h2>
          </div>
          <?php if (!$eps): ?>
            <p class="day-empty">No episodes yet. In WP Admin: Animemori -> Generate Episodes.</p>
          <?php else: ?>
            <ol class="schedule-list">
              <?php foreach ($eps as $e):
                $dt = null;
                if (!empty($e['air_datetime_utc'])) {
                  $dt = new DateTime($e['air_datetime_utc'], new DateTimeZone('UTC'));
                  $dt->setTimezone($userTz);
                }
              ?>
                <li class="schedule-item">
                  <div>
                    <div class="schedule-time">Episode <?php echo intval($e['episode_number']); ?></div>
                    <div class="schedule-title">
                      <span class="schedule-ep"><?php echo $dt ? esc_html($dt->format('Y-m-d')) : 'Date TBD'; ?></span>
                      <span class="tag"><?php echo esc_html($e['status']); ?></span>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </div>

        <?php
          $comments = function_exists('animemori_get_comments') ? animemori_get_comments($anime['id'], 50) : [];
          $comment_redirect = home_url($_SERVER['REQUEST_URI'] ?? '/');
        ?>
        <div class="comments-block">
          <div class="row-title">
            <h2>Comments</h2>
          </div>
          <?php if (!empty($_GET['am_comment_ok'])): ?>
            <p class="comment-status success">Thanks! Your comment was posted.</p>
          <?php elseif (!empty($_GET['am_comment_err'])): ?>
            <p class="comment-status error"><?php echo esc_html($_GET['am_comment_err']); ?></p>
          <?php endif; ?>

          <?php if ($comments): ?>
            <ul class="comment-list">
              <?php foreach ($comments as $c): ?>
                <li class="comment-item">
                  <div class="comment-meta">
                    <span class="comment-author"><?php echo esc_html($c['author_name'] ?: 'Anonymous'); ?></span>
                    <span class="comment-date"><?php echo esc_html(date('Y-m-d', strtotime($c['created_at']))); ?></span>
                  </div>
                  <div class="comment-body"><?php echo esc_html($c['content']); ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="day-empty">No comments yet. Be the first.</p>
          <?php endif; ?>

          <form class="comment-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('animemori_comment'); ?>
            <input type="hidden" name="action" value="animemori_anime_comment">
            <input type="hidden" name="anime_id" value="<?php echo intval($anime['id']); ?>">
            <input type="hidden" name="redirect" value="<?php echo esc_url($comment_redirect); ?>">

            <div class="comment-grid">
              <input type="text" name="author_name" placeholder="Name (optional)">
              <input type="email" name="author_email" placeholder="Email (optional)">
            </div>
            <textarea name="content" rows="4" placeholder="Write your comment..." required></textarea>
            <label class="comment-anon">
              <input type="checkbox" name="anonymous" value="1"> Post as anonymous
            </label>
            <div class="comment-actions">
              <button class="btn" type="submit">Post comment</button>
            </div>
            <p class="comment-note">By posting, you agree to our Privacy Policy and Security Policy.</p>
          </form>
        </div>
      </div>

      <aside class="sidebar">
        <details class="widget" open>
          <summary>Genres</summary>
          <div class="widget-body">
            <?php if (!empty($genre_names)): ?>
              <div class="genre-list">
                <?php foreach ($genre_names as $g): ?>
                  <a class="genre-pill" href="<?php echo esc_url(home_url('/anime/?genre=' . urlencode($g))); ?>"><?php echo esc_html($g); ?></a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="day-empty">No genres listed.</p>
            <?php endif; ?>
          </div>
        </details>

        <details class="widget" open>
          <summary><?php echo esc_html($reco_label); ?></summary>
          <div class="widget-body">
            <?php if (!empty($recommendations)): ?>
              <div class="reco-list">
                <?php foreach ($recommendations as $r):
                  $rt = animemori_pick_title($r['title_english'] ?? null, $r['title_romaji'] ?? null, 'Anime');
                ?>
                  <a class="reco-item" href="<?php echo esc_url(home_url('/anime/' . $r['slug'] . '/')); ?>">
                    <?php if (!empty($r['cover_image_url'])): ?>
                      <img class="reco-thumb" src="<?php echo esc_url($r['cover_image_url']); ?>" alt="<?php echo esc_attr($rt); ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                    <div>
                      <div class="reco-title"><?php echo esc_html($rt); ?></div>
                      <div class="reco-meta"><?php echo esc_html($r['year'] ?? ''); ?></div>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="day-empty">No recommendations yet.</p>
            <?php endif; ?>
          </div>
        </details>
      </aside>
    </div>
  </section>
<?php endif; ?>

<script>
(function () {
  var block = document.querySelector('.rating-block');
  if (!block) return;
  var ajaxUrl = block.getAttribute('data-ajax-url');
  var nonce = block.getAttribute('data-nonce');
  var animeId = block.getAttribute('data-anime-id');
  var stars = block.querySelectorAll('.rating-star');
  var avgEl = block.querySelector('.rating-avg');

  function setActive(count) {
    stars.forEach(function (star, idx) {
      if (idx < count) {
        star.classList.add('is-active');
      } else {
        star.classList.remove('is-active');
      }
    });
  }

  block.addEventListener('click', function (e) {
    var btn = e.target.closest('.rating-star');
    if (!btn) return;
    var rating = btn.getAttribute('data-value');

    var data = new URLSearchParams();
    data.append('action', 'animemori_rate');
    data.append('nonce', nonce);
    data.append('anime_id', animeId);
    data.append('rating', rating);

    fetch(ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: data.toString()
    })
    .then(function (res) { return res.json(); })
    .then(function (res) {
      if (!res || !res.success) return;
      if (avgEl && res.data && res.data.avg !== undefined) {
        avgEl.textContent = res.data.avg;
      }
      setActive(parseInt(rating, 10));
    });
  });
})();
</script>

<?php get_footer(); ?>
