<?php
/**
 * Upload-first course builder. With no ?id= it shows a big drop zone; the first
 * file dropped creates the draft course (api/course-builder.php) and the page
 * becomes a lesson timeline plus a short details panel, all without reloading.
 * With ?id= it reopens an existing draft or rejected course.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/data.php';
require __DIR__ . '/../../includes/course_builder.php';
$user = require_role(['CREATOR', 'ADMIN']);

$courseId = (int) query_param('id');
$state = null;
$creator = $user;
if ($courseId) {
    $course = db_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
    if (!$course || ((int) $course['creator_id'] !== (int) $user['id'] && $user['role'] !== 'ADMIN')) {
        http_response_code(404);
        exit('Course not found');
    }
    // Once submitted, a course is managed (analytics, pricing, students) on the manage page.
    if (!in_array($course['status'], ['DRAFT', 'REJECTED'], true)) {
        redirect('/dashboard/creator/course-manage.php?id=' . $courseId);
    }
    $creator = db_one('SELECT * FROM users WHERE id = ?', [$course['creator_id']]);
    $state = builder_state($courseId);
}
$creatorHasSubscription = $creator['pricing_model'] === 'MONTHLY_SUBSCRIPTION' && (float) $creator['school_monthly_price'] > 0;
$categories = get_categories();

$config = [
    'api' => base_url('api/course-builder.php'),
    'csrf' => csrf_token(),
    'courseId' => $courseId,
    'subscription' => $creatorHasSubscription,
    'manageUrl' => base_url('dashboard/creator/course-manage.php?id='),
    'listUrl' => base_url('dashboard/creator/index.php'),
    'buildUrl' => base_url('dashboard/creator/course-build.php?id='),
    'feeRate' => PLATFORM_FEE_RATE,
    'unnamed' => UNNAMED_MODULE_TITLES,
    'state' => $state,
];

$pageTitle = 'Create Course — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="cb" data-cb>
  <section class="cb-start" data-cb-start>
    <h1 class="h2">Build your course in modules</h1>
    <p class="cb-lede">Drop your lesson files to begin. They go into a first module that you name next. Add more modules, each with its own lessons, as you go.</p>
    <div class="cb-big" data-cb-drop>
      <?php dash_icon('upload'); ?>
      <h3>Drag videos or PDFs here</h3>
      <p>MP4, MOV, WebM or PDF. Up to 3 GB per video.</p>
      <div class="cb-acts">
        <label class="btn btn-primary" style="cursor:pointer;">Choose files<input type="file" multiple accept="video/*,application/pdf" data-cb-pick hidden></label>
      </div>
    </div>
    <p class="cb-skip">Prefer to plan your modules first? <button type="button" class="cb-link" data-cb-blank>Start with modules</button></p>
    <p class="cb-error" data-cb-start-error role="alert" hidden></p>
  </section>

  <section class="cb-build" data-cb-build hidden>
    <?php if ($state && $state['course']['status'] === 'REJECTED' && $state['course']['rejectionReason']): ?>
      <div class="alert alert-error" style="margin-bottom:16px;"><strong>Rejected:</strong> <?= e($state['course']['rejectionReason']) ?> Fix this and submit again.</div>
    <?php endif; ?>
    <div class="cb-cols">
      <div class="cb-main">
        <div class="cb-titlebar">
          <input type="text" class="cb-title" data-f="title" maxlength="120" placeholder="Name your course" aria-label="Course name" autocomplete="off">
          <span class="cb-pill" data-cb-pill>Draft</span>
        </div>
        <p class="cb-sub" data-cb-sub></p>
        <div class="cb-tl" data-cb-tl></div>
        <form class="cb-addmod" data-cb-addmod>
          <input type="text" data-cb-modname maxlength="190" placeholder="New module title (e.g. Getting started)" aria-label="New module title" autocomplete="off">
          <button type="submit" class="btn btn-primary btn-sm">Add module</button>
        </form>
      </div>

      <aside class="cb-side">
        <div class="cb-box">
          <div class="cb-box-head"><h3>Course details</h3><span class="cb-saved" data-cb-saved aria-live="polite"></span></div>
          <div class="cb-f"><span class="cb-lbl">Thumbnail</span>
            <label class="cb-thumbbox" data-cb-thumbbox>
              <img data-cb-thumb-img alt="Course thumbnail" hidden>
              <span class="cb-thumbph" data-cb-thumb-ph><?php dash_icon('upload'); ?><b>Add a thumbnail</b><small>JPG, PNG or WebP · 16:9 works best · up to 5 MB</small></span>
              <input type="file" accept="image/*" data-cb-thumb hidden>
            </label>
            <p class="cb-help" data-cb-thumb-err hidden></p></div>
          <div class="cb-f"><label for="cb-cat">Category</label>
            <select id="cb-cat" data-f="categoryId">
              <option value="">Select a category</option>
              <?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?>
            </select></div>

          <?php if ($creatorHasSubscription): ?>
            <div class="cb-f"><span class="cb-lbl">Pricing</span>
              <label class="cb-radio"><input type="radio" name="cb-sub" value="1" data-f="subscriptionIncluded" checked> <span><b>Included in my subscription</b><small>No separate price. Learners unlock it by subscribing.</small></span></label>
              <label class="cb-radio"><input type="radio" name="cb-sub" value="0" data-f="subscriptionIncluded"> <span><b>Sell separately</b><small>Its own price, on top of your subscription.</small></span></label>
            </div>
            <div class="cb-f" data-cb-pricebox hidden><label for="cb-price">Price (UGX)</label><input id="cb-price" type="number" min="0" step="1000" inputmode="numeric" data-f="price"></div>
          <?php else: ?>
            <div class="cb-f"><label for="cb-price">Price</label>
              <div class="cb-pr"><select data-f="kind" aria-label="Paid or free"><option value="paid">Paid</option><option value="free">Free</option></select>
                <input id="cb-price" type="number" min="0" step="1000" inputmode="numeric" placeholder="UGX 0" data-f="price" aria-label="Price in UGX"></div>
              <p class="cb-help" data-cb-earn></p></div>
          <?php endif; ?>

          <div class="cb-f">
            <label class="cb-radio"><input type="checkbox" data-cb-dl-toggle> <span><b>Sell lesson downloads</b><small>Optional. Learners pay an extra fee to download this course's files.</small></span></label>
            <div data-cb-dl-box hidden style="margin-top:8px;"><label for="cb-prem">Download price (UGX)</label><input id="cb-prem" type="number" min="0" step="1000" inputmode="numeric" placeholder="e.g. 15000" data-f="premiumPrice"></div>
          </div>
          <div class="cb-f"><label for="cb-summary">One-line summary</label><input id="cb-summary" type="text" maxlength="500" placeholder="One sentence describing the course" data-f="summary" autocomplete="off"></div>
          <div class="cb-f"><label for="cb-desc">What will learners get?</label><textarea id="cb-desc" rows="4" placeholder="What will they learn? What is included?" data-f="description"></textarea></div>

          <details class="cb-adv"><summary>More options</summary>
            <div class="cb-adv-in">
              <div class="cb-f"><label for="cb-acc">How long learners keep access</label>
                <select id="cb-acc" data-f="accessDurationDays">
                  <?php foreach (ACCESS_DURATION_OPTIONS as $o): ?><option value="<?= $o['days'] ?? 'lifetime' ?>"><?= e($o['label']) ?></option><?php endforeach; ?>
                </select></div>
              <p class="cb-help">Sale prices and payment plans are set from the course page once it's published.</p>
            </div>
          </details>
        </div>

        <div class="cb-box">
          <h3>Before you submit</h3>
          <div class="cb-meter"><i data-cb-meter></i></div>
          <ul class="cb-left" data-cb-left></ul>
          <button type="button" class="btn btn-primary btn-block" data-cb-submit disabled>Submit for review</button>
          <p class="cb-hint" data-cb-hint></p>
          <p class="cb-error" data-cb-submit-error role="alert" hidden></p>
        </div>
      </aside>
    </div>
  </section>
</div>

<script id="cb-config" type="application/json"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= e(versioned_asset('assets/js/course-builder.js')) ?>"></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
