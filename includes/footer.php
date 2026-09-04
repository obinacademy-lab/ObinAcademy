  </main>

  <?php
    require_once __DIR__ . '/data.php';
    $footerCategories = array_slice(get_categories(), 0, 6);
    $footerStats = get_platform_stats();
  ?>

  <footer class="site-footer">
    <div class="starfield" aria-hidden="true">
      <?php for ($i = 0; $i < 55; $i++):
        $x = mt_rand(0, 1000) / 10;
        $y = mt_rand(0, 1000) / 10;
        $size = mt_rand(10, 28) / 10;
        $minO = mt_rand(5, 25) / 100;
        $maxO = mt_rand(45, 95) / 100;
        $twinkleDur = mt_rand(20, 55) / 10;
        $driftDur = mt_rand(150, 420) / 10;
        $dx = mt_rand(-25, 25);
        $dy = mt_rand(-20, 20);
        $delay = mt_rand(-100, 0) / 10;
        $style = "--x:{$x}%; --y:{$y}%; --size:{$size}px; --min-o:{$minO}; --max-o:{$maxO}; "
               . "--twinkle-dur:{$twinkleDur}s; --drift-dur:{$driftDur}s; --dx:{$dx}px; --dy:{$dy}px; --delay:{$delay}s;";
      ?>
        <span class="star" style="<?= e($style) ?>"></span>
      <?php endfor; ?>
    </div>
    <div class="footer-glow footer-glow-a" aria-hidden="true"></div>
    <div class="footer-glow footer-glow-b" aria-hidden="true"></div>

    <div class="container">
      <div class="footer-mission reveal">
        <p>Empowering Africa,<br>one skill at a time.</p>
      </div>

      <div class="footer-pulse reveal reveal-delay-1">
        <div class="pulse-item"><span class="pulse-value" data-count-up data-count-value="<?= (int) $footerStats['course_count'] ?>">0+</span><span class="pulse-label">Courses</span></div>
        <span class="pulse-divider"></span>
        <div class="pulse-item"><span class="pulse-value" data-count-up data-count-value="<?= (int) $footerStats['learner_count'] ?>">0+</span><span class="pulse-label">Learners</span></div>
        <span class="pulse-divider"></span>
        <div class="pulse-item"><span class="pulse-value" data-count-up data-count-value="<?= (int) $footerStats['creator_count'] ?>">0+</span><span class="pulse-label">Creators</span></div>
      </div>

      <div class="footer-grid">
        <div class="footer-brand reveal reveal-delay-1">
          <?php render_logo(); ?>
          <p class="brand-desc">Turn your knowledge into income, or learn the skills you need to grow — across finance, business, AI, health, agriculture, and more.</p>

          <div class="pay-badges">
            <span class="pay-badge pay-badge-mtn">MTN Mobile Money</span>
            <span class="pay-badge pay-badge-airtel">Airtel Money</span>
          </div>

          <div class="social-icons">
            <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.9.25-1.5 1.53-1.5H16.5V4.34C16.19 4.3 15.13 4.2 14 4.2c-2.34 0-3.94 1.43-3.94 4.05V10.5H7.5v3H10V21h3.5z"/></svg></a>
            <a href="#" aria-label="Twitter"><svg viewBox="0 0 24 24"><path d="M22 5.9c-.7.3-1.5.6-2.3.7.8-.5 1.4-1.3 1.7-2.3-.8.5-1.7.8-2.6 1a4.1 4.1 0 0 0-7 3.7A11.6 11.6 0 0 1 3.3 4.6a4 4 0 0 0 1.3 5.5c-.6 0-1.2-.2-1.7-.5v.1c0 2 1.4 3.6 3.3 4a4.2 4.2 0 0 1-1.9.1 4.1 4.1 0 0 0 3.8 2.9A8.3 8.3 0 0 1 2 18.4a11.6 11.6 0 0 0 6.3 1.8c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.1z"/></svg></a>
            <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zM12 2c-2.7 0-3.1 0-4.1.1-1.1 0-1.8.2-2.4.5A4.8 4.8 0 0 0 2.6 5.5c-.3.6-.5 1.3-.5 2.4C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.1 1.1.2 1.8.5 2.4a4.8 4.8 0 0 0 2.9 2.9c.6.3 1.3.5 2.4.5C8.9 22 9.3 22 12 22s3.1 0 4.1-.1c1.1-.1 1.8-.2 2.4-.5a4.8 4.8 0 0 0 2.9-2.9c.3-.6.5-1.3.5-2.4.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.1-1.1-.2-1.8-.5-2.4a4.8 4.8 0 0 0-2.9-2.9c-.6-.3-1.3-.5-2.4-.5C15.1 2 14.7 2 12 2zm0 1.8c2.6 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.3.3.5.6.7 1.1.1.3.3.9.3 1.8.1 1 .1 1.4.1 4s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.7 1.1-.3.3-.6.5-1.1.7-.3.1-.9.3-1.8.3-1 .1-1.4.1-4 .1s-3 0-4-.1c-.9 0-1.5-.2-1.8-.3a3 3 0 0 1-1.1-.7 3 3 0 0 1-.7-1.1c-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.4-.1-4s0-3 .1-4c0-.9.2-1.5.3-1.8.2-.5.4-.8.7-1.1.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4-.1z"/></svg></a>
            <a href="#" aria-label="LinkedIn"><svg viewBox="0 0 24 24"><path d="M6.9 8.4H3.6V20h3.3V8.4zM5.3 3.4a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 20h-3.3v-6.1c0-1.5-.5-2.5-1.9-2.5-1 0-1.6.7-1.9 1.4-.1.2-.1.6-.1.9V20H10s0-10.6 0-11.6h3.3v1.6c.4-.7 1.2-1.7 3-1.7 2.2 0 3.8 1.4 3.8 4.5V20z"/></svg></a>
          </div>
        </div>

        <div class="reveal reveal-delay-2">
          <h4>Platform</h4>
          <ul>
            <li><a href="<?= e(base_url('courses/index.php')) ?>"><span>Explore Courses</span></a></li>
            <li><a href="<?= e(base_url('skills.php')) ?>"><span>Browse All Skills</span></a></li>
            <li><a href="<?= e(base_url('stories.php')) ?>"><span>Stories</span></a></li>
            <li><a href="<?= e(base_url('about.php')) ?>"><span>About Us</span></a></li>
            <li><a href="<?= e(base_url('contact.php')) ?>"><span>Contact</span></a></li>
          </ul>
        </div>

        <div class="reveal reveal-delay-3">
          <h4>For Creators</h4>
          <ul>
            <li><a href="<?= e(base_url('become-creator.php')) ?>"><span>Become a Creator</span></a></li>
            <li><a href="<?= e(base_url('dashboard/creator/index.php')) ?>"><span>Creator Dashboard</span></a></li>
          </ul>
        </div>

        <div class="reveal reveal-delay-4">
          <h4>For Learners</h4>
          <ul>
            <li><a href="<?= e(base_url('signup.php')) ?>"><span>Create Account</span></a></li>
            <li><a href="<?= e(base_url('dashboard/learner/index.php')) ?>"><span>My Learning</span></a></li>
          </ul>
        </div>

        <?php if ($footerCategories): ?>
        <div class="reveal reveal-delay-5">
          <h4>Popular Categories</h4>
          <ul>
            <?php foreach ($footerCategories as $cat): ?>
              <li><a href="<?= e(base_url('courses/index.php?category=' . $cat['slug'])) ?>"><span><?= e($cat['name']) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>

      <div class="footer-legal">
        <p>&copy; <?= date('Y') ?> Obin Academy. All rights reserved.</p>
        <nav class="footer-legal-links">
          <a href="<?= e(base_url('terms.php')) ?>">Terms of Service</a>
          <a href="<?= e(base_url('privacy.php')) ?>">Privacy Policy</a>
          <a href="#top" class="back-to-top">Back to top ↑</a>
        </nav>
      </div>

      <div class="footer-bottom">
        <p class="made-for">🌍 Proudly built for Africa</p>
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
