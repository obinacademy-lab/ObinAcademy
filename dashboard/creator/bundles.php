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

$pageTitle = 'Bundles — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Bundles</h1>
<p class="muted" style="margin-top:6px;">Package 2 or more of your courses together at one discounted price.</p>

<?php if ($errors): ?><div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success" style="margin-top:16px;"><?= e($notice) ?></div><?php endif; ?>

<?php if (count($myCourses) < 2): ?>
  <div class="card card-pad" style="margin-top:20px; border-style:dashed; text-align:center;">
    <p class="muted">You need at least 2 published courses before you can create a bundle.</p>
  </div>
<?php else: ?>
  <form method="post" class="card card-pad" style="margin-top:20px; max-width:640px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <h2 class="h3" style="margin:0 0 16px;">New Bundle</h2>
    <div class="field">
      <label for="title">Bundle Title</label>
      <input id="title" name="title" required placeholder="e.g. Complete Finance Starter Pack">
    </div>
    <div class="field">
      <label for="description">Description (optional)</label>
      <textarea id="description" name="description" rows="3" placeholder="What's included and why it's worth buying together"></textarea>
    </div>
    <div class="field">
      <label for="price">Bundle Price (UGX)</label>
      <input id="price" name="price" type="number" min="1" step="1" required placeholder="e.g. 90000">
      <p class="help">Set this below the combined price of the courses you pick, so the bundle is an actual discount.</p>
    </div>
    <div class="field">
      <label>Courses in This Bundle</label>
      <div class="stack gap-2" style="margin-top:8px;">
        <?php foreach ($myCourses as $c): ?>
          <label class="row gap-2" style="align-items:center; font-weight:600; cursor:pointer;">
            <input type="checkbox" name="courseIds[]" value="<?= (int) $c['id'] ?>">
            <span><?= e($c['title']) ?> <span class="small muted">(<?= e(format_money((float) $c['price'])) ?>)</span></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">Create Bundle</button>
  </form>
<?php endif; ?>

<?php if ($bundles): ?>
  <div class="table-wrap" style="margin-top:28px;">
    <table>
      <thead>
        <tr>
          <th>Bundle</th>
          <th>Courses</th>
          <th>Price</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bundles as $b): ?>
          <tr>
            <td style="font-weight:700;"><?= e($b['title']) ?></td>
            <td><?= (int) $b['course_count'] ?></td>
            <td><?= e(format_money((float) $b['price'])) ?></td>
            <td><span class="badge <?= $b['status'] === 'PUBLISHED' ? 'badge-published' : 'badge-draft' ?>"><?= $b['status'] === 'PUBLISHED' ? 'Published' : 'Draft' ?></span></td>
            <td class="row gap-2">
              <?php if ($b['status'] === 'PUBLISHED'): ?>
                <a href="<?= e(base_url('bundle.php?slug=' . $b['slug'])) ?>" class="btn btn-outline btn-sm" target="_blank" rel="noopener">View</a>
              <?php endif; ?>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="bundleId" value="<?= (int) $b['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm"><?= $b['status'] === 'PUBLISHED' ? 'Unpublish' : 'Publish' ?></button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('Delete this bundle? This can\'t be undone.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="bundleId" value="<?= (int) $b['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
