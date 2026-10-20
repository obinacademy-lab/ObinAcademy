/* Share campus (campus.php): the pop-up with Copy link, WhatsApp, Facebook, X, LinkedIn, TikTok and Instagram.
   WhatsApp, Facebook, X and LinkedIn open their own share page with the link filled in. TikTok and
   Instagram have no web share link, so on a phone the phone's own share sheet opens (it lists those
   apps); elsewhere the link is copied and the site opens, with a note to paste it. */
(function () {
  var box = document.querySelector('[data-share]');
  if (!box) return;
  var scrim = document.querySelector('.cq-sharescrim');
  var url = box.dataset.url, text = box.dataset.text, title = box.dataset.title;
  var note = box.querySelector('[data-share-note]'), input = box.querySelector('[data-share-input]');
  var copyBtn = box.querySelector('[data-share-copy]'), nativeBtn = box.querySelector('[data-share-native]');
  var opener = null;
  var enc = encodeURIComponent;
  var links = {
    whatsapp: 'https://wa.me/?text=' + enc(text + ' ' + url),
    facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + enc(url),
    x: 'https://twitter.com/intent/tweet?text=' + enc(text) + '&url=' + enc(url),
    linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url=' + enc(url)
  };
  box.querySelectorAll('a[data-net]').forEach(function (a) { a.href = links[a.dataset.net]; });
  var canShare = !!navigator.share, touch = matchMedia('(pointer: coarse)').matches;
  if (canShare && touch) nativeBtn.hidden = false;

  function say(msg) { note.textContent = msg; note.classList.remove('on'); void note.offsetWidth; note.classList.add('on'); }
  function open(from) {
    opener = from || null; scrim.hidden = false; box.hidden = false;
    requestAnimationFrame(function () { scrim.classList.add('on'); box.classList.add('on'); });
    note.textContent = ''; note.classList.remove('on');
    document.documentElement.classList.add('cq-share-open');
    setTimeout(function () { copyBtn.focus(); }, 60);
  }
  function close() {
    scrim.classList.remove('on'); box.classList.remove('on'); document.documentElement.classList.remove('cq-share-open');
    setTimeout(function () { scrim.hidden = true; box.hidden = true; }, 260);
    if (opener) opener.focus();
  }
  function copy() {
    return new Promise(function (resolve) {
      var done = function () { resolve(true); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(done, function () { fallback(); });
      } else fallback();
      function fallback() {
        try { input.focus(); input.select(); input.setSelectionRange(0, 999); var ok = document.execCommand('copy'); resolve(!!ok); }
        catch (e) { resolve(false); }
      }
    });
  }

  document.querySelectorAll('[data-share-open]').forEach(function (b) { b.addEventListener('click', function () { open(b); }); });
  box.querySelectorAll('[data-share-close]').forEach(function (b) { b.addEventListener('click', close); });
  scrim.addEventListener('click', close);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden) close(); });

  copyBtn.addEventListener('click', function () {
    copy().then(function (ok) {
      copyBtn.classList.toggle('done', ok);
      copyBtn.querySelector('span').textContent = ok ? 'Copied' : 'Press and hold to copy';
      say(ok ? 'Link copied. Paste it anywhere.' : 'Select the link above and copy it.');
      setTimeout(function () { copyBtn.classList.remove('done'); copyBtn.querySelector('span').textContent = 'Copy link'; }, 2200);
    });
  });
  input.addEventListener('focus', function () { input.select(); });

  function nativeShare() { return navigator.share({ title: title, text: text, url: url }); }
  nativeBtn.addEventListener('click', function () { nativeShare().catch(function () {}); });

  // TikTok and Instagram: no web share link exists for either.
  box.querySelectorAll('button[data-net]').forEach(function (b) {
    b.addEventListener('click', function () {
      var site = b.dataset.net === 'tiktok' ? 'https://www.tiktok.com/' : 'https://www.instagram.com/';
      var name = b.dataset.net === 'tiktok' ? 'TikTok' : 'Instagram';
      if (canShare && touch) { nativeShare().catch(function () {}); say('Pick ' + name + ' from your phone\'s share list.'); return; }
      copy().then(function (ok) {
        say(ok ? 'Link copied. Opening ' + name + ': paste it in your bio, story or a message.' : 'Opening ' + name + '. Copy the link above and paste it there.');
        setTimeout(function () { window.open(site, '_blank', 'noopener'); }, 900);
      });
    });
  });
})();
