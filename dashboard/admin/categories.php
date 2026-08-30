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

<div class="table-wrap" style="margin-top:20px;">
  <table>
    <thead><tr><th>Name</th><th>Courses</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td><?= e($c['name']) ?></td>
          <td><?= (int) $c['course_count'] ?></td>
          <td>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="categoryId" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-outline btn-sm" style="color:var(--danger); border-color:var(--danger);">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
