/* Admin Overview desk (dashboard/admin/index.php): a soft ripple from where a desk button is pressed.
   Charts, numbers and the range switch are drawn by admin-overview.js. */
(function () {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.addEventListener('pointerdown', function (e) {
    var b = e.target.closest('.dk-btn'); if (!b) return;
    var r = b.getBoundingClientRect(), s = document.createElement('span');
    s.className = 'dk-rp'; s.style.left = (e.clientX - r.left) + 'px'; s.style.top = (e.clientY - r.top) + 'px';
    b.appendChild(s); setTimeout(function () { s.remove(); }, 800);
  });
})();
