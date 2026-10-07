<?php
/**
 * Admin: monitor every creator — their courses, students and sales at a glance.
 * One row per creator; click through to creator.php for the full picture.
 *
 * "Sales" are paid rows in `earnings` (gross_amount > 0). Free enrollments also
 * write a zero-value earnings row, so they are excluded from sale counts.
 * The available balance uses the same formula as the creator's own Earnings page.
 */
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$q = query_param('q');
$filter = query_param('filter');
$sort = query_param('sort', 'sales');
if (!in_array($filter, ['', 'selling', 'nosales', 'pending'], true)) $filter = '';
$sorts = ['sales' => 'Top sales', 'students' => 'Most students', 'courses' => 'Most courses', 'recent' => 'Latest sale', 'newest' => 'Newest creators'];
if (!isset($sorts[$sort])) $sort = 'sales';

$params = [];
$sql = "
  SELECT u.id, u.name, u.email, u.phone, u.role, u.school_name, u.avatar_url, u.created_at,
         COALESCE(cs.total, 0) AS courses_total, COALESCE(cs.live, 0) AS courses_live, COALESCE(cs.pending_review, 0) AS courses_pending,
         COALESCE(st.students, 0) AS students,
         COALESCE(ea.sales, 0) AS sales, COALESCE(ea.gross, 0) AS gross, COALESCE(ea.fee, 0) AS fee, COALESCE(ea.net, 0) AS net, ea.last_sale,
         COALESCE(sp.payouts, 0) AS legacy_payouts,
         COALESCE(w.w_pending, 0) AS w_pending, COALESCE(w.w_approved, 0) AS w_approved
  FROM users u
  LEFT JOIN (SELECT creator_id, COUNT(*) AS total, SUM(status = 'PUBLISHED') AS live, SUM(status = 'PENDING_REVIEW') AS pending_review
             FROM courses GROUP BY creator_id) cs ON cs.creator_id = u.id
  LEFT JOIN (SELECT c.creator_id, COUNT(DISTINCT COALESCE(CAST(e.user_id AS CHAR), CONCAT('g', e.id))) AS students
             FROM enrollments e JOIN courses c ON c.id = e.course_id GROUP BY c.creator_id) st ON st.creator_id = u.id
  LEFT JOIN (SELECT creator_id, SUM(gross_amount > 0) AS sales, SUM(gross_amount) AS gross, SUM(platform_fee) AS fee, SUM(amount) AS net,
                    MAX(CASE WHEN gross_amount > 0 THEN created_at END) AS last_sale
             FROM earnings GROUP BY creator_id) ea ON ea.creator_id = u.id
  LEFT JOIN (SELECT creator_id, SUM(payout_amount) AS payouts FROM creator_subscription_payouts GROUP BY creator_id) sp ON sp.creator_id = u.id
  LEFT JOIN (SELECT creator_id, SUM(CASE WHEN status = 'PENDING' THEN amount ELSE 0 END) AS w_pending,
                    SUM(CASE WHEN status = 'APPROVED' THEN amount ELSE 0 END) AS w_approved
             FROM withdrawal_requests WHERE creator_id IS NOT NULL GROUP BY creator_id) w ON w.creator_id = u.id
  WHERE (u.role = 'CREATOR' OR COALESCE(cs.total, 0) > 0)";
