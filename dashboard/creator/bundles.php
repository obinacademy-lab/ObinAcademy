<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/bundles.php';
$user = require_role(['CREATOR', 'ADMIN']);

$errors = [];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');

    if ($action === 'create') {
        $courseIds = array_map('intval', $_POST['courseIds'] ?? []);
        $result = create_bundle(
            (int) $user['id'],
            post('title'),
            (float) post('price', '0'),
            trim(post('description')),
            $courseIds
        );
        if (isset($result['error'])) {
            $errors[] = $result['error'];
        } else {
            $notice = 'Bundle created as a draft — publish it when you\'re ready.';
        }
    } elseif ($action === 'toggle') {
        $bundleId = (int) post('bundleId');
        $bundle = get_bundle_for_creator((int) $user['id'], $bundleId);
        if ($bundle) {
            $newStatus = $bundle['status'] === 'PUBLISHED' ? 'DRAFT' : 'PUBLISHED';
            set_bundle_status((int) $user['id'], $bundleId, $newStatus);
            $notice = $newStatus === 'PUBLISHED' ? 'Bundle published.' : 'Bundle moved back to draft.';
        }
    } elseif ($action === 'delete') {
        delete_bundle((int) $user['id'], (int) post('bundleId'));
        $notice = 'Bundle deleted.';
    }
}

$bundles = get_bundles_for_creator((int) $user['id']);
$myCourses = db_all("SELECT id, title, price FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' ORDER BY title", [$user['id']]);

$publishedCount = count(array_filter($bundles, fn($b) => $b['status'] === 'PUBLISHED'));
$draftCount = count($bundles) - $publishedCount;

$pageTitle = 'Bundles — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="reveal">
  <h1 class="h2" style="margin:0;">Bundles</h1>
  <p class="muted" style="margin-top:6px; max-width:580px;">Package 2 or more of your courses together at one discounted price — learners see the combined value and what they save.</p>
</div>

<div class="grid md:grid-3 reveal" style="margin-top:22px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;">
    <div class="icon"><?php dash_icon('layout-dashboard'); ?></div>
    <div class="value"><?= count($bundles) ?></div>
    <div class="label">Total bundles</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#059669;">
    <div class="icon"><?php dash_icon('check-circle'); ?></div>
    <div class="value"><?= $publishedCount ?></div>
    <div class="label">Published</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon"><?php dash_icon('clock'); ?></div>
    <div class="value"><?= $draftCount ?></div>
    <div class="label">Draft</div>
  </div>
</div>

<?php if ($errors): ?><div class="alert alert-error" style="margin-top:20px;"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success" style="margin-top:20px;"><?= e($notice) ?></div><?php endif; ?>

<?php if (count($myCourses) < 2): ?>
  <div class="card card-pad reveal" style="margin-top:22px; border-style:dashed; text-align:center;">
    <p class="muted">You need at least 2 published courses before you can create a bundle.</p>
  </div>
<?php else: ?>
  <form method="post" class="card card-pad reveal" style="margin-top:22px; max-width:720px;" data-bundle-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <div class="row gap-2" style="align-items:center; margin-bottom:18px;">
      <span style="width:34px; height:34px; border-radius:10px; background:var(--dash-tint); color:var(--accent); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><?php dash_icon('layout-dashboard'); ?></span>
      <div style="font-weight:800; font-size:15px;">Create a New Bundle</div>
    </div>

    <div class="field">
      <label for="title">Bundle Title</label>
      <input id="title" name="title" required placeholder="e.g. Complete Finance Starter Pack">
    </div>
    <div class="field">
      <label for="description">Description (optional)</label>
      <textarea id="description" name="description" rows="3" placeholder="What's included and why it's worth buying together"></textarea>
    </div>

    <div class="field">
      <label>Courses in This Bundle</label>
      <div class="activity-feed" style="margin-top:8px; max-height:280px; overflow-y:auto;">
        <?php foreach ($myCourses as $c): ?>
          <label class="activity-row" style="cursor:pointer; align-items:center;" data-bundle-course data-price="<?= (float) $c['price'] ?>">
            <input type="checkbox" name="courseIds[]" value="<?= (int) $c['id'] ?>" data-bundle-checkbox style="accent-color:var(--accent); width:16px; height:16px; flex-shrink:0;">
            <span class="row-avatar"><?php dash_icon('book-open'); ?></span>
            <span class="activity-body"><strong><?= e($c['title']) ?></strong></span>
            <span class="small muted" style="flex-shrink:0;"><?= e(format_money((float) $c['price'])) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="field">
      <label for="price">Bundle Price (UGX)</label>
      <input id="price" name="price" type="number" min="1" step="1" required placeholder="e.g. 90000" data-bundle-price>
      <p class="help">Set this below the combined price of the courses you pick, so the bundle is an actual discount.</p>
    </div>

    <div style="margin:4px 0 18px; padding:14px 18px; background:var(--dash-bg); border:1px solid var(--dash-border); border-radius:12px; display:flex; flex-wrap:wrap; gap:16px 28px;">
      <div><div class="small muted">Courses selected</div><div style="font-weight:800; font-size:16px;" data-summary-count>0</div></div>
      <div><div class="small muted">Combined value</div><div style="font-weight:800; font-size:16px;" data-summary-combined>UGX 0</div></div>
      <div><div class="small muted">Learner saves</div><div style="font-weight:800; font-size:16px; color:var(--dash-good);" data-summary-savings>UGX 0 (0%)</div></div>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">Create Bundle</button>
  </form>
