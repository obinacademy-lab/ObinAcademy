/* Admin Login activity: scrambling counters and the heatmap tooltip for dashboard/admin/logins.php.
   The bars, squares and rows animate in CSS (admin-logins.css). */
(function () {
  const root = document.querySelector('[data-gx]');
  if (!root) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fmt = n => Math.round(n).toLocaleString('en-US');
  const easeInOut = t => t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

  /* counters scramble through random digits, then lock in left to right */
  root.querySelectorAll('[data-scramble]').forEach((el, k) => {
    const target = fmt(+el.dataset.scramble);
    if (reduce) { el.textContent = target; return; }
    const chars = target.split('');
    el.textContent = chars.map(c => /\d/.test(c) ? '0' : c).join('');
    setTimeout(() => {
      const t0 = performance.now();
      (function f(now) {
        const p = Math.min(1, (now - t0) / 900), locked = Math.floor(easeInOut(p) * (chars.length + 1));
        el.textContent = chars.map((c, i) => !/\d/.test(c) || i < locked ? c : String(Math.floor(Math.random() * 10))).join('');
        if (p < 1) requestAnimationFrame(f); else el.textContent = target;
      })(t0);
    }, 380 + k * 60);
  });

  /* heatmap: a tooltip that follows the pointer */
  const heat = root.querySelector('[data-heat]'), tip = document.querySelector('[data-tip]');
  if (heat && tip) {
    heat.addEventListener('mousemove', e => {
      const cell = e.target.closest('i');
      if (!cell) { tip.style.opacity = 0; return; }
      tip.textContent = cell.dataset.t;
      tip.style.transform = `translate(${e.clientX + 12}px, ${e.clientY - 34}px)`;
      tip.style.opacity = 1;
    });
    heat.addEventListener('mouseleave', () => { tip.style.opacity = 0; });
  }
})();
