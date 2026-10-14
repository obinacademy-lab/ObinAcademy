/* A member's campus (campus-learn.php): the line along the steps runs to the current step, and the
   live class countdown ticks. (The slide direction between pages is set in the page head.) */
(function () {
  // The blue line runs from the first step to the last done or current one. Offsets ignore the
  // entrance transforms, so the length is right while the steps are still sliding in.
  function spine() {
    document.querySelectorAll('.cl-steps').forEach(function (ol) {
      var reached = ol.querySelectorAll('.cl-step.done .cl-node, .cl-step.cur .cl-node'), first = ol.querySelector('.cl-node');
      if (!reached.length || !first) { ol.style.setProperty('--reach', '0px'); return; }
      var last = reached[reached.length - 1];
      var y = function (n) { return n.closest('.cl-step').offsetTop + n.offsetTop; };
      ol.style.setProperty('--reach', (y(last) - y(first)) + 'px');
    });
  }
  spine();
  addEventListener('resize', spine);
  addEventListener('load', spine);

  function pad(n) { return String(n).padStart(2, '0'); }
  var counts = document.querySelectorAll('[data-count]');
  function tick() {
    counts.forEach(function (c) {
      var s = Math.max(0, Math.floor((+c.dataset.at - Date.now()) / 1000));
      var v = [Math.floor(s / 86400), Math.floor(s / 3600) % 24, Math.floor(s / 60) % 60, s % 60].map(pad);
      c.querySelectorAll('b').forEach(function (b, i) {
        if (b.textContent === v[i]) return;
        b.textContent = v[i];
        b.classList.remove('flip'); void b.offsetWidth; b.classList.add('flip');
      });
      if (s === 0 && !c.dataset.done) { c.dataset.done = '1'; setTimeout(function () { location.reload(); }, 1500); }
    });
  }
  if (counts.length) { tick(); setInterval(tick, 1000); }

  // One tap on "Mark as done": stop double posts.
  document.addEventListener('submit', function (e) {
    var b = e.target.querySelector('button[type="submit"]');
    if (!b) return;
    if (b.dataset.sent) { e.preventDefault(); return; }
    b.dataset.sent = '1';
  });
})();
