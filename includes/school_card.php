<?php
/**
 * Renders a school card — a creator's storefront, not a single course.
 * Expects $school (a get_featured_schools()/search_schools() row) in
 * scope: id, name, school_name, avatar_url, school_cover_url,
 * pricing_model, school_monthly_price, course_count, student_count,
 * min_price. Reuses .home-discover-card's markup/CSS wholesale (thumb +
 * avatar + body + h3 + creator + meta) rather than inventing a new card
 * style — same visual language, just school-shaped content.
 */
function render_school_card(array $school): void {
    $schoolLabel = $school['school_name'] ?: $school['name'];
    $isSubscription = ($school['pricing_model'] ?? 'PER_COURSE') === 'MONTHLY_SUBSCRIPTION';
    $courseCount = (int) $school['course_count'];
    $studentCount = (int) $school['student_count'];
    $viewCount = (int) ($school['view_count'] ?? 0);
    $followerCount = (int) ($school['follower_count'] ?? 0);
    // No school_cover_url of its own yet — borrow a course thumbnail
    // (get_school_cards()'s fallback_thumbnail_url) rather than showing the
    // plain placeholder box.
    $coverUrl = $school['school_cover_url'] ?: ($school['fallback_thumbnail_url'] ?? null);
    ?>
    <a href="<?= e(base_url('profile.php?id=' . $school['id'])) ?>" class="home-discover-card reveal">
      <div class="thumb">
        <?php if (!empty($coverUrl)): ?>
          <img src="<?= e(asset_src($coverUrl)) ?>" alt="" loading="lazy">
        <?php else: ?>
          <div class="placeholder">Obin Academy</div>
        <?php endif; ?>
        <span class="avatar">
          <?php if (!empty($school['avatar_url'])): ?>
            <img src="<?= e(asset_src($school['avatar_url'])) ?>" alt="">
          <?php else: ?><?= e(mb_substr($school['name'], 0, 1)) ?><?php endif; ?>
        </span>
      </div>
      <div class="body">
        <h3><?= e($schoolLabel) ?></h3>
        <p class="creator">by <?= e($school['name']) ?></p>
        <div class="school-stats-row">
          <span title="<?= (int) $courseCount ?> course<?= $courseCount === 1 ? '' : 's' ?>"><?php dash_icon('book-open'); ?><?= number_format($courseCount) ?></span>
          <span title="<?= (int) $studentCount ?> student<?= $studentCount === 1 ? '' : 's' ?>"><?php dash_icon('users'); ?><?= number_format($studentCount) ?></span>
          <span title="<?= (int) $viewCount ?> view<?= $viewCount === 1 ? '' : 's' ?>"><?php dash_icon('eye'); ?><?= number_format($viewCount) ?></span>
          <span title="<?= (int) $followerCount ?> follower<?= $followerCount === 1 ? '' : 's' ?>"><?php dash_icon('heart'); ?><?= number_format($followerCount) ?></span>
        </div>
        <p class="school-price-row">
          <?php if ($isSubscription): ?>
            <strong><?= e(format_money((float) $school['school_monthly_price'])) ?></strong>/mo
          <?php elseif (!empty($school['min_price']) && (float) $school['min_price'] > 0): ?>
            From <strong><?= e(format_money((float) $school['min_price'])) ?></strong>
          <?php else: ?>
            Free
          <?php endif; ?>
        </p>
      </div>
    </a>
    <?php
}
