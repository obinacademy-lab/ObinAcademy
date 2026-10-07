      </div>

      <a class="auth-help" href="mailto:info@obinacademy.site">Need help? info@obinacademy.site</a>
    </section>

    <section class="auth-right" aria-label="Why Obin Academy">
      <div class="auth-stage">
        <?php foreach ($authSlides as $i => $slide): ?>
          <img class="auth-slide<?= $i === 0 ? ' on' : '' ?>" src="<?= e(versioned_asset('assets/img/' . $slide['img'])) ?>" alt="" <?= $i === 0 ? '' : 'loading="lazy"' ?>>
        <?php endforeach; ?>
        <div class="auth-dots" data-auth-dots></div>

        <div class="auth-call auth-call-a">
          <span class="auth-call-ic"><?php dash_icon('graduation-cap'); ?></span>
          <span class="auth-call-tx"><small data-auth-a1><?= e($authSlides[0]['a'][0]) ?></small><b data-auth-a2><?= e($authSlides[0]['a'][1]) ?></b></span>
        </div>
        <div class="auth-call auth-call-b">
          <b data-auth-b1><?= e($authSlides[0]['b'][0]) ?></b>
          <small data-auth-b2><?= e($authSlides[0]['b'][1]) ?></small>
        </div>
        <div class="auth-call-pill"><span data-auth-pill><?= e($authSlides[0]['pill']) ?></span></div>
      </div>
    </section>
  </div>
</div>
<script>
  window.OBIN_AUTH_SLIDES = <?= json_encode($authSlides, JSON_HEX_TAG) ?>;
</script>
<script src="<?= e(versioned_asset('assets/js/main.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/auth-slides.js')) ?>"></script>
</body>
</html>