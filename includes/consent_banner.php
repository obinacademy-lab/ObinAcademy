<?php
/**
 * Cookie consent banner — gates Phase 1's visitor tracking and Phase 2's
 * lead-capture popups behind an actual choice (see cookie-consent.js).
 * Hidden by default; JS shows it only when no oa_consent cookie exists yet.
 */
?>
<div class="consent-banner" data-consent-banner hidden>
  <div class="consent-banner-text">
    <p>
      We use cookies to understand how visitors use Obin Academy and to show relevant offers.
      Read our <a href="<?= e(base_url('privacy.php')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.
    </p>
  </div>
  <div class="consent-banner-actions">
    <button type="button" class="btn btn-outline btn-sm" data-consent-reject>Reject</button>
    <button type="button" class="btn btn-primary btn-sm" data-consent-accept>Accept</button>
  </div>
</div>
