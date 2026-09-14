<?php
/**
 * Renders the course-detail sidebar panel. Every course now requires an
 * active subscription (see require_course_access_or_redirect() in
 * includes/subscriptions.php) — courses/view.php has already redirected
 * away anyone who isn't the owner/admin, an active subscriber, or a
 * grandfathered one-time buyer before this ever renders, so there's no
 * "buy this course" state left to show here; just which of those three
 * reasons got them in.
 */
function render_enroll_panel(array $course, bool $isOwner, bool $isEnrolled): void {
    ?>
    <div class="enroll-panel reveal reveal-delay-2">
      <div class="thumb">
        <?php if (!empty($course['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($course['thumbnail_url'])) ?>" alt="">
        <?php else: ?>
          <div class="placeholder"><?php dash_icon('graduation-cap'); ?><span>Obin Academy</span></div>
        <?php endif; ?>
      </div>
      <div class="pad">
        <?php if ($isOwner): ?>
          <a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $course['id'])) ?>" class="btn btn-dark btn-block btn-lg">Manage Course</a>
        <?php elseif ($isEnrolled): ?>
          <a href="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>" class="btn btn-primary btn-block btn-lg">▶ Continue Learning</a>
          <div class="access-note" style="margin-top:14px;">
            <?php dash_icon('clock'); ?>
            <?= $course['access_duration_days'] ? (int) $course['access_duration_days'] . ' days of access after purchase' : 'Lifetime access' ?>
          </div>
        <?php else: ?>
          <a href="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>" class="btn btn-primary btn-block btn-lg">▶ Start Learning</a>
          <div class="access-note" style="margin-top:14px;">
            <?php dash_icon('crown'); ?>
            Included in your subscription
          </div>
        <?php endif; ?>

        <ul class="perks" style="margin-top:20px;">
          <li><?php dash_icon('check-circle'); ?>Every course, included</li>
          <li><?php dash_icon('check-circle'); ?>Stream video lessons and PDFs anytime</li>
          <li><?php dash_icon('check-circle'); ?>Certificate of completion</li>
          <li><?php dash_icon('check-circle'); ?>Learn on any device</li>
        </ul>
      </div>
    </div>
    <?php
}
