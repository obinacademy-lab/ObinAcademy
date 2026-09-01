<?php
/** Renders a course card. Expects $c (a get_course_cards() row) in scope. */
function render_course_card(array $c): void {
    $rating = round((float) $c['avg_rating'], 1);
    $roundedRating = (int) round($rating);
    $reviewCount = (int) $c['review_count'];
    $isBestseller = $reviewCount > 0 && $rating >= 4.5;
    $isNew = $reviewCount === 0;
    $hasSale = !empty($c['sale_price']) && (float) $c['sale_price'] > 0 && (float) $c['sale_price'] < (float) $c['price'];
    ?>
    <a href="<?= e(base_url('courses/view.php?slug=' . $c['slug'])) ?>" class="course-card">
      <div class="thumb">
        <?php if (!empty($c['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($c['thumbnail_url'])) ?>" alt="" loading="lazy">
        <?php else: ?>
          <div class="placeholder">Obin Academy</div>
        <?php endif; ?>
        <span class="cat-pill"><?= e($c['category_name']) ?></span>
        <?php if ($isBestseller): ?>
          <span class="badge-pill badge-bestseller">🏆 Bestseller</span>
        <?php elseif ($hasSale): ?>
          <span class="badge-pill badge-sale">🔥 Sale</span>
        <?php elseif ($isNew): ?>
          <span class="badge-pill badge-new">✨ New</span>
        <?php endif; ?>
      </div>
      <div class="body">
        <div class="top-row">
          <?php if ($reviewCount > 0): ?>
            <div class="rating">
              <span class="rating-num"><?= number_format($rating, 1) ?></span>
              <?php for ($i = 1; $i <= 5; $i++): ?><?= $i <= $roundedRating ? '★' : '☆' ?><?php endfor; ?>
              <span class="count">(<?= $reviewCount ?>)</span>
            </div>
          <?php else: ?>
            <span class="no-reviews">Just launched</span>
          <?php endif; ?>
        </div>

        <h3><?= e($c['title']) ?></h3>

        <div class="bottom-row">
          <div class="creator">
            <div class="avatar">
              <?php if (!empty($c['creator_avatar_url'])): ?>
                <img src="<?= e(asset_src($c['creator_avatar_url'])) ?>" alt="">
              <?php else: ?><?= e(mb_substr($c['creator_name'], 0, 1)) ?><?php endif; ?>
            </div>
            <span><?= e($c['creator_name']) ?></span>
          </div>
        </div>

        <div class="price-row">
          <span class="price">
            <?php if ($hasSale): ?>
              <span class="price-strike">UGX <?= number_format((float) $c['price']) ?></span><span class="currency">UGX</span><?= number_format((float) $c['sale_price']) ?>
            <?php elseif ((float) $c['price'] > 0): ?>
              <span class="currency">UGX</span><?= number_format((float) $c['price']) ?>
            <?php else: ?>
              Free
            <?php endif; ?>
          </span>
          <span class="view-btn">View Course <span class="arrow">→</span></span>
        </div>
      </div>
    </a>
    <?php
}
