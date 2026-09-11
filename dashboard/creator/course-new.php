<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['CREATOR', 'ADMIN']);

$errors = [];
$categories = get_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $type = post('type') === 'EVENT' ? 'EVENT' : 'COURSE';
    $title = post('title');
    $summary = post('summary');
    $description = post('description');
    $price = (float) post('price', '0');
    $categoryId = (int) post('categoryId');

    if (strlen($title) < 4) $errors[] = 'Title must be at least 4 characters.';
    if (strlen($summary) < 10) $errors[] = 'Summary must be at least 10 characters.';
    if (strlen($description) < 20) $errors[] = 'Description must be at least 20 characters.';
    if ($price < 0) $errors[] = 'Price cannot be negative.';
    if (!$categoryId) $errors[] = 'Select a category.';

    $accessDurationDays = null;
    $premiumPrice = null;
    $eventStartsAt = null;
    $eventEndsAt = null;
    $eventLocation = null;
    $eventOnlineUrl = null;
    $ticketCapacity = null;

    if ($type === 'EVENT') {
        $eventStartsAtRaw = post('eventStartsAt');
        $eventEndsAtRaw = post('eventEndsAt');
        $eventLocation = post('eventLocation') ?: null;
        $eventOnlineUrl = post('eventOnlineUrl') ?: null;
        $ticketCapacityRaw = post('ticketCapacity');
        $ticketCapacity = $ticketCapacityRaw === '' ? null : (int) $ticketCapacityRaw;

        if ($eventStartsAtRaw === '') {
            $errors[] = 'Set the event start date and time.';
        } else {
            $eventStartsAt = date('Y-m-d H:i:s', strtotime($eventStartsAtRaw));
        }
        if ($eventEndsAtRaw !== '') {
            $eventEndsAt = date('Y-m-d H:i:s', strtotime($eventEndsAtRaw));
            if ($eventStartsAt && strtotime($eventEndsAt) < strtotime($eventStartsAt)) {
                $errors[] = 'Event end time must be after the start time.';
            }
        }
        if (!$eventLocation && !$eventOnlineUrl) {
            $errors[] = 'Add a location or an online link for the event.';
        }
    } else {
        $accessDurationRaw = post('accessDurationDays', 'lifetime');
        $accessDurationDays = $accessDurationRaw === 'lifetime' ? null : (int) $accessDurationRaw;
        $premiumPriceRaw = post('premiumPrice');
        $premiumPrice = $premiumPriceRaw === '' ? null : (float) $premiumPriceRaw;
    }

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
            "INSERT INTO courses (title, slug, type, summary, description, price, category_id, access_duration_days, premium_price,
                event_starts_at, event_ends_at, event_location, event_online_url, ticket_capacity, creator_id, thumbnail_url, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT')",
            [$title, $slug, $type, $summary, $description, $price, $categoryId, $accessDurationDays, $premiumPrice,
                $eventStartsAt, $eventEndsAt, $eventLocation, $eventOnlineUrl, $ticketCapacity, $user['id'], $thumbnailUrl]
        );
        redirect('/dashboard/creator/course-manage.php?id=' . $id);
    }
}

$pageTitle = 'Create Course — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Create Course or Event</h1>

<?php if ($errors): ?>
  <div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card card-pad" style="margin-top:20px; max-width:680px;" data-type-form>
  <?= csrf_field() ?>
  <div class="field">
    <label for="type">What are you creating?</label>
    <select id="type" name="type" data-type-select>
      <option value="COURSE" <?= ($_POST['type'] ?? 'COURSE') === 'COURSE' ? 'selected' : '' ?>>Course — ongoing lessons learners buy access to</option>
      <option value="EVENT" <?= ($_POST['type'] ?? '') === 'EVENT' ? 'selected' : '' ?>>Event — a one-off ticketed workshop, webinar, or meetup</option>
    </select>
  </div>
  <div class="field">
    <label for="title" data-title-label>Course Title</label>
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
      <input id="price" name="price" type="number" min="0" step="1" value="0" required>
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

  <div class="grid sm:grid-2" data-course-fields <?= ($_POST['type'] ?? 'COURSE') === 'EVENT' ? 'hidden' : '' ?>>
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

  <div data-event-fields <?= ($_POST['type'] ?? 'COURSE') === 'EVENT' ? '' : 'hidden' ?>>
    <div class="grid sm:grid-2">
      <div class="field">
        <label for="eventStartsAt">Event Starts</label>
        <input id="eventStartsAt" name="eventStartsAt" type="datetime-local" value="<?= e($_POST['eventStartsAt'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="eventEndsAt">Event Ends (optional)</label>
        <input id="eventEndsAt" name="eventEndsAt" type="datetime-local" value="<?= e($_POST['eventEndsAt'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label for="eventLocation">Location (optional if online)</label>
      <input id="eventLocation" name="eventLocation" type="text" placeholder="e.g. Kampala Serena Hotel, Conference Room B" value="<?= e($_POST['eventLocation'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="eventOnlineUrl">Online Link (optional if in-person)</label>
      <input id="eventOnlineUrl" name="eventOnlineUrl" type="url" placeholder="Zoom / Google Meet link — shown to ticket holders only" value="<?= e($_POST['eventOnlineUrl'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="ticketCapacity">Ticket Capacity (optional)</label>
      <input id="ticketCapacity" name="ticketCapacity" type="number" min="1" step="1" placeholder="Leave blank for unlimited" value="<?= e($_POST['ticketCapacity'] ?? '') ?>">
      <p class="help">Selling stops automatically once this many tickets are sold.</p>
    </div>
  </div>

  <div class="field">
    <label for="thumbnail">Thumbnail Image (optional)</label>
    <input id="thumbnail" name="thumbnail" type="file" accept="image/*">
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg" data-submit-btn>Create Course & Continue</button>
</form>
<script>
(function () {
  var form = document.querySelector('[data-type-form]');
  var select = form.querySelector('[data-type-select]');
  var courseFields = form.querySelector('[data-course-fields]');
  var eventFields = form.querySelector('[data-event-fields]');
  var titleLabel = form.querySelector('[data-title-label]');
  var submitBtn = form.querySelector('[data-submit-btn]');
  function sync() {
    var isEvent = select.value === 'EVENT';
    courseFields.hidden = isEvent;
    eventFields.hidden = !isEvent;
    titleLabel.textContent = isEvent ? 'Event Title' : 'Course Title';
    submitBtn.textContent = isEvent ? 'Create Event & Continue' : 'Create Course & Continue';
  }
  select.addEventListener('change', sync);
  sync();
})();
</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
