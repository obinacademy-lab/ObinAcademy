<?php
/**
 * Admin: one creator in full — profile, money, every course, recent sales and
 * withdrawals. Reached from creators.php. Read-only; course management and
 * review stay on their own pages (linked from each course row).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['ADMIN']);

$id = (int) query_param('id');
$c = db_one('SELECT * FROM users WHERE id = ?', [$id]);
if (!$c) { http_response_code(404); exit('Creator not found'); }

$money = fn(string $sql, array $p = []): float => (float) (db_one($sql, $p)['n'] ?? 0);

$gross = $money("SELECT COALESCE(SUM(gross_amount),0) AS n FROM earnings WHERE creator_id = ?", [$id]);
$fee = $money("SELECT COALESCE(SUM(platform_fee),0) AS n FROM earnings WHERE creator_id = ?", [$id]);
$net = $money("SELECT COALESCE(SUM(amount),0) AS n FROM earnings WHERE creator_id = ?", [$id]);
$sales = (int) db_one("SELECT COUNT(*) AS n FROM earnings WHERE creator_id = ? AND gross_amount > 0", [$id])['n'];
$gross30 = $money("SELECT COALESCE(SUM(gross_amount),0) AS n FROM earnings WHERE creator_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$id]);
$sales30 = (int) db_one("SELECT COUNT(*) AS n FROM earnings WHERE creator_id = ? AND gross_amount > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$id])['n'];
$legacy = $money("SELECT COALESCE(SUM(payout_amount),0) AS n FROM creator_subscription_payouts WHERE creator_id = ?", [$id]);
$wPending = $money("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE creator_id = ? AND status = 'PENDING'", [$id]);
$wPaid = $money("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE creator_id = ? AND status = 'APPROVED'", [$id]);
$available = $net + $legacy - $wPending - $wPaid;
$students = (int) db_one("SELECT COUNT(DISTINCT COALESCE(CAST(e.user_id AS CHAR), CONCAT('g', e.id))) AS n FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.creator_id = ?", [$id])['n'];
$followers = (int) (db_one('SELECT COUNT(*) AS n FROM school_follows WHERE creator_id = ?', [$id])['n'] ?? 0);
$subscribers = (int) (db_one("SELECT COUNT(DISTINCT learner_id) AS n FROM school_subscriptions WHERE creator_id = ? AND status IN ('ACTIVE','GRACE')", [$id])['n'] ?? 0);
$subRevenue = $money("SELECT COALESCE(SUM(gross_amount),0) AS n FROM earnings WHERE creator_id = ? AND course_id IS NULL", [$id]);

$courses = db_all(
    "SELECT c.*, cat.name AS category_name,
            (SELECT COUNT(DISTINCT COALESCE(CAST(e.user_id AS CHAR), CONCAT('g', e.id))) FROM enrollments e WHERE e.course_id = c.id) AS students,
            (SELECT COUNT(*) FROM earnings x WHERE x.course_id = c.id AND x.gross_amount > 0) AS sales,
            (SELECT COALESCE(SUM(x.gross_amount),0) FROM earnings x WHERE x.course_id = c.id) AS gross,
            (SELECT COALESCE(SUM(x.amount),0) FROM earnings x WHERE x.course_id = c.id) AS net,
            (SELECT COALESCE(AVG(r.rating),0) FROM reviews r WHERE r.course_id = c.id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews r WHERE r.course_id = c.id) AS review_count,
            (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = c.id) AS lesson_count
     FROM courses c JOIN categories cat ON cat.id = c.category_id
     WHERE c.creator_id = ? ORDER BY FIELD(c.status,'PUBLISHED','PENDING_REVIEW','DRAFT','REJECTED','REMOVED'), gross DESC, c.created_at DESC",
    [$id]
);

$recent = db_all(
    "SELECT x.created_at, x.gross_amount, x.platform_fee, x.amount, c.title AS course_title, c.slug
     FROM earnings x LEFT JOIN courses c ON c.id = x.course_id
     WHERE x.creator_id = ? AND x.gross_amount > 0 ORDER BY x.created_at DESC, x.id DESC LIMIT 25",
    [$id]
);
$withdrawals = db_all('SELECT amount, phone, status, requested_at, resolved_at FROM withdrawal_requests WHERE creator_id = ? ORDER BY requested_at DESC LIMIT 10', [$id]);

// 30-day gross-sales bars (calendar days, zero-filled).
$byDay = [];
foreach (db_all("SELECT DATE(created_at) AS d, SUM(gross_amount) AS g FROM earnings WHERE creator_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at)", [$id]) as $r) {
    $byDay[$r['d']] = (float) $r['g'];
}
$series = [];
for ($i = 29; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $series[] = ['d' => $d, 'g' => $byDay[$d] ?? 0.0]; }
$maxDay = max(1.0, max(array_column($series, 'g')));

$subscriptionSchool = $c['pricing_model'] === 'MONTHLY_SUBSCRIPTION' && (float) $c['school_monthly_price'] > 0;
$badgeClass = ['DRAFT' => 'badge-draft', 'PENDING_REVIEW' => 'badge-pending', 'PUBLISHED' => 'badge-published', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected'];
$statusLabel = ['DRAFT' => 'Draft', 'PENDING_REVIEW' => 'Pending review', 'PUBLISHED' => 'Published', 'REJECTED' => 'Rejected', 'REMOVED' => 'Removed'];
$waPhone = null;
if (!empty($c['phone'])) {
    $digits = preg_replace('/\D/', '', $c['phone']);
    $waPhone = str_starts_with($digits, '256') ? $digits : '256' . ltrim($digits, '0');
}

$pageTitle = ($c['school_name'] ?: $c['name']) . ' — Creator — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<p class="small" style="margin-bottom:10px;"><a href="<?= e(base_url('dashboard/admin/creators.php')) ?>" style="color:var(--muted);">&larr; All creators</a></p>

<div class="card card-pad cm-head">
  <span class="cm-avatar cm-avatar-lg"><?php if (!empty($c['avatar_url'])): ?><img src="<?= e(asset_src($c['avatar_url'])) ?>" alt=""><?php else: ?><?= e(mb_substr($c['name'], 0, 1)) ?><?php endif; ?></span>
  <div style="min-width:0; flex:1;">
    <h1 class="h2" style="margin:0;"><?= e($c['school_name'] ?: $c['name']) ?></h1>
    <p class="muted" style="margin-top:4px;"><?= e($c['name']) ?> · <?= e(strtolower($c['role'])) ?> · joined <?= e(format_date($c['created_at'])) ?>
      <?php if ($subscriptionSchool): ?>· subscription school (<?= e(format_money((float) $c['school_monthly_price'])) ?>/mo)<?php endif; ?></p>
    <p class="small muted" style="margin-top:6px;">
      <a href="mailto:<?= e($c['email']) ?>" style="color:inherit;"><?= e($c['email']) ?></a>
      <?php if (!empty($c['phone'])): ?>· <?= e($c['phone']) ?> · <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--dash-good);">WhatsApp</a><?php endif; ?>
    </p>
  </div>
  <div class="row gap-2 wrap">
    <a class="btn btn-outline btn-sm" href="<?= e(base_url('profile.php?id=' . (int) $c['id'])) ?>" target="_blank" rel="noopener">View school page</a>
    <a class="btn btn-outline btn-sm" href="<?= e(base_url('dashboard/admin/users.php?q=' . urlencode($c['email']))) ?>">Account settings</a>
  </div>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:18px; gap:14px;">
  <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= e(format_money($gross)) ?></span><span class="mini-stat-label">Gross sales · <?= number_format($sales) ?> sale<?= $sales === 1 ? '' : 's' ?></span></div>
  <div class="mini-stat" style="--tint:#34d399;"><span class="mini-stat-value"><?= e(format_money($net)) ?></span><span class="mini-stat-label">Earned by creator (after fee)</span></div>
  <div class="mini-stat" style="--tint:#f87171;"><span class="mini-stat-value"><?= e(format_money($fee)) ?></span><span class="mini-stat-label">Platform fee</span></div>
  <div class="mini-stat" style="--tint:#a78bfa;"><span class="mini-stat-value"><?= e(format_money(max(0.0, $available))) ?></span><span class="mini-stat-label">Available to withdraw</span></div>
  <div class="mini-stat" style="--tint:#60a5fa;"><span class="mini-stat-value"><?= number_format($students) ?></span><span class="mini-stat-label">Students</span></div>
  <div class="mini-stat"><span class="mini-stat-value"><?= count($courses) ?></span><span class="mini-stat-label">Courses · <?= count(array_filter($courses, fn($x) => $x['status'] === 'PUBLISHED')) ?> live</span></div>
  <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= e(format_money($gross30)) ?></span><span class="mini-stat-label">Last 30 days · <?= number_format($sales30) ?> sale<?= $sales30 === 1 ? '' : 's' ?></span></div>
  <div class="mini-stat"><span class="mini-stat-value"><?= number_format($followers) ?></span><span class="mini-stat-label">Followers<?= $subscriptionSchool ? ' · ' . $subscribers . ' subscribers' : '' ?></span></div>
</div>

<h3 class="dash-section-label" style="margin-top:28px;">Sales, last 30 days</h3>
<div class="card card-pad" style="margin-top:12px;">
  <?php if ($gross30 <= 0): ?>
    <p class="muted" style="margin:0;">No paid sales in the last 30 days.</p>
  <?php else: ?>
    <svg class="cm-bars" viewBox="0 0 600 110" preserveAspectRatio="none" role="img" aria-label="Gross sales per day over the last 30 days">
      <?php foreach ($series as $i => $s): $h = $s['g'] > 0 ? max(3, ($s['g'] / $maxDay) * 100) : 0; ?>
        <rect x="<?= $i * 20 + 2 ?>" y="<?= 106 - $h ?>" width="16" height="<?= $h ?>" rx="2" fill="currentColor" opacity="<?= $s['g'] > 0 ? '0.9' : '0' ?>"><title><?= e(date('j M', strtotime($s['d']))) ?>: <?= e(format_money($s['g'])) ?></title></rect>
      <?php endforeach; ?>
      <line x1="0" y1="107" x2="600" y2="107" stroke="currentColor" opacity="0.15"/>
    </svg>
    <div class="cm-bars-axis"><span><?= e(date('j M', strtotime($series[0]['d']))) ?></span><span>Best day <?= e(format_money($maxDay)) ?></span><span>Today</span></div>
  <?php endif; ?>
</div>

<h3 class="dash-section-label" style="margin-top:28px;">Courses</h3>
<?php if (!$courses): ?>
  <div class="card card-pad" style="margin-top:12px; border-style:dashed; text-align:center;"><p class="muted">This creator has no courses yet.</p></div>
<?php else: ?>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table">
      <thead><tr><th>Course</th><th>Status</th><th class="num">Price</th><th class="num">Students</th><th class="num">Sales</th><th class="num">Gross</th><th class="num">Rating</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($courses as $co):
            $inSub = $subscriptionSchool && (int) $co['subscription_included'] === 1;
            $price = (float) $co['price'];
            $onSale = !empty($co['sale_price']) && (empty($co['sale_ends_at']) || strtotime($co['sale_ends_at']) > time());
        ?>
          <tr>
            <td data-label="Course">
              <div class="cm-course">
                <strong><?= e($co['title']) ?></strong>
                <small><?= e($co['category_name']) ?> · <?= (int) $co['lesson_count'] ?> lesson<?= (int) $co['lesson_count'] === 1 ? '' : 's' ?> · <?= number_format((int) $co['view_count']) ?> views · added <?= e(format_date($co['created_at'])) ?></small>
              </div>
            </td>
            <td data-label="Status"><span class="badge <?= $badgeClass[$co['status']] ?? 'badge-draft' ?>"><?= e($statusLabel[$co['status']] ?? $co['status']) ?></span></td>
            <td class="num" data-label="Price">
              <?php if ($inSub): ?>In subscription
              <?php elseif ($price <= 0): ?>Free
              <?php else: ?><?= e(format_money($onSale ? (float) $co['sale_price'] : $price)) ?><?= $onSale ? '<small class="cm-sub">on sale</small>' : '' ?><?= (int) $co['installments_enabled'] === 1 ? '<small class="cm-sub">2 installments on</small>' : '' ?>
              <?php endif; ?>
            </td>
            <td class="num" data-label="Students"><?= number_format((int) $co['students']) ?></td>
            <td class="num" data-label="Sales"><?= number_format((int) $co['sales']) ?></td>
            <td class="num" data-label="Gross"><?= e(format_money((float) $co['gross'])) ?></td>
            <td class="num" data-label="Rating"><?= (int) $co['review_count'] ? number_format((float) $co['avg_rating'], 1) . ' <small class="muted">(' . (int) $co['review_count'] . ')</small>' : '<span class="muted">—</span>' ?></td>
            <td class="cm-go">
              <?php if ($co['status'] === 'PENDING_REVIEW'): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(base_url('dashboard/admin/course-review.php?id=' . (int) $co['id'])) ?>">Review</a>
              <?php else: ?>
                <a class="btn btn-outline btn-sm" href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . (int) $co['id'])) ?>">Manage</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($subRevenue > 0): ?>
          <tr class="cm-extra"><td data-label="Course"><div class="cm-course"><strong>School subscriptions</strong><small>Not tied to one course</small></div></td><td></td><td></td><td></td><td></td><td class="num" data-label="Gross"><?= e(format_money($subRevenue)) ?></td><td></td><td></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:28px;">Recent sales</h3>
<?php if (!$recent): ?>
  <div class="card card-pad" style="margin-top:12px; border-style:dashed; text-align:center;"><p class="muted">No paid sales yet.</p></div>
<?php else: ?>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table">
      <thead><tr><th>When</th><th>Course</th><th class="num">Paid</th><th class="num">Platform fee</th><th class="num">Creator gets</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $s): ?>
          <tr>
            <td data-label="When"><?= e(format_date($s['created_at'])) ?> <small class="muted"><?= e(date('g:i A', strtotime($s['created_at']))) ?></small></td>
            <td data-label="Course"><?= $s['course_title'] ? e($s['course_title']) : '<span class="muted">School subscription</span>' ?></td>
            <td class="num" data-label="Paid"><?= e(format_money((float) $s['gross_amount'])) ?></td>
            <td class="num" data-label="Platform fee"><?= e(format_money((float) $s['platform_fee'])) ?></td>
            <td class="num" data-label="Creator gets"><?= e(format_money((float) $s['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($sales > count($recent)): ?><p class="small muted" style="margin-top:8px;">Showing the latest <?= count($recent) ?> of <?= number_format($sales) ?> sales.</p><?php endif; ?>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:28px;">Withdrawals</h3>
<p class="small muted" style="margin-top:6px;">Paid out <?= e(format_money($wPaid)) ?> · requested and waiting <?= e(format_money($wPending)) ?>.
  <a href="<?= e(base_url('dashboard/admin/withdrawals.php')) ?>" style="color:var(--accent);">Open Withdrawals</a></p>
<?php if ($withdrawals): ?>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table">
      <thead><tr><th>Requested</th><th class="num">Amount</th><th>Phone</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($withdrawals as $w): ?>
          <tr>
            <td data-label="Requested"><?= e(format_date($w['requested_at'])) ?></td>
            <td class="num" data-label="Amount"><?= e(format_money((float) $w['amount'])) ?></td>
            <td data-label="Phone"><?= e($w['phone']) ?></td>
            <td data-label="Status"><span class="badge <?= $w['status'] === 'APPROVED' ? 'badge-published' : ($w['status'] === 'PENDING' ? 'badge-pending' : 'badge-rejected') ?>"><?= e(ucfirst(strtolower($w['status']))) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
