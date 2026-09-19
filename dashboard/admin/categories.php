<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
$user = require_role(['ADMIN']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('_action');
    if ($action === 'add') {
        $name = post('name');
        if ($name === '') {
            $errors[] = 'Category name is required.';
        } else {
            $slug = slugify($name);
            $base = $slug; $n = 1;
            while (db_one('SELECT id FROM categories WHERE slug = ?', [$slug])) $slug = "$base-" . $n++;
            db_insert('INSERT INTO categories (name, slug) VALUES (?, ?)', [$name, $slug]);
            log_admin_action((int) $user['id'], $user['name'], 'category.created', 'Category', $name);
        }
    } elseif ($action === 'delete') {
        $catId = (int) post('categoryId');
        $inUse = db_one('SELECT id FROM courses WHERE category_id = ? LIMIT 1', [$catId]);
        if ($inUse) {
            $errors[] = 'Cannot delete a category that has courses in it.';
        } else {
            $cat = db_one('SELECT * FROM categories WHERE id = ?', [$catId]);
            db_run('DELETE FROM categories WHERE id = ?', [$catId]);
            if ($cat) log_admin_action((int) $user['id'], $user['name'], 'category.deleted', 'Category', $cat['name']);
        }
    }
    if (!$errors) redirect('/dashboard/admin/categories.php');
}

$categories = db_all('SELECT c.*, (SELECT COUNT(*) FROM courses co WHERE co.category_id=c.id) AS course_count FROM categories c ORDER BY c.name ASC');

$pageTitle = 'Categories — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Categories</h1>

<?php if ($errors): ?><div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" class="card card-pad row gap-2 wrap" style="margin-top:20px; max-width:480px;">
  <?= csrf_field() ?><input type="hidden" name="_action" value="add">
  <input name="name" placeholder="New category name" style="flex:1;">
  <button class="btn btn-primary">+ Add</button>
</form>

<?php if (!$categories): ?>
  <div class="card card-pad" style="margin-top:20px; border-style:dashed; text-align:center;">
    <p class="muted">No categories yet.</p>
  </div>
<?php else: ?>
  <div class="activity-feed" style="margin-top:20px;">
    <?php foreach ($categories as $c): ?>
      <div class="list-row">
        <div class="list-row-main">
          <span class="activity-dot tone-neutral" style="flex-shrink:0;"><?php dash_icon('tag'); ?></span>
          <div style="min-width:0;">
            <div style="font-weight:700;"><?= e($c['name']) ?></div>
            <div class="small muted" style="margin-top:2px;"><?= (int) $c['course_count'] ?> course<?= (int) $c['course_count'] === 1 ? '' : 's' ?></div>
          </div>
        </div>
        <div class="list-row-meta">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="categoryId" value="<?= (int) $c['id'] ?>">
            <button class="btn btn-outline btn-sm" style="color:var(--danger); border-color:var(--danger);">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
