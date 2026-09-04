<?php
/** Renders a course card. Expects $c (a get_course_cards() row) in scope. */
function render_course_card(array $c): void {
    $displayPrice = (!empty($c['sale_price']) && (float) $c['sale_price'] > 0 && (float) $c['sale_price'] < (float) $c['price'])
        ? (float) $c['sale_price']
        : (float) $c['price'];
    ?>
    <a href="<?= e(base_url('courses/view.php?slug=' . $c['slug'])) ?>" class="course-card">
      <div class="thumb">
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
          <span><?php dash_icon('users'); ?><?= number_format((int) $c['student_count']) ?> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?></span>
          <span><?php dash_icon('eye'); ?><?= number_format((int) $c['view_count']) ?> view<?= (int) $c['view_count'] === 1 ? '' : 's' ?></span>
        </div>

        <div class="price-row">
          <span class="price">
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
