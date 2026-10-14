/* Admin Revenue: scrambling figures and the dark 30-day chart (collected and kept, drawn by a glowing
   pen) for dashboard/admin/revenue.php. */
(function () {
  const root = document.querySelector('[data-rv]');
  if (!root) return;
  const raw = document.getElementById('rv-data');
  const D = raw ? JSON.parse(raw.textContent) : { days: [] };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const LIME = '#cdfb5e', KEPT = 'rgba(255,255,255,.75)';
  const fmt = n => Math.round(n).toLocaleString('en-US');
  const short = n => n >= 1e6 ? +(n / 1e6).toFixed(n >= 1e7 ? 1 : 2) + 'M' : n >= 1e3 ? Math.round(n / 1e3) + 'K' : String(Math.round(n));
  const easeInOut = t => t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
  function tween(dur, step, delay) {
    if (reduce) { step(1); return; }
    setTimeout(() => {
      const t0 = performance.now();
      (function f(now) { const p = Math.min(1, (now - t0) / dur); step(easeInOut(p)); if (p < 1) requestAnimationFrame(f); })(t0);
    }, delay || 0);
  }
  function curve(p) {
    const n = p.length;
    if (n === 1) return `M${p[0][0]},${p[0][1]}`;
    const dx = [], m = [], t = [];
    for (let i = 0; i < n - 1; i++) { dx[i] = p[i + 1][0] - p[i][0]; m[i] = (p[i + 1][1] - p[i][1]) / dx[i]; }
    t[0] = m[0]; t[n - 1] = m[n - 2];
    for (let i = 1; i < n - 1; i++) t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2;
    for (let i = 0; i < n - 1; i++) {
      if (m[i] === 0) { t[i] = t[i + 1] = 0; continue; }
      const a = t[i] / m[i], b = t[i + 1] / m[i], s = a * a + b * b;
      if (s > 9) { const k = 3 / Math.sqrt(s); t[i] = k * a * m[i]; t[i + 1] = k * b * m[i]; }
    }
    let d = `M${p[0][0]},${p[0][1]}`;
    for (let i = 0; i < n - 1; i++) {
      const h = dx[i] / 3;
      d += `C${p[i][0] + h},${p[i][1] + t[i] * h} ${p[i + 1][0] - h},${p[i + 1][1] - t[i + 1] * h} ${p[i + 1][0]},${p[i + 1][1]}`;
    }
    return d;
  }
  const nice = v => { const e = Math.pow(10, Math.floor(Math.log10(v))), f = v / e; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * e; };

  /* figures scramble, then lock in left to right */
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

  /* collected (lime, filled) and kept (white) per day */
  function chart(animate) {
    const host = root.querySelector('[data-chart]');
    if (!host || !host.clientWidth) return;
    const S = D.days || [], n = S.length;
    if (!n) return;
    const c = S.map(s => s.c), k = S.map(s => Math.max(0, s.c - s.p));
    const W = host.clientWidth, H = host.clientHeight, L = 44, R = 10, T = 10, B = 24, iw = W - L - R, ih = H - T - B;
    const top = Math.max(...c), empty = top <= 0;
    const step = nice((empty ? 400000 : top * 1.08) / 4), max = Math.ceil((empty ? 400000 : top * 1.08) / step) * step;
    const x = i => L + i / (n - 1) * iw, y = v => T + (1 - v / max) * ih;
    const P = a => curve(a.map((v, i) => [x(i), y(v)]));
    const label = s => { const [, mm, dd] = s.split('-').map(Number); return dd + ' ' + MON[mm - 1]; };
    let s = `<svg viewBox="0 0 ${W} ${H}"><defs>
      <linearGradient id="rvg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${LIME}" stop-opacity=".25"/><stop offset="1" stop-color="${LIME}" stop-opacity="0"/></linearGradient>
      <filter id="rvglow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="4" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
      <clipPath id="rvclip"><rect x="${L - 4}" y="-8" height="${H + 16}" width="${reduce || !animate ? iw + 16 : 0}"/></clipPath></defs>`;
    for (let v = 0, j = 0; v <= max + 1e-6; v += step, j++) {
      s += `<line x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" class="${j ? 'gl' : 'bl'}"/><text x="${L - 8}" y="${y(v) + 4}" text-anchor="end" class="axis">${v ? short(v) : '0'}</text>`;
    }
    [0, 7, 14, 21, n - 1].forEach(i => { if (i < n) s += `<text x="${x(i)}" y="${H - 5}" text-anchor="${i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle'}" class="axis">${label(S[i].d)}</text>`; });
    s += `<line class="cross" y1="${T}" y2="${T + ih}"/><g clip-path="url(#rvclip)">`;
    if (!empty) s += `<path d="${P(c)}L${x(n - 1)},${y(0)}L${L},${y(0)}Z" fill="url(#rvg)"/><path d="${P(k)}" fill="none" stroke="${KEPT}" stroke-width="1.6" stroke-dasharray="4 4"/>`;
    s += `</g><path class="main" d="${P(c)}" fill="none" stroke="${LIME}" stroke-width="2.4" stroke-linecap="round" stroke-opacity="${empty ? .3 : 1}"/>`;
    if (!empty) s += `<circle class="pen" r="5.5" fill="${LIME}" filter="url(#rvglow)" cx="${x(n - 1)}" cy="${y(c[n - 1])}"/><circle class="hd" r="5" fill="${LIME}" stroke="#07081f" stroke-width="2"/><rect class="hit" x="${L}" y="0" width="${iw}" height="${T + ih}" fill="transparent"/>`;
    s += `</svg>` + (empty ? '<div class="note">No campus payments in the last 30 days</div>' : '<div class="tip"></div>');
    host.innerHTML = s;
    const main = host.querySelector('.main'), pen = host.querySelector('.pen'), rect = host.querySelector('#rvclip rect');
    if (animate && !reduce) {
      const len = main.getTotalLength();
      main.style.strokeDasharray = len; main.style.strokeDashoffset = len;
      tween(1500, p => {
        main.style.strokeDashoffset = len * (1 - p);
        rect.setAttribute('width', (iw + 16) * p);
        if (pen) { const pt = main.getPointAtLength(len * p); pen.setAttribute('cx', pt.x); pen.setAttribute('cy', pt.y); }
      }, 700);
    }
    if (empty) return;
    const svg = host.querySelector('svg'), cross = host.querySelector('.cross'), dot = host.querySelector('.hd'), tip = host.querySelector('.tip');
    let last = -1;
    const move = ev => {
      const px = (ev.touches ? ev.touches[0].clientX : ev.clientX) - svg.getBoundingClientRect().left;
      const i = Math.max(0, Math.min(n - 1, Math.round((px - L) / iw * (n - 1))));
      host.classList.add('hov');
      if (i === last) return;
      last = i;
      cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i));
      dot.setAttribute('cx', x(i)); dot.setAttribute('cy', y(c[i]));
      tip.innerHTML = `<h4>${label(S[i].d)}</h4><div><span>Collected</span><b>UGX ${fmt(c[i])}</b></div><div><span>Professors</span><b>UGX ${fmt(S[i].p)}</b></div><div><span>Kept</span><b>UGX ${fmt(k[i])}</b></div>`;
      const tw = tip.offsetWidth, left = x(i) + 16 + tw > W ? x(i) - tw - 16 : x(i) + 16;
      tip.style.transform = `translate(${left}px, ${T}px)`;
    };
    const hit = host.querySelector('.hit');
    hit.addEventListener('mousemove', move);
    hit.addEventListener('touchmove', move, { passive: true });
    hit.addEventListener('mouseleave', () => { host.classList.remove('hov'); last = -1; });
    hit.addEventListener('touchend', () => setTimeout(() => host.classList.remove('hov'), 1200));
  }
  (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(() => chart(true));
  let t, lastW = root.clientWidth;
  addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => { if (Math.abs(root.clientWidth - lastW) < 8) return; lastW = root.clientWidth; chart(false); }, 150); });
})();
