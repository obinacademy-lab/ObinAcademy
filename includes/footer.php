  </main>

  <?php
    // Page links live only here now — the top nav was trimmed down to just
    // Log In / Become a Creator, so this is the site's one remaining way to
    // reach Home/Courses/Stories/About/Contact from anywhere on the site.
    // skool.com's own footer (the format this was asked to match) is just
    // one plain row of text links on a bare background — no columns, no
    // dark card, no logo repeated down here.
    $footerNavLinks = [
        '/index.php' => 'Home',
        '/courses/index.php' => 'Explore Schools',
        '/gift.php' => 'Gift a Course',
        '/stories.php' => 'Stories',
        '/about.php' => 'About Us',
        '/contact.php' => 'Contact',
    ];
  ?>
  <footer class="site-footer-minimal">
    <div class="container footer-minimal-inner">
      <nav class="footer-minimal-links">
        <?php foreach ($footerNavLinks as $href => $label): ?>
          <a href="<?= e(base_url($href)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(WHATSAPP_COMMUNITY_URL) ?>" target="_blank" rel="noopener noreferrer" class="footer-wa-community-link">
          <svg viewBox="0 0 24 24" width="15" height="15"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2zm5.86 14.02c-.25.7-1.25 1.29-1.98 1.44-.53.11-1.22.2-3.55-.76-2.98-1.24-4.89-4.24-5.04-4.44-.15-.2-1.21-1.6-1.21-3.06 0-1.46.76-2.17 1.03-2.47.27-.3.6-.37.8-.37h.57c.18 0 .43-.07.67.51.25.6.85 2.06.92 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.32.38-.45.51-.15.15-.31.31-.13.62.18.3.8 1.32 1.72 2.14 1.18 1.05 2.18 1.38 2.5 1.53.32.15.5.13.68-.08.18-.2.78-.9.99-1.21.2-.3.4-.25.68-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.75-.18 1.45z"/></svg>
          Join Our Students WhatsApp Community
        </a>
      </nav>
      <div class="footer-minimal-meta">
        <div class="footer-minimal-socials">
          <a href="https://wa.me/256775361998?text=<?= urlencode('Hi, I have a question about Obin Academy') ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="Chat with us on WhatsApp">
            <svg viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2zm5.86 14.02c-.25.7-1.25 1.29-1.98 1.44-.53.11-1.22.2-3.55-.76-2.98-1.24-4.89-4.24-5.04-4.44-.15-.2-1.21-1.6-1.21-3.06 0-1.46.76-2.17 1.03-2.47.27-.3.6-.37.8-.37h.57c.18 0 .43-.07.67.51.25.6.85 2.06.92 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.32.38-.45.51-.15.15-.31.31-.13.62.18.3.8 1.32 1.72 2.14 1.18 1.05 2.18 1.38 2.5 1.53.32.15.5.13.68-.08.18-.2.78-.9.99-1.21.2-.3.4-.25.68-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.75-.18 1.45z"/></svg>
          </a>
          <a href="https://www.tiktok.com/@obinacademy.site" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok">
            <svg viewBox="0 0 24 24"><path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
          </a>
          <a href="https://www.facebook.com/profile.php?id=61591414895842" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
            <svg viewBox="0 0 24 24"><path d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.9.25-1.5 1.53-1.5H16.5V4.34C16.19 4.3 15.13 4.2 14 4.2c-2.34 0-3.94 1.43-3.94 4.05V10.5H7.5v3H10V21h3.5z"/></svg>
          </a>
          <a href="https://www.instagram.com/obinacademyofficial/?hl=en" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram">
            <svg viewBox="0 0 24 24"><path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zM12 2c-2.7 0-3.1 0-4.1.1-1.1 0-1.8.2-2.4.5A4.8 4.8 0 0 0 2.6 5.5c-.3.6-.5 1.3-.5 2.4C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.1 1.1.2 1.8.5 2.4a4.8 4.8 0 0 0 2.9 2.9c.6.3 1.3.5 2.4.5C8.9 22 9.3 22 12 22s3.1 0 4.1-.1c1.1-.1 1.8-.2 2.4-.5a4.8 4.8 0 0 0 2.9-2.9c.3-.6.5-1.3.5-2.4.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.1-1.1-.2-1.8-.5-2.4a4.8 4.8 0 0 0-2.9-2.9c-.6-.3-1.3-.5-2.4-.5C15.1 2 14.7 2 12 2zm0 1.8c2.6 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.3.3.5.6.7 1.1.1.3.3.9.3 1.8.1 1 .1 1.4.1 4s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.7 1.1-.3.3-.6.5-1.1.7-.3.1-.9.3-1.8.3-1 .1-1.4.1-4 .1s-3 0-4-.1c-.9 0-1.5-.2-1.8-.3a3 3 0 0 1-1.1-.7 3 3 0 0 1-.7-1.1c-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.4-.1-4s0-3 .1-4c0-.9.2-1.5.3-1.8.2-.5.4-.8.7-1.1.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4-.1z"/></svg>
          </a>
          <a href="https://www.youtube.com/@obinacademy" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube">
            <svg viewBox="0 0 24 24"><path d="M23.5 6.19a2.99 2.99 0 0 0-2.11-2.12C19.51 3.55 12 3.55 12 3.55s-7.51 0-9.39.52A2.99 2.99 0 0 0 .5 6.19 31.26 31.26 0 0 0 0 12a31.26 31.26 0 0 0 .5 5.81 2.99 2.99 0 0 0 2.11 2.12c1.88.52 9.39.52 9.39.52s7.51 0 9.39-.52a2.99 2.99 0 0 0 2.11-2.12A31.26 31.26 0 0 0 24 12a31.26 31.26 0 0 0-.5-5.81zM9.75 15.57V8.43L15.82 12z"/></svg>
          </a>
          <a href="mailto:info@obinacademy.site" aria-label="Email Us" title="Email Us" class="email">
            <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m2 7 10 6 10-6"></path></svg>
          </a>
        </div>
        <div class="footer-minimal-copy">&copy; <?= date('Y') ?> Obin Academy</div>
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
