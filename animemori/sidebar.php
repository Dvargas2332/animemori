<?php
if (!defined('ABSPATH')) exit;
?>
<aside class="sidebar">
  <details class="widget" open>
    <summary>Search</summary>
    <div class="widget-body">
      <form method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <input type="search" name="s" placeholder="Search anime" value="<?php echo esc_attr(get_search_query()); ?>">
        <button type="submit">Search</button>
      </form>
    </div>
  </details>

  <?php if (is_front_page()): ?>
    <details class="widget">
      <summary>Quick links</summary>
      <div class="widget-body">
        <p><a href="<?php echo esc_url(home_url('/schedule/')); ?>">Weekly schedule</a></p>
        <p><a href="<?php echo esc_url(home_url('/upcoming/')); ?>">Upcoming releases</a></p>
        <p><a href="<?php echo esc_url(home_url('/anime/')); ?>">All anime</a></p>
      </div>
    </details>
  <?php endif; ?>

  <details class="widget">
    <summary>Weekly schedule</summary>
    <div class="widget-body">
      <?php echo do_shortcode('[animemori_schedule days="7"]'); ?>
    </div>
  </details>

  <details class="widget">
    <summary>Upcoming releases</summary>
    <div class="widget-body">
      <?php echo do_shortcode('[animemori_upcoming hours="72"]'); ?>
    </div>
  </details>
</aside>
