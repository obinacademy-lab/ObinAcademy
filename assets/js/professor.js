/* Professor dashboard: the dock's stretching ink and account menu (every professor page), and on the
   Overview: tiles dealt out from the centre, the class dial drawing round, hold-to-complete, the
   split-flap live class board, the drawn students sparkline, the live countdown and the
   students/community swap (a view transition). */
(function () {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ease = 'cubic-bezier(.16, 1, .3, 1)';

  // The dock's ink sits under the current tab and stretches toward the one you pick.
  const tabs = document.querySelector('[data-pf-tabs]'), ink = document.querySelector('[data-pf-ink]');
  if (tabs && ink) {
    const links = [...tabs.querySelectorAll('a')];
    const place = (a, animate) => {
      if (!a) { ink.style.width = '0'; return; }
      const r = a.getBoundingClientRect(), p = tabs.getBoundingClientRect();
      const from = { l: parseFloat(ink.style.left) || 0, w: parseFloat(ink.style.width) || 0 };
      const to = { l: r.left - p.left + tabs.scrollLeft, w: r.width };
      ink.style.left = to.l + 'px'; ink.style.width = to.w + 'px';
      if (animate && !reduce && from.w && ink.animate) {
        const l = Math.min(from.l, to.l), w = Math.max(from.l + from.w, to.l + to.w) - l;
        ink.animate([{ left: from.l + 'px', width: from.w + 'px' }, { left: l + 'px', width: w + 'px', offset: .45 }, { left: to.l + 'px', width: to.w + 'px' }], { duration: 480, easing: ease });
      }
    };
    const current = tabs.querySelector('a.on');
    place(current, false);
    if (current) current.scrollIntoView({ block: 'nearest', inline: 'center' });
    links.forEach(a => a.addEventListener('click', () => { links.forEach(x => x.classList.toggle('on', x === a)); place(a, true); }));
    window.addEventListener('resize', () => place(tabs.querySelector('a.on'), false));
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => place(tabs.querySelector('a.on'), false));
  }

  // Account menu.
  const meBtn = document.querySelector('[data-pf-menu]'), menu = document.querySelector('[data-pf-menu-list]');
  if (meBtn && menu) {
    meBtn.addEventListener('click', e => { e.stopPropagation(); const open = menu.hidden; menu.hidden = !open; meBtn.setAttribute('aria-expanded', String(open)); });
    document.addEventListener('click', e => { if (!menu.hidden && !menu.contains(e.target)) { menu.hidden = true; meBtn.setAttribute('aria-expanded', 'false'); } });
  }

  // Forms that need a yes first (moving a group back a month).
  document.querySelectorAll('[data-pf-overview] form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  const ov = document.querySelector('[data-pf-overview]');
  if (!ov) return;

  // The undo window: count down to when the month opens, then refresh so the group shows as moved on.
  const opensAt = ov.querySelector('[data-opens-at]'), left = ov.querySelector('[data-undo-left]');
  if (opensAt && left) {
    const at = new Date(opensAt.dataset.opensAt).getTime();
    const tick = () => {
      const ms = at - Date.now();
      if (ms <= 0) { left.textContent = '0:00'; setTimeout(() => location.reload(), 1500); return; }
      left.textContent = Math.floor(ms / 60000) + ':' + String(Math.floor(ms % 60000 / 1000)).padStart(2, '0');
      setTimeout(tick, 1000);
    };
    tick();
  }

  // 1. The desk deals itself: tiles start stacked at the centre, slightly turned, then glide to place.
  const tiles = [...ov.querySelectorAll('[data-in]')];
  const desk = ov.querySelector('[data-desk]');
  if (!reduce && desk && tiles.length && tiles[0].animate) {
    const d = desk.getBoundingClientRect(), cx = d.left + d.width / 2, cy = d.top + d.height / 2;
    tiles.forEach((t, i) => {
      const r = t.getBoundingClientRect();
      const dx = cx - (r.left + r.width / 2), dy = Math.max(-260, Math.min(260, cy - (r.top + r.height / 2)));
      t.animate([
        { transform: `translate(${dx * .55}px, ${dy * .55}px) rotate(${(i % 2 ? 1 : -1) * (2 + i)}deg) scale(.86)`, opacity: 0, filter: 'blur(4px)' },
        { transform: 'none', opacity: 1, filter: 'blur(0)' }
      ], { duration: 950, delay: 60 + i * 70, easing: ease, fill: 'backwards' });
    });
  }

  // 2. The class dial: twelve month dots, the arc drawn round to the month the class is on.
  const arc = ov.querySelector('[data-arc]'), ticks = ov.querySelector('[data-ticks]');
  if (arc && ticks) {
    const done = +arc.dataset.done, now = +arc.dataset.now;
    for (let m = 1; m <= 12; m++) {
      const a = (m - .5) / 12 * Math.PI * 2 - Math.PI / 2;
      const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      c.setAttribute('cx', (120 + Math.cos(a) * 118).toFixed(1)); c.setAttribute('cy', (120 + Math.sin(a) * 118).toFixed(1));
      c.setAttribute('r', m === now && done < 12 ? 5 : 3.5);
      c.setAttribute('class', 'tick' + (m <= done ? ' done' : (m === now ? ' now' : '')));
      ticks.appendChild(c);
    }
    const C = 2 * Math.PI * 96, part = done >= 12 ? 1 : (now - .5) / 12;
    arc.style.strokeDasharray = C; arc.style.strokeDashoffset = C * (1 - part);
    if (!reduce && arc.animate) arc.animate([{ strokeDashoffset: C }, { strokeDashoffset: C * (1 - part) }], { duration: 1600, delay: 500, easing: ease, fill: 'backwards' });
  }

  // 3. Hold to complete: the lime fills while held; letting go early drains it; full sends the form.
  const hold = ov.querySelector('[data-hold]');
  if (hold) {
    const bar = hold.querySelector('[data-fillbar]'), label = hold.querySelector('[data-hold-label]'), form = hold.closest('form');
    let anim = null;
    const start = e => {
      e.preventDefault();
      if (hold.classList.contains('full')) return;
      if (!bar.animate) { if (confirm(label.textContent + '?')) form.submit(); return; }
      anim = bar.animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { duration: 1400, easing: 'linear', fill: 'forwards' });
      anim.onfinish = () => { if (anim.playbackRate < 0) return; hold.classList.add('full'); label.textContent = 'Opening the next month…'; form.submit(); };
    };
    const stop = () => { if (anim && anim.playState === 'running' && anim.playbackRate > 0) anim.reverse(); };
    hold.addEventListener('pointerdown', start);
    ['pointerup', 'pointerleave', 'pointercancel'].forEach(t => hold.addEventListener(t, stop));
    hold.addEventListener('keydown', e => { if ((e.key === ' ' || e.key === 'Enter') && !e.repeat) start(e); });
    hold.addEventListener('keyup', e => { if (e.key === ' ' || e.key === 'Enter') stop(); });
  }

  // 4. Split-flap board: each character flips through the alphabet and lands on the live class time.
  const board = ov.querySelector('[data-board]');
  if (board) {
    const text = board.dataset.board, chars = ' ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789:';
    const esc = s => s.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
    board.innerHTML = text.split('').map(ch => `<span class="pf-flap${ch === ' ' ? ' sp' : ''}"><span class="h top">${esc(ch)}</span><span class="h bot"><span>${esc(ch)}</span></span></span>`).join('');
    if (!reduce && board.animate) {
      [...board.children].forEach((flap, i) => {
        const target = text[i]; if (target === ' ') return;
        const top = flap.querySelector('.top'), bot = flap.querySelector('.bot span'), steps = 5 + i * 2;
        let k = 0;
        const tick = () => {
          const ch = k < steps ? chars[1 + Math.floor(Math.random() * (chars.length - 1))] : target;
          const fold = document.createElement('span'); fold.className = 'fold'; fold.textContent = top.textContent; flap.appendChild(fold);
          top.textContent = ch;
          fold.animate([{ transform: 'rotateX(0)' }, { transform: 'rotateX(-90deg)' }], { duration: 90, easing: 'ease-in' }).onfinish = () => { fold.remove(); bot.textContent = ch; };
          if (k++ < steps) setTimeout(tick, 95);
        };
        top.textContent = ' '; bot.textContent = ' ';
        setTimeout(tick, 700 + i * 60);
      });
    }
  }

  // Live countdown to the next class.
  const cd = ov.querySelector('[data-countdown]');
  if (cd) {
    const at = new Date(cd.dataset.countdown);
    const upd = () => {
      const ms = at - new Date();
      if (ms <= 0) { cd.textContent = ms > -2 * 36e5 ? 'live now' : 'just ended'; return; }
      const d = Math.floor(ms / 864e5), h = Math.floor(ms % 864e5 / 36e5), m = Math.floor(ms % 36e5 / 6e4);
      cd.textContent = 'in ' + (d ? d + ' d ' + String(h).padStart(2, '0') + ' h' : h + ' h ' + String(m).padStart(2, '0') + ' m');
    };
    upd(); setInterval(upd, 30000);
  }

  // 5. Sparkline: a smooth line through eight weeks of active students, drawn left to right.
  const spark = ov.querySelector('[data-spark]');
  if (spark) {
    const pts = spark.dataset.spark.split(',').map(Number), W = 300, H = 70;
    const max = Math.max(...pts, 1), min = Math.min(...pts), span = Math.max(1, max - min);
    const P = pts.map((v, i) => [i / Math.max(1, pts.length - 1) * W, H - 6 - (v - min) / span * (H - 12)]);
    let d = `M${P[0][0]},${P[0][1]}`;
    for (let i = 1; i < P.length; i++) { const [x0, y0] = P[i - 1], [x1, y1] = P[i], mx = (x0 + x1) / 2; d += ` C${mx},${y0} ${mx},${y1} ${x1},${y1}`; }
    const line = spark.querySelector('[data-spark-line]'), area = spark.querySelector('[data-spark-area]'), end = spark.querySelector('[data-spark-end]');
    line.setAttribute('d', d); area.setAttribute('d', d + ` L${W},${H} L0,${H} Z`);
    const last = P[P.length - 1]; end.setAttribute('cx', last[0]); end.setAttribute('cy', last[1]);
    if (!reduce && line.animate) {
      const L = line.getTotalLength(); line.style.strokeDasharray = L;
      line.animate([{ strokeDashoffset: L }, { strokeDashoffset: 0 }], { duration: 1500, delay: 700, easing: ease, fill: 'backwards' });
      area.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 900, delay: 1500, fill: 'backwards' });
      end.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 400, delay: 2100, fill: 'backwards' });
    }
  }

  // 6. Duties: the check marks draw in once the desk has settled.
  if (!reduce) ov.querySelectorAll('.pf-duty.done .check svg').forEach((svg, i) => {
    if (svg.animate) svg.animate([{ strokeDashoffset: 24 }, { strokeDashoffset: 0 }], { duration: 500, delay: 1100 + i * 150, easing: ease, fill: 'backwards' });
  });

  // 7. Students / Community: swapped with a view transition (old list sinks, new one rises).
  const seg = ov.querySelectorAll('.pf-seg button'), link = ov.querySelector('[data-pane-link]');
  const panes = ov.querySelectorAll('[data-pane-box] > [data-pane]');
  seg.forEach(b => b.addEventListener('click', () => {
    if (b.getAttribute('aria-pressed') === 'true') return;
    const swap = () => {
      seg.forEach(x => x.setAttribute('aria-pressed', String(x === b)));
      panes.forEach(p => { p.hidden = p.dataset.pane !== b.dataset.pane; });
      if (link) {
        link.href = b.dataset.pane === 'students' ? link.dataset.studentsHref : link.dataset.communityHref;
        if (!link.dataset.studentsText) link.dataset.studentsText = link.textContent;
        link.textContent = b.dataset.pane === 'students' ? link.dataset.studentsText : 'Open community →';
      }
    };
    if (document.startViewTransition && !reduce) document.startViewTransition(swap); else swap();
  }));
  if (location.hash === '#desk-switch' && seg[1]) seg[1].click();
})();
