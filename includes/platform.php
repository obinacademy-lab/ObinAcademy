<?php
/**
 * The platform shell ("Bright"): the header every page shares (with the Campuses mega-menu), the footer, and the dashboard tab bar.
 * Public pages (header.php / footer.php) and the learner and admin dashboards (dashboard_header.php) all build
 * their chrome from here, so a change to the nav or a link happens in one place. Styles: assets/css/platform.css.
 * Behaviour reuses main.js: [data-site-header] shadow on scroll, [data-nav-toggle] / [data-mobile-menu] for the phone menu.
 */

const PL_WHATSAPP_URL = 'https://wa.me/256775361998';

/** The main navigation: [label, href, paths that count as "on"]. */
function pl_nav_items(): array {
    return [
        ['Campuses', 'campuses.php', ['/campuses.php', '/campus.php', '/index.php', '/']],
        ['Teach', 'teach.php', ['/teach.php']],
        ['Earn', 'become-affiliate.php', ['/become-affiliate.php']],
        ['About', 'about.php', ['/about.php']],
        ['Contact', 'contact.php', ['/contact.php']],
    ];
}

function pl_logo(): void {
    ?>
    <a class="pl-logo" href="<?= e(base_url('index.php')) ?>">
      <span class="pl-logo-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg></span>Obin Academy
    </a>
    <?php
}

/**
 * The open campuses for the header mega-menu, plus the line shown in its promo card. Never throws: if the database is
 * unreachable the header simply has no mega-menu.
 * @return array{0: array, 1: ?float} [published campuses, cheapest day pass or null]
 */
function pl_mega_campuses(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [[], null];
    try {
        require_once __DIR__ . '/campuses.php';
        $open = get_published_campuses();
        $day = null;
        if ($open && campus_daily_ready()) {
            $prices = array_filter(array_map(fn($c) => (float) ($c['daily_price'] ?? 0), $open), fn($v) => $v > 0);
            $day = $prices ? min($prices) : null;
        }
        $cache = [$open, $day];
    } catch (Throwable $e) {
    }
    return $cache;
}

/**
 * The top of every page: the sticky header (with the Campuses mega-menu) and the phone menu.
 * $o['bell']     HTML for the notification bell (shown before the account menu)
 * $o['search']   HTML for a search box between the nav and the account area (admin)
 * $o['account']  [[href, label, icon-svg-inner, danger?], ...] links in the account dropdown
 */
