/* A member's campus (campus-learn.php): live class countdown, and an instant tick when a lesson is
   marked done from a checklist (the form still posts; the page comes back with the tick drawn). */
(function () {
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

  // On narrow screens the year track scrolls: start it with the student's month in view.
  var scroller = document.querySelector('.cl-scroll'), now = scroller && scroller.querySelector('.cl-stn.now');
  if (now && scroller.scrollWidth > scroller.clientWidth) scroller.scrollLeft = Math.max(0, now.offsetLeft - scroller.clientWidth / 2 + now.offsetWidth / 2);

  document.addEventListener('submit', function (e) {
    var f = e.target.closest('.cl-tickf');
    if (!f) return;
    var row = f.closest('.cl-row'), btn = f.querySelector('button');
    if (btn.disabled) { e.preventDefault(); return; }
    btn.disabled = true;
    if (row) { row.classList.add('is-done', 'is-just'); var st = row.querySelector('.cl-state'); if (st) { st.className = 'cl-state done'; st.textContent = 'Done'; } }
  });
})();
