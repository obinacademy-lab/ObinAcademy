      </div>
    </div>

    <div class="auth-trust">
      <div class="auth-trust-stats">
        <div class="item"><div class="num"><?= (int) $authStats['course_count'] ?>+</div><div class="lbl">Courses</div></div>
        <div class="divider"></div>
        <div class="item"><div class="num"><?= (int) $authStats['learner_count'] ?>+</div><div class="lbl">Learners</div></div>
        <div class="divider"></div>
        <div class="item"><div class="num"><?= (int) $authStats['creator_count'] ?>+</div><div class="lbl">Creators</div></div>
      </div>
      <?php $authQuotes = array_slice($authTestimonials, 0, 3); ?>
      <?php if ($authQuotes): ?>
        <div class="auth-quote-cycle" data-quote-cycle>
          <?php foreach ($authQuotes as $qi => $q): ?>
            <div class="auth-quote-slide <?= $qi === 0 ? 'active' : '' ?>">
              <p>&ldquo;<?= e(mb_strimwidth($q['quote'], 0, 110, '…')) ?>&rdquo;</p>
              <div class="who"><?= e($q['author_name']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/main.js')) ?>"></script>
<?php if (count($authQuotes) > 1): ?>
<script>
  (() => {
    const slides = document.querySelectorAll('[data-quote-cycle] .auth-quote-slide');
    if (slides.length < 2 || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
    let qi = 0;
    setInterval(() => {
      slides[qi].classList.remove('active');
      qi = (qi + 1) % slides.length;
      slides[qi].classList.add('active');
    }, 5000);
  })();
</script>
<?php endif; ?>
</body>
</html>
