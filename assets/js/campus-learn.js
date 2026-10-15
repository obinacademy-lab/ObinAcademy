/* A member's campus (campus-learn.php): the This month / Whole year switch on the progress card, the
   live class countdown, a ripple on buttons, and one tap only on "Mark complete". */
(function () {
  // This month / Whole year
  var seg = document.querySelector('[data-seg]');
  if (seg) seg.addEventListener('click', function (e) {
    var b = e.target.closest('[data-scope]'); if (!b) return;
    var on = b.dataset.scope; if (seg.dataset.on === on) return;
    seg.dataset.on = on;
    seg.querySelectorAll('[data-scope]').forEach(function (x) { x.setAttribute('aria-pressed', x === b); });
    document.querySelectorAll('[data-scope-show]').forEach(function (el) {
      var show = el.dataset.scopeShow === on; el.hidden = !show;
      if (show) { el.classList.remove('swap'); void el.offsetWidth; el.classList.add('swap'); }
    });
    var t = document.querySelector('[data-scope-track]'); if (t) t.style.setProperty('--p', t.dataset[on] + '%');
  });

  // Live class countdown
  function pad(n) { return String(n).padStart(2, '0'); }
  var counts = document.querySelectorAll('[data-count]');
  function tick() {
    counts.forEach(function (c) {
      var s = Math.max(0, Math.floor((+c.dataset.at - Date.now()) / 1000));
      var v = [Math.floor(s / 86400), Math.floor(s / 3600) % 24, Math.floor(s / 60) % 60, s % 60].map(pad);
      c.querySelectorAll('b').forEach(function (b, i) {
        if (b.textContent === v[i]) return;
        b.textContent = v[i]; b.classList.remove('flip'); void b.offsetWidth; b.classList.add('flip');
      });
      if (s === 0 && !c.dataset.done) { c.dataset.done = '1'; setTimeout(function () { location.reload(); }, 1500); }
    });
  }
  if (counts.length) { tick(); setInterval(tick, 1000); }

  // A soft ripple from where the button was pressed
  document.addEventListener('pointerdown', function (e) {
    var b = e.target.closest('.cl-btn'); if (!b || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var r = b.getBoundingClientRect(), s = document.createElement('span');
    s.className = 'cl-ripple'; s.style.left = (e.clientX - r.left) + 'px'; s.style.top = (e.clientY - r.top) + 'px';
    b.appendChild(s); setTimeout(function () { s.remove(); }, 800);
  });

  // One tap on "Mark complete": no double posts
  document.addEventListener('submit', function (e) {
    var b = e.target.querySelector('button[type="submit"]'); if (!b) return;
    if (b.dataset.sent) { e.preventDefault(); return; }
    b.dataset.sent = '1';
  });
})();
