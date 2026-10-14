/* Admin Leads: scrambling counters, the glitch on the waiting number, the leads-per-day chart drawn by a
   glowing pen, the week sparkline, message templates, and opening/closing a lead's panel. */
(function () {
  const root = document.querySelector('[data-lx]');
  if (!root) return;
  const raw = document.getElementById('lx-data');
  const D = raw ? JSON.parse(raw.textContent) : { daily: [], dates: [] };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
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

  /* ---------- counters scramble, then lock in left to right ---------- */
  function scramble(el, delay) {
    const target = fmt(+el.dataset.scramble);
    el.dataset.final = target;
    if (reduce) { el.textContent = target; return; }
    const chars = target.split('');
    el.textContent = chars.map(c => /\d/.test(c) ? '0' : c).join('');
    setTimeout(() => {
      const t0 = performance.now();
      (function f(now) {
        const p = Math.min(1, (now - t0) / 900), locked = Math.floor(easeInOut(p) * (chars.length + 1));
        el.textContent = chars.map((c, i) => !/\d/.test(c) || i < locked ? c : String(Math.floor(Math.random() * 10))).join('');
        if (p < 1) requestAnimationFrame(f); else { el.textContent = target; if (el.hasAttribute('data-glitch')) el.classList.add('glitch'); }
      })(t0);
    }, delay);
  }
  root.querySelectorAll('[data-scramble]').forEach(el => {
    const sec = el.closest('[style*="--i"]');
    const i = sec ? (+getComputedStyle(sec).getPropertyValue('--i') || 0) : 0;
    scramble(el, i * 110 + 360);
  });

  /* ---------- leads per day: drawn by a glowing lime pen ---------- */
  function chart(animate) {
    const host = root.querySelector('[data-chart]');
    if (!host || !host.clientWidth) return;
    const d = D.daily || [], dates = D.dates || [], n = d.length;
    if (!n) return;
    const W = host.clientWidth, H = host.clientHeight, L = 30, R = 10, T = 8, B = 24, iw = W - L - R, ih = H - T - B;
    const top = Math.max(...d), steps = top <= 4 ? 4 : top <= 8 ? 4 : 5;
    const stepV = Math.max(1, Math.ceil(Math.max(top, 4) / steps)), max = stepV * steps;
    const x = i => L + (n === 1 ? iw / 2 : i / (n - 1) * iw), y = v => T + (1 - v / max) * ih;
    const P = curve(d.map((v, i) => [x(i), y(v)]));
    const label = s => { const [, mm, dd] = s.split('-').map(Number); return dd + ' ' + MON[mm - 1]; };
    let s = `<svg viewBox="0 0 ${W} ${H}"><defs>
      <linearGradient id="lxg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2b22ff" stop-opacity=".2"/><stop offset="1" stop-color="#2b22ff" stop-opacity="0"/></linearGradient>
      <filter id="lxglow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="3.5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
      <clipPath id="lxclip"><rect x="${L}" y="-6" height="${H + 12}" width="${reduce || !animate ? iw : 0}"/></clipPath></defs>`;
    for (let v = 0; v <= max; v += stepV) s += `<line x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" class="${v ? 'gl' : 'bl'}"/><text x="${L - 8}" y="${y(v) + 4}" text-anchor="end" class="axis">${v}</text>`;
    const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 80))));
    dates.forEach((dt, i) => { if ((n - 1 - i) % every) return; s += `<text x="${x(i)}" y="${H - 5}" text-anchor="${i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle'}" class="axis">${label(dt)}</text>`; });
    s += `<line class="cross" y1="${T}" y2="${T + ih}"/>
      <path d="${P}L${x(n - 1)},${y(0)}L${L},${y(0)}Z" fill="url(#lxg)" clip-path="url(#lxclip)"/>
      <path class="main" d="${P}" fill="none" stroke="#2b22ff" stroke-width="2.4" stroke-linecap="round"/>
      <circle class="pen" r="5" fill="#cdfb5e" stroke="#2b22ff" stroke-width="2" filter="url(#lxglow)" cx="${x(n - 1)}" cy="${y(d[n - 1])}"/>
      <circle class="hd" r="5" fill="#2b22ff" stroke="#fff" stroke-width="2"/>
      <rect class="hit" x="${L}" y="0" width="${iw}" height="${T + ih}" fill="transparent"/></svg><div class="tip"></div>`;
    host.innerHTML = s;
    const main = host.querySelector('.main'), pen = host.querySelector('.pen');
    if (animate && !reduce) {
      const len = main.getTotalLength(), rect = host.querySelector('#lxclip rect');
      main.style.strokeDasharray = len; main.style.strokeDashoffset = len;
      tween(1500, p => {
        main.style.strokeDashoffset = len * (1 - p);
        rect.setAttribute('width', iw * p);
        const pt = main.getPointAtLength(len * p);
        pen.setAttribute('cx', pt.x); pen.setAttribute('cy', pt.y);
      }, 900, easeInOut);
    }
    const svg = host.querySelector('svg'), cross = host.querySelector('.cross'), dot = host.querySelector('.hd'), tip = host.querySelector('.tip');
    let last = -1;
    const move = ev => {
      const px = (ev.touches ? ev.touches[0].clientX : ev.clientX) - svg.getBoundingClientRect().left;
      const i = Math.max(0, Math.min(n - 1, Math.round((px - L) / iw * (n - 1))));
      host.classList.add('hov');
      if (i === last) return;
      last = i;
      cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i));
      dot.setAttribute('cx', x(i)); dot.setAttribute('cy', y(d[i]));
      tip.innerHTML = `<h4>${label(dates[i])}</h4><b>${d[i]} lead${d[i] === 1 ? '' : 's'}</b>`;
      const tw = tip.offsetWidth, left = x(i) + 16 + tw > W ? x(i) - tw - 16 : x(i) + 16;
      tip.style.transform = `translate(${left}px, ${T}px)`;
    };
    const hit = host.querySelector('.hit');
    hit.addEventListener('mousemove', move);
    hit.addEventListener('touchmove', move, { passive: true });
    hit.addEventListener('mouseleave', () => { host.classList.remove('hov'); last = -1; });
    hit.addEventListener('touchend', () => setTimeout(() => host.classList.remove('hov'), 1200));
  }

  /* ---------- leads captured this week, as a lime line ---------- */
  function spark(animate) {
    const svg = root.querySelector('[data-spark]');
    if (!svg) return;
    const d = (D.daily || []).slice(-7);
    if (d.length < 2) return;
    const w = svg.clientWidth || 160, h = svg.clientHeight || 30, max = Math.max(1, ...d);
    const P = curve(d.map((v, i) => [i / (d.length - 1) * w, 3 + (1 - v / max) * (h - 6)]));
    svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
    svg.innerHTML = `<path d="${P}" fill="none" stroke="#cdfb5e" stroke-width="1.8" stroke-linecap="round"/>`;
    if (!animate || reduce) return;
    const p = svg.querySelector('path'), l = p.getTotalLength();
    p.style.strokeDasharray = l; p.style.strokeDashoffset = l;
    tween(1200, q => { p.style.strokeDashoffset = l * (1 - q); }, 900);
  }

  /* ---------- a lead's panel ---------- */
  const drawer = document.querySelector('[data-drawer]');
  if (drawer) {
    document.body.style.overflow = 'hidden';
    const scrim = document.querySelector('.lx-scrim');
    const closeUrl = scrim ? scrim.getAttribute('href') : null;
    const close = e => {
      if (e) e.preventDefault();
      if (reduce || !closeUrl) { location.href = closeUrl; return; }
      drawer.style.animation = 'none';
      void drawer.offsetWidth;
      drawer.classList.add('closing');
      if (scrim) scrim.classList.add('closing');
      setTimeout(() => { location.href = closeUrl; }, 420);
    };
    document.querySelectorAll('[data-close]').forEach(a => a.addEventListener('click', close));
    addEventListener('keydown', e => { if (e.key === 'Escape') close(e); });
    const box = document.getElementById('lxMessage');
    document.querySelectorAll('#lxMsg input[name=template]').forEach(r => r.addEventListener('change', () => { if (r.checked && box) box.value = r.dataset.text; }));
    document.querySelectorAll('#lxMsg [data-confirm]').forEach(b => b.addEventListener('click', e => { if (!confirm(b.dataset.confirm)) e.preventDefault(); }));
    // Opened from a "message" link: bring the composer into view inside the panel.
    if (location.hash === '#message') {
      const m = document.getElementById('message'), body = drawer.querySelector('.lx-d-body');
      if (m && body) setTimeout(() => body.scrollTo({ top: m.offsetTop - 16, behavior: reduce ? 'auto' : 'smooth' }), reduce ? 0 : 700);
    }
    const first = drawer.querySelector('.lx-d-close');
    if (first) first.focus({ preventScroll: true });
  }

  function boot() { chart(!drawer); spark(!drawer); }
  (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(boot);
  let t, lastW = root.clientWidth;
  addEventListener('resize', () => {
    clearTimeout(t);
    t = setTimeout(() => { if (Math.abs(root.clientWidth - lastW) < 8) return; lastW = root.clientWidth; chart(false); spark(false); }, 150);
  });
})();
