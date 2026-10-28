  </main>

  <?php // The same slim bar obinacademy.com uses: logo on the left, links, copyright on the right. (Community = the WhatsApp community.) ?>
  <footer class="site-footer-minimal">
    <div class="container footer-minimal-inner">
      <?php render_logo(true); ?>
      <nav class="footer-minimal-links" aria-label="Footer">
        <a href="<?= e(base_url('index.php#campuses')) ?>">Campuses</a>
        <a href="<?= e(base_url('teach.php')) ?>">Teach</a>
        <a href="<?= e(base_url('become-affiliate.php')) ?>">Earn</a>
        <a href="<?= e(base_url('about.php')) ?>">About us</a>
        <a href="<?= e(base_url('contact.php')) ?>">Contact us</a>
        <a href="<?= e(base_url('terms.php')) ?>">Terms</a>
        <a href="<?= e(base_url('privacy.php')) ?>">Privacy</a>
        <a href="<?= e(WHATSAPP_COMMUNITY_URL) ?>" target="_blank" rel="noopener noreferrer">Community</a>
        <a href="#" data-install-app hidden>Install the app</a>
        <?php if (!empty($user)): ?>
          <a href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a>
        <?php else: ?>
          <a href="<?= e(base_url('login.php')) ?>">Sign in</a>
        <?php endif; ?>
      </nav>
      <p class="footer-minimal-copy">&copy; <?= date('Y') ?> Obin Academy</p>
    </div>
  </footer>

  <?php require __DIR__ . '/consent_banner.php'; ?>
  <?php require __DIR__ . '/lead_popup.php'; ?>

  <script>
    window.OBIN_BASE_URL = <?= json_encode(rtrim(base_url(''), '/')) ?>;
    window.OBIN_LOGGED_IN = <?= !empty($user) ? 'true' : 'false' ?>;
  </script>
  <script src="<?= e(versioned_asset('assets/js/main.js')) ?>"></script>
  <script src="<?= e(versioned_asset('assets/js/cookie-consent.js')) ?>"></script>
  <script src="<?= e(versioned_asset('assets/js/visitor-tracker.js')) ?>"></script>
  <script src="<?= e(versioned_asset('assets/js/lead-capture.js')) ?>"></script>
  <script src="<?= e(versioned_asset('assets/js/notifications.js')) ?>"></script>
<?php if (!empty($user)): ?><script src="<?= e(versioned_asset('assets/js/chat-alerts.js')) ?>" data-base="<?= e(rtrim(base_url(''), '/')) ?>" defer></script><?php endif; ?>
</body>
</html>
