<?php
/**
 * Dashboard shell. Requires $user (from require_login/require_role) and
 * optionally $pageTitle before include.
 */
require_once __DIR__ . '/notifications.php';

// Grouped as [group label => [href, label, icon name]] so the sidebar reads
// as sections of related tasks rather than one flat list of links.
$navByRole = [
    'LEARNER' => [
        'Learning' => [
            ['/dashboard/learner/index.php', 'My Learning', 'graduation-cap'],
        ],
        'Account' => [
            ['/dashboard/settings.php', 'Settings', 'settings'],
        ],
    ],
    'CREATOR' => [
        'Teaching' => [
            ['/dashboard/creator/index.php', 'My Courses', 'book-open'],
            ['/dashboard/creator/course-new.php', 'Create Course', 'plus-circle'],
            ['/dashboard/creator/earnings.php', 'Earnings', 'wallet'],
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
        ],
        'Manage' => [
            ['/dashboard/admin/users.php', 'Users', 'users'],
            ['/dashboard/admin/creator-applications.php', 'Creator Applications', 'user-plus'],
            ['/dashboard/admin/courses.php', 'Courses', 'book-open'],
            ['/dashboard/admin/revenue.php', 'Revenue', 'trending-up'],
            ['/dashboard/admin/categories.php', 'Categories', 'tag'],
            ['/dashboard/admin/testimonials.php', 'Stories', 'quote'],
            ['/dashboard/admin/withdrawals.php', 'Withdrawals', 'banknote'],
        ],
        'Community' => [
            ['/dashboard/admin/community-stats.php', 'Community Stats', 'trending-up'],
            ['/dashboard/admin/reports.php', 'Reports', 'shield'],
        ],
        'System' => [
            ['/dashboard/admin/audit-log.php', 'Audit Log', 'scroll-text'],
            ['/dashboard/settings.php', 'Settings', 'settings'],
        ],
    ],
];
$navGroups = $navByRole[$user['role']];
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
    $navBadges = [
        '/dashboard/admin/creator-applications.php' => (int) db_one("SELECT COUNT(*) AS n FROM creator_applications WHERE status='PENDING'")['n'],
        '/dashboard/admin/withdrawals.php' => (int) db_one("SELECT COUNT(*) AS n FROM withdrawal_requests WHERE status='PENDING'")['n'],
        '/dashboard/admin/courses.php' => (int) db_one("SELECT COUNT(*) AS n FROM courses WHERE status='PENDING_REVIEW'")['n'],
        '/dashboard/admin/leads.php' => (int) db_one("SELECT COUNT(*) AS n FROM leads WHERE status='NEW'")['n'],
        '/dashboard/admin/reports.php' => (int) db_one("SELECT COUNT(*) AS n FROM community_reports WHERE status='pending'")['n'],
    ];
    $sidebarWidget = [
        'title' => 'This Month',
        'icon' => 'trending-up',
        'rows' => [
            ['label' => 'Platform Revenue', 'value' => format_money((float) (db_one("SELECT COALESCE(SUM(platform_fee),0) AS n FROM earnings WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")['n'] ?? 0))],
            ['label' => 'New Signups', 'value' => (string) (int) db_one("SELECT COUNT(*) AS n FROM users WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")['n']],
        ],
    ];
} elseif ($user['role'] === 'CREATOR') {
    $sidebarWidget = [
        'title' => 'Your Earnings',
        'icon' => 'wallet',
        'rows' => [
            ['label' => 'Total Earned', 'value' => format_money((float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM earnings WHERE creator_id=?', [$user['id']])['n'] ?? 0))],
            ['label' => 'Published Courses', 'value' => (string) (int) db_one("SELECT COUNT(*) AS n FROM courses WHERE creator_id=? AND status='PUBLISHED'", [$user['id']])['n']],
        ],
    ];
} elseif ($user['role'] === 'LEARNER') {
    $enrollStats = db_one('SELECT COUNT(*) AS n, COALESCE(AVG(progress),0) AS avg_progress FROM enrollments WHERE user_id=?', [$user['id']]);
    $sidebarWidget = [
        'title' => 'Your Progress',
        'icon' => 'graduation-cap',
        'rows' => [
            ['label' => 'Enrolled Courses', 'value' => (string) (int) $enrollStats['n']],
            ['label' => 'Avg. Completion', 'value' => round((float) $enrollStats['avg_progress']) . '%'],
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/dashboard.css')) ?>">
</head>
<body>
<div class="dash<?= $user['role'] === 'ADMIN' ? ' dash-admin' : '' ?>">
  <div class="dash-overlay" data-dash-overlay data-dash-close></div>
  <aside class="dash-sidebar" data-dash-sidebar>
    <div class="dash-sidebar-head">
      <?php render_logo(true); ?>
      <button data-dash-close aria-label="Close menu">✕</button>
    </div>

    <a href="<?= e(base_url('index.php')) ?>" target="_blank" rel="noopener" class="dash-visit-site">
      <?php dash_icon('arrow-right', 'visit-icon'); ?>
      <span>Visit Live Site</span>
    </a>

    <div class="dash-nav-scroll">
      <?php foreach ($navGroups as $groupLabel => $items): ?>
        <div class="dash-nav-group">
          <div class="dash-nav-group-label"><?= e($groupLabel) ?></div>
          <nav class="dash-nav">
            <?php foreach ($items as [$href, $label, $icon]): $badge = $navBadges[$href] ?? 0; ?>
              <a href="<?= e(base_url($href)) ?>" class="<?= $currentPath === $href ? 'active' : '' ?>">
                <?php dash_icon($icon); ?><span><?= e($label) ?></span>
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
  </aside>

  <div class="dash-main">
    <div class="dash-header">
      <div class="dash-header-left">
        <button data-dash-open class="dash-hamburger" aria-label="Open menu">☰</button>
        <?php render_logo(true, 'dash-header-logo'); ?>
        <div class="dash-search">
          <?php dash_icon('search'); ?>
          <input type="text" placeholder="Search learners, courses, payments…">
        </div>
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
