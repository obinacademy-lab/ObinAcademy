<?php if (!empty($authAlt)): ?>
        <div class="auth-alt">
          <p><?= e($authAlt['lead']) ?></p>
          <a href="<?= e($authAlt['href']) ?>" class="btn-lime"><?= e($authAlt['cta']) ?></a>
        </div>
<?php endif; ?>
      </div>

      <a class="auth-help" href="mailto:info@obinacademy.site"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="m7 9 5 3.5L17 9"/></svg>info@obinacademy.site</a>
    </section>

    <section class="auth-right" aria-label="Why Obin Academy">
      <div class="auth-stage">
        <?php foreach ($authSlides as $i => $slide): ?>
          <img class="auth-slide<?= $i === 0 ? ' on' : '' ?>" src="<?= e(base_url('assets/img/' . $slide['img'])) ?>" alt="" <?= $i === 0 ? '' : 'loading="lazy"' ?>>
        <?php endforeach; ?>
        <div class="auth-notch"><span class="auth-pill"><i></i><span data-auth-pill><?= e($authSlides[0]['pill']) ?></span></span></div>
        <div class="auth-cap"><h2 data-auth-h><?= e($authSlides[0]['h']) ?></h2><p data-auth-p><?= e($authSlides[0]['p']) ?></p></div>
      </div>
      <div class="auth-nav">
        <button type="button" class="auth-tile auth-prev" data-auth-prev aria-label="Previous slide"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></button>
        <div class="auth-dots" data-auth-dots></div>
        <button type="button" class="auth-tile auth-next" data-auth-next aria-label="Next slide"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
      </div>
    </section>
  </div>
</div>
<script>
  window.OBIN_AUTH_SLIDES = <?= json_encode(array_map(fn($s) => ['pill' => $s['pill'], 'h' => $s['h'], 'p' => $s['p']], $authSlides), JSON_HEX_TAG) ?>;
</script>
<script src="<?= e(versioned_asset('assets/js/main.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/auth-slides.js')) ?>"></script>
</body>
</html>