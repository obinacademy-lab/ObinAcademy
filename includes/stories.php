<?php
/**
 * Video stories: real students talking about their campus, recorded on video and uploaded by the admin
 * (dashboard/admin/testimonials.php). No profile pictures: the video is the proof. Shown on the home
 * page, the About page and stories.php once published. Files live in public uploads/stories/ so the web
 * server can stream them directly.
 */

const VIDEO_STORY_QUOTE_MAX = 280;

/** True once migration/add-video-stories.sql has been applied. */
function video_stories_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT 1 FROM video_stories LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

/** Every story with its campus name, in the order they show on the site. */
function get_video_stories(?string $status = null): array {
    if (!video_stories_ready()) return [];
    $sql = 'SELECT s.*, c.name AS campus_name, c.slug AS campus_slug, c.status AS campus_status FROM video_stories s LEFT JOIN campuses c ON c.id = s.campus_id';
    $params = [];
    if ($status) { $sql .= ' WHERE s.status = ?'; $params[] = $status; }
    return db_all($sql . ' ORDER BY s.sort_order ASC, s.id DESC', $params);
}

/** Published stories for the public pages, at most $limit. */
function get_published_video_stories(int $limit = 0): array {
    $all = get_video_stories('PUBLISHED');
    return $limit > 0 ? array_slice($all, 0, $limit) : $all;
}

/** "1:24" from seconds, or '' when unknown. */
function video_story_length(?int $seconds): string {
    if (!$seconds) return '';
    return floor($seconds / 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}

/**
 * One story card for the public pages: the video (first frame as the cover, plays with sound on tap),
 * the pull quote, the student's name and campus, then a button to join: the story's own campus when it
 * is open, otherwise the list of campuses. assets/css/stories.css + assets/js/stories.js.
 */
function render_video_story_card(array $s): void {
    $len = video_story_length($s['duration_seconds'] !== null ? (int) $s['duration_seconds'] : null);
    $open = !empty($s['campus_slug']) && ($s['campus_status'] ?? '') === 'PUBLISHED';
    $ctaUrl = $open ? base_url('campus.php?slug=' . urlencode($s['campus_slug'])) : base_url('campuses.php');
    $ctaText = $open ? 'Join this campus' : 'Explore the campuses';
    ?>
    <figure class="vs-card">
      <div class="vs-media">
        <video src="<?= e(base_url($s['video_url'])) ?>#t=0.5" preload="metadata" playsinline controlslist="nodownload" aria-label="<?= e($s['student_name']) ?> talks about the <?= e($s['campus_name'] ?: 'Obin Academy') ?> Campus"></video>
        <button type="button" class="vs-play" aria-label="Play <?= e($s['student_name']) ?>'s story"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg></button>
        <?php if ($len): ?><span class="vs-len"><?= e($len) ?></span><?php endif; ?>
        <?php if ($s['campus_name']): ?><span class="vs-campus"><?= e($s['campus_name']) ?></span><?php endif; ?>
      </div>
      <figcaption>
        <q><?= e($s['quote']) ?></q>
        <span class="vs-who"><b><?= e($s['student_name']) ?></b><?= $s['campus_name'] ? '<small>' . e($s['campus_name']) . ' Campus</small>' : '' ?></span>
        <a class="vs-join" href="<?= e($ctaUrl) ?>"<?= $open ? ' aria-label="Join the ' . e($s['campus_name']) . ' Campus"' : '' ?>><span><?= e($ctaText) ?></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
      </figcaption>
    </figure>
    <?php
}
