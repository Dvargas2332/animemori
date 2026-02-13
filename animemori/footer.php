<?php
?>
</main>
<footer class="site-footer">
  <div class="container">
    <p><?php echo esc_html(get_bloginfo('name')); ?> - Weekly anime calendar, updated automatically.</p>
    <p style="margin-top:6px; font-size:11px;">
      <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Privacy Policy</a>
      |
      <a href="<?php echo esc_url(home_url('/security-policy/')); ?>">Security Policy</a>
      |
      <a href="<?php echo esc_url(home_url('/terms/')); ?>">Terms</a>
    </p>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
