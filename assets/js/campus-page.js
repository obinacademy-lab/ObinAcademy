/* Public campus page (campus.php): the hero network field, the demo console typing the campus's three
   tasks, the magnetic join button, the skills glow, the year's circuit trace, the professor card tilt,
   the yearly/monthly switch (it also tells payment.js which plan to charge) and the phone join bar. */
(function () {
  const root = document.querySelector('[data-cp]');
  if (!root) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const hero = root.querySelector('.cp-hero');

  // Hero field: drifting points joined by faint lines; points near the pointer light up.
  const cv = root.querySelector('.cp-net');
  if (cv && cv.getContext) {
    const cx = cv.getContext('2d');
    let W = 0, H = 0, pts = [], mouse = { x: -999, y: -999 }, visible = true;
    const size = () => {
      const r = cv.getBoundingClientRect(), d = Math.min(2, window.devicePixelRatio || 1);
      W = r.width; H = r.height; cv.width = W * d; cv.height = H * d; cx.setTransform(d, 0, 0, d, 0, 0);
      const n = Math.round(Math.min(70, W * H / 14000));
      pts = Array.from({ length: n }, () => ({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .25, vy: (Math.random() - .5) * .25 }));
    };
    const draw = () => {
      cx.clearRect(0, 0, W, H);
      for (const p of pts) {
        if (!reduce) { p.x += p.vx; p.y += p.vy; if (p.x < 0 || p.x > W) p.vx *= -1; if (p.y < 0 || p.y > H) p.vy *= -1; }
      }
      for (let i = 0; i < pts.length; i++) {
        for (let j = i + 1; j < pts.length; j++) {
          const a = pts[i], b = pts[j], dx = a.x - b.x, dy = a.y - b.y, d2 = dx * dx + dy * dy;
          if (d2 < 15000) { cx.strokeStyle = 'rgba(160,170,255,' + (0.16 * (1 - d2 / 15000)) + ')'; cx.lineWidth = 1; cx.beginPath(); cx.moveTo(a.x, a.y); cx.lineTo(b.x, b.y); cx.stroke(); }
        }
        const p = pts[i], md = Math.hypot(p.x - mouse.x, p.y - mouse.y), near = md < 140;
        cx.fillStyle = near ? 'rgba(205,251,94,.95)' : 'rgba(205,251,94,.35)';
        cx.beginPath(); cx.arc(p.x, p.y, near ? 2.4 : 1.4, 0, Math.PI * 2); cx.fill();
        if (near) { cx.strokeStyle = 'rgba(205,251,94,' + (0.35 * (1 - md / 140)) + ')'; cx.beginPath(); cx.moveTo(p.x, p.y); cx.lineTo(mouse.x, mouse.y); cx.stroke(); }
      }
      if (!reduce && visible) requestAnimationFrame(draw);
    };
    window.addEventListener('resize', size); size(); draw();
    hero.addEventListener('pointermove', e => { const r = cv.getBoundingClientRect(); mouse = { x: e.clientX - r.left, y: e.clientY - r.top }; });
    hero.addEventListener('pointerleave', () => { mouse = { x: -999, y: -999 }; });
    // Stop drawing while the hero is off screen.
    if ('IntersectionObserver' in window && !reduce) {
      new IntersectionObserver(([en]) => { const was = visible; visible = en.isIntersecting; if (visible && !was) draw(); }).observe(hero);
    }
  }

  // Demo console: types the question, then streams the answer word by word, task after task.
  const tasksEl = root.querySelector('[data-tasks]');
  const q = root.querySelector('[data-q]'), a = root.querySelector('[data-a]'), nameEl = root.querySelector('[data-task-name]');
  const dots = root.querySelectorAll('.cp-dots i');
  let tasks = [];
  try { tasks = JSON.parse(tasksEl ? tasksEl.textContent : '[]'); } catch (e) { tasks = []; }
  if (q && a && tasks.length && !reduce) {
    const caret = '<span class="cp-caret"></span>';
    const esc = s => s.replace(/[&<>]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch]));
    const wait = ms => new Promise(r => setTimeout(r, ms));
    const run = async k => {
      const [label, ask, ans] = tasks[k];
      nameEl.innerHTML = 'Task ' + (k + 1) + ' of ' + tasks.length + ' · <b>' + esc(label) + '</b>';
      dots.forEach((d, i) => { d.classList.remove('on'); if (i === k) { void d.offsetWidth; d.classList.add('on'); } });
      a.innerHTML = '';
      for (let i = 0; i <= ask.length; i++) { q.innerHTML = esc(ask.slice(0, i)) + caret; await wait(22 + Math.random() * 30); }
      q.textContent = ask; await wait(500);
      a.innerHTML = '<span style="color:var(--on-3)">Thinking…</span>'; await wait(700);
      let out = '';
      for (const w of ans.split(/(\s+)/)) { out += w; a.innerHTML = esc(out) + caret; await wait(w.trim() ? 45 + Math.random() * 60 : 0); }
      a.textContent = ans; await wait(2600);
    };
    (async () => { let k = 0; await wait(900); for (;;) { await run(k); k = (k + 1) % tasks.length; } })();
  }

  // Magnetic join button.
  if (!reduce) root.querySelectorAll('[data-magnet]').forEach(b => {
    b.addEventListener('pointermove', e => { const r = b.getBoundingClientRect(); b.style.transform = 'translate(' + ((e.clientX - r.left - r.width / 2) * .25) + 'px,' + ((e.clientY - r.top - r.height / 2) * .35) + 'px)'; });
    b.addEventListener('pointerleave', () => { b.style.transition = 'transform .6s cubic-bezier(.16,1,.3,1), box-shadow .4s'; b.style.transform = ''; setTimeout(() => { b.style.transition = ''; }, 600); });
  });

  // Skills: a lime glow follows the pointer.
  const glow = root.querySelector('[data-glow]');
  if (glow) glow.addEventListener('pointermove', e => { const r = glow.getBoundingClientRect(); glow.style.setProperty('--mx', (e.clientX - r.left) + 'px'); glow.style.setProperty('--my', (e.clientY - r.top) + 'px'); });

  // The year: the circuit trace draws as the section passes through the screen.
  const trace = root.querySelector('[data-trace]'), year = root.querySelector('#year');
  if (trace && year) {
    const tick = () => {
      const r = year.getBoundingClientRect(), vh = window.innerHeight;
      trace.style.setProperty('--p', reduce ? 1 : Math.max(0, Math.min(1, (vh - r.top) / (vh * .9 + r.height * .4))).toFixed(3));
    };
    window.addEventListener('scroll', tick, { passive: true }); tick();
  }

  // Professor card tilts toward the pointer.
  const card = root.querySelector('[data-tilt]');
  if (card && !reduce) {
    card.addEventListener('pointermove', e => { const r = card.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5; card.style.transform = 'perspective(900px) rotateY(' + (x * 10) + 'deg) rotateX(' + (-y * 10) + 'deg)'; });
    card.addEventListener('pointerleave', () => { card.style.transform = ''; });
  }

  // Plan switch: slides the knob, updates the price, and sets the plan payment.js will charge.
  const sw = root.querySelector('[data-switch]');
  if (sw) sw.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
    sw.dataset.plan = b.dataset.plan;
    sw.querySelectorAll('button').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    root.querySelector('[data-amount]').textContent = b.dataset.amount;
    root.querySelector('[data-per]').textContent = b.dataset.per;
    root.querySelector('[data-day]').innerHTML = b.dataset.day;
    const widget = root.querySelector('[data-payment-widget]');
    if (widget) widget.dataset.plan = b.dataset.plan;
    const payAmt = root.querySelector('[data-pay-amount]');
    if (payAmt) payAmt.textContent = b.dataset.amount;
  }));

  // Phone join bar: shows after the hero, hides at the join panel.
  const dock = root.querySelector('[data-dock]'), join = root.querySelector('#join');
  if (dock && join) {
    const tick = () => {
      const pastHero = hero.getBoundingClientRect().bottom < 0, jr = join.getBoundingClientRect();
      dock.classList.toggle('show', pastHero && !(jr.top < window.innerHeight * .8 && jr.bottom > 0));
    };
    window.addEventListener('scroll', tick, { passive: true }); tick();
  }
})();
