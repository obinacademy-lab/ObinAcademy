<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/data.php';
require __DIR__ . '/../../includes/audit.php';
$user = require_role(['CREATOR', 'ADMIN']);

$courseId = (int) query_param('id');
$course = db_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
if (!$course) { http_response_code(404); exit('Course not found'); }

$isAdmin = $user['role'] === 'ADMIN';
$isOwner = (int) $course['creator_id'] === (int) $user['id'];
if (!$isOwner && !$isAdmin) { http_response_code(404); exit('Course not found'); }
$actingAsAdmin = $isAdmin && !$isOwner;

function note_admin_edit(bool $actingAsAdmin, array $user, string $action, string $targetLabel, ?string $detail = null): void {
    if (!$actingAsAdmin) return;
    log_admin_action((int) $user['id'], $user['name'] ?: $user['email'], $action, 'Course', $targetLabel, $detail);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('_action');

    if ($action === 'update_details') {
        $title = post('title');
        $summary = post('summary');
        $description = post('description');
        $price = (float) post('price', '0');
        $salePriceRaw = post('salePrice');
        $salePrice = $salePriceRaw === '' ? null : (float) $salePriceRaw;
        $categoryId = (int) post('categoryId');
        $accessDurationRaw = post('accessDurationDays', 'lifetime');
        $accessDurationDays = $accessDurationRaw === 'lifetime' ? null : (int) $accessDurationRaw;
        $premiumPriceRaw = post('premiumPrice');
        $premiumPrice = $premiumPriceRaw === '' ? null : (float) $premiumPriceRaw;

        if (strlen($title) < 4) $errors[] = 'Title must be at least 4 characters.';
        if (strlen($summary) < 10) $errors[] = 'Summary must be at least 10 characters.';
        if (strlen($description) < 20) $errors[] = 'Description must be at least 20 characters.';
        if ($salePrice !== null && ($salePrice <= 0 || $salePrice >= $price)) {
            $errors[] = 'Sale price must be greater than 0 and less than the regular price.';
        }

        $thumbnailUrl = null;
        if (!empty($_FILES['thumbnail']['name'])) {
            try { $thumbnailUrl = save_upload($_FILES['thumbnail'], 'thumbnails'); }
            catch (Throwable $e) { $errors[] = $e->getMessage(); }
        }

        if (!$errors) {
            $sql = 'UPDATE courses SET title=?, summary=?, description=?, price=?, sale_price=?, category_id=?, access_duration_days=?, premium_price=?' . ($thumbnailUrl ? ', thumbnail_url=?' : '') . ' WHERE id=?';
            $params = [$title, $summary, $description, $price, $salePrice, $categoryId, $accessDurationDays, $premiumPrice];
            if ($thumbnailUrl) $params[] = $thumbnailUrl;
            $params[] = $courseId;
            db_run($sql, $params);
            note_admin_edit($actingAsAdmin, $user, 'course.edited', $course['title'], 'details updated');
            flash_set('success', 'Course details updated.');
            redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
        }
    } elseif ($action === 'add_module') {
        $title = post('moduleTitle');
        if ($title !== '') {
            $count = (int) db_one('SELECT COUNT(*) AS n FROM modules WHERE course_id = ?', [$courseId])['n'];
            db_insert('INSERT INTO modules (title, sort_order, course_id) VALUES (?, ?, ?)', [$title, $count, $courseId]);
        }
        redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
    } elseif ($action === 'delete_module') {
        db_run('DELETE FROM modules WHERE id = ? AND course_id = ?', [(int) post('moduleId'), $courseId]);
        redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
    } elseif ($action === 'add_lesson') {
        $moduleId = (int) post('moduleId');
        $title = post('lessonTitle');
        $type = post('lessonType') === 'PDF' ? 'PDF' : 'VIDEO';

        if ($title === '') {
            $errors[] = 'Lesson title is required.';
        } elseif (empty($_FILES['file']['name'])) {
            $errors[] = 'A file is required.';
        } else {
            try {
                $fileUrl = save_upload($_FILES['file'], $type === 'VIDEO' ? 'videos' : 'pdfs');
                $count = (int) db_one('SELECT COUNT(*) AS n FROM lessons WHERE module_id = ?', [$moduleId])['n'];
                db_insert(
                    'INSERT INTO lessons (title, type, file_url, file_name, sort_order, module_id) VALUES (?, ?, ?, ?, ?, ?)',
                    [$title, $type, $fileUrl, $_FILES['file']['name'], $count, $moduleId]
                );
                redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'delete_lesson') {
        db_run('DELETE FROM lessons WHERE id = ?', [(int) post('lessonId')]);
        redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
    } elseif ($action === 'submit_for_review') {
        if (in_array($course['status'], ['DRAFT', 'REJECTED'], true)) {
            $moduleCount = (int) db_one('SELECT COUNT(*) AS n FROM modules WHERE course_id = ?', [$courseId])['n'];
            $lessonCount = (int) db_one('SELECT COUNT(*) AS n FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = ?', [$courseId])['n'];
            if ($moduleCount === 0 || $lessonCount === 0) {
                flash_set('error', 'Add at least one module with a lesson before submitting for review.');
            } else {
                db_run("UPDATE courses SET status='PENDING_REVIEW', submitted_at=NOW(), rejection_reason=NULL WHERE id=?", [$courseId]);
                note_admin_edit($actingAsAdmin, $user, 'course.submitted', $course['title'], 'via creator dashboard');
            }
        }
        redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
    } elseif ($action === 'withdraw_submission') {
        if ($course['status'] === 'PENDING_REVIEW') {
            db_run("UPDATE courses SET status='DRAFT' WHERE id=?", [$courseId]);
            note_admin_edit($actingAsAdmin, $user, 'course.withdrawn', $course['title'], 'via creator dashboard');
        }
        redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
    } elseif ($action === 'unpublish') {
        if ($course['status'] === 'PUBLISHED') {
            db_run("UPDATE courses SET status='DRAFT' WHERE id=?", [$courseId]);
            note_admin_edit($actingAsAdmin, $user, 'course.unpublished', $course['title'], 'via creator dashboard');
        }
        redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
    } elseif ($action === 'delete_course') {
        $enrolledCount = (int) db_one('SELECT COUNT(*) AS n FROM enrollments WHERE course_id = ?', [$courseId])['n'];
        if ($enrolledCount > 0) {
            // Hard-deleting would cascade away real students' paid access and
            // the payment/earnings records proving they paid — never safe once
            // anyone has enrolled. Admins get "Remove from Platform" instead,
            // which hides the course without touching what's already been sold.
            flash_set('error', "This course has $enrolledCount enrolled student" . ($enrolledCount === 1 ? '' : 's') . " — deleting it would take away their paid access and erase the sales record. " . ($isAdmin ? 'Use "Remove from Platform" instead.' : 'Contact support if this course needs to come down.'));
        } else {
            db_run('DELETE FROM courses WHERE id = ?', [$courseId]);
            note_admin_edit($actingAsAdmin, $user, 'course.deleted', $course['title'], 'via creator dashboard');
            redirect($actingAsAdmin ? '/dashboard/admin/courses.php' : '/dashboard/creator/index.php');
        }
    } elseif ($action === 'admin_remove' && $isAdmin) {
        $reason = trim((string) post('removeReason'));
        if ($reason === '') {
            flash_set('error', 'Enter a reason for removing this course.');
        } else {
            db_run("UPDATE courses SET status='REMOVED', reviewed_at=NOW(), rejection_reason=? WHERE id=?", [$reason, $courseId]);
            log_admin_action((int) $user['id'], $user['name'] ?: $user['email'], 'course.removed', 'Course', $course['title'], $reason);
            flash_set('success', 'Course removed from the platform. Existing students keep the access they already paid for.');
            redirect('/dashboard/admin/courses.php');
        }
    } elseif ($action === 'admin_restore' && $isAdmin) {
        if ($course['status'] === 'REMOVED') {
            db_run("UPDATE courses SET status='DRAFT', rejection_reason=NULL WHERE id=?", [$courseId]);
            log_admin_action((int) $user['id'], $user['name'] ?: $user['email'], 'course.restored', 'Course', $course['title']);
            flash_set('success', 'Course restored. It\'s back in Draft — the creator can resubmit it for review.');
        }
        redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
    }

    // reload course after any mutation that didn't already redirect (e.g. validation errors)
    $course = db_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
}

$categories = get_categories();
$modules = db_all('SELECT * FROM modules WHERE course_id = ? ORDER BY sort_order ASC', [$courseId]);
foreach ($modules as &$m) {
    $m['lessons'] = db_all('SELECT * FROM lessons WHERE module_id = ? ORDER BY sort_order ASC', [$m['id']]);
}
unset($m);
$studentCount = (int) db_one('SELECT COUNT(*) AS n FROM enrollments WHERE course_id = ?', [$courseId])['n'];

$badgeClass = ['DRAFT' => 'badge-draft', 'PENDING_REVIEW' => 'badge-pending', 'PUBLISHED' => 'badge-published', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected'];
$statusLabel = ['DRAFT' => 'Draft', 'PENDING_REVIEW' => 'Pending Review', 'PUBLISHED' => 'Published', 'REJECTED' => 'Rejected', 'REMOVED' => 'Removed by Admin'];

$pageTitle = $course['title'] . ' — Manage — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3">
  <div>
    <h1 class="h2"><?= e($course['title']) ?></h1>
    <p class="muted" style="margin-top:6px;"><?= e(format_money((float) $course['price'])) ?> &middot; <?= $studentCount ?> students</p>
    <span class="badge <?= $badgeClass[$course['status']] ?>" style="margin-top:10px; display:inline-flex;"><?= $statusLabel[$course['status']] ?></span>
  </div>
  <div class="row gap-2 wrap">
    <?php if (in_array($course['status'], ['DRAFT', 'REJECTED'], true)): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="submit_for_review"><button class="btn btn-primary btn-sm">Submit for Review</button></form>
    <?php elseif ($course['status'] === 'PENDING_REVIEW'): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="withdraw_submission"><button class="btn btn-outline btn-sm">Withdraw</button></form>
    <?php elseif ($course['status'] === 'PUBLISHED'): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="unpublish"><button class="btn btn-outline btn-sm">Unpublish</button></form>
    <?php endif; ?>

    <?php if ($isAdmin && $course['status'] === 'REMOVED'): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="admin_restore"><button class="btn btn-outline btn-sm">↺ Restore Course</button></form>
    <?php elseif ($isAdmin && $course['status'] !== 'REMOVED'): ?>
      <details>
        <summary class="btn btn-outline btn-sm" style="color:var(--danger); border-color: var(--danger); cursor:pointer; list-style:none;">🚫 Remove From Platform</summary>
        <form method="post" class="card card-pad" style="margin-top:8px; max-width:360px;">
          <?= csrf_field() ?>
          <input type="hidden" name="_action" value="admin_remove">
          <label class="small" style="font-weight:700;">Reason (shown to the creator)</label>
          <textarea name="removeReason" rows="3" placeholder="e.g. Repeated copyright complaints, misleading course content..." required style="margin-top:6px;"></textarea>
          <p class="small muted" style="margin-top:6px;">Already-enrolled students keep the access they paid for. The course just stops selling and disappears from listings.</p>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;">Confirm Removal</button>
        </form>
      </details>
    <?php endif; ?>

    <?php if ($studentCount === 0): ?>
      <form method="post" data-confirm="Permanently delete this course? This cannot be undone.">
        <?= csrf_field() ?><input type="hidden" name="_action" value="delete_course">
        <button class="btn btn-outline btn-sm" style="color:var(--danger); border-color: var(--danger);">Delete Course</button>
      </form>
    <?php else: ?>
      <button type="button" class="btn btn-outline btn-sm" style="opacity:0.5; cursor:not-allowed;" title="<?= (int) $studentCount ?> student<?= $studentCount === 1 ? '' : 's' ?> already enrolled — deleting would erase their paid access. <?= $isAdmin ? 'Use Remove From Platform instead.' : '' ?>" disabled>Delete Course</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($course['status'] === 'REJECTED' && $course['rejection_reason']): ?>
  <div class="alert alert-error" style="margin-top:16px;"><strong>Rejected:</strong> <?= e($course['rejection_reason']) ?></div>
<?php elseif ($course['status'] === 'REMOVED' && $course['rejection_reason']): ?>
  <div class="alert alert-error" style="margin-top:16px;"><strong>Removed by an administrator:</strong> <?= e($course['rejection_reason']) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<details class="card" style="margin-top:24px;">
  <summary class="card-pad" style="cursor:pointer; font-weight:700; list-style:none;">✎ Edit Course Details</summary>
  <form method="post" enctype="multipart/form-data" class="card-pad" style="border-top:1px solid var(--border);">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="update_details">
    <div class="field"><label>Course Title</label><input name="title" required value="<?= e($course['title']) ?>"></div>
    <div class="grid sm:grid-2">
      <div class="field">
        <label>Category</label>
        <select name="categoryId" required>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>" <?= (int) $cat['id'] === (int) $course['category_id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Price (UGX)</label><input name="price" type="number" min="0" step="1" value="<?= e((string) $course['price']) ?>" required></div>
    </div>
    <div class="field">
      <label>Sale Price (UGX, optional)</label>
      <input name="salePrice" type="number" min="0" step="1" value="<?= e($course['sale_price'] !== null ? (string) $course['sale_price'] : '') ?>" placeholder="Leave blank for no discount">
      <p class="help">When set (and lower than the price above), learners see the discounted price everywhere and pay that instead.</p>
    </div>
    <div class="field"><label>Short Summary</label><input name="summary" required value="<?= e($course['summary']) ?>"></div>
    <div class="field"><label>Full Description</label><textarea name="description" rows="5" required><?= e($course['description']) ?></textarea></div>
    <div class="grid sm:grid-2">
      <div class="field">
        <label>Course Access Duration</label>
        <select name="accessDurationDays">
          <?php foreach (ACCESS_DURATION_OPTIONS as $o): ?>
            <option value="<?= $o['days'] ?? 'lifetime' ?>" <?= ($course['access_duration_days'] === null ? 'lifetime' : (string) $course['access_duration_days']) === (string) ($o['days'] ?? 'lifetime') ? 'selected' : '' ?>><?= e($o['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Premium Download Price (UGX, optional)</label><input name="premiumPrice" type="number" min="0" step="1" value="<?= e($course['premium_price'] !== null ? (string) $course['premium_price'] : '') ?>" placeholder="Leave blank to disable downloads"></div>
    </div>
    <div class="field"><label>Replace Thumbnail (optional)</label><input name="thumbnail" type="file" accept="image/*"></div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
</details>

<h2 class="h3" style="margin-top:36px;">Curriculum</h2>
<p class="muted small" style="margin-top:6px;">Organize your course into modules, then add video or PDF lessons to each.</p>

<div class="stack gap-3" style="margin-top:16px;">
  <?php foreach ($modules as $mi => $module): ?>
    <div class="card">
      <div class="row between" style="background:var(--surface); padding:12px 20px; border-radius: var(--radius) var(--radius) 0 0;">
        <span style="font-weight:700; font-size:14px;">Module <?= $mi + 1 ?>: <?= e($module['title']) ?></span>
        <form method="post" data-confirm="Delete this module and all its lessons?"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_module"><input type="hidden" name="moduleId" value="<?= (int) $module['id'] ?>"><button style="background:none;border:none;color:var(--danger);cursor:pointer;">🗑</button></form>
      </div>
      <ul style="list-style:none; margin:0; padding:0;">
        <?php foreach ($module['lessons'] as $lesson): ?>
          <li class="row between" style="padding:10px 20px; border-top:1px solid var(--border); font-size:14px;">
            <span><?= $lesson['type'] === 'VIDEO' ? '▶' : '📄' ?> <?= e($lesson['title']) ?></span>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_lesson"><input type="hidden" name="lessonId" value="<?= (int) $lesson['id'] ?>"><button style="background:none;border:none;color:var(--danger);cursor:pointer;">🗑</button></form>
          </li>
        <?php endforeach; ?>
        <?php if (!$module['lessons']): ?><li class="small muted" style="padding:14px 20px; border-top:1px solid var(--border);">No lessons yet.</li><?php endif; ?>
      </ul>
      <div style="padding:14px 20px; border-top:1px solid var(--border);">
        <details>
          <summary style="cursor:pointer; font-size:13px; font-weight:700; color:var(--accent); list-style:none;">+ Add Lesson</summary>
          <form method="post" enctype="multipart/form-data" class="stack gap-2" style="margin-top:12px;">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="add_lesson">
            <input type="hidden" name="moduleId" value="<?= (int) $module['id'] ?>">
            <div class="grid sm:grid-2">
              <input name="lessonTitle" placeholder="Lesson title" required>
              <select name="lessonType"><option value="VIDEO">Video</option><option value="PDF">PDF</option></select>
            </div>
            <input name="file" type="file" accept="video/*,application/pdf" required>
            <button type="submit" class="btn btn-dark btn-sm" style="width:fit-content;">Save Lesson</button>
          </form>
        </details>
      </div>
    </div>
  <?php endforeach; ?>

  <form method="post" class="card card-pad row gap-2 wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="add_module">
    <input name="moduleTitle" placeholder="New module title (e.g. Getting Started)" style="flex:1; min-width:200px;">
    <button type="submit" class="btn btn-primary">+ Add Module</button>
  </form>
</div>

<script>
document.querySelectorAll('form[data-confirm]').forEach((f) => {
  f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
});
</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
