// Rotating photo stage on the login / signup pages: arrows, dots, a peek at the next photo, auto-advance.
(function () {
  var slides = window.OBIN_AUTH_SLIDES || [];
  var imgs = [].slice.call(document.querySelectorAll('.auth-slide'));
  var dots = document.querySelector('[data-auth-dots]');
  var next = document.querySelector('[data-auth-next]');
  var prev = document.querySelector('[data-auth-prev]');
  if (!slides.length || imgs.length < 2 || !dots || !next || !prev) return;
  var i = 0, timer = null;
  var pill = document.querySelector('[data-auth-pill]');
  var h = document.querySelector('[data-auth-h]');
  var p = document.querySelector('[data-auth-p]');

  slides.forEach(function (_, n) {
    var b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', 'Slide ' + (n + 1));
    b.addEventListener('click', function () { go(n, true); });
    dots.appendChild(b);
  });

  function go(n, byUser) {
    i = (n + slides.length) % slides.length;
    imgs.forEach(function (im, k) { im.classList.toggle('on', k === i); });
    [].forEach.call(dots.children, function (d, k) { d.classList.toggle('on', k === i); });
    pill.textContent = slides[i].pill;
    h.textContent = slides[i].h;
    p.textContent = slides[i].p;
    next.style.backgroundImage = 'url(' + imgs[(i + 1) % imgs.length].src + ')';
    if (byUser) { clearInterval(timer); start(); }
  }
  function start() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = setInterval(function () { go(i + 1); }, 5200);
  }
  prev.addEventListener('click', function () { go(i - 1, true); });
  next.addEventListener('click', function () { go(i + 1, true); });
  go(0);
  start();
})();