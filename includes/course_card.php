<?php
/** Renders a course or event card. Expects $c (a get_course_cards()/get_event_cards() row) in scope. */
function render_course_card(array $c): void {
    $isEvent = $c['type'] === 'EVENT';
    $hasSale = course_has_active_sale($c);
    $displayPrice = $hasSale ? (float) $c['sale_price'] : (float) $c['price'];
    $saleDaysLeft = $hasSale ? course_sale_days_left($c) : null;
    $eventSoldOut = $isEvent && event_is_sold_out($c, (int) $c['student_count']);
    $eventPassed = $isEvent && event_has_passed($c);
    ?>
    <a href="<?= e(base_url('courses/view.php?slug=' . $c['slug'])) ?>" class="course-card">
      <div class="thumb <?= $isEvent ? 'thumb-portrait' : '' ?>">
        <?php if ($isEvent && $eventSoldOut): ?>
          <span class="badge-pill badge-sale">Sold Out</span>
        <?php elseif ($isEvent && !$eventPassed): ?>
          <span class="badge-pill badge-new">🎟 Event</span>
        <?php elseif ($hasSale): ?>
          <span class="badge-pill badge-sale">🔥 <?= $saleDaysLeft !== null ? $saleDaysLeft . ' day' . ($saleDaysLeft === 1 ? '' : 's') . ' left' : 'On Sale' ?></span>
        <?php elseif (!empty($c['reviewed_at']) && strtotime($c['reviewed_at']) >= strtotime('-' . NEW_COURSE_BADGE_DAYS . ' days')): ?>
          <span class="badge-pill badge-new">New</span>
        <?php endif; ?>
        <?php if (!empty($c['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($c['thumbnail_url'])) ?>" alt="" loading="lazy">
        <?php else: ?>
          <div class="placeholder">Obin Academy</div>
        <?php endif; ?>
      </div>
      <div class="body">
        <div class="creator-row">
          <div class="avatar">
            <?php if (!empty($c['creator_avatar_url'])): ?>
              <img src="<?= e(asset_src($c['creator_avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($c['creator_name'], 0, 1)) ?><?php endif; ?>
          </div>
          <span><?= e($c['creator_name']) ?></span>
        </div>

        <h3><?= e($c['title']) ?></h3>
        <p class="desc"><?= e($c['summary']) ?></p>

        <div class="stats-row">
          <?php if ($isEvent): ?>
            <span><?php dash_icon('calendar'); ?><?= $c['event_starts_at'] ? e(date('M j, g:i A', strtotime($c['event_starts_at']))) : 'Date TBA' ?></span>
            <span><?php dash_icon($c['event_online_url'] ? 'globe' : 'map-pin'); ?><?= $c['event_online_url'] ? 'Online' : e(mb_strimwidth((string) $c['event_location'], 0, 22, '…')) ?></span>
          <?php else: ?>
            <span><?php dash_icon('users'); ?><?= number_format((int) $c['student_count']) ?> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?></span>
            <span><?php dash_icon('eye'); ?><?= number_format((int) $c['view_count']) ?> view<?= (int) $c['view_count'] === 1 ? '' : 's' ?></span>
          <?php endif; ?>
        </div>

        <div class="price-row">
          <span class="price">
            <?php if ($hasSale): ?><span class="price-strike"><?= e(format_money((float) $c['price'])) ?></span><?php endif; ?>
            <?php if ($displayPrice > 0): ?>
              <span class="currency">UGX</span><?= number_format($displayPrice) ?>
            <?php else: ?>
              Free
            <?php endif; ?>
          </span>
        </div>
      </div>
    </a>
    <?php
}
