<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/data.php';
$user = require_login();

$errors = [];
$categories = get_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = post('title');
    $summary = post('summary');
    $description = post('description');
    $price = (float) post('price', '0');
    $categoryId = (int) post('categoryId');
    $eventStartsAt = post('eventStartsAt');
    $eventEndsAtRaw = post('eventEndsAt');
    $eventEndsAt = $eventEndsAtRaw !== '' ? $eventEndsAtRaw : null;
    $eventLocation = post('eventLocation');
    $eventLocation = $eventLocation !== '' ? $eventLocation : null;
    $eventOnlineUrl = post('eventOnlineUrl');
    $eventOnlineUrl = $eventOnlineUrl !== '' ? $eventOnlineUrl : null;
    $ticketCapacityRaw = post('ticketCapacity');
    $ticketCapacity = $ticketCapacityRaw === '' ? null : (int) $ticketCapacityRaw;

    if (strlen($title) < 4) $errors[] = 'Title must be at least 4 characters.';
    if (strlen($summary) < 10) $errors[] = 'Summary must be at least 10 characters.';
    if (strlen($description) < 20) $errors[] = 'Description must be at least 20 characters.';
    if ($price < 0) $errors[] = 'Price cannot be negative.';
    if (!$categoryId) $errors[] = 'Select a category.';
    if (!$eventStartsAt) $errors[] = 'Set an event start date & time.';
    if (!$eventLocation && !$eventOnlineUrl) $errors[] = 'Add a location or an online link.';

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
            "INSERT INTO courses (title, slug, summary, description, price, category_id, creator_id, thumbnail_url, status, type, event_starts_at, event_ends_at, event_location, event_online_url, ticket_capacity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT', 'EVENT', ?, ?, ?, ?, ?)",
            [$title, $slug, $summary, $description, $price, $categoryId, $user['id'], $thumbnailUrl, $eventStartsAt, $eventEndsAt, $eventLocation, $eventOnlineUrl, $ticketCapacity]
        );
        redirect('/dashboard/creator/course-manage.php?id=' . $id);
    }
}

$pageTitle = 'Create Event — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Create Event</h1>

<?php if ($errors): ?>
  <div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card card-pad" style="margin-top:20px; max-width:680px;">
  <?= csrf_field() ?>
  <div class="field">
    <label for="title">Event Title</label>
    <input id="title" name="title" type="text" required placeholder="e.g. Personal Finance Bootcamp" value="<?= e($_POST['title'] ?? '') ?>">
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
      <label for="price">Ticket Price (UGX)</label>
      <input id="price" name="price" type="number" min="0" step="1" value="0" required>
    </div>
  </div>

  <div class="field">
    <label for="summary">Short Summary</label>
    <input id="summary" name="summary" type="text" required placeholder="One sentence describing the event">
  </div>

  <div class="field">
    <label for="description">Full Description</label>
    <textarea id="description" name="description" rows="5" required placeholder="What's this event about? Who is it for?"></textarea>
  </div>

  <div class="grid sm:grid-2">
    <div class="field">
      <label for="eventStartsAt">Event Starts</label>
      <input id="eventStartsAt" name="eventStartsAt" type="datetime-local" required>
    </div>
    <div class="field">
      <label for="eventEndsAt">Event Ends (optional)</label>
      <input id="eventEndsAt" name="eventEndsAt" type="datetime-local">
    </div>
  </div>

  <div class="grid sm:grid-2">
    <div class="field">
      <label for="eventLocation">Location (optional if online)</label>
      <input id="eventLocation" name="eventLocation" type="text" placeholder="e.g. Kampala Serena Hotel, Conference Room B">
    </div>
    <div class="field">
      <label for="eventOnlineUrl">Online Link (optional if in-person)</label>
      <input id="eventOnlineUrl" name="eventOnlineUrl" type="url" placeholder="Zoom / Google Meet link — shown to ticket holders only">
    </div>
  </div>

  <div class="field">
    <label for="ticketCapacity">Ticket Capacity (optional)</label>
    <input id="ticketCapacity" name="ticketCapacity" type="number" min="1" step="1" placeholder="Leave blank for unlimited">
  </div>

  <div class="field">
    <label for="thumbnail">Thumbnail Image (optional)</label>
    <input id="thumbnail" name="thumbnail" type="file" accept="image/*">
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg">Create Event & Continue</button>
</form>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
