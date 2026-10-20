<?php if (!empty($profShell)): ?>
</main>
<script src="<?= e(versioned_asset('assets/js/main.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/professor.js')) ?>"></script>
<?php if (!empty($user)): ?><script src="<?= e(versioned_asset('assets/js/chat-alerts.js')) ?>" data-base="<?= e(rtrim(base_url(''), '/')) ?>" defer></script><?php endif; ?>
</body>
</html>
<?php return; endif; ?>
    </div>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/main.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/charts.js')) ?>"></script>
<?php if (!empty($user)): ?><script src="<?= e(versioned_asset('assets/js/chat-alerts.js')) ?>" data-base="<?= e(rtrim(base_url(''), '/')) ?>" defer></script><?php endif; ?>
</body>
</html>
