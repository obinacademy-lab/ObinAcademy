  </main>

  <?php
    require_once __DIR__ . '/data.php';
    $footerStats = get_platform_stats();
    $footerLearnLinks = [
        '/courses/index.php' => 'Explore Courses',
        '/skills.php' => 'Browse by Industry',
        '/stories.php' => 'Success Stories',
    ];
    $footerTeachLinks = [
        '/become-creator.php' => 'Become a Creator',
        '/become-affiliate.php' => 'Become an Affiliate',
    ];
    $footerCompanyLinks = [
        '/about.php' => 'About Us',
        '/contact.php' => 'Contact',
    ];
    $footerLegalLinks = [
        '/privacy.php' => 'Privacy Policy',
        '/terms.php' => 'Terms of Service',
    ];
  ?>
  <footer class="site-footer">
    <div class="starfield" aria-hidden="true"></div>
    <div class="footer-glow footer-glow-a" aria-hidden="true"></div>
    <div class="footer-glow footer-glow-b" aria-hidden="true"></div>

    <div class="container">
      <div class="footer-mission">
        <p>Learn skills that actually pay off.</p>
      </div>

      <div class="footer-pulse">
        <div class="pulse-item">
          <span class="pulse-value" data-count-up data-count-value="<?= (int) $footerStats['course_count'] ?>" data-count-suffix="">0</span>
          <span class="pulse-label">Courses</span>
        </div>
        <div class="pulse-divider"></div>
        <div class="pulse-item">
          <span class="pulse-value" data-count-up data-count-value="<?= (int) $footerStats['learner_count'] ?>" data-count-suffix="">0</span>
          <span class="pulse-label">Learners</span>
        </div>
        <div class="pulse-divider"></div>
        <div class="pulse-item">
          <span class="pulse-value" data-count-up data-count-value="<?= (int) $footerStats['creator_count'] ?>" data-count-suffix="">0</span>
          <span class="pulse-label">Creators</span>
        </div>
      </div>

      <div class="footer-grid">
        <div class="footer-brand">
          <?php render_logo(); ?>
          <p class="brand-desc">Practical courses in Finance, Tech, Business, and more — taught by experienced African creators, paid for instantly with mobile money.</p>
          <div class="pay-badges">
            <span class="pay-badge pay-badge-mtn">MTN Mobile Money</span>
            <span class="pay-badge pay-badge-airtel">Airtel Money</span>
          </div>
        </div>
        <div>
          <h4>Learn</h4>
          <ul>
            <?php foreach ($footerLearnLinks as $href => $label): ?>
              <li><a href="<?= e(base_url($href)) ?>"><span><?= e($label) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <h4>Teach &amp; Earn</h4>
          <ul>
            <?php foreach ($footerTeachLinks as $href => $label): ?>
              <li><a href="<?= e(base_url($href)) ?>"><span><?= e($label) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <h4>Company</h4>
          <ul>
            <?php foreach ($footerCompanyLinks as $href => $label): ?>
              <li><a href="<?= e(base_url($href)) ?>"><span><?= e($label) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="footer-legal">
        <nav class="footer-legal-links">
          <?php foreach ($footerLegalLinks as $href => $label): ?>
            <a href="<?= e(base_url($href)) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
          <a href="#top" class="back-to-top" data-back-to-top>Back to top ↑</a>
        </nav>
      </div>

      <div class="footer-bottom">
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
