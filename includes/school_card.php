<?php
/**
 * Renders a school card — a creator's storefront, not a single course.
 * Expects $school (a get_featured_schools()/search_schools() row) in
 * scope: id, name, school_name, headline, avatar_url, school_cover_url,
 * pricing_model, school_monthly_price, course_count, student_count,
 * avg_rating, review_count, min_price.
 *
 * Laid out like skool.com's discover cards: banner on top, then the
 * school's logo and name on one line, its headline (up to three lines),
 * and a single meta row of courses, students, rating (only when the school
 * actually has reviews) and starting price.
 */
function render_school_card(array $school): void {
    $schoolLabel = $school['school_name'] ?: $school['name'];
    $isSubscription = ($school['pricing_model'] ?? 'PER_COURSE') === 'MONTHLY_SUBSCRIPTION';
    $courseCount = (int) $school['course_count'];
    $studentCount = (int) $school['student_count'];
    $reviewCount = (int) ($school['review_count'] ?? 0);
    $avgRating = (float) ($school['avg_rating'] ?? 0);
    $headline = trim((string) ($school['headline'] ?? ''));
    // Only worth a second line when the school has its own name distinct
    // from the creator's — otherwise "by X" just repeats the title.
    $showByline = !empty($school['school_name']) && $school['school_name'] !== $school['name'];
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
        <span class="price-tag">
          <?php if ($isSubscription): ?><?= e(format_money((float) $school['school_monthly_price'])) ?>/mo
          <?php elseif (!empty($school['min_price']) && (float) $school['min_price'] > 0): ?>From <?= e(format_money((float) $school['min_price'])) ?>
          <?php else: ?>Free<?php endif; ?>
        </span>
      </div>
      <div class="body">
        <div class="school-id">
          <span class="avatar">
            <?php if (!empty($school['avatar_url'])): ?>
              <img src="<?= e(asset_src($school['avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($school['name'], 0, 1)) ?><?php endif; ?>
          </span>
          <div class="school-id-text">
            <h3><?= e($schoolLabel) ?></h3>
            <?php if ($showByline): ?><span class="creator">by <?= e($school['name']) ?></span><?php endif; ?>
          </div>
        </div>
        <?php if ($headline !== ''): ?><p class="desc"><?= e($headline) ?></p><?php endif; ?>
        <div class="school-meta">
          <span title="<?= $courseCount ?> course<?= $courseCount === 1 ? '' : 's' ?>"><?php dash_icon('book-open'); ?><?= number_format($courseCount) ?></span>
          <span title="<?= $studentCount ?> student<?= $studentCount === 1 ? '' : 's' ?>"><?php dash_icon('users'); ?><?= number_format($studentCount) ?></span>
          <?php if ($reviewCount > 0): ?>
            <span class="rating" title="<?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.5 2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9L12 2.5Z"/></svg><?= number_format($avgRating, 1) ?> (<?= number_format($reviewCount) ?>)</span>
          <?php endif; ?>
          <span class="price">
            <?php if ($isSubscription): ?>
              <strong><?= e(format_money((float) $school['school_monthly_price'])) ?></strong>/mo
            <?php elseif (!empty($school['min_price']) && (float) $school['min_price'] > 0): ?>
              From <strong><?= e(format_money((float) $school['min_price'])) ?></strong>
            <?php else: ?>
              Free
            <?php endif; ?>
          </span>
        </div>
      </div>
    </a>
    <?php
}
