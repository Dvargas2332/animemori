<?php

if (!defined('ABSPATH')) exit;



function animemori_core_register_admin() {

  if (is_admin()) {

    add_action('admin_menu', 'animemori_admin_menu');

    add_action('admin_post_animemori_import_anime', 'animemori_handle_import_anime');

    add_action('admin_post_animemori_generate_episodes', 'animemori_handle_generate_episodes');

    add_action('admin_post_animemori_run_auto_import', 'animemori_handle_run_auto_import');

    add_action('admin_notices', 'animemori_admin_notices');

  }

}



function animemori_admin_menu() {

  add_menu_page(

    'Animemori',

    'Animemori',

    'manage_options',

    'animemori',

    'animemori_admin_page_import',

    'dashicons-admin-generic'

  );



  add_submenu_page(

    'animemori',

    'Import Anime',

    'Import Anime',

    'manage_options',

    'animemori',

    'animemori_admin_page_import'

  );



  add_submenu_page(

    'animemori',

    'Generate Episodes',

    'Generate Episodes',

    'manage_options',

    'animemori-generate',

    'animemori_admin_page_generate'

  );



  add_submenu_page(

    'animemori',

    'Auto Import Log',

    'Auto Import Log',

    'manage_options',

    'animemori-auto-import',

    'animemori_admin_page_auto_import'

  );

}



function animemori_admin_notices() {

  if (!current_user_can('manage_options')) return;

  $auto = get_option('animemori_last_auto_import');

  $refresh = get_option('animemori_last_refresh');



  $refresh_errors = 0;

  $refresh_skipped = 0;

  if (is_array($refresh)) {

    $refresh_errors = isset($refresh['errors']) ? count($refresh['errors']) : 0;

    $refresh_skipped = isset($refresh['skipped']) ? count($refresh['skipped']) : 0;

  }



  $cut_total = 0;

  $err_total = 0;

  if (is_array($auto) && !empty($auto['results'])) {

    foreach ($auto['results'] as $res) {

      if (!is_array($res)) continue;

      $cut_total += intval($res['cut_total'] ?? 0);

      $err_total += intval($res['errors'] ?? 0);

    }

  }



  if ($cut_total <= 0 && $err_total <= 0 && $refresh_errors <= 0 && $refresh_skipped <= 0) return;



  $msg = 'Animemori alerts: ';

  if ($err_total > 0) $msg .= 'import errors=' . $err_total . ' ';

  if ($cut_total > 0) $msg .= 'import cuts=' . $cut_total . ' ';

  if ($refresh_errors > 0) $msg .= 'refresh errors=' . $refresh_errors . ' ';

  if ($refresh_skipped > 0) $msg .= 'refresh skipped=' . $refresh_skipped . ' ';

  $msg .= 'View details in Auto Import Log.';

  $url = admin_url('admin.php?page=animemori-auto-import');

  ?>

  <div class="notice notice-warning">

    <p><?php echo esc_html($msg); ?> <a href="<?php echo esc_url($url); ?>">Open log</a></p>

  </div>

  <?php

}



function animemori_admin_page_import() {

  if (!current_user_can('manage_options')) return;

  ?>

  <div class="wrap">

    <h1>Animemori - Import Anime</h1>

    <p>Imports anime data from a public API. It stores the raw payload JSON for future use.</p>



    <?php if (!empty($_GET['am_msg'])): ?>

      <div class="notice notice-success"><p><?php echo esc_html($_GET['am_msg']); ?></p></div>

    <?php endif; ?>

    <?php if (!empty($_GET['am_err'])): ?>

      <div class="notice notice-error"><p><?php echo esc_html($_GET['am_err']); ?></p></div>

    <?php endif; ?>



    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

      <?php wp_nonce_field('animemori_import_anime'); ?>

      <input type="hidden" name="action" value="animemori_import_anime" />

      <table class="form-table">

        <tr>

          <th scope="row"><label for="source_id">Source ID</label></th>

          <td><input name="source_id" id="source_id" type="number" min="1" required /></td>

        </tr>

      </table>

      <?php submit_button('Import'); ?>

    </form>

  </div>

  <?php

}



