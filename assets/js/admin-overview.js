/* Admin Overview: charts, count-ups and the date-range switch for dashboard/admin/index.php.
   Reads the figures the page prints into #ov-data; draws everything as inline SVG. */
(function () {
  const root = document.querySelector('[data-ov]');
  const raw = document.getElementById('ov-data');
  if (!root || !raw) return;
  const D = JSON.parse(raw.textContent);
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const NS = 'http://www.w3.org/2000/svg';
  const DAY = 86400000;
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const C = { brand: '#2b22ff', kept: '#6aa81e', muted: '#8b8fa9', warn: '#b45f06', panel: '#ffffff' };
  let uid = 0;

  const el = (t, a) => { const e = document.createElementNS(NS, t); for (const k in a) e.setAttribute(k, a[k]); return e; };
  const fmt = n => Math.round(n).toLocaleString('en-US');
  const short = n => n >= 1e6 ? +(n / 1e6).toFixed(n >= 1e7 ? 1 : 2) + 'M' : n >= 1e3 ? +(n / 1e3).toFixed(n >= 1e4 ? 0 : 1) + 'K' : String(Math.round(n));
  const ease = t => t === 1 ? 1 : 1 - Math.pow(2, -10 * t); // easeOutExpo
  function tween(dur, step, done) {
    if (reduce || dur <= 0) { step(1); done && done(); return; }
    const t0 = performance.now();
    (function f(now) { const p = Math.min(1, (now - t0) / dur); step(ease(p)); p < 1 ? requestAnimationFrame(f) : done && done(); })(t0);
  }

  /* ---------- the data, one entry per day ---------- */
  const parse = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
  const key = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const start = parse(D.from), today = parse(D.today);
  const days = [];
  for (let d = new Date(start); d <= today; d.setDate(d.getDate() + 1)) {
    const m = D.money[key(d)] || [0, 0, 0];
    days.push({ date: new Date(d), c: m[0], p: m[1], n: m[2] });
  }
  const N = days.length;
  const members = D.members || [];
  const activeOn = t => { let a = 0, y = 0; for (const m of members) if (m[0] * 1000 <= t && m[1] * 1000 > t) { a++; y += m[2]; } return [a, y]; };
  const joinedBetween = (a, b) => members.filter(m => m[0] * 1000 >= a && m[0] * 1000 < b).length;

  function windowSums(len, offset) {
    let c = 0, p = 0, n = 0;
    for (let i = Math.max(0, N - len - offset); i < N - offset; i++) { c += days[i].c; p += days[i].p; n += days[i].n; }
    return { c, p, k: Math.max(0, c - p), n };
  }

  /* Chart points for a range: daily for 7 and 30 days, weekly for 90, calendar months for 12 months. */
  function buckets(len) {
    const slice = days.slice(N - len);
    const out = [];
    const day = d => d.getDate() + ' ' + MON[d.getMonth()];
    if (len <= 30) {
      slice.forEach(d => out.push({ label: day(d.date), full: d.date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }), c: d.c, p: d.p, n: d.n }));
    } else if (len <= 90) {
      for (let e = slice.length; e > 0; e -= 7) {
        const part = slice.slice(Math.max(0, e - 7), e), last = part[part.length - 1].date, first = part[0].date;
        out.unshift({ label: day(last), full: day(first) + ' – ' + day(last), c: sum(part, 'c'), p: sum(part, 'p'), n: sum(part, 'n') });
      }
    } else {
      const byMonth = new Map();
      slice.forEach(d => {
        const k = d.date.getFullYear() * 12 + d.date.getMonth();
        if (!byMonth.has(k)) byMonth.set(k, { label: MON[d.date.getMonth()], full: MON[d.date.getMonth()] + ' ' + d.date.getFullYear(), c: 0, p: 0, n: 0 });
        const b = byMonth.get(k); b.c += d.c; b.p += d.p; b.n += d.n;
      });
      out.push(...byMonth.values());
    }
    out.forEach(b => { b.k = Math.max(0, b.c - b.p); });
    return out;
  }
  const sum = (a, f) => a.reduce((s, x) => s + x[f], 0);

  /* ---------- curves ---------- */
  /* Monotone cubic (Fritsch–Carlson): smooth, and never bulges above or below the real values. */
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
  function gradient(svg, color, top) {
    const id = 'ovg' + (++uid), defs = el('defs', {}), g = el('linearGradient', { id, x1: 0, y1: 0, x2: 0, y2: 1 });
    g.append(el('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': top }), el('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': 0 }));
    defs.append(g); svg.append(defs);
    return `url(#${id})`;
  }
  /* Reveals a group left to right, like a pen drawing the curve. */
  function wipe(svg, group, x0, w, h, dur, delay) {
    const id = 'ovc' + (++uid), cp = el('clipPath', { id }), r = el('rect', { x: x0 - 6, y: -20, width: reduce ? w + 12 : 0, height: h + 40 });
    cp.append(r); svg.append(cp); group.setAttribute('clip-path', `url(#${id})`);
    if (!reduce) setTimeout(() => tween(dur, p => r.setAttribute('width', (w + 12) * p)), delay || 0);
  }

  /* ---------- sparklines ---------- */
  function spark(svg, data, color, delay) {
    svg.innerHTML = '';
    const w = svg.clientWidth || 260, h = svg.clientHeight || 58, pad = 6;
    svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
    const max = Math.max(...data), min = Math.min(...data), flat = max === min;
    const pts = data.map((v, i) => [data.length === 1 ? w : i / (data.length - 1) * w, flat ? h - pad - 2 : pad + (1 - (v - min) / (max - min)) * (h - pad * 2 - 4)]);
    const d = curve(pts), g = el('g', {});
    g.append(el('path', { d: d + `L${w},${h}L0,${h}Z`, fill: gradient(svg, color, flat ? 0.05 : 0.16) }));
    g.append(el('path', { d, fill: 'none', stroke: color, 'stroke-width': 1.75, 'stroke-linecap': 'round', 'stroke-opacity': flat ? 0.35 : 1 }));
    svg.append(g);
    wipe(svg, g, 0, w, h, 1100, delay);
  }

  /* ---------- full chart: axes, curves, hover ---------- */
  function chart(host, o) {
    const old = host.querySelector('.ov-layer');
    if (old && !reduce && o.animate) { old.classList.add('out'); setTimeout(() => old.remove(), 380); } else if (old) old.remove();
    host.querySelectorAll('.ov-tip, .ov-empty-note').forEach(x => x.remove());

    const W = host.clientWidth, H = host.clientHeight, L = 54, R = 10, T = 14, B = 30;
    const iw = W - L - R, ih = H - T - B, n = o.labels.length;
    const svg = el('svg', { viewBox: `0 0 ${W} ${H}`, class: 'ov-layer', style: 'position:absolute;inset:0' });
    host.append(svg);
    const top = Math.max(...o.series.flatMap(s => s.values));
    const empty = top <= 0;
    const step = nice((empty ? o.emptyMax : top * 1.05) / 4), max = Math.ceil((empty ? o.emptyMax : top * 1.05) / step) * step;
    const x = i => L + (n === 1 ? iw / 2 : i / (n - 1) * iw), y = v => T + (1 - v / max) * ih;

    for (let v = 0, k = 0; v <= max + 1e-9; v += step, k++) {
      svg.append(el('line', { x1: L, x2: W - R, y1: y(v), y2: y(v), class: k === 0 ? 'ov-base-line' : 'ov-grid-line' }));
      const t = el('text', { x: L - 12, y: y(v) + 4, 'text-anchor': 'end', class: 'ov-axis' }); t.textContent = o.yfmt(v); svg.append(t);
    }
    const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 76))));
    o.labels.forEach((lb, i) => {
      if ((n - 1 - i) % every) return;
      const t = el('text', { x: x(i), y: H - 8, 'text-anchor': i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle', class: 'ov-axis' });
      t.textContent = lb; svg.append(t);
    });

    const cross = el('line', { y1: T, y2: T + ih, class: 'ov-cross' });
    svg.append(cross);
    const plot = el('g', {}), ends = el('g', {}), dots = [];
    o.series.forEach(s => {
      const pts = s.values.map((v, i) => [x(i), y(v)]), d = curve(pts);
      if (s.fill && !empty) plot.append(el('path', { d: d + `L${x(n - 1)},${y(0)}L${x(0)},${y(0)}Z`, fill: gradient(svg, s.color, s.fill) }));
      plot.append(el('path', { d, fill: 'none', stroke: s.color, 'stroke-width': s.width || 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-opacity': empty ? 0.35 : 1 }));
      const e = pts[pts.length - 1];
      if (!empty) {
        const ring = el('circle', { cx: e[0], cy: e[1], r: 4, fill: s.color, class: 'ov-end-ring' });
        const dot = el('circle', { cx: e[0], cy: e[1], r: 4, fill: C.panel, stroke: s.color, 'stroke-width': 2 });
        ends.append(ring, dot);
      }
      dots.push(el('circle', { r: 4.5, fill: s.color, stroke: C.panel, 'stroke-width': 2, class: 'ov-hover-dot' }));
    });
    svg.append(plot, ends);
    dots.forEach(d => svg.append(d));
    wipe(svg, plot, L, iw, H, 1300, o.delay || 0);
    if (!reduce) {
      ends.style.opacity = 0; ends.style.transition = 'opacity .5s ease';
      setTimeout(() => { ends.style.opacity = 1; }, (o.delay || 0) + 1050);
    }

    if (empty) {
      const note = document.createElement('div'); note.className = 'ov-empty-note'; note.textContent = o.emptyText; host.append(note);
      return;
    }
    const tip = document.createElement('div'); tip.className = 'ov-tip'; host.append(tip);
    const hit = el('rect', { x: L, y: 0, width: iw, height: T + ih, fill: 'transparent' });
    svg.append(hit);
    let last = -1;
    function move(ev) {
      const r = svg.getBoundingClientRect();
      const px = (ev.touches ? ev.touches[0].clientX : ev.clientX) - r.left;
      const i = Math.max(0, Math.min(n - 1, Math.round((px - L) / iw * (n - 1))));
      host.classList.add('hovering');
      if (i === last) return;
      last = i;
      const cx = x(i);
      cross.setAttribute('x1', cx); cross.setAttribute('x2', cx);
      o.series.forEach((s, k) => { dots[k].setAttribute('cx', cx); dots[k].setAttribute('cy', y(s.values[i])); });
      tip.innerHTML = o.tip(i);
      const tw = tip.offsetWidth, left = cx + 18 + tw > W ? cx - tw - 18 : cx + 18;
      tip.style.transform = `translate(${left}px, ${T + 4}px)`;
    }
    const leave = () => { host.classList.remove('hovering'); last = -1; };
    hit.addEventListener('mousemove', move);
    hit.addEventListener('touchstart', move, { passive: true });
    hit.addEventListener('touchmove', move, { passive: true });
    hit.addEventListener('mouseleave', leave);
    hit.addEventListener('touchend', () => setTimeout(leave, 1200));
  }

  /* ---------- KPIs ---------- */
  const shown = {};
  function count(elm, to, dur, delay) {
    const keyName = elm.dataset.kpi || elm.dataset.total || elm.dataset.count;
    const from = shown[keyName + elm.className] || 0, prefix = elm.dataset.prefix || '';
    shown[keyName + elm.className] = to;
    if (delay && !reduce) elm.textContent = prefix + fmt(from);
    setTimeout(() => tween(dur, p => { elm.textContent = prefix + fmt(from + (to - from) * p); }), delay || 0);
  }
  function chip(cur, prev, label) {
    if (prev <= 0) return cur > 0 ? `<span class="ov-chip up">New</span>${label}` : `<span class="ov-chip flat">0</span>nothing yet in this period`;
    const pct = (cur - prev) / prev * 100, cls = pct > 0.05 ? 'up' : pct < -0.05 ? 'down' : 'flat';
    return `<span class="ov-chip ${cls}">${pct > 0.05 ? '▲' : pct < -0.05 ? '▼' : ''} ${Math.abs(pct).toFixed(1)}%</span>${label}`;
  }
  const rangeName = len => len === 365 ? 'previous 12 months' : `previous ${len} days`;

  let range = 30, mode = 'total', first = true;
  const running = a => { let t = 0; return a.map(v => (t += v)); };
  function render(animate) {
    renderTotals(animate);
    drawMoney(animate);
  }
  function renderTotals() {
    const cur = windowSums(range, 0), prev = windowSums(range, range);
    const b = buckets(range);
    const q = s => root.querySelector(s);
    const delay = first ? 450 : 0, dur = first ? 1400 : 700;

    count(q('[data-kpi="collected"]'), cur.c, dur, delay);
    count(q('[data-kpi="kept"]'), cur.k, dur, delay);
    count(q('[data-kpi="members"]'), D.activeNow, dur, delay);
    q('[data-kpi-meta="collected"]').innerHTML = chip(cur.c, prev.c, 'vs ' + rangeName(range));
    q('[data-kpi-meta="kept"]').innerHTML = cur.c > 0 ? `<span class="ov-chip flat">${Math.round(cur.k / cur.c * 100)}%</span>of collections` : chip(cur.k, prev.k, 'vs ' + rangeName(range));
    const joined = joinedBetween(today.getTime() - (range - 1) * DAY, today.getTime() + DAY);
    q('[data-kpi-meta="members"]').innerHTML = `<span class="ov-chip ${joined ? 'up' : 'flat'}">${joined ? '▲ ' : ''}${joined}</span>joined in this period`;

    root.querySelectorAll('[data-total]').forEach(t => { t.dataset.prefix = t.dataset.total === 'payments' ? '' : 'UGX '; });
    count(q('[data-total="collected"]'), cur.c, dur, delay);
    count(q('[data-total="professors"]'), cur.p, dur, delay);
    count(q('[data-total="kept"]'), cur.k, dur, delay);
    count(q('[data-total="payments"]'), cur.n, dur, delay);

    // Sparks: money per bucket, members with access across the range.
    const sDelay = first ? 650 : 0;
    spark(q('[data-spark="collected"]'), running(b.map(x => x.c)), C.brand, sDelay);
    spark(q('[data-spark="kept"]'), running(b.map(x => x.k)), C.kept, sDelay + 80);
    const pts = Math.min(range, 30), mem = [];
    for (let i = pts - 1; i >= 0; i--) mem.push(activeOn(today.getTime() + DAY - 1 - Math.round(i * range / pts) * DAY)[0]);
    spark(q('[data-spark="members"]'), mem, C.brand, sDelay + 160);

  }

  /* The money chart: a running total by default (a smooth rising curve), or the amount per day/week/month. */
  function drawMoney(animate) {
    const b = buckets(range), tot = mode === 'total';
    const cc = running(b.map(x => x.c)), ck = running(b.map(x => x.k));
    const unit = range <= 30 ? 'day' : range <= 90 ? 'week' : 'month';
    chart(root.querySelector('[data-chart="money"]'), {
      animate, delay: first ? 600 : 80,
      labels: b.map(x => x.label),
      series: [{ values: tot ? cc : b.map(x => x.c), color: C.brand, fill: 0.18, width: 2.25 }, { values: tot ? ck : b.map(x => x.k), color: C.kept, fill: 0.05, width: 2 }],
      yfmt: v => v === 0 ? '0' : short(v),
      emptyMax: 1000000, emptyText: 'No campus payments in this period',
      tip: i => `<h4>${b[i].full}</h4>
        <div><span><i style="background:${C.brand}"></i>Collected</span><b>UGX ${fmt(b[i].c)}</b></div>
        <div><span><i style="background:${C.muted}"></i>Professors</span><b>UGX ${fmt(b[i].p)}</b></div>
        <div><span><i style="background:${C.kept}"></i>Kept</span><b>UGX ${fmt(b[i].k)}</b></div>
        <div><span>Payments</span><b>${fmt(b[i].n)}</b></div>` + (tot ? `
        <div class="ov-tip-sum"><span>Collected so far</span><b>UGX ${fmt(cc[i])}</b></div>` : '')
    });
    root.querySelector('[data-chart-note]').textContent = tot ? 'Running total over the period; hover for each ' + unit : 'Amount collected each ' + unit;
  }

  function staticCharts(animate) {
    // Renewals: memberships ending on each of the next 14 days.
    const ends = [];
    for (let i = 0; i < 14; i++) {
      const a = today.getTime() + i * DAY, z = a + DAY;
      ends.push(members.filter(m => m[1] * 1000 >= a && m[1] * 1000 < z).length);
    }
    spark(root.querySelector('[data-spark="renewals"]'), ends, C.warn, animate ? 890 : 0);

    // Member growth: members with access at the end of each of the last 12 months.
    const labels = [], full = [], all = [], yearly = [];
    for (let k = 11; k >= 0; k--) {
      const m = new Date(today.getFullYear(), today.getMonth() - k + 1, 1);
      const t = k === 0 ? Date.now() : m.getTime() - 1;
      const pm = new Date(t);
      const [a, y] = activeOn(t);
      labels.push(MON[pm.getMonth()]); full.push((k === 0 ? 'Today, ' : 'End of ') + MON[pm.getMonth()] + ' ' + pm.getFullYear());
      all.push(a); yearly.push(y);
    }
    chart(root.querySelector('[data-chart="members"]'), {
      animate: false, delay: animate ? 900 : 0, labels,
      series: [{ values: all, color: C.brand, fill: 0.14, width: 2.25 }, { values: yearly, color: C.kept, fill: 0.05, width: 2 }],
      yfmt: v => fmt(v), emptyMax: 20, emptyText: 'No members yet',
      tip: i => `<h4>${full[i]}</h4>
        <div><span><i style="background:${C.brand}"></i>All members</span><b>${fmt(all[i])}</b></div>
        <div><span><i style="background:${C.kept}"></i>Yearly plan</span><b>${fmt(yearly[i])}</b></div>
        <div><span><i style="background:${C.muted}"></i>Monthly plan</span><b>${fmt(all[i] - yearly[i])}</b></div>`
    });

    root.querySelectorAll('svg[data-trend]').forEach((s, k) => spark(s, running(JSON.parse(s.dataset.trend)), C.brand, animate ? 900 + k * 60 : 0));
  }

  /* ---------- segmented switches with a sliding pill ---------- */
  const segs = [...root.querySelectorAll('.ov-seg')];
  function placePill(seg, instant) {
    const pill = seg.querySelector('.ov-seg-pill'), btn = seg.querySelector('button.on');
    if (instant) pill.style.transition = 'none';
    pill.style.width = btn.offsetWidth + 'px';
    pill.style.transform = `translateX(${btn.offsetLeft}px)`;
    if (instant) { pill.offsetWidth; pill.style.transition = ''; }
  }
  segs.forEach(seg => seg.querySelectorAll('button').forEach(btn => btn.addEventListener('click', () => {
    if (btn.classList.contains('on')) return;
    seg.querySelectorAll('button').forEach(b => { const on = b === btn; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    placePill(seg);
    if (btn.dataset.range) { range = +btn.dataset.range; render(true); }
    else if (btn.dataset.filter) filterCards(btn.dataset.filter);
    else { mode = btn.dataset.mode; drawMoney(true); }
  })));

  root.querySelectorAll('[data-count]').forEach(n => count(n, +n.dataset.count, 1400, 450));
  /* Campus cards: show all, open or draft campuses; the shown cards settle back in one after another. */
  function filterCards(f) {
    let n = 0;
    root.querySelectorAll('.ov-cc-card').forEach(card => {
      const show = f === 'all' || card.dataset.state === f || (card.classList.contains('ov-cc-add') && f === 'draft');
      card.classList.toggle('hide', !show);
      card.classList.remove('swap');
      if (show) { void card.offsetWidth; card.style.setProperty('--n', n++); card.classList.add('swap'); }
    });
    root.querySelectorAll('.ov-cc-card:not(.hide) svg[data-trend]').forEach((s, k) => spark(s, running(JSON.parse(s.dataset.trend)), C.brand, 200 + k * 60));
  }

  function boot() {
    segs.forEach(sg => placePill(sg, true));
    render(false);
    staticCharts(true);
    first = false;
  }
  (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(boot);

  let lastW = root.clientWidth, t;
  addEventListener('resize', () => {
    clearTimeout(t);
    t = setTimeout(() => {
      segs.forEach(sg => placePill(sg, true));
      if (Math.abs(root.clientWidth - lastW) < 8) return;
      lastW = root.clientWidth;
      render(false); staticCharts(false);
    }, 150);
  });
})();