if ($q !== '') {
    $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.school_name LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$all = db_all($sql, $params);

foreach ($all as &$r) {
    $r['available'] = (float) $r['net'] + (float) $r['legacy_payouts'] - (float) $r['w_pending'] - (float) $r['w_approved'];
}
unset($r);

// Summary cards cover everyone matching the search, regardless of the filter pill.
$sum = ['creators' => count($all), 'selling' => 0, 'live' => 0, 'students' => 0, 'gross' => 0.0, 'fee' => 0.0, 'owed' => 0.0, 'pending' => 0];
foreach ($all as $r) {
    if ((int) $r['sales'] > 0) $sum['selling']++;
    $sum['live'] += (int) $r['courses_live'];
    $sum['students'] += (int) $r['students'];
    $sum['gross'] += (float) $r['gross'];
    $sum['fee'] += (float) $r['fee'];
    $sum['owed'] += max(0.0, $r['available']);
    if ((int) $r['courses_pending'] > 0) $sum['pending']++;
}

$creators = array_values(array_filter($all, function ($r) use ($filter) {
    return match ($filter) {
        'selling' => (int) $r['sales'] > 0,
        'nosales' => (int) $r['sales'] === 0,
        'pending' => (int) $r['courses_pending'] > 0,
        default => true,
    };
}));
usort($creators, function ($a, $b) use ($sort) {
    return match ($sort) {
        'students' => [(int) $b['students'], (float) $b['gross']] <=> [(int) $a['students'], (float) $a['gross']],
        'courses' => [(int) $b['courses_live'], (int) $b['courses_total']] <=> [(int) $a['courses_live'], (int) $a['courses_total']],
        'recent' => strcmp((string) ($b['last_sale'] ?? ''), (string) ($a['last_sale'] ?? '')),
        'newest' => strcmp((string) $b['created_at'], (string) $a['created_at']),
        default => [(float) $b['gross'], (int) $b['sales']] <=> [(float) $a['gross'], (int) $a['sales']],
    };
});

$link = function (array $over = []) use ($q, $filter, $sort): string {
    $p = array_filter(array_merge(['q' => $q, 'filter' => $filter, 'sort' => $sort === 'sales' ? '' : $sort], $over), fn($v) => $v !== '' && $v !== null);
    return base_url('dashboard/admin/creators.php' . ($p ? '?' . http_build_query($p) : ''));
};
$filters = ['' => 'All creators', 'selling' => 'Selling', 'nosales' => 'No sales yet', 'pending' => 'Courses awaiting review'];

$pageTitle = 'Creators — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3" style="align-items:flex-end;">
  <div>
    <h1 class="h2">Creators</h1>
    <p class="muted" style="margin-top:6px;">Every creator on Obin Academy, with their courses, students and sales. Open a creator for the full breakdown.</p>
  </div>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:20px; gap:14px;">
  <div class="mini-stat"><span class="mini-stat-value"><?= (int) $sum['creators'] ?></span><span class="mini-stat-label">Creators · <?= (int) $sum['selling'] ?> selling</span></div>
  <div class="mini-stat" style="--tint:#34d399;"><span class="mini-stat-value"><?= (int) $sum['live'] ?></span><span class="mini-stat-label">Live courses</span></div>
  <div class="mini-stat" style="--tint:#60a5fa;"><span class="mini-stat-value"><?= number_format($sum['students']) ?></span><span class="mini-stat-label">Students enrolled</span></div>
  <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= e(format_money($sum['gross'])) ?></span><span class="mini-stat-label">Gross sales · all time</span></div>
  <div class="mini-stat" style="--tint:#f87171;"><span class="mini-stat-value"><?= e(format_money($sum['fee'])) ?></span><span class="mini-stat-label">Platform fees earned</span></div>
  <div class="mini-stat" style="--tint:#a78bfa;"><span class="mini-stat-value"><?= e(format_money($sum['owed'])) ?></span><span class="mini-stat-label">Owed to creators (unwithdrawn)</span></div>
</div>

<div class="row between wrap gap-3" style="margin-top:26px; align-items:center;">
  <form method="get" class="search-pill" style="max-width:340px; margin:0;">
    <?php dash_icon('search'); ?>
    <input type="text" name="q" placeholder="Search name, school, email or phone" value="<?= e($q) ?>">
    <?php if ($filter): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
    <?php if ($sort !== 'sales'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
  </form>
  <p class="small muted"><?= count($creators) ?> creator<?= count($creators) === 1 ? '' : 's' ?><?= $q ? ' matching "' . e($q) . '"' : '' ?></p>
</div>

<div class="row gap-2 wrap" style="margin-top:14px;">
  <?php foreach ($filters as $val => $label): ?>
    <a href="<?= e($link(['filter' => $val])) ?>" class="btn btn-sm <?= $filter === $val ? 'btn-primary' : 'btn-outline' ?>"><?= e($label) ?><?= $val === 'pending' && $sum['pending'] ? ' (' . (int) $sum['pending'] . ')' : '' ?></a>
  <?php endforeach; ?>
</div>
<div class="row gap-2 wrap" style="margin-top:8px; align-items:center;">
  <span class="small muted">Sort by</span>
  <?php foreach ($sorts as $val => $label): ?>
    <a href="<?= e($link(['sort' => $val === 'sales' ? '' : $val])) ?>" class="cm-sortpill<?= $sort === $val ? ' is-on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$creators): ?>
  <div class="card card-pad" style="margin-top:18px; border-style:dashed; text-align:center;">
    <p class="muted">No creators match this view.</p>
  </div>
<?php else: ?>
  <div class="card cm-wrap" style="margin-top:18px;">
    <table class="cm-table">
      <thead>
        <tr>
          <th>Creator</th>
          <th class="num">Courses</th>
          <th class="num">Students</th>
          <th class="num">Sales</th>
          <th class="num">Gross</th>
          <th class="num">Available</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($creators as $c): $isSelf = (int) $c['id'] === (int) $user['id']; ?>
          <tr class="cm-row" data-href="<?= e(base_url('dashboard/admin/creator.php?id=' . (int) $c['id'])) ?>">
            <td data-label="Creator">
              <a class="cm-who" href="<?= e(base_url('dashboard/admin/creator.php?id=' . (int) $c['id'])) ?>">
                <span class="cm-avatar"><?php if (!empty($c['avatar_url'])): ?><img src="<?= e(asset_src($c['avatar_url'])) ?>" alt=""><?php else: ?><?= e(mb_substr($c['name'], 0, 1)) ?><?php endif; ?></span>
                <span class="cm-who-text">
                  <strong><?= e($c['school_name'] ?: $c['name']) ?><?= $isSelf ? ' <span class="you-badge">You</span>' : '' ?></strong>
                  <small><?= e($c['school_name'] ? $c['name'] . ' · ' : '') ?><?= e($c['email']) ?></small>
                </span>
              </a>
            </td>
            <td class="num" data-label="Courses">
              <strong><?= (int) $c['courses_live'] ?></strong><span class="muted"> / <?= (int) $c['courses_total'] ?></span>
              <?php if ((int) $c['courses_pending'] > 0): ?><span class="badge badge-pending cm-badge"><?= (int) $c['courses_pending'] ?> to review</span><?php endif; ?>
            </td>
            <td class="num" data-label="Students"><?= number_format((int) $c['students']) ?></td>
            <td class="num" data-label="Sales"><?= number_format((int) $c['sales']) ?><small class="cm-sub"><?= $c['last_sale'] ? 'last ' . e(time_ago($c['last_sale'])) : 'no sales yet' ?></small></td>
            <td class="num" data-label="Gross"><?= e(format_money((float) $c['gross'])) ?></td>
            <td class="num" data-label="Available"><?= e(format_money(max(0.0, $c['available']))) ?><?php if ((float) $c['w_pending'] > 0): ?><small class="cm-sub">UGX <?= number_format((float) $c['w_pending']) ?> requested</small><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="small muted" style="margin-top:10px;">Sales count paid purchases only: courses, bundles, gifts, installments and school subscriptions. Free enrollments appear under Students. "Available" is what the creator could withdraw right now.</p>
<?php endif; ?>
<script>document.querySelectorAll('.cm-row[data-href]').forEach(function (r) { r.addEventListener('click', function (e) { if (!e.target.closest('a')) location.href = r.dataset.href; }); });</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