function animemori_admin_page_generate() {

  if (!current_user_can('manage_options')) return;

  ?>

  <div class="wrap">

    <h1>Animemori - Generate Episodes</h1>

    <p>Generates scheduled episodes based on JST broadcast schedule. Keeps a future buffer to power the calendar.</p>



    <?php if (!empty($_GET['am_msg'])): ?>

      <div class="notice notice-success"><p><?php echo esc_html($_GET['am_msg']); ?></p></div>

    <?php endif; ?>

    <?php if (!empty($_GET['am_err'])): ?>

      <div class="notice notice-error"><p><?php echo esc_html($_GET['am_err']); ?></p></div>

    <?php endif; ?>



    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

      <?php wp_nonce_field('animemori_generate_episodes'); ?>

      <input type="hidden" name="action" value="animemori_generate_episodes" />

      <table class="form-table">

        <tr>

          <th scope="row"><label for="anime_id">Anime ID (internal)</label></th>

          <td><input name="anime_id" id="anime_id" type="number" min="1" required /></td>

        </tr>

        <tr>

          <th scope="row"><label for="buffer_weeks">Buffer (weeks)</label></th>

          <td><input name="buffer_weeks" id="buffer_weeks" type="number" min="1" max="52" value="8" /></td>

        </tr>

      </table>

      <?php submit_button('Generate'); ?>

    </form>

  </div>

  <?php

}



function animemori_admin_page_auto_import() {

  if (!current_user_can('manage_options')) return;

  $data = get_option('animemori_last_auto_import');

  $results = is_array($data['results'] ?? null) ? $data['results'] : [];

  $ran_at = $data['ran_at'] ?? null;



  $labels = [

    'in_season' => 'In Season',

    'upcoming' => 'Upcoming',

    'finished' => 'Finished',

    'cancelled' => 'Cancelled',

  ];

  ?>

  <div class="wrap">

    <h1>Animemori - Auto Import Log</h1>

    <p>Last run: <?php echo esc_html($ran_at ?: '-'); ?></p>



    <?php if (!empty($_GET['am_msg'])): ?>

      <div class="notice notice-success"><p><?php echo esc_html($_GET['am_msg']); ?></p></div>

    <?php endif; ?>

    <?php if (!empty($_GET['am_err'])): ?>

      <div class="notice notice-error"><p><?php echo esc_html($_GET['am_err']); ?></p></div>

    <?php endif; ?>



    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0;">

      <?php wp_nonce_field('animemori_run_auto_import'); ?>

      <input type="hidden" name="action" value="animemori_run_auto_import" />

      <?php submit_button('Run Auto Import Now', 'secondary', 'submit', false); ?>

    </form>



    <?php if (!$results): ?>

      <p>No auto-import log yet.</p>

    <?php else: ?>

      <?php foreach ($labels as $key => $label):

        $res = $results[$key] ?? null;

        if (!is_array($res)) continue;

        $cut_list = $res['cut'] ?? [];

      ?>

        <h2><?php echo esc_html($label); ?></h2>

        <ul>

          <li>Imported: <?php echo intval($res['imported'] ?? 0); ?></li>

          <li>Skipped: <?php echo intval($res['skipped'] ?? 0); ?></li>

          <li>Errors: <?php echo intval($res['errors'] ?? 0); ?></li>

          <li>Cuts: <?php echo intval($res['cut_total'] ?? 0); ?></li>

        </ul>



        <?php if (!empty($cut_list)): ?>

          <p><strong>Cut items (first <?php echo count($cut_list); ?>):</strong></p>

          <ul>

            <?php foreach ($cut_list as $c): ?>

              <li><?php echo esc_html(($c['title'] ?? 'Untitled') . ' (MAL ' . ($c['mal_id'] ?? '-') . ')'); ?></li>

            <?php endforeach; ?>

          </ul>

        <?php endif; ?>

      <?php endforeach; ?>

    <?php endif; ?>



    <?php

      $refresh = get_option('animemori_last_refresh');

      $refresh_errors = is_array($refresh['errors'] ?? null) ? $refresh['errors'] : [];

      $refresh_skipped = is_array($refresh['skipped'] ?? null) ? $refresh['skipped'] : [];

      $refresh_ran_at = $refresh['ran_at'] ?? null;

    ?>

    <h2>Daily Refresh Log</h2>

    <p>Last run: <?php echo esc_html($refresh_ran_at ?: '-'); ?></p>

    <ul>

      <li>Errors: <?php echo intval(count($refresh_errors)); ?></li>

      <li>Skipped: <?php echo intval(count($refresh_skipped)); ?></li>

    </ul>



    <?php if (!empty($refresh_errors)): ?>

      <p><strong>Refresh errors:</strong></p>

      <ul>

        <?php foreach (array_slice($refresh_errors, 0, 50) as $e): ?>

          <li><?php echo esc_html(($e['title'] ?? $e['slug'] ?? 'Anime') . ' (ID ' . ($e['anime_id'] ?? '-') . '): ' . ($e['reason'] ?? 'error')); ?></li>

        <?php endforeach; ?>

      </ul>

    <?php endif; ?>



    <?php if (!empty($refresh_skipped)): ?>

      <p><strong>Refresh skipped:</strong></p>

      <ul>

        <?php foreach (array_slice($refresh_skipped, 0, 50) as $s): ?>

          <li><?php echo esc_html(($s['title'] ?? $s['slug'] ?? 'Anime') . ' (ID ' . ($s['anime_id'] ?? '-') . '): ' . ($s['reason'] ?? 'skipped')); ?></li>

        <?php endforeach; ?>

      </ul>

    <?php endif; ?>

  </div>

  <?php

}



