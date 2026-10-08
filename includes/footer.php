  </main>

  <?php
    // Safe to require_once here specifically because footer.php always runs
    // LAST — any page that already plain-`require`s data.php itself (the
    // established convention elsewhere) did so earlier in its own
    // execution, so this just finds it already loaded and skips. The
    // reverse order (a require_once this early in a page's life, e.g. from
    // something bootstrap.php pulls in) is what actually breaks pages that
    // plain-require the same file again later — see includes/email.php's
    // own comment on this for the incident that taught us this.
    require_once __DIR__ . '/data.php';
  ?>
  <?php
    // The same slim bar obinacademy.com uses: logo on the left, three links, copyright on the right.
    // (Community = the WhatsApp community; Become a teacher = open a school.)
  ?>
  <footer class="site-footer-minimal">
    <div class="container footer-minimal-inner">
      <?php render_logo(true); ?>
      <nav class="footer-minimal-links" aria-label="Footer">
        <a href="<?= e(base_url('about.php')) ?>">About us</a>
        <a href="<?= e(base_url('contact.php')) ?>">Contact us</a>
        <a href="<?= e(WHATSAPP_COMMUNITY_URL) ?>" target="_blank" rel="noopener noreferrer">Community</a>
        <a href="<?= e(base_url('become-creator.php')) ?>">Become a teacher</a>
        <a href="<?= e(base_url('become-affiliate.php')) ?>">Become an affiliate</a>
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

  <?php
    // Site-wide "recent activity" toast — the same component built for the
    // course detail page (see courses/view.php), reusing its CSS/JS
    // patterns exactly, but scoped to real enrollments across ANY
    // published course rather than one, and clicking navigates to that
    // course instead of scrolling to an enroll panel that may not exist on
    // this page. courses/view.php sets $suppressGlobalActivityToast so its
    // own specialized (click-to-scroll) version isn't doubled up with this
    // one on that one page.
    $recentPlatformActivity = empty($suppressGlobalActivityToast) ? get_recent_activity_feed(null, 8) : [];
  ?>
  <?php if ($recentPlatformActivity): ?>
    <button type="button" class="activity-toast" id="globalActivityToast" aria-live="polite">
      <div class="activity-toast-badge" aria-hidden="true"><span data-activity-initial></span><span class="ring"></span></div>
      <div class="activity-toast-body">
        <p class="activity-toast-text" data-activity-text></p>
        <p class="activity-toast-time"><span class="live-dot" aria-hidden="true"></span><span data-activity-time></span></p>
      </div>
      <div class="activity-toast-arrow" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </div>
      <span class="activity-toast-close" data-activity-close role="button" tabindex="0" aria-label="Dismiss">&times;</span>
    </button>
    <script>
      (() => {
        var toast = document.getElementById('globalActivityToast');
        if (!toast) return;
        var storageKey = 'oaActivityToastDismissedGlobal';
        try { if (sessionStorage.getItem(storageKey)) return; } catch (e) {}

        var entries = [
          <?php foreach ($recentPlatformActivity as $a): ?>
          {
            name: <?= json_encode(display_name_initial($a['learner_name']), JSON_HEX_TAG) ?>,
            city: <?= json_encode($a['city'], JSON_HEX_TAG) ?>,
            timeAgo: <?= json_encode(time_ago($a['at']), JSON_HEX_TAG) ?>,
            action: <?= json_encode($a['action'], JSON_HEX_TAG) ?>,
            courseTitle: <?= json_encode(mb_strimwidth($a['course_title'], 0, 46, '…'), JSON_HEX_TAG) ?>,
            courseUrl: <?= json_encode(base_url('courses/view.php?slug=' . $a['course_slug']), JSON_HEX_TAG) ?>
          },
          <?php endforeach; ?>
        ];

        var initialEl = toast.querySelector('[data-activity-initial]');
        var textEl = toast.querySelector('[data-activity-text]');
        var timeEl = toast.querySelector('[data-activity-time]');
        var closeBtn = toast.querySelector('[data-activity-close]');
        var dismissed = false;
        var timers = [];
        var currentUrl = null;

        function dismiss() {
          dismissed = true;
          timers.forEach(clearTimeout);
          toast.classList.remove('show');
          try { sessionStorage.setItem(storageKey, '1'); } catch (e) {}
        }
        closeBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          dismiss();
        });
        closeBtn.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            e.stopPropagation();
            dismiss();
          }
        });

        // Tapping the toast goes straight to that course's page — no
        // single enroll panel to scroll to on most pages, so the click
        // converts by sending the visitor to the thing they'd buy instead.
        toast.addEventListener('click', function () {
          if (currentUrl) window.location.href = currentUrl;
        });

        function repositionAboveFooter() {
          var footer = document.querySelector('.site-footer-minimal');
          if (!footer) return;
          var overlap = window.innerHeight - footer.getBoundingClientRect().top;
          toast.style.bottom = overlap > 0 ? (overlap + 16) + 'px' : '';
        }
        setInterval(repositionAboveFooter, 200);

        function showEntry(i) {
          if (dismissed || entries.length === 0) return;
          var e = entries[i % entries.length];
          currentUrl = e.courseUrl;
          toast.classList.remove('show');
          initialEl.textContent = (e.name.charAt(0) || '?').toUpperCase();
          textEl.innerHTML = '<b>' + e.name + '</b>' + (e.city ? ' from ' + e.city : '') + ' ' + e.action + ' ' + e.courseTitle;
          timeEl.textContent = e.timeAgo;
          requestAnimationFrame(function () { toast.classList.add('show'); });
          timers.push(setTimeout(function () {
            toast.classList.remove('show');
            timers.push(setTimeout(function () { showEntry(i + 1); }, 500));
          }, 4500));
        }

        timers.push(setTimeout(function () { showEntry(0); }, 5000));
      })();
    </script>
  <?php endif; ?>

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
</body>
</html>
