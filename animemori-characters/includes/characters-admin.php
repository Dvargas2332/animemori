<?php
if (!defined('ABSPATH')) exit;

function animemori_chars_user_can_manage() {
  return current_user_can('manage_options');
}

function animemori_chars_register_admin() {
  if (!is_admin()) return;
  add_action('admin_menu', 'animemori_chars_admin_menu');
  add_action('admin_post_animemori_chars_save_settings', 'animemori_chars_save_settings');
  add_action('admin_post_animemori_chars_import', 'animemori_chars_handle_import');
}

function animemori_chars_admin_menu() {
  add_submenu_page(
    'animemori',
    'Characters',
    'Characters',
    'manage_options',
    'animemori-characters',
    'animemori_chars_admin_page'
  );
}

function animemori_chars_admin_page() {
  if (!animemori_chars_user_can_manage()) return;
  $settings = animemori_chars_get_settings();
  $last = get_option('animemori_chars_last_run');
  ?>
  <div class="wrap">
    <h1>Animemori Characters (AniList)</h1>

    <?php if (!empty($_GET['am_msg'])): ?>
      <div class="notice notice-success"><p><?php echo esc_html($_GET['am_msg']); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['am_err'])): ?>
      <div class="notice notice-error"><p><?php echo esc_html($_GET['am_err']); ?></p></div>
    <?php endif; ?>

    <h2>Settings</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('animemori_chars_save_settings'); ?>
      <input type="hidden" name="action" value="animemori_chars_save_settings" />
      <table class="form-table">
        <tr>
          <th scope="row">AniList endpoint</th>
          <td><input type="text" name="endpoint" value="<?php echo esc_attr($settings['endpoint']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row">Auth token (optional)</th>
          <td><input type="text" name="auth_token" value="<?php echo esc_attr($settings['auth_token']); ?>" class="regular-text" /></td>
        </tr>
        <tr>
          <th scope="row">Per page</th>
          <td><input type="number" name="per_page" min="1" max="50" value="<?php echo esc_attr($settings['per_page']); ?>" /></td>
        </tr>
        <tr>
          <th scope="row">Max pages</th>
          <td><input type="number" name="max_pages" min="1" max="10" value="<?php echo esc_attr($settings['max_pages']); ?>" /></td>
        </tr>
        <tr>
          <th scope="row">Max per run</th>
          <td><input type="number" name="max_per_run" min="1" max="200" value="<?php echo esc_attr($settings['max_per_run']); ?>" /></td>
        </tr>
        <tr>
          <th scope="row">Sleep (microseconds)</th>
          <td><input type="number" name="sleep_us" min="0" value="<?php echo esc_attr($settings['sleep_us']); ?>" /></td>
        </tr>
        <tr>
          <th scope="row">Auto import</th>
          <td><label><input type="checkbox" name="auto_enabled" value="1" <?php checked(!empty($settings['auto_enabled'])); ?> /> Enabled</label></td>
        </tr>
      </table>
      <?php submit_button('Save settings'); ?>
    </form>

    <h2>Manual import</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('animemori_chars_import'); ?>
      <input type="hidden" name="action" value="animemori_chars_import" />
      <table class="form-table">
        <tr>
          <th scope="row">Anime ID (internal)</th>
          <td><input type="number" name="anime_id" min="1" /></td>
        </tr>
        <tr>
          <th scope="row">MAL ID</th>
          <td><input type="number" name="mal_id" min="1" /></td>
        </tr>
      </table>
      <?php submit_button('Import characters'); ?>
    </form>

    <h2>Last auto import</h2>
    <?php if (!empty($last) && is_array($last)): ?>
      <p>Ran at: <?php echo esc_html($last['ran_at'] ?? '-'); ?></p>
      <p>Imported: <?php echo intval($last['imported'] ?? 0); ?> | Linked: <?php echo intval($last['linked'] ?? 0); ?></p>
      <?php if (!empty($last['errors'])): ?>
        <p><strong>Errors:</strong></p>
        <ul>
          <?php foreach ($last['errors'] as $e): ?>
            <li><?php echo esc_html('anime_id=' . ($e['anime_id'] ?? '-') . ' mal_id=' . ($e['mal_id'] ?? '-') . ' error=' . ($e['error'] ?? '')); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php else: ?>
      <p>No auto import log yet.</p>
    <?php endif; ?>
  </div>
  <?php
}

function animemori_chars_save_settings() {
  if (!animemori_chars_user_can_manage()) wp_die('Unauthorized');
  check_admin_referer('animemori_chars_save_settings');

  $settings = animemori_chars_get_settings();
  $settings['endpoint'] = esc_url_raw($_POST['endpoint'] ?? $settings['endpoint']);
  $settings['auth_token'] = sanitize_text_field($_POST['auth_token'] ?? '');
  $settings['per_page'] = max(1, min(50, intval($_POST['per_page'] ?? $settings['per_page'])));
  $settings['max_pages'] = max(1, min(10, intval($_POST['max_pages'] ?? $settings['max_pages'])));
  $settings['max_per_run'] = max(1, min(200, intval($_POST['max_per_run'] ?? $settings['max_per_run'])));
  $settings['sleep_us'] = max(0, intval($_POST['sleep_us'] ?? $settings['sleep_us']));
  $settings['auto_enabled'] = !empty($_POST['auto_enabled']) ? 1 : 0;

  update_option('animemori_chars_settings', $settings, false);
  wp_redirect(add_query_arg(['page' => 'animemori-characters', 'am_msg' => 'Settings saved'], admin_url('admin.php')));
  exit;
}

function animemori_chars_handle_import() {
  if (!animemori_chars_user_can_manage()) wp_die('Unauthorized');
  check_admin_referer('animemori_chars_import');

  $anime_id = intval($_POST['anime_id'] ?? 0);
  $mal_id = intval($_POST['mal_id'] ?? 0);

  if ($anime_id > 0 && $mal_id <= 0) {
    $row = animemori_chars_fetch_anime_row($anime_id);
    if (!$row || empty($row['source_id'])) {
      wp_redirect(add_query_arg(['page' => 'animemori-characters', 'am_err' => 'Anime not found or missing MAL ID'], admin_url('admin.php')));
      exit;
    }
    $mal_id = intval($row['source_id']);
  }

  if ($mal_id <= 0) {
    wp_redirect(add_query_arg(['page' => 'animemori-characters', 'am_err' => 'Provide an Anime ID or MAL ID'], admin_url('admin.php')));
    exit;
  }

  $res = animemori_chars_import_by_mal_id($mal_id, $anime_id);
  if (is_wp_error($res)) {
    wp_redirect(add_query_arg(['page' => 'animemori-characters', 'am_err' => $res->get_error_message()], admin_url('admin.php')));
    exit;
  }

  $msg = 'Imported=' . intval($res['imported'] ?? 0) . ' linked=' . intval($res['linked'] ?? 0);
  wp_redirect(add_query_arg(['page' => 'animemori-characters', 'am_msg' => $msg], admin_url('admin.php')));
  exit;
}
