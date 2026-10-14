<?php
/**
 * Dashboard shell. Requires $user (from require_login/require_role) and
 * optionally $pageTitle, $pageCss (extra stylesheets) and $dashClass (a class on the shell) before include.
 */
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/professor.php';

// Professors get their own dashboard shell (a top dock, no student pages): includes/professor_header.php.
if (is_professor($user)) {
    require __DIR__ . '/professor_header.php';
    return;
}

// Grouped as [group label => [href, label, icon name]] so the sidebar reads
// as sections of related tasks rather than one flat list of links.
$navByRole = [
    'LEARNER' => [
        'Learning' => [
            ['/dashboard/learner/index.php', 'My Campuses', 'graduation-cap'],
            ['/index.php#campuses', 'All campuses', 'book-open'],
        ],
        'Account' => [
            ['/dashboard/settings.php', 'Settings', 'settings'],
        ],
    ],
    'ADMIN' => [
        'Overview' => [
            ['/dashboard/admin/index.php', 'Overview', 'layout-dashboard'],
            ['/dashboard/admin/analytics.php', 'Visitors', 'globe'],
            ['/dashboard/admin/leads.php', 'Leads', 'sparkle'],
            ['/dashboard/admin/logins.php', 'Login Activity', 'shield'],
        ],
        'Campuses' => [
            ['/dashboard/admin/campuses.php', 'Campuses', 'graduation-cap'],
            ['/dashboard/admin/professor-pay.php', 'Professor Pay', 'banknote'],
            ['/dashboard/admin/instructor-applications.php', 'Teach Applications', 'user-plus'],
        ],
        'Manage' => [
            ['/dashboard/admin/users.php', 'Users', 'users'],
            ['/dashboard/admin/revenue.php', 'Revenue', 'trending-up'],
            ['/dashboard/admin/payments.php', 'Payments', 'wallet'],
            ['/dashboard/admin/testimonials.php', 'Stories', 'quote'],
        ],
        'System' => [
            ['/dashboard/admin/audit-log.php', 'Audit Log', 'scroll-text'],
            ['/dashboard/admin/storage-cleanup.php', 'Storage', 'trash'],
            ['/dashboard/settings.php', 'Settings', 'settings'],
        ],
    ],
];
// Anyone who is not an admin uses the learner dashboard (there are no other roles any more).
$navGroups = $navByRole[$user['role'] === 'ADMIN' ? 'ADMIN' : 'LEARNER'];
$currentPath = current_path();

