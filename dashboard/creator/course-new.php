<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['CREATOR', 'ADMIN']);

$errors = [];
$categories = get_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = post('title');
    $summary = post('summary');
    $description = post('description');
    $price = (float) post('price', '0');
    $categoryId = (int) post('categoryId');
    $accessDurationRaw = post('accessDurationDays', 'lifetime');
    $accessDurationDays = $accessDurationRaw === 'lifetime' ? null : (int) $accessDurationRaw;
    $premiumPriceRaw = post('premiumPrice');
    $premiumPrice = $premiumPriceRaw === '' ? null : (float) $premiumPriceRaw;

    if (strlen($title) < 4) $errors[] = 'Title must be at least 4 characters.';
    if (strlen($summary) < 10) $errors[] = 'Summary must be at least 10 characters.';
    if (strlen($description) < 20) $errors[] = 'Description must be at least 20 characters.';
    if ($price < 0) $errors[] = 'Price cannot be negative.';
    if (!$categoryId) $errors[] = 'Select a category.';

    $thumbnailUrl = null;
    if (!empty($_FILES['thumbnail']['name'])) {
        try {
            $thumbnailUrl = save_upload($_FILES['thumbnail'], 'thumbnails');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $baseSlug = slugify($title);
        $slug = $baseSlug;
        $n = 1;
        while (db_one('SELECT id FROM courses WHERE slug = ?', [$slug])) {
            $slug = "$baseSlug-" . $n++;
        }

        $id = db_insert(
            "INSERT INTO courses (title, slug, summary, description, price, category_id, access_duration_days, premium_price, creator_id, thumbnail_url, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT')",
            [$title, $slug, $summary, $description, $price, $categoryId, $accessDurationDays, $premiumPrice, $user['id'], $thumbnailUrl]
        );
        redirect('/dashboard/creator/course-manage.php?id=' . $id);
    }
}

$pageTitle = 'Create Course — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Create Course</h1>

<?php if ($errors): ?>
  <div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card card-pad" style="margin-top:20px; max-width:680px;">
  <?= csrf_field() ?>
  <div class="field">
    <label for="title">Course Title</label>
    <input id="title" name="title" type="text" required placeholder="e.g. Personal Finance Fundamentals" value="<?= e($_POST['title'] ?? '') ?>">
  </div>

  <div class="grid sm:grid-2">
    <div class="field">
      <label for="categoryId">Category</label>
      <select id="categoryId" name="categoryId" required>
        <option value="">Select a category</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="price">Price (UGX)</label>
      <label style="display:flex; align-items:center; gap:6px; font-weight:400; font-size:12.5px; color:var(--muted); margin-bottom:6px; cursor:pointer;">
        <input type="checkbox" id="isFree" style="width:auto;" onchange="var p=document.getElementById('price'); p.disabled=this.checked; p.style.opacity=this.checked?'0.5':'1'; if(this.checked) p.value='0';">
        Make this course free
      </label>
      <input id="price" name="price" type="number" min="0" step="1" value="0" required>
      <p class="help">Free courses still let you charge separately for downloads below.</p>
    </div>
  </div>

  <div class="field">
    <label for="summary">Short Summary</label>
    <input id="summary" name="summary" type="text" required placeholder="One sentence describing the course">
  </div>

  <div class="field">
    <label for="description">Full Description</label>
    <textarea id="description" name="description" rows="5" required placeholder="What will students learn? What's included?"></textarea>
  </div>

  <div class="grid sm:grid-2">
    <div class="field">
      <label for="accessDurationDays">Course Access Duration</label>
      <select id="accessDurationDays" name="accessDurationDays">
        <?php foreach (ACCESS_DURATION_OPTIONS as $o): ?>
          <option value="<?= $o['days'] ?? 'lifetime' ?>" <?= $o['days'] === null ? 'selected' : '' ?>><?= e($o['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="help">How long a learner keeps access after buying.</p>
    </div>
    <div class="field">
      <label for="premiumPrice">Premium Download Price (UGX, optional)</label>
      <input id="premiumPrice" name="premiumPrice" type="number" min="0" step="1" placeholder="Leave blank to disable downloads">
      <p class="help">Learners pay this to unlock downloads for this course.</p>
    </div>
  </div>

  <div class="field">
    <label for="thumbnail">Thumbnail Image (optional)</label>
    <input id="thumbnail" name="thumbnail" type="file" accept="image/*">
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg">Create Course & Continue</button>
</form>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
