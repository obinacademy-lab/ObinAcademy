<?php
/**
 * Admin: one search across people, courses and payments — what the box at the top of every admin
 * page opens. Each group shows the closest matches and a link to the full list.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/admin_payments.php';
$user = require_role(['ADMIN']);

$q = trim(query_param('q'));
$like = '%' . addcslashes($q, '%_\\') . '%';
$people = $courses = $payments = [];

if (mb_strlen($q) >= 2) {
    $people = db_all(
        'SELECT id, name, email, phone, role, created_at FROM users WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR school_name LIKE ? ORDER BY created_at DESC LIMIT 12',
        [$like, $like, $like, $like]
    );
    $courses = db_all(
        "SELECT c.id, c.title, c.status, c.price, COALESCE(u.school_name, u.name) AS creator_label
         FROM courses c JOIN users u ON u.id = c.creator_id WHERE c.title LIKE ? OR c.slug LIKE ? ORDER BY c.created_at DESC LIMIT 12",
        [$like, $like]
    );
    $payments = admin_get_payments(['status' => '', 'type' => '', 'q' => $q, 'from' => '', 'to' => ''], 8, 0);
}
$paymentTotal = mb_strlen($q) >= 2 ? admin_count_payments(['status' => '', 'type' => '', 'q' => $q, 'from' => '', 'to' => '']) : 0;
$badge = ['PUBLISHED' => 'badge-published', 'PENDING_REVIEW' => 'badge-pending', 'DRAFT' => 'badge-draft', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected', 'SUCCESS' => 'badge-published', 'FAILED' => 'badge-rejected', 'PENDING' => 'badge-pending'];
$roleLabel = ['LEARNER' => 'Learner', 'CREATOR' => 'Creator', 'ADMIN' => 'Admin'];
$none = !$people && !$courses && !$payments;

$pageTitle = 'Search — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="vw-top">
  <div>
    <h1 class="h2">Search</h1>
    <p class="muted" style="margin-top:4px;"><?= mb_strlen($q) >= 2 ? 'Results for “' . e($q) . '”' : 'Type at least two letters in the box at the top to find people, courses and payments.' ?></p>
  </div>
</div>

<?php if (mb_strlen($q) >= 2 && $none): ?>
  <div class="card card-pad" style="margin-top:20px; border-style:dashed; text-align:center;"><p class="muted">Nothing found for “<?= e($q) ?>”. Try a name, an email, a phone number, a course title or a payment reference.</p></div>
<?php endif; ?>

<?php if ($people): ?>
  <h3 class="dash-section-label" style="margin-top:24px;">People</h3>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table"><tbody>
      <?php foreach ($people as $p): ?>
        <tr class="cm-row" data-href="<?= e(base_url('dashboard/admin/user.php?id=' . (int) $p['id'])) ?>">
          <td data-label="Name"><a href="<?= e(base_url('dashboard/admin/user.php?id=' . (int) $p['id'])) ?>" style="color:inherit; font-weight:700;"><?= e($p['name']) ?></a><small class="cm-sub"><?= e($p['email']) ?><?= $p['phone'] ? ' · ' . e($p['phone']) : '' ?></small></td>
          <td data-label="Role"><span class="badge badge-draft"><?= e($roleLabel[$p['role']] ?? $p['role']) ?></span></td>
          <td data-label="Joined"><?= e(format_date($p['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
<?php endif; ?>

<?php if ($courses): ?>
  <h3 class="dash-section-label" style="margin-top:24px;">Courses</h3>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table"><tbody>
      <?php foreach ($courses as $c):
          $to = $c['status'] === 'PENDING_REVIEW' ? 'dashboard/admin/course-review.php?id=' : 'dashboard/creator/course-manage.php?id='; ?>
        <tr class="cm-row" data-href="<?= e(base_url($to . (int) $c['id'])) ?>">
          <td data-label="Course"><a href="<?= e(base_url($to . (int) $c['id'])) ?>" style="color:inherit; font-weight:700;"><?= e($c['title']) ?></a><small class="cm-sub">by <?= e($c['creator_label']) ?></small></td>
          <td data-label="Status"><span class="badge <?= $badge[$c['status']] ?? 'badge-draft' ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', $c['status'])))) ?></span></td>
          <td class="num" data-label="Price"><?= (float) $c['price'] > 0 ? e(format_money((float) $c['price'])) : 'Free / subscription' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
<?php endif; ?>

<?php if ($payments): ?>
  <h3 class="dash-section-label" style="margin-top:24px;">Payments</h3>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table"><tbody>
      <?php foreach ($payments as $p): ?>
        <tr class="cm-row" data-href="<?= e(base_url('dashboard/admin/payments.php?q=' . urlencode($q))) ?>">
          <td data-label="When"><?= e(format_date($p['created_at'])) ?><small class="cm-sub">#<?= (int) $p['id'] ?></small></td>
          <td data-label="Buyer"><?= e($p['payer_name'] ?: ($p['guest_name'] ?: 'Guest')) ?><small class="cm-sub"><?= e($p['phone']) ?></small></td>
          <td data-label="For"><?= e(admin_payment_what($p)) ?></td>
          <td class="num" data-label="Amount"><?= e(format_money((float) $p['amount'])) ?></td>
          <td data-label="Status"><span class="badge <?= $badge[$p['status']] ?? 'badge-draft' ?>"><?= e(ucfirst(strtolower($p['status']))) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
  <?php if ($paymentTotal > count($payments)): ?>
    <p class="small" style="margin-top:10px;"><a href="<?= e(base_url('dashboard/admin/payments.php?q=' . urlencode($q))) ?>" style="color:var(--accent); font-weight:700;">See all <?= number_format($paymentTotal) ?> matching payments →</a></p>
  <?php endif; ?>
<?php endif; ?>
<script>document.querySelectorAll('.cm-row[data-href]').forEach(function (r) { r.addEventListener('click', function (e) { if (!e.target.closest('a')) location.href = r.dataset.href; }); });</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
