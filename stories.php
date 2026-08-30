<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

$user = current_user();
$errors = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$user) {
        $errors[] = 'You must be logged in to share your story.';
    } else {
        $quote = post('quote');
        $rating = (int) post('rating', '5');
        if (strlen($quote) < 20) $errors[] = 'Tell us a bit more — at least 20 characters.';
        if ($rating < 1 || $rating > 5) $rating = 5;

        if (!$errors) {
            db_insert('INSERT INTO testimonials (quote, rating, author_id) VALUES (?, ?, ?)', [$quote, $rating, $user['id']]);
            $submitted = true;
        }
    }
}

$testimonials = get_published_testimonials();

$pageTitle = 'Stories — Obin Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="course-hero">
  <div class="container" style="max-width:640px; text-align:center;">
    <span class="pill">Real Results</span>
    <h1 style="margin-top:14px;">Stories From Our Community</h1>
    <p class="summary" style="margin:14px auto 0;">Hear from learners and creators building real skills and real income on Obin Academy.</p>
  </div>
</section>

<div class="section testimonials-decor">
  <div class="container">
    <?php if ($testimonials): ?>
      <div class="grid sm:grid-2 lg:grid-3">
        <?php foreach ($testimonials as $t): ?>
          <div class="testimonial-card">
            <span class="quote-mark">&ldquo;</span>
            <div class="rating-row">
              <span class="stars"><?= str_repeat('★', (int) $t['rating']) . str_repeat('☆', 5 - (int) $t['rating']) ?></span>
              <span class="rating-num"><?= number_format((float) $t['rating'], 1) ?></span>
            </div>
            <p class="quote"><?= e($t['quote']) ?></p>
            <div class="author">
              <div class="avatar">
                <?php if (!empty($t['author_avatar_url'])): ?>
                  <img src="<?= e(asset_src($t['author_avatar_url'])) ?>" alt="">
                <?php else: ?><?= e(mb_substr($t['author_name'], 0, 1)) ?><?php endif; ?>
              </div>
              <div>
                <div class="name"><?= e($t['author_name']) ?></div>
                <?php if (!empty($t['author_headline'])): ?><div class="role"><?= e($t['author_headline']) ?></div><?php endif; ?>
                <div class="verified">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"></path><circle cx="12" cy="12" r="10"></circle></svg>
                  Verified Learner
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted text-center">No stories published yet.</p>
    <?php endif; ?>
  </div>
</div>

<div class="container" style="max-width:520px; padding-bottom:72px;">
  <h2 class="h3 text-center">Share Your Story</h2>
  <?php if ($submitted): ?>
    <div class="alert alert-success" style="margin-top:16px;">Thanks for sharing! Your story is pending review.</div>
  <?php else: ?>
    <?php if ($errors): ?><div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <?php if (!$user): ?>
      <p class="muted text-center card card-pad" style="margin-top:16px;"><a href="<?= e(base_url('login.php?redirect=/stories.php')) ?>" style="color:var(--accent); font-weight:600;">Log in</a> to share your story.</p>
    <?php else: ?>
      <form method="post" class="card card-pad" style="margin-top:16px;">
        <?= csrf_field() ?>
        <div class="field">
          <label>Your Rating</label>
          <div class="row gap-1" data-star-input style="font-size:22px; color:var(--gold); cursor:pointer;">
            <?php for ($i = 1; $i <= 5; $i++): ?><span data-star="<?= $i ?>">☆</span><?php endfor; ?>
          </div>
          <input type="hidden" name="rating" value="5" data-rating-input>
        </div>
        <div class="field"><label for="quote">Your Story</label><textarea id="quote" name="quote" rows="4" required placeholder="What did Obin Academy help you achieve?"></textarea></div>
        <button type="submit" class="btn btn-primary btn-block">Submit Story</button>
      </form>
      <script>
        (() => {
          const wrap = document.querySelector('[data-star-input]');
          const input = document.querySelector('[data-rating-input]');
          if (!wrap) return;
          wrap.querySelectorAll('[data-star]').forEach((s) => {
            s.addEventListener('click', () => {
              const val = Number(s.dataset.star);
              input.value = String(val);
              wrap.querySelectorAll('[data-star]').forEach((el) => { el.textContent = Number(el.dataset.star) <= val ? '★' : '☆'; });
            });
          });
        })();
      </script>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
