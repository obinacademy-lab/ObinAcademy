<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/coupons.php';
$user = require_role(['CREATOR', 'ADMIN']);

$errors = [];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    if ($action === 'create') {
        $courseId = post('courseId') !== '' ? (int) post('courseId') : null;
        $maxUses = post('maxUses') !== '' ? (int) post('maxUses') : null;
        $expiresAtRaw = post('expiresAt');
        $expiresAt = $expiresAtRaw !== '' ? $expiresAtRaw . ' 23:59:59' : null;
        $result = create_coupon(
            (int) $user['id'],
            post('code'),
            post('discountType'),
            (float) post('discountValue', '0'),
            $courseId,
            $maxUses,
            $expiresAt
        );
        if (isset($result['error'])) {
            $errors[] = $result['error'];
        } else {
            $notice = 'Coupon created.';
        }
    } elseif ($action === 'toggle') {
        $couponId = (int) post('couponId');
        $coupon = db_one('SELECT status FROM coupons WHERE id = ? AND creator_id = ?', [$couponId, $user['id']]);
        if ($coupon) {
            $newStatus = $coupon['status'] === 'ACTIVE' ? 'DISABLED' : 'ACTIVE';
            set_coupon_status((int) $user['id'], $couponId, $newStatus);
            $notice = $newStatus === 'ACTIVE' ? 'Coupon re-enabled.' : 'Coupon disabled.';
        }
    } elseif ($action === 'delete') {
        delete_coupon((int) $user['id'], (int) post('couponId'));
        $notice = 'Coupon deleted.';
    }
}

$coupons = get_coupons_for_creator((int) $user['id']);
$myCourses = db_all("SELECT id, title FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' ORDER BY title", [$user['id']]);

$pageTitle = 'Coupons — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Coupons</h1>
<p class="muted" style="margin-top:6px;">Run a discount code for one course or your whole catalog — a learner enters it at checkout.</p>

<?php if ($errors): ?><div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success" style="margin-top:16px;"><?= e($notice) ?></div><?php endif; ?>

<form method="post" class="card card-pad" style="margin-top:20px; max-width:640px;">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <h2 class="h3" style="margin:0 0 16px;">New Coupon</h2>
  <div class="grid sm:grid-2">
    <div class="field">
      <label for="code">Code</label>
      <input id="code" name="code" required placeholder="e.g. LAUNCH20" style="text-transform:uppercase;" maxlength="40">
      <p class="help">Letters, numbers, dashes, underscores. Not case-sensitive.</p>
    </div>
    <div class="field">
      <label for="courseId">Applies To</label>
      <select id="courseId" name="courseId">
        <option value="">All of my courses</option>
        <?php foreach ($myCourses as $c): ?>
          <option value="<?= (int) $c['id'] ?>"><?= e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="grid sm:grid-2">
    <div class="field">
      <label for="discountType">Discount Type</label>
      <select id="discountType" name="discountType">
        <option value="PERCENT">Percentage off</option>
        <option value="FIXED">Fixed amount off (UGX)</option>
      </select>
    </div>
    <div class="field">
      <label for="discountValue">Discount Value</label>
      <input id="discountValue" name="discountValue" type="number" min="1" step="1" required placeholder="e.g. 20">
    </div>
  </div>
  <div class="grid sm:grid-2">
    <div class="field">
      <label for="maxUses">Usage Limit (optional)</label>
      <input id="maxUses" name="maxUses" type="number" min="1" step="1" placeholder="Unlimited">
    </div>
    <div class="field">
      <label for="expiresAt">Expires (optional)</label>
      <input id="expiresAt" name="expiresAt" type="date">
    </div>
  </div>
  <button type="submit" class="btn btn-primary btn-block">Create Coupon</button>
</form>

<?php if ($coupons): ?>
  <div class="activity-feed" style="margin-top:28px;">
    <?php foreach ($coupons as $c):
      $courseTitle = null;
      if ($c['course_id']) {
          foreach ($myCourses as $mc) { if ((int) $mc['id'] === (int) $c['course_id']) { $courseTitle = $mc['title']; break; } }
      }
    ?>
      <div class="list-row">
        <div class="list-row-main">
          <div style="min-width:0;">
            <div style="font-weight:700; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
              <span style="font-family:monospace;"><?= e($c['code']) ?></span>
              <span class="badge <?= $c['status'] === 'ACTIVE' ? 'badge-published' : 'badge-draft' ?>"><?= $c['status'] === 'ACTIVE' ? 'Active' : 'Disabled' ?></span>
            </div>
            <div class="small muted" style="margin-top:2px;">
              <?= $c['discount_type'] === 'PERCENT' ? (int) $c['discount_value'] . '%' : e(format_money((float) $c['discount_value'])) ?> off &middot;
              <?= $courseTitle ? e($courseTitle) : 'All courses' ?> &middot;
              <?= (int) $c['uses_count'] ?><?= $c['max_uses'] ? ' / ' . (int) $c['max_uses'] : '' ?> uses
              <?= $c['expires_at'] ? ' &middot; expires ' . e(format_date($c['expires_at'])) : '' ?>
            </div>
          </div>
        </div>
        <div class="list-row-meta">
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="couponId" value="<?= (int) $c['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm"><?= $c['status'] === 'ACTIVE' ? 'Disable' : 'Enable' ?></button>
          </form>
          <form method="post" style="display:inline;" onsubmit="return confirm('Delete this coupon? This can\'t be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="couponId" value="<?= (int) $c['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card card-pad" style="text-align:center; margin-top:28px; border-style:dashed;">
    <p class="muted">You haven't created any coupons yet.</p>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
