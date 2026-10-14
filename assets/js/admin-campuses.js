/* Admin Campuses (launch control): scrambling console figures, the ledger filter, workspace tabs and
   the 12-month chart for dashboard/admin/campuses.php. The reactor and rows animate in CSS. */
(function () {
  const root = document.querySelector('[data-acx]');
  if (!root) return;
  const raw = document.getElementById('acx-data');
  const D = raw ? JSON.parse(raw.textContent) : { series: [] };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const NS = 'http://www.w3.org/2000/svg';
  const C = { brand: '#2b22ff', yearly: '#6aa81e', monthly: '#b9bbd0', panel: '#ffffff' };
  const fmt = n => Math.round(n).toLocaleString('en-US');
  const short = n => n >= 1e6 ? +(n / 1e6).toFixed(n >= 1e7 ? 1 : 2) + 'M' : n >= 1e3 ? Math.round(n / 1e3) + 'K' : String(Math.round(n));
  const ease = t => t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
  const easeInOut = t => t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
  let uid = 0;
  function tween(dur, step, delay) {
    if (reduce) { step(1); return; }
    setTimeout(() => {
      const t0 = performance.now();
      (function f(now) { const p = Math.min(1, (now - t0) / dur); step(ease(p)); if (p < 1) requestAnimationFrame(f); })(t0);
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
  const nice = v => { const e = Math.pow(10, Math.floor(Math.log10(v))), f = v / e; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * e; };

  /* ---------- console figures scramble, then lock in left to right ---------- */
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

  /* ---------- ledger: all, open or draft campuses; the shown rows whip back in ---------- */
  const chips = root.querySelector('[data-filter-group]');
  if (chips) chips.querySelectorAll('button').forEach(btn => btn.addEventListener('click', () => {
    chips.querySelectorAll('button').forEach(b => b.classList.toggle('on', b === btn));
    let n = 0;
    root.querySelectorAll('.lc-row').forEach(row => {
      const show = btn.dataset.filter === 'all' || row.dataset.state === btn.dataset.filter;
      row.classList.toggle('hide', !show);
      row.classList.remove('swap');
      if (show) { void row.offsetWidth; row.style.setProperty('--n', n++); row.classList.add('swap'); }
    });
  }));

  /* ---------- workspace tabs ---------- */
  const tabs = root.querySelector('[data-tabs]'), bar = tabs.querySelector('.acx-bar');
  function placeBar(instant) {
    const b = tabs.querySelector('button.on');
    if (instant) bar.style.transition = 'none';
    bar.style.width = b.offsetWidth + 'px';
    bar.style.transform = `translateX(${b.offsetLeft}px)`;
    if (instant) { void bar.offsetWidth; bar.style.transition = ''; }
  }
  tabs.querySelectorAll('button').forEach(btn => btn.addEventListener('click', () => {
    tabs.querySelectorAll('button').forEach(b => b.classList.toggle('on', b === btn));
    placeBar();
    root.querySelectorAll('[data-panel]').forEach(p => p.classList.toggle('on', p.dataset.panel === btn.dataset.tab));
    if (btn.dataset.tab === 'money') moneyChart(true);
  }));

  /* ---------- 12-month chart: all plans, yearly, monthly ---------- */
  function moneyChart(animate) {
    const host = root.querySelector('[data-chart]');
    if (!host || !host.clientWidth) return;
    const S = D.series || [];
    const n = S.length;
    if (!n) return;
    const yr = S.map(s => s.y), mo = S.map(s => s.m), all = S.map(s => s.y + s.m);
    const W = host.clientWidth, H = host.clientHeight, L = 50, R = 10, T = 10, B = 26, iw = W - L - R, ih = H - T - B;
    const topV = Math.max(...all), empty = topV <= 0;
    const step = nice((empty ? 1000000 : topV * 1.05) / 4), max = Math.ceil((empty ? 1000000 : topV * 1.05) / step) * step;
    const x = i => L + (n === 1 ? iw / 2 : i / (n - 1) * iw), y = v => T + (1 - v / max) * ih;
    const P = a => curve(a.map((v, i) => [x(i), y(v)]));
    const gid = 'acxg' + (++uid), cid = 'acxc' + (++uid);
    let s = `<svg viewBox="0 0 ${W} ${H}" xmlns="${NS}"><defs><linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${C.brand}" stop-opacity=".16"/><stop offset="1" stop-color="${C.brand}" stop-opacity="0"/></linearGradient>
      <clipPath id="${cid}"><rect x="${L - 4}" y="-6" height="${H + 12}" width="${reduce || !animate ? iw + 14 : 0}"/></clipPath></defs>`;
    for (let v = 0, k = 0; v <= max + 1e-6; v += step, k++) {
      s += `<line x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" class="${k ? 'gl' : 'bl'}"/><text x="${L - 10}" y="${y(v) + 4}" text-anchor="end" class="axis">${v ? short(v) : '0'}</text>`;
    }
    S.forEach((p, i) => { s += `<text x="${x(i)}" y="${H - 6}" text-anchor="${i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle'}" class="axis">${p.label}</text>`; });
    s += `<line class="cross" y1="${T}" y2="${T + ih}"/><g clip-path="url(#${cid})">`;
    if (!empty) s += `<path d="${P(all)}L${x(n - 1)},${y(0)}L${L},${y(0)}Z" fill="url(#${gid})"/><path d="${P(mo)}" fill="none" stroke="${C.monthly}" stroke-width="1.8" stroke-dasharray="4 4"/><path d="${P(yr)}" fill="none" stroke="${C.yearly}" stroke-width="2"/>`;
    s += `<path d="${P(all)}" fill="none" stroke="${C.brand}" stroke-width="2.4" stroke-linecap="round" stroke-opacity="${empty ? 0.3 : 1}"/></g>`;
    if (!empty) s += `<circle cx="${x(n - 1)}" cy="${y(all[n - 1])}" r="4.5" fill="${C.panel}" stroke="${C.brand}" stroke-width="2"/><circle class="hd" r="4.5" fill="${C.brand}" stroke="${C.panel}" stroke-width="2"/><rect class="hit" x="${L}" y="0" width="${iw}" height="${T + ih}" fill="transparent"/>`;
    s += `</svg>` + (empty ? '<div class="note">No campus payments yet</div>' : '<div class="tip"></div>');
    host.innerHTML = s;
    const rect = host.querySelector('clipPath rect');
    if (animate && !reduce) tween(1300, p => rect.setAttribute('width', (iw + 14) * p), 150);
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
      dot.setAttribute('cx', x(i)); dot.setAttribute('cy', y(all[i]));
      tip.innerHTML = `<h4>${S[i].full || S[i].label}</h4>
        <div><span><i style="background:${C.brand}"></i>All plans</span><b>UGX ${fmt(all[i])}</b></div>
        <div><span><i style="background:${C.yearly}"></i>Yearly</span><b>UGX ${fmt(yr[i])}</b></div>
        <div><span><i style="background:${C.monthly}"></i>Monthly</span><b>UGX ${fmt(mo[i])}</b></div>`;
      const tw = tip.offsetWidth, left = x(i) + 16 + tw > W ? x(i) - tw - 16 : x(i) + 16;
      tip.style.transform = `translate(${left}px, ${T}px)`;
    };
    const hit = host.querySelector('.hit');
    hit.addEventListener('mousemove', move);
    hit.addEventListener('touchmove', move, { passive: true });
    hit.addEventListener('mouseleave', () => { host.classList.remove('hov'); last = -1; });
    hit.addEventListener('touchend', () => setTimeout(() => host.classList.remove('hov'), 1200));
  }

  function boot() {
    placeBar(true);
    moneyChart(true);
  }
  (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(boot);
  let t, lastW = root.clientWidth;
  addEventListener('resize', () => {
    clearTimeout(t);
    t = setTimeout(() => {
      placeBar(true);
      if (Math.abs(root.clientWidth - lastW) < 8) return;
      lastW = root.clientWidth;
      moneyChart(false);
    }, 150);
  });
})();
