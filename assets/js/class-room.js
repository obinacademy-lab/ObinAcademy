/* The campus class room (campus-learn.php?view=community): a WhatsApp-style group chat.
   Reads #cr-config (printed by the page), talks to api/class-room.php, and polls every few seconds
   (no WebSocket server on this host). Replies (menu or swipe right), one reaction per person, emoji and
   the Obin sticker pack, photos, wins with their own comment threads, All / Wins / Professor filters,
   a sound switch. The chime comes from assets/js/chat-alerts.js (window.ObinChime). */
(function () {
  var cfgEl = document.getElementById('cr-config');
  var root = document.querySelector('[data-cr]');
  if (!cfgEl || !root) return;
  var C = JSON.parse(cfgEl.textContent);
  var $ = function (s, el) { return (el || root).querySelector(s); };
  var msgsEl = $('.cr-msgs'), input = $('#cr-input'), sendBtn = $('#cr-send'), errEl = $('#cr-err');
  var QUICK = ['👍', '❤️', '😂', '🔥', '👏', '🎉'];
  var EMO = {
    '😀': '😀 😃 😄 😁 😆 😅 🤣 😂 🙂 😉 😊 😇 🥰 😍 🤩 😘 😋 😜 🤪 😎 🤓 🧐 🤔 🤗 🤭 😏 😌 😴 🥳 😮 😲 🤯 😳 🥺 😢 😭 😤 😡 🙄 😬',
    '👍': '👍 👎 👏 🙌 👐 🤲 🤝 🙏 ✊ 👊 ✌️ 🤞 🤟 🤘 👌 🤌 👈 👉 👆 👇 ☝️ ✋ 👋 💪 🫡 🫶 ✍️ 👀 🧠',
    '🔥': '🔥 ✨ ⭐ 🌟 💯 ✅ ❌ ❗ ❓ 💡 🎯 🚀 🏆 🥇 🎉 🎊 🎁 💎 💰 💸 📈 📉 💼 📚 📝 💻 📱 ⏰ 📅 🔔',
    '❤️': '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 💖 💗 💓 💞 💕 💔 ❣️',
    '🍔': '☕ 🍵 🥤 🍕 🍔 🍟 🌮 🍿 🍩 🍪 🎂 🍫 🍎 🍌 🥑 🌽 🇺🇬'
  };
  var I = {
    tick: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 13 4 4 8-9M10 15.5l1.5 1.5 8-9"/></svg>',
    reply: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v6"/></svg>',
    bin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>',
    copy: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>',
    chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'
  };

  var list = C.messages || [], byId = {}, filter = 'all', replyTo = null, more = !!C.more;
  list.forEach(function (m) { byId[m.id] = m; });

  /* ---------- helpers ---------- */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function linkify(s) { return esc(s).replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"])/g, '<a href="$1" target="_blank" rel="noopener nofollow ugc">$1</a>'); }
  var onlyEmojiRe; try { onlyEmojiRe = new RegExp('^(\\p{Extended_Pictographic}|\\uFE0F|\\u200D|\\s){1,10}$', 'u'); } catch (e) { onlyEmojiRe = null; }
  function onlyEmoji(t) { return !!(onlyEmojiRe && t && t.length <= 24 && onlyEmojiRe.test(t)); }
  function dayLabel(d) {
    if (d === C.today) return 'Today';
    if (d === C.yesterday) return 'Yesterday';
    var p = d.split('-'), dt = new Date(+p[0], +p[1] - 1, +p[2]);
    return dt.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
  }
  function sticker(key) {
    var s = C.stickers[key]; if (!s) return '';
    return '<span class="cr-stk"><span class="s ' + s[0] + '" style="--r:' + s[3] + '"><span class="e">' + s[1] + '</span>' + esc(s[2]) + '</span></span>';
  }
  function preview(m) {
    if (m.kind === 'DELETED') return 'This message was deleted';
    if (m.kind === 'STICKER') return 'Sticker';
    if (m.kind === 'WIN') return '🏆 ' + (m.title || 'A win');
    if (m.kind === 'IMAGE') return '📷 ' + (m.text || 'Photo');
    return m.text;
  }
  function toast(t) { var el = $('.cr-toast'); el.textContent = t; el.classList.add('on'); clearTimeout(toast.t); toast.t = setTimeout(function () { el.classList.remove('on'); }, 2400); }
  function showErr(t) { errEl.textContent = t; errEl.hidden = !t; }
  function api(data, file) {
    var body;
    if (file) { body = new FormData(); Object.keys(data).forEach(function (k) { if (data[k] != null) body.append(k, data[k]); }); body.append('photo', file); body.append('csrf_token', C.csrf); body.append('campus', C.campus); }
    else body = JSON.stringify(Object.assign({ csrf_token: C.csrf, campus: C.campus }, data));
    return fetch(C.api, { method: 'POST', credentials: 'same-origin', headers: file ? {} : { 'Content-Type': 'application/json' }, body: body })
      .then(function (r) { return r.json().catch(function () { return { error: 'Something went wrong. Try again.' }; }); });
  }
  function get(params) {
    var q = new URLSearchParams(Object.assign({ campus: C.campus }, params));
    return fetch(C.api + '?' + q, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; });
  }

  /* ---------- rendering ---------- */
  function reactsHtml(m) {
    if (!m.r || !m.r.length || m.kind === 'WIN') return '';
    return '<div class="cr-reacts' + (m.mine ? ' me' : '') + '">' + m.r.map(function (r) {
      return '<button type="button" data-react="' + m.id + '" data-e="' + esc(r.e) + '" class="' + (r.m ? 'mine' : '') + '">' + esc(r.e) + '<small>' + r.n + '</small></button>';
    }).join('') + '</div>';
  }
  function winHtml(m, inThread) {
    var counts = {}; (m.r || []).forEach(function (r) { counts[r.e] = r; });
    var rx = ['🔥', '👏', '🎉'].map(function (e) { var r = counts[e]; return '<button type="button" data-react="' + m.id + '" data-e="' + e + '" class="' + (r && r.m ? 'mine' : '') + '">' + e + '<small>' + (r ? r.n : 0) + '</small></button>'; }).join('');
    return '<div class="cr-win"><div class="wh"><span class="tag">🏆 WIN</span><span><b>' + esc(m.mine ? 'You' : m.who) + '</b><small>' + esc(m.time) + '</small></span></div>' +
      (m.image ? '<div class="wimg"><img src="' + esc(m.image) + '" alt="" loading="lazy" data-view></div>' : '') +
      '<div class="wbody"><h4>' + esc(m.title) + '</h4>' + (m.text ? '<p>' + linkify(m.text) + '</p>' : '') + '</div>' +
      '<div class="wfoot"><span class="rx">' + rx + '</span>' + (inThread ? '' : '<button type="button" class="cm" data-open-win="' + m.id + '">' + I.chat + m.c + ' comment' + (m.c === 1 ? '' : 's') + '</button>') + '</div></div>';
  }
  function msgHtml(m, prev, fresh, inThread) {
    var cont = prev && prev.uid === m.uid && prev.day === m.day && prev.kind !== 'WIN' && m.kind !== 'WIN';
    var cls = 'cr-m' + (m.mine ? ' me' : '') + (cont ? ' cont' : '') + (m.kind === 'STICKER' ? ' stkm' : '') + (m.kind === 'WIN' ? ' winm' : '') + (m.kind === 'DELETED' ? ' gone' : '') + (fresh ? ' new' : '');
    var who = '<span class="cr-who" style="color:' + m.color + '">' + esc(m.who) + (m.tag ? '<em>' + esc(m.tag) + '</em>' : '') + '</span>';
    var meta = '<span class="cr-meta">' + esc(m.time) + (m.mine ? I.tick : '') + '</span>';
    var q = m.reply ? '<button type="button" class="cr-quote" data-jump="' + m.reply.id + '"><b>' + esc(m.reply.who) + '</b><span>' + esc(m.reply.text) + '</span></button>' : '';
    var body;
    if (m.kind === 'DELETED') body = '🚫 This message was deleted' + meta;
    else if (m.kind === 'WIN') body = winHtml(m, inThread);
    else if (m.kind === 'STICKER') body = q + sticker(m.sticker) + meta;
    else if (m.kind === 'IMAGE') body = who + q + '<img class="photo" src="' + esc(m.image) + '" alt="" loading="lazy" data-view>' + (m.text ? '<div class="cr-tx">' + linkify(m.text) + '</div>' : '') + meta;
    else body = who + q + '<div class="cr-tx">' + (onlyEmoji(m.text) ? '<span class="big">' + esc(m.text) + '</span>' : linkify(m.text)) + meta + '</div>';
    var av = m.avatar ? '<img src="' + esc(m.avatar) + '" alt="">' : esc((m.who || '?').charAt(0).toUpperCase());
    return '<div class="' + cls + '" data-id="' + m.id + '"><span class="av" style="background:' + m.color + '">' + av + '</span><div class="cr-b"><span class="cr-swipe">' + I.reply + '</span>' + body + '</div></div>' + reactsHtml(m);
  }
  function visible(m) { return filter === 'all' || (filter === 'win' && m.kind === 'WIN') || (filter === 'prof' && m.tag); }
  function render(opts) {
    opts = opts || {};
    var atBottom = msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight < 120;
    var keepFrom = opts.keep ? msgsEl.scrollHeight - msgsEl.scrollTop : null;
    var html = more && filter === 'all' ? '<button type="button" class="cr-more" data-more>Load earlier messages</button>' : '';
    var prev = null, lastDay = null, shown = 0;
    list.forEach(function (m) {
      if (!visible(m)) return;
      if (m.day !== lastDay) { html += '<span class="cr-day">' + esc(dayLabel(m.day)) + '</span>'; lastDay = m.day; prev = null; }
      html += msgHtml(m, prev, opts.fresh && opts.fresh[m.id]); prev = m; shown++;
    });
    if (!shown) html += '<div class="cr-empty"><i>' + (filter === 'win' ? '🏆' : '👋') + '</i><b>' + (filter === 'win' ? 'No wins yet' : filter === 'prof' ? 'Nothing from your professor yet' : 'Say hello to your class') + '</b><span>' + (filter === 'win' ? 'Be the first. Tap the trophy and share something you achieved.' : 'Ask a question, share your progress or help a classmate.') + '</span></div>';
    msgsEl.innerHTML = html;
    if (keepFrom !== null) msgsEl.scrollTop = msgsEl.scrollHeight - keepFrom;
    else if (opts.bottom || atBottom) msgsEl.scrollTop = msgsEl.scrollHeight;
  }

  /* ---------- reply ---------- */
  var rb = $('#cr-reply');
  function setReply(id) {
    var m = byId[id]; if (!m || m.kind === 'DELETED') return;
    replyTo = id; $('#cr-reply-who').textContent = m.mine ? 'You' : m.who; $('#cr-reply-tx').textContent = preview(m);
    rb.classList.add('on'); input.focus();
  }
  function clearReply() { replyTo = null; rb.classList.remove('on'); }
  $('#cr-reply-x').addEventListener('click', clearReply);

  /* ---------- message menu: reactions, reply, copy, delete ---------- */
  var bar = null;
  function closeBar() { if (bar) { bar.remove(); bar = null; } }
  function openBar(mEl, host) {
    closeBar();
    var id = +mEl.dataset.id, m = byId[id] || (threadState.byId && threadState.byId[id]);
    if (!m || m.kind === 'DELETED') return;
    var r = mEl.querySelector('.cr-b').getBoundingClientRect(), hr = host.getBoundingClientRect();
    bar = document.createElement('div'); bar.className = 'cr-act'; bar.dataset.id = id;
    bar.innerHTML = QUICK.map(function (e) { return '<button type="button" data-qr="' + e + '" aria-label="React ' + e + '">' + e + '</button>'; }).join('') + '<span class="sep"></span>' +
      (host === chatEl ? '<button type="button" class="tool" data-rep>' + I.reply + 'Reply</button>' : '') +
      (m.kind === 'TEXT' ? '<button type="button" class="tool" data-copy>' + I.copy + 'Copy</button>' : '') +
      (m.del ? '<button type="button" class="tool red" data-del>' + I.bin + 'Delete</button>' : '');
    host.appendChild(bar);
    var w = bar.offsetWidth, left = Math.min(Math.max(8, (m.mine ? r.right - w : r.left) - hr.left), hr.width - w - 8);
    bar.style.left = left + 'px'; bar.style.top = Math.max(56, r.top - hr.top - 52) + 'px';
  }
  function applyState(id, st) {
    var m = byId[id] || (threadState.byId && threadState.byId[id]); if (!m) return false;
    var before = JSON.stringify([m.r, m.c, m.kind]);
    m.r = st.r || []; m.c = st.c || 0; if (st.x) { m.kind = 'DELETED'; m.text = ''; m.r = []; m.del = false; }
    return before !== JSON.stringify([m.r, m.c, m.kind]);
  }
  function react(id, e) {
    var m = byId[id] || (threadState.byId && threadState.byId[id]); if (!m) return;
    // Show it straight away; the server's answer settles the counts.
    var r = (m.r || []).map(function (x) { return Object.assign({}, x); }), mine = r.filter(function (x) { return x.m; })[0];
    r.forEach(function (x) { if (x.m) { x.n--; x.m = false; } });
    if (!mine || mine.e !== e) { var t = r.filter(function (x) { return x.e === e; })[0]; if (t) { t.n++; t.m = true; } else r.push({ e: e, n: 1, m: true }); }
    m.r = r.filter(function (x) { return x.n > 0; });
    redraw();
    api({ action: 'react', id: id, emoji: e }).then(function (res) { if (res.state && res.state[id]) { applyState(id, res.state[id]); redraw(); } else if (res.error) toast(res.error); });
  }
  function redraw() { render({ keep: true }); if (threadState.open) drawThread(); }

  var chatEl = $('.cr-chat');
  function onMsgClick(e, host) {
    var rx = e.target.closest('[data-react]'); if (rx) { react(+rx.dataset.react, rx.dataset.e); return true; }
    var j = e.target.closest('[data-jump]');
    if (j) { var t = msgsEl.querySelector('.cr-m[data-id="' + j.dataset.jump + '"]'); if (t) { t.scrollIntoView({ block: 'center', behavior: 'smooth' }); t.classList.remove('flash'); void t.offsetWidth; t.classList.add('flash'); } else toast('That message is further up. Load earlier messages to see it.'); return true; }
    var v = e.target.closest('[data-view]'); if (v) { var vw = $('.cr-view'); vw.querySelector('img').src = v.src; vw.classList.add('on'); return true; }
    if (e.target.closest('[data-open-win]') || e.target.closest('[data-more]') || e.target.closest('a')) return false;
    var mEl = e.target.closest('.cr-m'); if (mEl && !swipe.moved) { openBar(mEl, host); return true; }
    return false;
  }
  msgsEl.addEventListener('click', function (e) { onMsgClick(e, chatEl); });
  // The pinned professor message jumps to the original.
  var pin = $('.cr-pin');
  if (pin) pin.addEventListener('click', function () { var t = msgsEl.querySelector('.cr-m[data-id="' + pin.dataset.jump + '"]'); if (t) { t.scrollIntoView({ block: 'center', behavior: 'smooth' }); t.classList.remove('flash'); void t.offsetWidth; t.classList.add('flash'); } else toast('That message is further up. Load earlier messages to see it.'); });
  document.addEventListener('click', function (e) {
    if (!bar) return;
    var q = e.target.closest('[data-qr]'); if (q) { react(+bar.dataset.id, q.dataset.qr); closeBar(); return; }
    if (e.target.closest('[data-rep]')) { setReply(+bar.dataset.id); closeBar(); return; }
    if (e.target.closest('[data-copy]')) { var m = byId[+bar.dataset.id]; if (m && navigator.clipboard) navigator.clipboard.writeText(m.text).then(function () { toast('Copied'); }, function () {}); closeBar(); return; }
    if (e.target.closest('[data-del]')) {
      var id = +bar.dataset.id; closeBar();
      if (!confirm('Delete this message for everyone?')) return;
      api({ action: 'delete', id: id }).then(function (res) { if (res.ok) { applyState(id, { r: [], c: 0, x: true }); redraw(); } else toast(res.error || 'Could not delete it.'); });
      return;
    }
    if (!e.target.closest('.cr-act') && !e.target.closest('.cr-m')) closeBar();
  });
  msgsEl.addEventListener('scroll', closeBar, { passive: true });
  // Stay at the newest message while photos finish loading (they change the height).
  var stick = true;
  msgsEl.addEventListener('scroll', function () { stick = msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight < 140; }, { passive: true });
  msgsEl.addEventListener('load', function (e) { if (e.target.tagName === 'IMG' && stick) msgsEl.scrollTop = msgsEl.scrollHeight; }, true);

  // Swipe a message right to reply, as in WhatsApp (touch screens).
  var swipe = { el: null, x: 0, moved: false };
  msgsEl.addEventListener('pointerdown', function (e) { var m = e.target.closest('.cr-m'); if (!m || e.pointerType === 'mouse' || m.classList.contains('gone')) return; swipe.el = m; swipe.x = e.clientX; swipe.y = e.clientY; swipe.moved = false; });
  msgsEl.addEventListener('pointermove', function (e) {
    if (!swipe.el) return;
    if (Math.abs(e.clientY - swipe.y) > 24) { endSwipe(e, true); return; }
    var dx = Math.max(0, Math.min(80, e.clientX - swipe.x)); if (dx > 8) swipe.moved = true;
    var b = swipe.el.querySelector('.cr-b'), ic = swipe.el.querySelector('.cr-swipe');
    b.style.transform = 'translateX(' + dx + 'px)'; ic.style.opacity = dx / 60; ic.style.transform = 'scale(' + Math.min(1, .6 + dx / 150) + ')';
  });
  function endSwipe(e, cancel) {
    if (!swipe.el) return;
    var dx = e.clientX - swipe.x, b = swipe.el.querySelector('.cr-b'), ic = swipe.el.querySelector('.cr-swipe');
    b.style.transform = ''; ic.style.opacity = ''; ic.style.transform = '';
    if (!cancel && dx > 60) { setReply(+swipe.el.dataset.id); if (navigator.vibrate) try { navigator.vibrate(15); } catch (x) {} }
    swipe.el = null; setTimeout(function () { swipe.moved = false; }, 60);
  }
  msgsEl.addEventListener('pointerup', endSwipe); msgsEl.addEventListener('pointercancel', function (e) { endSwipe(e, true); });

  /* ---------- sending ---------- */
  function lastId() { return list.length ? list[list.length - 1].id : 0; }
  function addMessages(rows, fromPoll) {
    var fresh = {}, others = 0;
    rows.forEach(function (m) { if (byId[m.id]) return; byId[m.id] = m; list.push(m); fresh[m.id] = true; if (!m.mine) others++; });
    if (!Object.keys(fresh).length) return;
    list.sort(function (a, b) { return a.id - b.id; });
    render({ fresh: fresh, bottom: !fromPoll });
    if (fromPoll && others && C.sound && window.ObinChime) window.ObinChime('in');
  }
  var sending = false;
  function send(data, file) {
    if (sending) return Promise.resolve(null);
    sending = true; sendBtn.disabled = true; showErr('');
    if (replyTo && !data.parent_id) data.reply_to = replyTo;
    return api(Object.assign({ action: 'send' }, data), file).then(function (res) {
      sending = false; sendBtn.disabled = false;
      if (res.error) { showErr(res.error); return null; }
      if (!data.parent_id) { clearReply(); addMessages([res.message]); }
      return res.message;
    }).catch(function () { sending = false; sendBtn.disabled = false; showErr('Could not send. Check your connection and try again.'); return null; });
  }
  function grow(t) { t.style.height = 'auto'; t.style.height = Math.min(120, t.scrollHeight) + 'px'; }
  input.addEventListener('input', function () { grow(input); });
  function sendText() {
    var t = input.value.trim(); if (!t) { input.focus(); return; }
    send({ kind: 'TEXT', text: t }).then(function (m) { if (m) { input.value = ''; grow(input); } });
  }
  sendBtn.addEventListener('click', sendText);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !('ontouchstart' in window && window.innerWidth < 700)) { e.preventDefault(); sendText(); } });

  // Photos
  var pf = $('#cr-photo-file'), pb = $('#cr-photo');
  if (pb) pb.addEventListener('click', function () { pf.click(); });
  pf.addEventListener('change', function () {
    var f = pf.files[0]; if (!f) return;
    if (f.size > 8 * 1024 * 1024) { showErr('That photo is over 8MB. Choose a smaller one.'); pf.value = ''; return; }
    send({ kind: 'IMAGE', text: input.value.trim() }, f).then(function (m) { if (m) { input.value = ''; grow(input); } });
    pf.value = '';
  });

  /* ---------- emoji + stickers ---------- */
  var drawer = $('#cr-drawer'), eBtn = $('#cr-emoji');
  eBtn.addEventListener('click', function () { var on = drawer.classList.toggle('on'); eBtn.classList.toggle('on', on); eBtn.setAttribute('aria-expanded', on); });
  var cats = Object.keys(EMO), cur = cats[0];
  function drawEmoji() {
    $('#cr-dcats').innerHTML = cats.map(function (c) { return '<button type="button" data-cat="' + c + '" aria-pressed="' + (c === cur) + '">' + c + '</button>'; }).join('');
    $('#cr-egrid').innerHTML = EMO[cur].split(' ').map(function (e) { return '<button type="button" data-emo="' + e + '">' + e + '</button>'; }).join('');
  }
  drawEmoji();
  $('#cr-sgrid').innerHTML = Object.keys(C.stickers).map(function (k) { return '<button type="button" data-stk="' + k + '" aria-label="Sticker: ' + esc(C.stickers[k][2]) + '">' + sticker(k) + '</button>'; }).join('');
  drawer.addEventListener('click', function (e) {
    var c = e.target.closest('[data-cat]'); if (c) { cur = c.dataset.cat; drawEmoji(); return; }
    var em = e.target.closest('[data-emo]');
    if (em) { var s = input.selectionStart != null ? input.selectionStart : input.value.length, en = input.selectionEnd != null ? input.selectionEnd : s; input.value = input.value.slice(0, s) + em.dataset.emo + input.value.slice(en); input.selectionStart = input.selectionEnd = s + em.dataset.emo.length; grow(input); return; }
    var st = e.target.closest('[data-stk]'); if (st) { send({ kind: 'STICKER', sticker: st.dataset.stk }); return; }
    var d = e.target.closest('[data-d]'); if (d) { drawer.querySelectorAll('[data-d]').forEach(function (b) { b.setAttribute('aria-pressed', b === d); }); $('#cr-d-emoji').hidden = d.dataset.d !== 'emoji'; $('#cr-d-stk').hidden = d.dataset.d !== 'stk'; }
  });

  /* ---------- filters ---------- */
  var tabs = $('.cr-tabs');
  if (tabs) {
    var knob = tabs.querySelector('.knob');
    var place = function (b) { knob.style.width = b.offsetWidth + 'px'; knob.style.transform = 'translateX(' + (b.offsetLeft - 3) + 'px)'; };
    place(tabs.querySelector('[aria-pressed="true"]'));
    addEventListener('resize', function () { place(tabs.querySelector('[aria-pressed="true"]')); });
    tabs.addEventListener('click', function (e) { var b = e.target.closest('[data-f]'); if (!b) return; tabs.querySelectorAll('[data-f]').forEach(function (x) { x.setAttribute('aria-pressed', x === b); }); place(b); filter = b.dataset.f; render({ bottom: true }); });
  }

  // Load earlier
  msgsEl.addEventListener('click', function (e) {
    var b = e.target.closest('[data-more]'); if (!b) return;
    b.textContent = 'Loading…';
    get({ before: list.length ? list[0].id : 0 }).then(function (d) {
      if (!d) return;
      d.messages.forEach(function (m) { if (!byId[m.id]) { byId[m.id] = m; list.push(m); } });
      list.sort(function (a, b2) { return a.id - b2.id; });
      more = !!d.more; render({ keep: true });
    });
  });

  /* ---------- sound switch ---------- */
  function setSound(on, save) {
    C.sound = on;
    root.querySelectorAll('[data-sound]').forEach(function (el) {
      if (el.getAttribute('role') === 'switch') el.setAttribute('aria-checked', on);
      else { el.classList.toggle('off', !on); el.setAttribute('aria-label', on ? 'Turn notification sound off' : 'Turn notification sound on'); el.innerHTML = on ? C.icons.bell : C.icons.bellOff; }
    });
    if (save) api({ action: 'sound', on: on ? 1 : 0 }).then(function () { toast(on ? 'Notification sound on' : 'Notification sound off'); if (on && window.ObinChime) window.ObinChime('alert'); });
  }
  root.querySelectorAll('[data-sound]').forEach(function (el) { el.addEventListener('click', function () { setSound(!C.sound, true); }); });
  setSound(!!C.sound, false);

  /* ---------- wins: post one, comment on one ---------- */
  var scrim = $('.cr-scrim'), modal = $('.cr-modal'), thread = $('.cr-thread');
  var threadState = { open: null, list: [], byId: {}, win: null };
  function openModal() { closeBar(); scrim.classList.add('on'); modal.classList.add('on'); setTimeout(function () { $('#cr-win-title').focus(); }, 250); }
  function closeAll() {
    scrim.classList.remove('on'); modal.classList.remove('on'); thread.classList.remove('on'); threadState.open = null;
    if (history.replaceState && /[?&]win=/.test(location.search)) history.replaceState(null, '', location.pathname + location.search.replace(/[?&]win=\d+/, '').replace(/^&/, '?'));
  }
  root.querySelectorAll('[data-share-win]').forEach(function (b) { b.addEventListener('click', openModal); });
  scrim.addEventListener('click', closeAll);
  root.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', closeAll); });
  addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(); closeBar(); $('.cr-view').classList.remove('on'); } });
  $('.cr-view').addEventListener('click', function () { this.classList.remove('on'); });
  var winFile = null, wf = $('#cr-win-file'), drop = $('.cr-drop');
  wf.addEventListener('change', function () {
    var f = wf.files[0]; if (!f) return; winFile = f;
    var old = drop.querySelector('img'); if (old) old.remove();
    var im = document.createElement('img'); im.alt = ''; im.src = URL.createObjectURL(f); drop.appendChild(im);
  });
  $('#cr-win-post').addEventListener('click', function (e) {
    var title = $('#cr-win-title').value.trim(); if (!title) { $('#cr-win-title').focus(); return; }
    var btn = this; btn.disabled = true;
    var x = e.clientX, y = e.clientY;
    send({ kind: 'WIN', title: title, text: $('#cr-win-text').value.trim() }, winFile).then(function (m) {
      btn.disabled = false;
      if (!m) { closeAll(); return; }
      confetti(x, y); closeAll();
      $('#cr-win-title').value = ''; $('#cr-win-text').value = ''; winFile = null; wf.value = ''; var im = drop.querySelector('img'); if (im) im.remove();
    });
  });

  function drawThread() {
    var tb = $('.cr-th-b'), m = threadState.win; if (!m) return;
    var html = m.kind === 'DELETED' ? '<p class="cr-empty">This win was deleted.</p>' : winHtml(m, true), prev = null;
    threadState.list.forEach(function (c) { html += msgHtml(c, prev, false, true); prev = c; });
    if (!threadState.list.length) html += '<div class="cr-empty" style="margin-top:20px"><i>🎉</i><b>Be the first to cheer</b><span>Say well done, or ask how they did it.</span></div>';
    var atBottom = tb.scrollHeight - tb.scrollTop - tb.clientHeight < 80;
    tb.innerHTML = html; if (atBottom) tb.scrollTop = tb.scrollHeight;
  }
  function openThread(id) {
    closeBar();
    threadState = { open: id, list: [], byId: {}, win: byId[id] || null };
    if (threadState.win) threadState.byId[id] = threadState.win;
    drawThread(); scrim.classList.add('on'); thread.classList.add('on');
    get({ thread: id }).then(function (d) {
      if (!d || threadState.open !== id) { if (d === null) toast('That win is no longer here.'); return; }
      threadState.win = d.win; threadState.byId[id] = d.win; if (byId[id]) Object.assign(byId[id], d.win);
      threadState.list = d.messages; d.messages.forEach(function (c) { threadState.byId[c.id] = c; });
      drawThread(); $('.cr-th-b').scrollTop = 1e9;
    });
  }
  root.addEventListener('click', function (e) { var o = e.target.closest('[data-open-win]'); if (o) openThread(+o.dataset.openWin); });
  $('.cr-th-b').addEventListener('click', function (e) { onMsgClick(e, thread); });
  var thInput = $('#cr-th-input');
  thInput.addEventListener('input', function () { grow(thInput); });
  function sendComment() {
    var t = thInput.value.trim(), id = threadState.open; if (!t || !id) return;
    api({ action: 'send', kind: 'TEXT', text: t, parent_id: id }).then(function (res) {
      if (res.error) { toast(res.error); return; }
      thInput.value = ''; grow(thInput);
      if (threadState.open !== id) return;
      threadState.list.push(res.message); threadState.byId[res.message.id] = res.message;
      if (byId[id]) { byId[id].c = (byId[id].c || 0) + 1; render({ keep: true }); }
      drawThread(); $('.cr-th-b').scrollTop = 1e9;
    });
  }
  $('#cr-th-send').addEventListener('click', sendComment);
  thInput.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && window.innerWidth >= 700) { e.preventDefault(); sendComment(); } });

  function confetti(x, y) {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var c = document.createElement('div'); c.className = 'cr-confetti'; c.style.left = (x || innerWidth / 2) + 'px'; c.style.top = (y || innerHeight / 2) + 'px';
    var cols = ['#cdfb5e', '#3b33ff', '#ffcf4a', '#ff5a5f', '#ffffff'];
    for (var k = 0; k < 40; k++) { var i = document.createElement('i'), a = Math.random() * Math.PI * 2, d = 80 + Math.random() * 180; i.style.background = cols[k % cols.length]; i.style.setProperty('--x', Math.cos(a) * d + 'px'); i.style.setProperty('--y', Math.sin(a) * d - 60 + 'px'); i.style.setProperty('--rot', (Math.random() * 720 - 360) + 'deg'); c.appendChild(i); }
    document.body.appendChild(c); setTimeout(function () { c.remove(); }, 1500);
  }

  /* ---------- live updates ---------- */
  var polling = false;
  function poll() {
    if (polling) return; polling = true;
    var since = list.length ? list[Math.max(0, list.length - 60)].id : 0;
    var p = { after: lastId(), since: since };
    if (!document.hidden) p.seen = 1;
    get(p).then(function (d) {
      polling = false; if (!d) return;
      var changed = false;
      Object.keys(d.state || {}).forEach(function (id) { if (applyState(+id, d.state[id])) changed = true; });
      if (d.messages && d.messages.length) addMessages(d.messages, true);
      else if (changed) redraw();
    }).catch(function () { polling = false; });
    if (threadState.open) {
      var id = threadState.open, after = threadState.list.length ? threadState.list[threadState.list.length - 1].id : 0;
      get({ thread: id, after: after }).then(function (d) {
        if (!d || threadState.open !== id) return;
        var add = d.messages.filter(function (c) { return !threadState.byId[c.id]; });
        threadState.win = d.win;
        add.forEach(function (c) { threadState.list.push(c); threadState.byId[c.id] = c; });
        if (add.length) { drawThread(); if (add.some(function (c) { return !c.mine; }) && C.sound && window.ObinChime) window.ObinChime('in'); }
      });
    }
  }
  setInterval(function () { if (!document.hidden) poll(); }, 4000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

  render({ bottom: true });
  if (C.openWin) openThread(+C.openWin);
})();