function pl_render_head(?array $user, array $o = []): void {
    $path = current_path();
    $nav = pl_nav_items();
    $account = $o['account'] ?? [
        ['dashboard.php', 'Dashboard', '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>', false],
        ['dashboard/settings.php', 'Settings', '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>', false],
        ['logout.php', 'Sign Out', '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>', true],
    ];
    [$megaCampuses, $megaDay] = pl_mega_campuses();
    ?>
  <header class="pl-hd" data-site-header>
    <div class="pl-w pl-hd-in">
      <?php pl_logo(); ?>
      <nav class="pl-nav" aria-label="Main">
        <?php foreach ($nav as $i => [$label, $href, $on]):
            $isOn = in_array($path, $on, true);
            $hasMega = $i === 0 && $megaCampuses; ?>
          <a href="<?= e(base_url($href)) ?>" class="<?= $isOn ? 'on' : '' ?><?= $hasMega ? ' pl-hasm' : '' ?>"<?= $isOn ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($hasMega): ?><svg class="pl-dd" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg><?php endif; ?></a>
        <?php endforeach; ?>
      </nav>
      <?= $o['search'] ?? '' ?>
      <div class="pl-act">
        <?= $o['bell'] ?? '' ?>
        <?php if ($user): ?>
          <div class="account-menu pl-account">
            <button class="account-trigger" aria-haspopup="true">
              <span class="account-avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
              <span class="account-name"><?= e(explode(' ', trim($user['name']))[0]) ?></span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="account-dropdown">
              <div class="account-dropdown-head">
                <div class="name"><?= e($user['name']) ?></div>
                <div class="email"><?= e($user['email']) ?></div>
              </div>
              <?php foreach ($account as [$href, $label, $icon, $danger]): ?>
                <a href="<?= e(base_url($href)) ?>"<?= $danger ? ' class="danger"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg><?= e($label) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <a href="<?= e(base_url('login.php')) ?>" class="pl-login">Log in</a>
          <a href="<?= e(base_url('campuses.php')) ?>" class="pl-btn p sm">Join a campus</a>
        <?php endif; ?>
        <button class="pl-burger" data-nav-toggle aria-label="Toggle menu" aria-expanded="false">
          <svg class="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
          <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>
    </div>
    <?php if ($megaCampuses): ?>
    <div class="pl-mega" aria-label="Campuses">
      <div class="pl-w">
        <div class="pl-ml">
          <?php foreach ($megaCampuses as $mc): $mp = campus_photo($mc); ?>
            <a class="pl-mi" href="<?= e(base_url('campus.php?slug=' . rawurlencode($mc['slug']))) ?>">
              <?php if ($mp): ?><img src="<?= e($mp) ?>" alt="" width="54" height="54" loading="lazy"><?php else: ?><span class="pl-mi-ic"><?= campus_icon($mc['icon'], 1.6) ?></span><?php endif; ?>
              <span><b><?= e($mc['name']) ?></b><small><?= e(format_money((float) $mc['monthly_price'])) ?>/mo</small></span>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="pl-mp">
          <?php if ($megaDay): ?>
            <small>New · Day pass</small>
            <b>Try any campus for <?= e(format_money($megaDay)) ?> a day.</b>
          <?php else: ?>
            <small>Pay your way</small>
            <b>Monthly or yearly, with MTN or Airtel Money.</b>
          <?php endif; ?>
          <a class="pl-btn a sm" href="<?= e(base_url('index.php#how')) ?>">See how it works</a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </header>

  <div class="mobile-menu pl-mm" data-mobile-menu>
    <div class="container">
      <?php if ($user): ?>
        <div class="mobile-menu-account">
          <span class="account-avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
          <div><div class="name"><?= e($user['name']) ?></div><div class="email"><?= e($user['email']) ?></div></div>
        </div>
      <?php endif; ?>
      <?php foreach ($nav as [$label, $href, $on]): ?>
        <a href="<?= e(base_url($href)) ?>" class="<?= in_array($path, $on, true) ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($user): ?>
        <a href="<?= e(base_url('dashboard.php')) ?>" class="<?= str_starts_with($path, '/dashboard') && $path !== '/dashboard/settings.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="<?= $path === '/dashboard/settings.php' ? 'active' : '' ?>">Settings</a>
        <hr class="mm-rule">
        <a href="<?= e(base_url('logout.php')) ?>" class="mm-danger">Sign Out</a>
      <?php else: ?>
        <a href="<?= e(base_url('login.php')) ?>" class="mm-cta mm-cta-ink">Log In</a>
        <a href="<?= e(base_url('campuses.php')) ?>" class="mm-cta mm-cta-gold">Join a Campus →</a>
      <?php endif; ?>
      <a href="<?= e(PL_WHATSAPP_URL) ?>" target="_blank" rel="noopener noreferrer">WhatsApp us</a>
      <a href="#" data-install-app hidden>Install the app</a>
    </div>
  </div>
  <div class="mobile-menu-scrim" data-mobile-scrim></div>
    <?php
}

