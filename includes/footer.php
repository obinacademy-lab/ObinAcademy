  </main>

  <?php
    // Page links live only here now — the top nav was trimmed down to just
    // Log In / Become a Creator, so this is the site's one remaining way to
    // reach Home/Courses/Stories/About/Contact from anywhere on the site.
    $footerNavLinks = [
        '/index.php' => 'Home',
        '/courses/index.php' => 'Explore Courses',
        '/stories.php' => 'Stories',
        '/about.php' => 'About Us',
        '/contact.php' => 'Contact',
    ];
  ?>
  <footer class="site-footer site-footer-compact">
    <div class="starfield" aria-hidden="true"></div>
    <div class="footer-glow footer-glow-a" aria-hidden="true"></div>
    <div class="footer-glow footer-glow-b" aria-hidden="true"></div>

    <div class="container">
      <nav class="footer-legal-links" style="justify-content:center; flex-wrap:wrap; padding:22px 0 0;">
        <?php foreach ($footerNavLinks as $href => $label): ?>
          <a href="<?= e(base_url($href)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="footer-bottom footer-bottom-compact">
        <?php render_logo(); ?>
        <p class="made-for">&copy; <?= date('Y') ?> Obin Academy</p>
        <a href="https://wa.me/256775361998?text=<?= urlencode('Hi, I have a question about Obin Academy') ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-btn">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2zm5.86 14.02c-.25.7-1.25 1.29-1.98 1.44-.53.11-1.22.2-3.55-.76-2.98-1.24-4.89-4.24-5.04-4.44-.15-.2-1.21-1.6-1.21-3.06 0-1.46.76-2.17 1.03-2.47.27-.3.6-.37.8-.37h.57c.18 0 .43-.07.67.51.25.6.85 2.06.92 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.32.38-.45.51-.15.15-.31.31-.13.62.18.3.8 1.32 1.72 2.14 1.18 1.05 2.18 1.38 2.5 1.53.32.15.5.13.68-.08.18-.2.78-.9.99-1.21.2-.3.4-.25.68-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.75-.18 1.45z"/></svg>
          Chat with us on WhatsApp
          <span class="ping"></span>
        </a>
      </div>
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
</body>
</html>
