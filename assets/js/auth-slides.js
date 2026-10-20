// Login / signup / password-reset pages: a show/hide eye on every password field.
(function () {
  document.querySelectorAll('.auth-card input[type="password"]').forEach(function (input) {
    var wrap = document.createElement('div');
    wrap.className = 'inp has-eye';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
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
})();
