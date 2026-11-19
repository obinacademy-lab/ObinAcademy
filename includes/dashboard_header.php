<?php
/**
 * Dashboard shell. Requires $user (from require_login/require_role) and
 * optionally $pageTitle, $pageCss (extra stylesheets) and $dashClass (a class on the shell) before include.
 */
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/professor.php';
require_once __DIR__ . '/platform.php';

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
            ['/campuses.php', 'All campuses', 'book-open'],
        ],
        'Earn' => [
            ['/dashboard/learner/affiliate.php', 'My Referrals', 'users'],
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
            ['/dashboard/admin/affiliate-pay.php', 'Affiliate Pay', 'share'],
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
    try { $navBadges['/dashboard/admin/professor-pay.php'] = (int) db_one("SELECT COUNT(*) AS n FROM campus_payout_requests WHERE status = 'PENDING'")['n']; } catch (Throwable $e) {} // professors asking to be paid
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
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&family=Sora:wght@400;500;600;700;800&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400;1,6..72,500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/dashboard.css')) ?>">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/platform.css')) ?>">
  <?php foreach ($pageCss ?? [] as $css): ?><link rel="stylesheet" href="<?= e(versioned_asset($css)) ?>">
  <?php endforeach; ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/desk-bright.css')) ?>">
  <?php require __DIR__ . '/pwa-head.php'; ?>
</head>
<body class="pl pl-dashbody">
<div class="dash dash-premium pl-dash theme-<?= e(dashboard_theme_for_user($user)) ?> <?= e($dashClass ?? '') ?>">
  <div class="dash-main">
<?php
$plSearch = '';
$plBell = '';
if ($user['role'] === 'ADMIN') {
    ob_start(); ?>
        <form class="pl-search" method="get" action="<?= e(base_url('dashboard/admin/search.php')) ?>" role="search">
          <?php dash_icon('search'); ?>
          <input type="search" name="q" value="" placeholder="Search people and payments…" aria-label="Search people and payments" autocomplete="off" enterkeyhint="search">
        </form>
<?php
    $plSearch = ob_get_clean();
    ob_start(); ?>
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
<?php
    $plBell = ob_get_clean();
}
pl_render_head($user, [
    'search' => $plSearch,
    'bell' => $plBell,
    'account' => [
        ['dashboard/settings.php', 'Settings', '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>', false],
        ['index.php', 'Visit site', '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>', false],
        ['logout.php', 'Sign Out', '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>', true],
    ],
]);
pl_render_tabs($user['role'] === 'ADMIN' ? $navGroups : ['Menu' => array_merge(...array_values($navGroups))], $navBadges);
?>
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
