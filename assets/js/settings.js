/* Settings: profile-strength counter, live photo preview, show/hide password, password strength and
   match hints, and the dashboard colour that applies (and saves) as soon as one is picked. */
(function () {
  const root = document.querySelector('[data-se]');
  if (!root) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  root.querySelectorAll('[data-scramble]').forEach(el => {
    const target = +el.dataset.scramble;
    if (reduce || !target) { el.textContent = target; return; }
    const t0 = performance.now();
    (function f(now) {
      const p = Math.min(1, (now - t0) / 1400), e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * e);
      if (p < 1) requestAnimationFrame(f);
    })(t0);
  });

  // Photo: show the chosen picture straight away, in the form and on the identity card.
  const input = root.querySelector('[data-photo-input]');
  if (input) {
    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      const url = URL.createObjectURL(file);
      root.querySelectorAll('[data-photo], [data-photo-mirror]').forEach(box => {
        box.innerHTML = '';
        const img = document.createElement('img'); img.src = url; img.alt = '';
        box.appendChild(img);
        box.classList.remove('fresh'); void box.offsetWidth; box.classList.add('fresh');
      });
      const label = root.querySelector('[data-photo-label]');
      if (label) label.textContent = file.name.length > 26 ? file.name.slice(0, 24) + '…' : file.name;
    });
  }

  root.querySelectorAll('[data-reveal]').forEach(btn => btn.addEventListener('click', () => {
    const field = btn.previousElementSibling, show = field.type === 'password';
    field.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }));

  const fresh = root.querySelector('[data-new]'), again = root.querySelector('[data-confirm-pw]'), meter = root.querySelector('[data-strength]');
  if (fresh && meter) {
    const words = ['At least 8 characters', 'Weak', 'Fair', 'Good', 'Strong'];
    const rate = v => {
      if (v.length < 8) return v.length ? 1 : 0;
      let s = 1;
      if (v.length >= 12) s++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
      if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
      return Math.min(4, s);
    };
    const check = () => {
      const lvl = rate(fresh.value);
      meter.dataset.level = lvl;
      meter.querySelector('span').textContent = fresh.value.length && fresh.value.length < 8 ? (8 - fresh.value.length) + ' more character' + (8 - fresh.value.length === 1 ? '' : 's') : words[lvl];
      if (again) {
        const bad = again.value !== '' && again.value !== fresh.value;
        again.classList.toggle('mismatch', bad);
        again.setCustomValidity(bad ? 'The two new passwords do not match.' : '');
      }
    };
    fresh.addEventListener('input', check);
    if (again) again.addEventListener('input', check);
  }

  // Dashboard colour: repaint the sidebar now, then save.
  const form = document.getElementById('theme-form'), themeInput = document.getElementById('theme-input'), shell = document.querySelector('.dash');
  if (form) form.querySelectorAll('[data-theme-key]').forEach(btn => btn.addEventListener('click', () => {
    const key = btn.dataset.themeKey;
    themeInput.value = key;
    form.querySelectorAll('[data-theme-key]').forEach(b => { b.classList.toggle('on', b === btn); b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
    if (shell) shell.className = shell.className.replace(/\btheme-\S+/g, '').replace(/\s+/g, ' ').trim() + ' theme-' + key;
    form.submit();
  }));
})();