function animemori_handle_import_anime() {

  if (!current_user_can('manage_options')) wp_die('Unauthorized');

  check_admin_referer('animemori_import_anime');



  $source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;

  if ($source_id <= 0) {

    wp_redirect(add_query_arg(['page'=>'animemori','am_err'=>'Invalid MAL ID'], admin_url('admin.php')));

    exit;

  }



  $res = animemori_import_anime_from_mal($source_id);

  if (is_wp_error($res)) {

    wp_redirect(add_query_arg(['page'=>'animemori','am_err'=>$res->get_error_message()], admin_url('admin.php')));

    exit;

  }



  $generated = null;
  if (!empty($res['status']) && in_array($res['status'], ['AIRING','UPCOMING'], true)) {
    $gen = animemori_generate_episodes_for_anime(intval($res['anime_id']), 8);
    if (!is_wp_error($gen)) {
      $generated = $gen['generated'] ?? null;
    }
  }

  $msg = 'Imported. Internal anime_id=' . $res['anime_id'] . ' slug=' . $res['slug'];
  if ($generated !== null) {
    $msg .= ' episodes generated=' . $generated;
  }

  wp_redirect(add_query_arg(['page'=>'animemori','am_msg'=>$msg], admin_url('admin.php')));

  exit;

}



function animemori_handle_run_auto_import() {

  if (!current_user_can('manage_options')) wp_die('Unauthorized');

  check_admin_referer('animemori_run_auto_import');



  $res = animemori_auto_import_job();

  if (is_wp_error($res)) {

    wp_redirect(add_query_arg(['page'=>'animemori-auto-import','am_err'=>$res->get_error_message()], admin_url('admin.php')));

  } else {

    wp_redirect(add_query_arg(['page'=>'animemori-auto-import','am_msg'=>'Auto import completed.'], admin_url('admin.php')));

  }

  exit;

}



function animemori_handle_generate_episodes() {

  if (!current_user_can('manage_options')) wp_die('Unauthorized');

  check_admin_referer('animemori_generate_episodes');



  $anime_id = isset($_POST['anime_id']) ? intval($_POST['anime_id']) : 0;

  $buffer = isset($_POST['buffer_weeks']) ? intval($_POST['buffer_weeks']) : 8;



  $res = animemori_generate_episodes_for_anime($anime_id, $buffer);

  if (is_wp_error($res)) {

    wp_redirect(add_query_arg(['page'=>'animemori-generate','am_err'=>$res->get_error_message()], admin_url('admin.php')));

    exit;

  }

  $msg = 'Done. Generated/updated=' . ($res['generated'] ?? 0);

  wp_redirect(add_query_arg(['page'=>'animemori-generate','am_msg'=>$msg], admin_url('admin.php')));

  exit;

}

