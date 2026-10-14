/* Admin Stories: preview the chosen video and read its length before upload, the quote counter,
   the "uploading" state, reel tiles that play on hover, counters and the delete confirm. */
(function () {
  const root = document.querySelector('[data-sx]');
  if (!root) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  root.querySelectorAll('[data-scramble]').forEach((el, k) => {
    const target = String(+el.dataset.scramble);
    if (reduce) { el.textContent = target; return; }
    el.textContent = target.replace(/\d/g, '0');
    setTimeout(() => {
      const t0 = performance.now();
      (function f(now) {
        const p = Math.min(1, (now - t0) / 800), locked = Math.floor(p * (target.length + 1));
        el.textContent = target.split('').map((c, i) => i < locked ? c : String(Math.floor(Math.random() * 10))).join('');
        if (p < 1) requestAnimationFrame(f); else el.textContent = target;
      })(t0);
    }, 380 + k * 60);
  });

  root.querySelectorAll('.sx-tile video').forEach(v => {
    const tile = v.parentElement;
    tile.addEventListener('mouseenter', () => { v.currentTime = 0; v.play().catch(() => {}); });
    tile.addEventListener('mouseleave', () => { v.pause(); v.currentTime = .5; });
  });

  // Only one library video plays at a time.
  root.querySelectorAll('.sx-media video').forEach(v => v.addEventListener('play', () => {
    root.querySelectorAll('.sx-media video').forEach(o => { if (o !== v) o.pause(); });
  }));

  root.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  const form = root.querySelector('[data-add]');
  if (!form) return;
  const drop = form.querySelector('[data-drop]'), input = form.querySelector('[data-file]');
  const preview = form.querySelector('[data-preview]'), nameTag = form.querySelector('[data-filename]');
  const duration = form.querySelector('[data-duration]');
  let objectUrl = null;

  function show() {
    const file = input.files[0];
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    duration.value = '';
    if (!file) { drop.classList.remove('has'); preview.hidden = nameTag.hidden = true; preview.removeAttribute('src'); return; }
    objectUrl = URL.createObjectURL(file);
    preview.src = objectUrl;
    preview.hidden = nameTag.hidden = false;
    nameTag.textContent = file.name + ' · ' + (file.size / 1048576).toFixed(1) + 'MB';
    const clear = document.createElement('button');
    clear.type = 'button'; clear.textContent = '✕'; clear.title = 'Choose a different video';
    clear.addEventListener('click', e => { e.preventDefault(); input.value = ''; show(); });
    nameTag.appendChild(clear);
    drop.classList.add('has');
  }
  preview.addEventListener('loadedmetadata', () => { if (isFinite(preview.duration)) duration.value = Math.round(preview.duration); });
  input.addEventListener('change', show);
  ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('over'); }));
  ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, () => drop.classList.remove('over')));
  drop.addEventListener('drop', e => {
    e.preventDefault();
    if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(); }
  });

  const quote = form.querySelector('[data-quote]'), count = form.querySelector('[data-count]');
  const max = +quote.getAttribute('maxlength');
  const tick = () => { count.textContent = quote.value.length; count.parentElement.classList.toggle('near', quote.value.length > max - 30); };
  quote.addEventListener('input', tick); tick();

  form.addEventListener('submit', e => {
    // The clicked button carries publish=1/0; keep it by copying it into a hidden field before disabling.
    const btn = e.submitter;
    if (btn && btn.name) {
      const h = document.createElement('input'); h.type = 'hidden'; h.name = btn.name; h.value = btn.value; form.appendChild(h);
    }
    form.querySelectorAll('.sx-submit button').forEach(b => { b.disabled = true; });
    form.querySelector('[data-uploading]').hidden = false;
  });
})();