<?php endif; ?>

<?php if ($bundles): ?>
  <h3 class="dash-section-label reveal" style="margin-top:36px;">Your Bundles</h3>
  <div class="activity-feed reveal" style="margin-top:14px;">
    <?php foreach ($bundles as $b): ?>
      <?php
        $savings = (float) $b['courses_total_price'] - (float) $b['price'];
        $savingsPct = (float) $b['courses_total_price'] > 0 ? round($savings / (float) $b['courses_total_price'] * 100) : 0;
        $isPublished = $b['status'] === 'PUBLISHED';
      ?>
      <div class="activity-row list-row">
        <div class="list-row-main">
          <span class="activity-dot" style="background:var(--dash-tint); color:var(--accent); flex-shrink:0;"><?php dash_icon('layout-dashboard'); ?></span>
          <div class="activity-body">
            <strong><?= e($b['title']) ?></strong>
            <div class="small muted" style="margin-top:2px;">
              <?= (int) $b['course_count'] ?> course<?= (int) $b['course_count'] === 1 ? '' : 's' ?> · <?= e(format_money((float) $b['price'])) ?>
              <?php if ($savings > 0): ?> · <span style="color:var(--dash-good); font-weight:700;">Save <?= e(format_money($savings)) ?> (<?= $savingsPct ?>%)</span><?php endif; ?>
            </div>
          </div>
        </div>
        <div class="list-row-meta">
          <span class="status-pill" style="<?= $isPublished ? 'color:var(--dash-good); background:var(--dash-good-bg); border-color:#bbf7d0;' : 'color:var(--muted); background:var(--dash-border-soft); border-color:transparent;' ?>">
            <?= $isPublished ? 'Published' : 'Draft' ?>
          </span>
          <div class="row gap-2">
            <?php if ($isPublished): ?>
              <a href="<?= e(base_url('bundle.php?slug=' . $b['slug'])) ?>" class="icon-btn-danger" target="_blank" rel="noopener" aria-label="View bundle" title="View bundle"><?php dash_icon('eye'); ?></a>
            <?php endif; ?>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="bundleId" value="<?= (int) $b['id'] ?>">
              <button type="submit" class="icon-btn-danger" aria-label="<?= $isPublished ? 'Unpublish' : 'Publish' ?>" title="<?= $isPublished ? 'Unpublish' : 'Publish' ?>"><?php dash_icon($isPublished ? 'x-circle' : 'check-circle'); ?></button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this bundle? This can\'t be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="bundleId" value="<?= (int) $b['id'] ?>">
              <button type="submit" class="icon-btn-danger" aria-label="Delete bundle" title="Delete bundle"><?php dash_icon('trash'); ?></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
(() => {
  const form = document.querySelector('[data-bundle-form]');
  if (!form) return;
  const checkboxes = form.querySelectorAll('[data-bundle-checkbox]');
  const priceInput = form.querySelector('[data-bundle-price]');
  const countOut = form.querySelector('[data-summary-count]');
  const combinedOut = form.querySelector('[data-summary-combined]');
  const savingsOut = form.querySelector('[data-summary-savings]');

  function formatUGX(n) {
    return 'UGX ' + Math.round(n).toLocaleString('en-US');
  }

  function update() {
    let count = 0;
    let combined = 0;
    checkboxes.forEach((cb) => {
      if (cb.checked) {
        count++;
        combined += parseFloat(cb.closest('[data-bundle-course]').dataset.price) || 0;
      }
    });
    const price = parseFloat(priceInput.value) || 0;
    const savings = Math.max(0, combined - price);
    const pct = combined > 0 ? Math.round((savings / combined) * 100) : 0;
    countOut.textContent = String(count);
    combinedOut.textContent = formatUGX(combined);
    savingsOut.textContent = formatUGX(savings) + ' (' + pct + '%)';
  }

  checkboxes.forEach((cb) => cb.addEventListener('change', update));
  priceInput.addEventListener('input', update);
  update();
})();
</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
