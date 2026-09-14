<?php
/**
 * Renders the course-detail sidebar panel. The course page itself is
 * public now — this panel is what actually gates learning. Four states:
 * owner, a grandfathered one-time buyer, an active subscriber, or
 * everyone else (guest or logged-in non-subscriber), who gets a
 * Subscribe CTA instead of a watch button.
 */
function render_enroll_panel(array $course, bool $isOwner, bool $isEnrolled, bool $isSubscriber): void {
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
        <?php elseif ($isSubscriber): ?>
          <a href="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>" class="btn btn-primary btn-block btn-lg">▶ Start Learning</a>
          <div class="access-note" style="margin-top:14px;">
            <?php dash_icon('crown'); ?>
            Included in your subscription
          </div>
        <?php else: ?>
          <a href="<?= e(base_url('subscribe.php')) ?>" class="btn btn-gold btn-block btn-lg">🔓 Subscribe to Start Learning</a>
          <div class="access-note" style="margin-top:14px;">
            <?php dash_icon('crown'); ?>
            One plan unlocks every course, including this one
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
