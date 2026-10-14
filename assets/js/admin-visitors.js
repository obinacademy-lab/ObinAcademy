/* Admin Visitors: scrambling numbers, the daily chart drawn by a glowing pen, the device ring, the
   browser/system tabs and the range switch for dashboard/admin/analytics.php. */
(function () {
  const root = document.querySelector('[data-vx]');
  if (!root) return;
  const raw = document.getElementById('vx-data');
  const D = raw ? JSON.parse(raw.textContent) : { series: [], ring: [] };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const C = { brand: '#2b22ff', visits: '#6aa81e', panel: '#ffffff' };
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const fmt = n => Math.round(n).toLocaleString('en-US');
  const easeOut = t => t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
  const easeInOut = t => t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
  function tween(dur, step, delay, ease) {
    if (reduce) { step(1); return; }
    setTimeout(() => {
      const t0 = performance.now();
      (function f(now) { const p = Math.min(1, (now - t0) / dur); step((ease || easeOut)(p)); if (p < 1) requestAnimationFrame(f); })(t0);
    }, delay || 0);
  }
  /* Monotone cubic curve: smooth, and never bulges past the real values. */
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
  // Visitor counts are whole numbers, so the axis steps are 1, 2 or 5 times a power of ten.
  const nice = v => { if (v <= 1) return 1; const e = Math.pow(10, Math.floor(Math.log10(v))), f = v / e; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * e; };

  /* ---------- numbers scramble, then lock in left to right ---------- */
  function scramble(el, delay) {
    const target = fmt(+el.dataset.scramble);
    if (reduce) { el.textContent = target; return; }
    const chars = target.split('');
    el.textContent = chars.map(c => /\d/.test(c) ? '0' : c).join('');
    const dur = 900;
    setTimeout(() => {
      const t0 = performance.now();
      (function f(now) {
        const p = Math.min(1, (now - t0) / dur);
        const locked = Math.floor(easeInOut(p) * (chars.length + 1));
        el.textContent = chars.map((c, i) => !/\d/.test(c) || i < locked ? c : String(Math.floor(Math.random() * 10))).join('');
        if (p < 1) requestAnimationFrame(f); else el.textContent = target;
      })(t0);
    }, delay);
  }
  root.querySelectorAll('[data-scramble]').forEach(el => {
    const sec = el.closest('[style*="--i"]');
    const i = sec ? +getComputedStyle(sec).getPropertyValue('--i') || 0 : 0;
    scramble(el, i * 110 + 380);
  });

  /* ---------- daily chart: unique visitors drawn by a glowing pen, visits dashed behind ---------- */
  function chart(animate) {
    const host = root.querySelector('[data-chart]');
    if (!host || !host.clientWidth) return;
    const S = D.series || [];
    const n = S.length;
    if (!n) return;
    const u = S.map(s => s.u), v = S.map(s => s.v);
    const W = host.clientWidth, H = host.clientHeight, L = 42, R = 12, T = 12, B = 26, iw = W - L - R, ih = H - T - B;
    const topV = Math.max(...v, ...u), empty = topV <= 0;
    const step = nice((empty ? 20 : topV * 1.08) / 4), max = Math.ceil((empty ? 20 : topV * 1.08) / step) * step;
    const x = i => L + (n === 1 ? iw / 2 : i / (n - 1) * iw), y = val => T + (1 - val / max) * ih;
    const P = a => curve(a.map((val, i) => [x(i), y(val)]));
    const label = d => { const [yy, mm, dd] = d.split('-').map(Number); return dd + ' ' + MON[mm - 1]; };
    let s = `<svg viewBox="0 0 ${W} ${H}"><defs>
      <linearGradient id="vxg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${C.brand}" stop-opacity=".2"/><stop offset="1" stop-color="${C.brand}" stop-opacity="0"/></linearGradient>
      <filter id="vxglow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="4" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
      <clipPath id="vxclip"><rect x="${L - 4}" y="-8" height="${H + 16}" width="${reduce || !animate ? iw + 16 : 0}"/></clipPath></defs>`;
    for (let val = 0, k = 0; val <= max + 1e-6; val += step, k++) {
      s += `<line x1="${L}" x2="${W - R}" y1="${y(val)}" y2="${y(val)}" class="${k ? 'gl' : 'bl'}"/><text x="${L - 10}" y="${y(val) + 4}" text-anchor="end" class="axis">${fmt(val)}</text>`;
    }
    const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 80))));
    S.forEach((p, i) => { if ((n - 1 - i) % every) return; s += `<text x="${x(i)}" y="${H - 6}" text-anchor="${i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle'}" class="axis">${label(p.d)}</text>`; });
    s += `<line class="cross" y1="${T}" y2="${T + ih}"/>`;
    s += `<g clip-path="url(#vxclip)">${empty ? '' : `<path d="${P(u)}L${x(n - 1)},${y(0)}L${L},${y(0)}Z" fill="url(#vxg)"/><path d="${P(v)}" fill="none" stroke="${C.visits}" stroke-width="1.8" stroke-dasharray="4 4"/>`}</g>`;
    s += `<path class="main" d="${P(u)}" fill="none" stroke="${C.brand}" stroke-width="2.6" stroke-linecap="round" stroke-opacity="${empty ? .3 : 1}"/>`;
    if (!empty) {
      s += `<circle class="pen" r="6" fill="${C.brand}" filter="url(#vxglow)" opacity="0"/>
        <circle class="shock" cx="${x(n - 1)}" cy="${y(u[n - 1])}" r="4" fill="none" stroke="${C.brand}" stroke-width="2" opacity="0"/>
        <circle class="end" cx="${x(n - 1)}" cy="${y(u[n - 1])}" r="4.5" fill="${C.panel}" stroke="${C.brand}" stroke-width="2.2" opacity="${reduce || !animate ? 1 : 0}"/>
        <circle class="hd" r="5" fill="${C.brand}" stroke="${C.panel}" stroke-width="2"/>
        <rect class="hit" x="${L}" y="0" width="${iw}" height="${T + ih}" fill="transparent"/>`;
    }
    s += `</svg>` + (empty ? '<div class="note">No visits recorded in this period yet</div>' : '<div class="tip"></div>');
    host.innerHTML = s;

    const main = host.querySelector('.main');
    if (animate && !reduce) {
      const len = main.getTotalLength(), pen = host.querySelector('.pen'), rect = host.querySelector('#vxclip rect');
      main.style.strokeDasharray = len; main.style.strokeDashoffset = len;
      tween(1500, p => {
        main.style.strokeDashoffset = len * (1 - p);
        rect.setAttribute('width', (iw + 16) * p);
        if (pen) {
          const pt = main.getPointAtLength(len * p);
          pen.setAttribute('cx', pt.x); pen.setAttribute('cy', pt.y);
          pen.setAttribute('opacity', p < 1 ? 1 : 0);
        }
        if (p === 1 && !empty) {
          host.querySelector('.end').setAttribute('opacity', 1);
          const sh = host.querySelector('.shock');
          tween(700, q => { sh.setAttribute('r', 4 + q * 22); sh.setAttribute('opacity', (1 - q) * .8); }, 0);
        }
      }, 650, easeInOut);
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
      dot.setAttribute('cx', x(i)); dot.setAttribute('cy', y(u[i]));
      const [yy, mm, dd] = S[i].d.split('-').map(Number);
      tip.innerHTML = `<h4>${new Date(yy, mm - 1, dd).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}</h4>
        <div><span><i style="background:${C.brand}"></i>Unique visitors</span><b>${fmt(u[i])}</b></div>
        <div><span><i style="background:${C.visits}"></i>Visits</span><b>${fmt(v[i])}</b></div>`;
      const tw = tip.offsetWidth, left = x(i) + 18 + tw > W ? x(i) - tw - 18 : x(i) + 18;
      tip.style.transform = `translate(${left}px, ${T}px)`;
    };
    const hit = host.querySelector('.hit');
    hit.addEventListener('mousemove', move);
    hit.addEventListener('touchmove', move, { passive: true });
    hit.addEventListener('mouseleave', () => { host.classList.remove('hov'); last = -1; });
    hit.addEventListener('touchend', () => setTimeout(() => host.classList.remove('hov'), 1200));
  }

  /* ---------- device ring: whips round on a spring while each segment draws ---------- */
  function ring() {
    const svg = root.querySelector('[data-ring]');
    if (!svg) return;
    const R = 62, Cc = 2 * Math.PI * R, parts = (D.ring || []).filter(p => p.p > 0);
    let off = 0, s = `<circle cx="75" cy="75" r="${R}" fill="none" stroke="#eff0f5" stroke-width="14"/>`;
    parts.forEach((p, k) => {
      const len = Math.max(0, p.p * Cc - (parts.length > 1 ? 3 : 0));
      s += `<circle cx="75" cy="75" r="${R}" fill="none" stroke="${p.c}" stroke-width="14" stroke-dasharray="${reduce ? len : 0} ${Cc}" stroke-dashoffset="${-off}" data-len="${len}" data-k="${k}"/>`;
      off += p.p * Cc;
    });
    svg.innerHTML = s;
    if (reduce) return;
    svg.style.transition = 'none';
    svg.style.transform = 'rotate(-450deg) scale(.7)';
    void svg.getBoundingClientRect();
    setTimeout(() => {
      svg.style.transition = 'transform 1.4s cubic-bezier(.2, 1.3, .3, 1)';
      svg.style.transform = 'rotate(-90deg) scale(1)';
    }, 700);
    svg.querySelectorAll('[data-len]').forEach(c => {
      const len = +c.dataset.len;
      tween(900, p => c.setAttribute('stroke-dasharray', `${len * p} ${Cc}`), 750 + c.dataset.k * 200);
    });
  }

  /* ---------- sliding markers: range pill and the browser/system tab bar ---------- */
  function slide(group, marker, sel, instant) {
    const on = group.querySelector(sel);
    if (!on || !marker) return;
    if (instant) marker.style.transition = 'none';
    marker.style.width = on.offsetWidth + 'px';
    marker.style.transform = `translateX(${on.offsetLeft}px)`;
    if (instant) { void marker.offsetWidth; marker.style.transition = ''; }
  }
  const seg = root.querySelector('.vx-seg'), pill = seg.querySelector('.vx-pill');
  seg.querySelectorAll('a').forEach(a => a.addEventListener('click', e => {
    if (a.classList.contains('on') || e.metaKey || e.ctrlKey || e.shiftKey) return;
    e.preventDefault();
    seg.querySelectorAll('a').forEach(x => x.classList.toggle('on', x === a));
    slide(seg, pill, 'a.on');
    if (reduce) { location.href = a.href; return; }
    root.classList.add('leaving');
    setTimeout(() => { location.href = a.href; }, 420);
  }));
  const tabs = root.querySelector('[data-tabs]'), tbar = tabs && tabs.querySelector('.vx-tbar');
  if (tabs) tabs.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
    tabs.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
    slide(tabs, tbar, 'button.on');
    root.querySelectorAll('[data-pane]').forEach(p => {
      const on = p.dataset.pane === b.dataset.tab;
      p.classList.toggle('on', on);
      if (on) p.querySelectorAll('.vx-snap, .vx-rk').forEach(el => { el.style.animation = 'none'; void el.offsetWidth; el.style.animation = ''; });
    });
  }));
  // Coming back with the browser's back button can restore the page mid-exit.
  addEventListener('pageshow', e => { if (e.persisted) root.classList.remove('leaving'); });

  function boot() {
    slide(seg, pill, 'a.on', true);
    if (tabs) slide(tabs, tbar, 'button.on', true);
    chart(true);
    ring();
  }
  (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(boot);
  let t, lastW = root.clientWidth;
  addEventListener('resize', () => {
    clearTimeout(t);
    t = setTimeout(() => {
      slide(seg, pill, 'a.on', true);
      if (tabs) slide(tabs, tbar, 'button.on', true);
      if (Math.abs(root.clientWidth - lastW) < 8) return;
      lastW = root.clientWidth;
      chart(false);
    }, 150);
  });
})();
