  </main>

  <?php pl_render_footer($user ?? null); ?>

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
