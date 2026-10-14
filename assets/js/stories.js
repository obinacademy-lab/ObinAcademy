/* Video stories (render_video_story_card): tap the play button to watch with sound and the native
   controls; only one story plays at a time; a finished story goes back to its cover. */
(function () {
  const cards = document.querySelectorAll('.vs-card');
  if (!cards.length) return;
  const videos = [];
  cards.forEach(card => {
    const v = card.querySelector('video'), btn = card.querySelector('.vs-play');
    if (!v || !btn) return;
    videos.push(v);
    const start = () => {
      videos.forEach(o => { if (o !== v) o.pause(); });
      if (v.currentTime < 1) v.currentTime = 0;
      v.controls = true;
      v.play().catch(() => {});
    };
    btn.addEventListener('click', start);
    v.addEventListener('play', () => { card.classList.add('playing'); videos.forEach(o => { if (o !== v) o.pause(); }); });
    v.addEventListener('ended', () => { card.classList.remove('playing'); v.controls = false; v.currentTime = .5; });
  });
})();