/** The dark footer: about + payment logos, three link columns, legal line. */
function pl_render_footer(?array $user): void {
    ?>
  <footer class="pl-ft">
    <div class="pl-w">
      <div class="pl-ft-cols">
        <div class="pl-ft-about">
          <?php pl_logo(); ?>
          <p>Learn a skill that pays, step by step, from professors who do it for a living.</p>
          <div class="pl-pay"><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN Mobile Money" width="28" height="28" loading="lazy"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel Money" width="28" height="28" loading="lazy"></div>
        </div>
        <div>
          <h4>Learn</h4>
          <ul>
            <li><a href="<?= e(base_url('campuses.php')) ?>">All campuses</a></li>
            <li><a href="<?= e(base_url('index.php#how')) ?>">How it works</a></li>
            <li><a href="<?= e(base_url('index.php#prices')) ?>">Pricing</a></li>
            <li><a href="<?= e(base_url('index.php#faq')) ?>">Questions</a></li>
          </ul>
        </div>
        <div>
          <h4>Earn</h4>
          <ul>
            <li><a href="<?= e(base_url('teach.php')) ?>">Teach at Obin Academy</a></li>
            <li><a href="<?= e(base_url('become-affiliate.php')) ?>">Become an affiliate</a></li>
            <li><a href="<?= e(WHATSAPP_COMMUNITY_URL) ?>" target="_blank" rel="noopener noreferrer">Community</a></li>
          </ul>
        </div>
        <div>
          <h4>Company</h4>
          <ul>
            <li><a href="<?= e(base_url('about.php')) ?>">About us</a></li>
            <li><a href="<?= e(base_url('contact.php')) ?>">Contact us</a></li>
            <li><a href="<?= e(base_url('terms.php')) ?>">Terms</a></li>
            <li><a href="<?= e(base_url('privacy.php')) ?>">Privacy</a></li>
            <li><?php if ($user): ?><a href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a><?php else: ?><a href="<?= e(base_url('login.php')) ?>">Sign in</a><?php endif; ?></li>
            <li><a href="#" data-install-app hidden>Install the app</a></li>
          </ul>
        </div>
      </div>
      <div class="pl-ft-legal"><span>&copy; <?= date('Y') ?> Obin Academy · Kampala, Uganda</span><span>Pay with MTN or Airtel Money · <a href="<?= e(PL_WHATSAPP_URL) ?>" target="_blank" rel="noopener noreferrer">WhatsApp us</a></span></div>
    </div>
  </footer>
    <?php
}

/**
 * The dashboard tab bar under the header. $groups is [group label => [[href, label, icon, badge], ...]]:
 * a single group renders as plain tabs (learners); several render as dropdown groups on desktop (admin).
 * On a phone every item shows as one scrolling row of tabs.
 */
function pl_render_tabs(array $groups, array $badges = []): void {
    $path = current_path();
    $flat = [];
    foreach ($groups as $label => $items) foreach ($items as $it) $flat[] = $it;
    $multi = count($groups) > 1;
    ?>
  <div class="pl-tabs" data-pl-tabs>
    <div class="pl-w pl-tabs-in">
      <?php if ($multi): ?>
        <nav class="pl-tabs-groups" aria-label="Dashboard">
          <?php foreach ($groups as $label => $items):
              $on = false;
              foreach ($items as $it) if ($path === $it[0]) $on = true; ?>
            <div class="pl-grp<?= $on ? ' on' : '' ?>">
              <button type="button" class="pl-gt" aria-haspopup="true"><?= e($label) ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
              <div class="pl-gm">
                <?php foreach ($items as [$href, $name]): $b = $badges[$href] ?? 0; ?>
                  <a href="<?= e(base_url(ltrim($href, '/'))) ?>" class="<?= $path === $href ? 'on' : '' ?>"><span><?= e($name) ?></span><?php if ($b > 0): ?><em><?= (int) $b ?></em><?php endif; ?></a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <nav class="pl-tabs-flat<?= $multi ? ' multi' : '' ?>" aria-label="Dashboard">
        <?php foreach ($flat as [$href, $name]): $b = $badges[$href] ?? 0; ?>
          <a href="<?= e(base_url(ltrim($href, '/'))) ?>" class="<?= $path === $href ? 'on' : '' ?>"<?= $path === $href ? ' aria-current="page"' : '' ?>><?= e($name) ?><?php if ($b > 0): ?><em><?= (int) $b ?></em><?php endif; ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>
    <?php
}

/** Which campus category a campus belongs to, for the filter chips on the campuses page. */
function pl_campus_category(array $campus): string {
    $s = strtolower($campus['slug'] . ' ' . $campus['name']);
    if (preg_match('/forex|crypto|trading/', $s)) return 'Trading';
    if (preg_match('/content|creat/', $s)) return 'Creative';
    if (preg_match('/\bai\b|tech|ai-/', $s)) return 'Technology';
    if (preg_match('/fitness|nutrition|health/', $s)) return 'Health';
    return 'Business';
}
