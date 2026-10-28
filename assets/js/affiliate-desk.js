/* "My Referrals" (dashboard/learner/affiliate.php): counts the KPI/amount figures up from zero on load,
   and morphs the "Copy" button into a checkmark when the link is copied. Entrance motion, the headline
   reveal and the button ripple are all CSS (admin-desk.css) or shared (admin-desk.js); this is just the
   two bits that need script. Respects prefers-reduced-motion like the rest of the desk system. */
(function () {
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  function ease(t) { return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
  function fmt(n) { return Math.round(n).toLocaleString('en-US'); }
  function tween(dur, step, delay) {
    if (reduce || dur <= 0) { step(1); return; }
    setTimeout(function () {
      var t0 = performance.now();
      (function f(now) { var p = Math.min(1, (now - t0) / dur); step(ease(p)); if (p < 1) requestAnimationFrame(f); })(t0);
    }, delay || 0);
  }

  document.querySelectorAll('[data-count]').forEach(function (el, k) {
    var to = +el.dataset.count;
    tween(1300, function (p) { el.textContent = fmt(to * p); }, 550 + k * 70);
  });

  document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.dataset.copyLink;
      var done = function () {
        btn.classList.add('done');
        var label = btn.querySelector('span'); if (label) label.textContent = 'Copied';
        setTimeout(function () {
          btn.classList.remove('done');
          if (label) label.textContent = 'Copy';
        }, 1800);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, done);
      } else {
        try {
          var tmp = document.createElement('textarea');
          tmp.value = text; tmp.style.position = 'fixed'; tmp.style.opacity = '0';
          document.body.appendChild(tmp); tmp.focus(); tmp.select();
          document.execCommand('copy'); tmp.remove();
        } catch (e) {}
        done();
      }
    });
  });
})();
