      </div>
    </div>

    <div class="auth-trust">
      <div class="item"><div class="num"><?= (int) $authStats['course_count'] ?>+</div><div class="lbl">Courses</div></div>
      <div class="divider"></div>
      <div class="item"><div class="num"><?= (int) $authStats['learner_count'] ?>+</div><div class="lbl">Learners</div></div>
      <div class="divider"></div>
      <div class="item"><div class="num"><?= (int) $authStats['creator_count'] ?>+</div><div class="lbl">Creators</div></div>
    </div>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/main.js')) ?>"></script>
</body>
</html>
