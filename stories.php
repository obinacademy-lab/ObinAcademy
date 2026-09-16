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
$stats = get_platform_stats();

$pageTitle = 'Stories — Obin Academy';
$pageDescription = 'Real stories from learners and creators building real skills — and real income — on Obin Academy.';
require __DIR__ . '/includes/header.php';
?>
<section class="home-hero-v3">
  <div class="container" style="text-align:center;">
    <h1><span class="hero-shine">Stories From Our Community</span></h1>
    <p class="summary" style="margin-left:auto; margin-right:auto;">Hear from learners and creators building real skills and real income on Obin Academy.</p>

    <?php if ($testimonials): ?>
      <div class="hero-trust-stat" style="justify-content:center; color:var(--ink); background:color-mix(in srgb, var(--gold) 12%, white); border:1px solid color-mix(in srgb, var(--gold) 30%, var(--border));">
        <?php dash_icon('check-circle'); ?>
        <span style="color:#92660a;"><?= count($testimonials) ?></span> verified <?= count($testimonials) === 1 ? 'story' : 'stories' ?> from real learners and creators
      </div>
    <?php endif; ?>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<div class="section testimonials-decor">
  <div class="container">
    <?php if ($testimonials): ?>
      <div class="stories-slider" data-stories-slider>
        <div class="stories-track" data-stories-track>
          <?php foreach ($testimonials as $t): ?>
            <div class="testimonial-card">
              <span class="quote-mark">&ldquo;</span>
              <div class="rating-row">
                <span class="stars"><?= str_repeat('★', (int) $t['rating']) . str_repeat('☆', 5 - (int) $t['rating']) ?></span>
                <span class="rating-num"><?= number_format((float) $t['rating'], 1) ?></span>
              </div>
              <p class="quote"><?= e($t['quote']) ?></p>
              <div class="author">
                <div class="avatar"><?= e(mb_substr($t['author_name'], 0, 1)) ?></div>
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
        <?php if (count($testimonials) > 1): ?>
          <button type="button" class="stories-nav prev" data-stories-prev aria-label="Previous story">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
          </button>
          <button type="button" class="stories-nav next" data-stories-next aria-label="Next story">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
          </button>
          <div class="stories-dots" data-stories-dots></div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p class="muted text-center">No stories published yet.</p>
    <?php endif; ?>
  </div>
</div>

<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="grid lg:grid-2" style="gap:48px; align-items:center; max-width:960px; margin:0 auto;">
      <div class="reveal">
        <span class="eyebrow">Join the Community</span>
        <h2 class="h2" style="margin-top:14px;">Share Your Story</h2>
        <p class="lede" style="margin-top:14px; max-width:none; font-size:16px; line-height:1.75; color:var(--muted);">
          Finished a course, landed a client, or grew your income with a skill you learned here? Your story could be the reason someone else takes the leap.
        </p>
        <ul class="check-list" style="margin-top:26px;">
          <li><span class="check-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></span><span class="label-text">Takes less than a minute to submit</span></li>
          <li><span class="check-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></span><span class="label-text">Featured right here on the Stories page</span></li>
          <li><span class="check-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></span><span class="label-text">Reviewed before it goes live — no spam, ever</span></li>
        </ul>
      </div>

      <div class="reveal reveal-delay-2">
        <?php if ($submitted): ?>
          <div class="alert alert-success">Thanks for sharing! Your story is pending review.</div>
        <?php else: ?>
          <?php if ($errors): ?><div class="alert alert-error" style="margin-bottom:16px;"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
          <?php if (!$user): ?>
            <div class="card card-pad" style="text-align:center;">
              <p class="muted">Log in or create an account first to share your story.</p>
              <div class="row gap-2 center" style="margin-top:14px;">
                <a href="<?= e(base_url('login.php?redirect=/stories.php')) ?>" class="btn btn-outline">Log In</a>
                <a href="<?= e(base_url('signup.php?redirect=/stories.php')) ?>" class="btn btn-primary">Sign Up</a>
              </div>
            </div>
          <?php else: ?>
      <form method="post" class="card card-pad">
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
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
