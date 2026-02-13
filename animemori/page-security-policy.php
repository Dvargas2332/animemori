<?php
get_header();
?>

<section class="hero">
  <div class="container">
    <p class="hero-meta">Policy</p>
    <h1 class="hero-title">Security Policy</h1>
    <p class="hero-sub">How we protect the site and user data.</p>
  </div>
</section>

<section class="section">
  <div class="container layout">
    <div class="detail-card" style="grid-template-columns: 1fr;">
      <div>
        <h2>1. Site protection</h2>
        <p>We maintain server and application security updates, monitor for abuse, and limit automated requests.</p>

        <h2>2. Data integrity</h2>
        <p>We sanitize input to reduce spam or malicious content and store only the data needed to run the service.</p>

        <h2>3. Reporting issues</h2>
        <p>If you find a security issue, please contact us immediately. We will review and respond as quickly as possible.</p>
      </div>
    </div>

    <?php get_sidebar(); ?>
  </div>
</section>

<?php get_footer(); ?>

