// Login / signup pages: rotating photo with floating callouts, and a show/hide eye on password fields.
(function () {
  // ---- show / hide password
  document.querySelectorAll('.auth-card input[type="password"]').forEach(function (input) {
    var wrap = input.parentElement;
    wrap.classList.add('has-eye');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'auth-eye';
    btn.setAttribute('aria-label', 'Show password');
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.classList.toggle('is-on', show);
    });
    wrap.appendChild(btn);
  });

  // ---- photo slides
  var slides = window.OBIN_AUTH_SLIDES || [];
  var imgs = [].slice.call(document.querySelectorAll('.auth-slide'));
  var dots = document.querySelector('[data-auth-dots]');
  if (!slides.length || imgs.length < 2 || !dots) return;
  var i = 0, timer = null;
  var txt = {
    pill: document.querySelector('[data-auth-pill]'),
    a1: document.querySelector('[data-auth-a1]'), a2: document.querySelector('[data-auth-a2]'),
    b1: document.querySelector('[data-auth-b1]'), b2: document.querySelector('[data-auth-b2]')
  };

  slides.forEach(function (_, n) {
    var b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', 'Slide ' + (n + 1));
    b.addEventListener('click', function () { go(n, true); });
    dots.appendChild(b);
  });

  function go(n, byUser) {
    i = (n + slides.length) % slides.length;
    var s = slides[i];
    imgs.forEach(function (im, k) { im.classList.toggle('on', k === i); });
    [].forEach.call(dots.children, function (d, k) { d.classList.toggle('on', k === i); });
    txt.pill.textContent = s.pill;
    txt.a1.textContent = s.a[0]; txt.a2.textContent = s.a[1];
    txt.b1.textContent = s.b[0]; txt.b2.textContent = s.b[1];
    if (byUser) { clearInterval(timer); start(); }
  }
  function start() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = setInterval(function () { go(i + 1); }, 5200);
  }
  go(0);
  start();
})();