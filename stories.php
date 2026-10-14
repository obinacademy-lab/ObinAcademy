<?php
/**
 * Stories: real students on video, talking about their campus. Uploaded and published by the admin
 * (dashboard/admin/testimonials.php) with the student's permission. No profile pictures, no star
 * ratings, nothing written for them: the video is the proof. Students who want to share theirs get
 * in touch through the contact page and we record it with them.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/stories.php';

$user = current_user();
$stories = get_published_video_stories();
$campusCount = count(array_unique(array_filter(array_column($stories, 'campus_id'))));

$pageTitle = 'Student Stories — Obin Academy';
$pageDescription = 'Real Obin Academy students on camera, in their own words: what they learned in their campus and what changed for them.';
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;1,6..72,400';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/stories.css')) ?>">
<div class="vs-page">
  <section class="vs-hero">
    <span class="vs-eyebrow"><i></i>Recorded by our students</span>
    <h1>Real students. <em>Their own words.</em></h1>
    <p><?php if ($stories): ?><?= count($stories) ?> student<?= count($stories) === 1 ? '' : 's' ?><?= $campusCount > 1 ? ' from ' . $campusCount . ' campuses' : '' ?> on camera, talking about what they learned and what changed. Tap a story to watch it with sound.<?php else: ?>Our students are recording their stories now. Check back soon to hear them in their own words.<?php endif; ?></p>
  </section>

  <div class="vs-body">
    <?php if ($stories): ?>
      <div class="vs-wall vs-dark">
        <?php foreach ($stories as $s) render_video_story_card($s); ?>
      </div>
    <?php else: ?>
      <div class="vs-empty">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/></svg>
        <b>The first stories are on their way</b>
        <p>Every story here is a real student on camera, shared with their permission.</p>
      </div>
    <?php endif; ?>
  </div>

  <section class="vs-share">
    <div>
      <div>
        <h2>Is a campus working for you?</h2>
        <p>Tell us. If you are happy to be filmed, we will help you record a short video (a phone is enough) and share it here, so the next student can see what is possible.</p>
      </div>
      <a class="vs-cta" href="<?= e(base_url('contact.php')) ?>">Share your story <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    </div>
  </section>
</div>
<script src="<?= e(versioned_asset('assets/js/stories.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
