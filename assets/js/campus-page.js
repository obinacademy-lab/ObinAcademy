/* Public campus page (campus.php): the price odometer and plan switch (it also tells payment.js which
   plan to charge), blocks rising in as they reach the screen, the year's tiles flipping in, and the
   hero photo drifting slower than the page. */
(function () {
  const root = document.querySelector('[data-cq]');
  if (!root) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduce && 'IntersectionObserver' in window) root.classList.add('js-cq');

  // Price odometer: every digit rolls to its new value. Both prices are padded to the same number of
  // digits; the leading padding ("lead") folds away, so 1,000,000 can roll down to 100,000.
  const odo = root.querySelector('[data-odo]');
  const buttons = root.querySelectorAll('[data-switch] button');
  const digits = Math.max(...[...buttons].map(b => String(parseInt(b.dataset.price, 10) || 0).length));
  const layout = n => {
    const s = String(n).padStart(digits, '0'), out = [];
    let lead = true;
    for (let i = 0; i < s.length; i++) {
      const fromRight = s.length - i;
      if (s[i] !== '0' || i === s.length - 1) lead = false;
      out.push({ ch: s[i], lead });
      if (fromRight > 1 && (fromRight - 1) % 3 === 0) out.push({ ch: ',', lead });
    }
    return out;
  };
  const build = n => {
    odo.innerHTML = layout(n).map(p => p.ch === ','
      ? '<span class="c' + (p.lead ? ' lead' : '') + '">,</span>'
      : '<span class="d' + (p.lead ? ' lead' : '') + '"><span style="transform:translateY(-' + p.ch + 'em)">' + '0123456789'.split('').map(d => '<i>' + d + '</i>').join('') + '</span></span>').join('');
  };
  const roll = n => {
    const parts = layout(n), cells = odo.children;
    let k = 0;
    parts.forEach((p, i) => {
      const cell = cells[i];
      if (!cell) return;
      cell.classList.toggle('lead', p.lead);
      if (p.ch !== ',') { cell.firstElementChild.style.transitionDelay = (k++ * 60) + 'ms'; cell.firstElementChild.style.transform = 'translateY(-' + p.ch + 'em)'; }
    });
  };
  const yearlyBtn = root.querySelector('[data-switch] [data-plan="YEARLY"]');
  if (odo && yearlyBtn) build(parseInt(yearlyBtn.dataset.price, 10) || 0);

  const sw = root.querySelector('[data-switch]');
  if (sw) buttons.forEach(b => b.addEventListener('click', () => {
    sw.dataset.plan = b.dataset.plan;
    buttons.forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    const n = parseInt(b.dataset.price, 10) || 0;
    if (reduce) build(n); else roll(n);
    root.querySelector('[data-per]').textContent = b.dataset.per;
    root.querySelector('[data-day]').innerHTML = b.dataset.day;
    const widget = root.querySelector('[data-payment-widget]');
    if (widget) widget.dataset.plan = b.dataset.plan;
    const amt = root.querySelector('[data-pay-amount]');
    if (amt) amt.textContent = b.dataset.amount;
  }));

  // Blocks rise in once as they reach the screen; the year's tiles flip in one by one.
  if (root.classList.contains('js-cq')) {
    const io = new IntersectionObserver(entries => entries.forEach(en => {
      if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
    }), { threshold: .12 });
    root.querySelectorAll('.cq-reveal, [data-year]').forEach(el => io.observe(el));
  }

  // The hero photo drifts a little slower than the page.
  const pic = root.querySelector('[data-parallax]');
  if (pic && !reduce) window.addEventListener('scroll', () => { pic.style.translate = '0 ' + Math.min(60, window.scrollY * .12) + 'px'; }, { passive: true });
})();