// Live counts shown as badges on the relevant nav item, and a small
// role-specific snapshot widget — so the sidebar carries real information
// instead of sitting mostly empty below a short link list.
$navBadges = [];
$sidebarWidget = null;
$adminNotifications = [];
$unreadNotifCount = 0;
if ($user['role'] === 'ADMIN') {
    $adminNotifications = get_admin_notifications(8);
    $unreadNotifCount = get_unread_notification_count();
    $navBadges['/dashboard/admin/leads.php'] = (int) db_one("SELECT COUNT(*) AS n FROM leads WHERE status='NEW'")['n'];
    try {
        $navBadges['/dashboard/admin/instructor-applications.php'] = (int) db_one("SELECT COUNT(*) AS n FROM instructor_applications WHERE status='NEW'")['n'];
    } catch (Throwable $e) {
        $navBadges['/dashboard/admin/instructor-applications.php'] = 0;
    }
    $sidebarWidget = [
        'title' => 'This Month',
        'icon' => 'trending-up',
        'rows' => [
            ['label' => 'Campus payments', 'value' => format_money((float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM payments WHERE type='CAMPUS_MEMBERSHIP' AND status='SUCCESS' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")['n'] ?? 0))],
            ['label' => 'New Signups', 'value' => (string) (int) db_one("SELECT COUNT(*) AS n FROM users WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")['n']],
        ],
    ];
} else {
    $mine = db_one('SELECT COUNT(*) AS n, MIN(access_until) AS next_end FROM campus_memberships WHERE user_id = ? AND access_until > NOW()', [$user['id']]);
    $sidebarWidget = [
        'title' => 'Your Campuses',
        'icon' => 'graduation-cap',
        'rows' => [
            ['label' => 'Active campuses', 'value' => (string) (int) ($mine['n'] ?? 0)],
            ['label' => 'Next access ends', 'value' => !empty($mine['next_end']) ? date('j M Y', strtotime($mine['next_end'])) : '—'],
        ],
    ];
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle ?? 'Dashboard — Obin Academy') ?></title>
  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="icon" href="<?= e(versioned_asset('favicon-32x32.png')) ?>" type="image/png" sizes="32x32">
  <link rel="icon" href="<?= e(versioned_asset('favicon-16x16.png')) ?>" type="image/png" sizes="16x16">
  <link rel="apple-touch-icon" href="<?= e(versioned_asset('apple-touch-icon.png')) ?>" sizes="180x180">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/dashboard.css')) ?>">
  <?php foreach ($pageCss ?? [] as $css): ?><link rel="stylesheet" href="<?= e(versioned_asset($css)) ?>">
  <?php endforeach; ?>
  <?php require __DIR__ . '/pwa-head.php'; ?>
</head>
<body>
<div class="dash dash-premium theme-<?= e(dashboard_theme_for_user($user)) ?> <?= e($dashClass ?? '') ?>">
  <div class="dash-overlay" data-dash-overlay data-dash-close></div>
  <aside class="dash-sidebar" data-dash-sidebar>
    <div class="dash-sidebar-head">
      <?php render_logo(true); ?>
      <button data-dash-close aria-label="Close menu">✕</button>
    </div>

    <div class="dash-me">
      <span class="dash-me-av"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
      <span class="dash-me-text"><b><?= e($user['name']) ?></b><small><?= e(ucfirst(strtolower($user['role']))) ?></small></span>
    </div>

    <div class="dash-nav-scroll">
      <?php foreach ($navGroups as $groupLabel => $items): ?>
        <div class="dash-nav-group">
          <div class="dash-nav-group-label"><?= e($groupLabel) ?></div>
          <nav class="dash-nav">
            <?php foreach ($items as [$href, $label, $icon]): $badge = $navBadges[$href] ?? 0; ?>
              <a href="<?= e(base_url($href)) ?>" class="<?= $currentPath === $href ? 'active' : '' ?>">
                <span class="nav-ic"><?php dash_icon($icon); ?></span><span class="nav-t"><?= e($label) ?></span>
                <?php if ($badge > 0): ?><span class="nav-badge"><?= $badge ?></span><?php endif; ?>
              </a>
            <?php endforeach; ?>
          </nav>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($sidebarWidget): ?>
      <div class="dash-widget">
        <div class="dash-widget-head"><?php dash_icon($sidebarWidget['icon']); ?><?= e($sidebarWidget['title']) ?></div>
        <?php foreach ($sidebarWidget['rows'] as $row): ?>
          <div class="dash-widget-row"><span><?= e($row['label']) ?></span><strong><?= e($row['value']) ?></strong></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="dash-sidebar-foot">
      <a href="<?= e(base_url('index.php')) ?>" target="_blank" rel="noopener">Visit site</a>
      <a href="<?= e(base_url('logout.php')) ?>">Sign out</a>
    </div>
  </aside>

  <div class="dash-main">
    <div class="dash-header">
      <div class="dash-header-left">
        <button data-dash-open class="dash-hamburger" aria-label="Open menu">☰</button>
        <?php render_logo(true, 'dash-header-logo'); ?>
        <?php if ($user['role'] === 'ADMIN'): ?>
        <form class="dash-search" method="get" action="<?= e(base_url('dashboard/admin/search.php')) ?>" role="search">
          <?php dash_icon('search'); ?>
          <input type="search" name="q" value="" placeholder="Search people and payments…" aria-label="Search people and payments" autocomplete="off" enterkeyhint="search">
        </form>
        <?php endif; ?>
      </div>
      <div class="dash-header-right">
        <?php if ($user['role'] === 'ADMIN'): ?>
        <div class="account-menu dash-notif-menu">
          <button class="dash-icon-btn" aria-label="Notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($unreadNotifCount > 0): ?><span class="notif-dot"></span><?php endif; ?>
          </button>
          <div class="account-dropdown dash-notif-dropdown">
            <div class="account-dropdown-head row between" style="align-items:center;">
              <span class="name" style="font-size:13px;">Notifications</span>
              <?php if ($unreadNotifCount > 0): ?>
                <form method="post" action="<?= e(base_url('api/mark-notifications-read.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="redirect" value="<?= e(current_path()) ?>">
                  <button type="submit" class="dash-notif-mark-read">Mark all read</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($adminNotifications): ?>
              <?php foreach ($adminNotifications as $n): ?>
                <div class="dash-notif-row <?= $n['is_read'] ? '' : 'unread' ?>">
                  <p><?= e($n['message']) ?></p>
                  <span class="muted"><?= e(format_date($n['created_at'])) ?></span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="muted small" style="padding:12px;">No notifications yet.</p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
        <div class="account-menu dash-account">
          <button class="dash-who">
            <span class="avatar"><?= e(mb_substr($user['name'], 0, 1)) ?></span>
            <span class="who-text">
              <span class="name"><?= e($user['name']) ?></span>
              <span class="role"><?= e(ucfirst(strtolower($user['role']))) ?></span>
            </span>
            <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
          </button>
          <div class="account-dropdown">
            <div class="account-dropdown-head">
              <div class="name"><?= e($user['name']) ?></div>
              <div class="email"><?= e($user['email']) ?></div>
            </div>
            <a href="<?= e(base_url('dashboard/settings.php')) ?>"><?php dash_icon('settings'); ?>Settings</a>
            <a href="<?= e(base_url('logout.php')) ?>" class="danger"><?php dash_icon('log-out'); ?>Sign Out</a>
          </div>
        </div>
      </div>
    </div>

    <div class="dash-content">
      <?php
        $flashError = flash_get('error');
        $flashSuccess = flash_get('success');
      ?>
      <?php if ($flashError): ?>
        <div class="alert alert-error" data-flash><?= e($flashError) ?></div>
      <?php endif; ?>
      <?php if ($flashSuccess): ?>
        <div class="alert alert-success" data-flash><?= e($flashSuccess) ?></div>
      <?php endif; ?>
