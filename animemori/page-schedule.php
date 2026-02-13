<?php
get_header();
?>

<section class="hero">
  <div class="container">
    <p class="hero-meta">Schedule</p>
    <h1 class="hero-title">Weekly schedule</h1>
    <p class="hero-sub">Fresh episode times based on the latest broadcast data.</p>
    <div class="hero-actions">
      <a class="btn" href="<?php echo esc_url(home_url('/upcoming/')); ?>">Upcoming releases</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container layout">
    <div>
      <div class="row">
        <div class="row-title">
          <h2>This week</h2>
        </div>
        <div class="row-block">
          <?php echo do_shortcode('[animemori_schedule days="7"]'); ?>
        </div>
      </div>
    </div>
    <?php get_sidebar(); ?>
  </div>
</section>

<?php get_footer(); ?>
