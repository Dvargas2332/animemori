<?php
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php $theme = wp_get_theme(); ?>
  <link rel="stylesheet" href="<?php echo esc_url(get_stylesheet_uri() . '?v=' . $theme->get('Version')); ?>">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
  <div class="container header-row">
    <a class="site-title" href="<?php echo esc_url(home_url('/')); ?>">
      <?php
      $logo_mark_file = get_template_directory() . '/assets/logo-mark.png';
      $logo_mascot_file = get_template_directory() . '/assets/logo-mascot.png';
      $logo_mark_url = get_template_directory_uri() . '/assets/logo-mark.png';
      $logo_mascot_url = get_template_directory_uri() . '/assets/logo-mascot.png';
      ?>
      <?php if (has_custom_logo()) : ?>
        <?php the_custom_logo(); ?>
      <?php elseif (file_exists($logo_mark_file)) : ?>
        <img class="brand-logo" src="<?php echo esc_url($logo_mark_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
      <?php elseif (file_exists($logo_mascot_file)) : ?>
        <img class="brand-logo" src="<?php echo esc_url($logo_mascot_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
      <?php endif; ?>
      <span class="brand-name"><?php echo esc_html(get_bloginfo('name')); ?></span>
    </a>
    <nav class="site-nav">
      <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
      <a href="<?php echo esc_url(home_url('/schedule/')); ?>">Schedule</a>
      <a href="<?php echo esc_url(home_url('/upcoming/')); ?>">Upcoming</a>
      <a href="<?php echo esc_url(home_url('/anime/')); ?>">Anime</a>
    </nav>
    <div class="header-actions">
      <a class="btn ghost" href="<?php echo esc_url(home_url('/schedule/')); ?>">View schedule</a>
    </div>
  </div>
</header>
<main class="site-main">
