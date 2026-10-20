/* Class room alerts on every signed-in page: polls api/chat-alerts.php, plays a short chime and shows a
   small pop-up when a classmate posts (unless the person turned the sound off in Settings), and fills
   any [data-chat-unread] / [data-chat-unread="slug"] badge. The chime is drawn with Web Audio, so there
   is no sound file. Browsers only allow sound after the first tap or key press on the page.
   Load with: <script src="chat-alerts.js" data-base="https://site" defer></script>.
   window.ObinChime() is shared with assets/js/class-room.js. */
(function () {
  var script = document.currentScript;
  var base = (script && script.dataset.base) || '';
  var ctx = null;
  function unlock() {
    try {
      if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
      if (ctx.state === 'suspended') ctx.resume();
    } catch (e) {}
  }
  addEventListener('pointerdown', unlock, { passive: true });
  addEventListener('keydown', unlock);

  // Two soft rising notes: "ti-ding". kind 'in' is quieter, for messages arriving in an open chat.
  window.ObinChime = function (kind) {
    if (!ctx || ctx.state !== 'running') return;
    var t = ctx.currentTime, vol = kind === 'in' ? 0.07 : 0.14;
    [[880, 0], [1318.5, 0.11]].forEach(function (n) {
      var o = ctx.createOscillator(), g = ctx.createGain();
      o.type = 'sine'; o.frequency.value = n[0];
      g.gain.setValueAtTime(0.0001, t + n[1]);
      g.gain.exponentialRampToValueAtTime(vol, t + n[1] + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, t + n[1] + 0.32);
      o.connect(g); g.connect(ctx.destination); o.start(t + n[1]); o.stop(t + n[1] + 0.35);
    });
    if (kind !== 'in' && navigator.vibrate) { try { navigator.vibrate(60); } catch (e) {} }
  };

  var KEY = 'obin-chat-last';
  var room = document.body ? document.body.dataset.chatRoom : '';
  function lastSeen() { try { return +(localStorage.getItem(KEY) || 0); } catch (e) { return 0; } }
  function setSeen(id) { try { localStorage.setItem(KEY, String(id)); } catch (e) {} }

  var pop = null;
  function showPop(l) {
    if (!pop) {
      pop = document.createElement('a');
      pop.className = 'obin-chat-pop';
      pop.style.cssText = 'position:fixed;z-index:9999;right:16px;bottom:16px;max-width:min(360px,calc(100vw - 32px));display:flex;gap:12px;align-items:center;padding:12px 14px;border-radius:16px;background:#070b24;color:#f5f6fb;box-shadow:0 20px 44px -16px rgba(7,11,36,.6);font:500 13.5px/1.4 Geist,system-ui,sans-serif;text-decoration:none;transform:translateY(140%);opacity:0;transition:transform .5s cubic-bezier(.34,1.5,.64,1),opacity .3s';
      document.body.appendChild(pop);
    }
    pop.href = l.url;
    pop.innerHTML = '<span style="flex:none;width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:#cdfb5e;color:#0a0d1c;font-size:18px">💬</span><span style="min-width:0"><b style="display:block;font-size:12px;color:#cdfb5e;letter-spacing:.02em"></b><span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></span></span>';
    pop.querySelector('b').textContent = l.campus + ' class room';
    pop.querySelector('span span').textContent = l.who + ': ' + l.text;
    requestAnimationFrame(function () { pop.style.transform = 'none'; pop.style.opacity = '1'; });
    clearTimeout(showPop.t);
    showPop.t = setTimeout(function () { pop.style.transform = 'translateY(140%)'; pop.style.opacity = '0'; }, 6000);
  }

  function badges(data) {
    document.querySelectorAll('[data-chat-unread]').forEach(function (el) {
      var slug = el.getAttribute('data-chat-unread'), n = slug ? (data.rooms[slug] || 0) : data.unread;
      el.textContent = n > 99 ? '99+' : String(n);
      el.hidden = !n;
    });
  }

  var first = true;
  function poll() {
    if (document.hidden) return;
    fetch(base + '/api/chat-alerts.php', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data) return;
        window.ObinChatSound = !!data.sound;
        badges(data);
        var l = data.latest;
        if (!l) { first = false; return; }
        var seen = lastSeen();
        if (l.id > seen) {
          setSeen(l.id);
          // Not on the first look (that is old news), and not for the class room that is open right now.
          if (!first && seen && l.slug !== room) {
            showPop(l);
            if (data.sound) window.ObinChime('alert');
          }
        }
        first = false;
      })
      .catch(function () {});
  }
  poll();
  setInterval(poll, 15000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
