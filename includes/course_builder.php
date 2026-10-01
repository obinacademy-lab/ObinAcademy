<?php
/**
 * Helpers for the upload-first course builder (dashboard/creator/course-build.php
 * and api/course-builder.php).
 *
 * A course starts as a DRAFT the moment the creator drops their first file.
 * courses.category_id is NOT NULL, so a draft that hasn't been categorised yet
 * points at a placeholder "Uncategorized" category (created on demand — no
 * migration needed). That placeholder never shows up in public category lists
 * (see get_categories()) and a course can't be submitted while it's still in it.
 */

const UNCATEGORIZED_SLUG = 'uncategorized';
const UNTITLED_COURSE = 'Untitled course';
const DEFAULT_MODULE_TITLE = 'Untitled module';
// Placeholder module names: a creator has to give every module a real name before submitting.
const UNNAMED_MODULE_TITLES = ['Untitled module', 'New module'];

function uncategorized_category_id(): int {
    $row = db_one('SELECT id FROM categories WHERE slug = ?', [UNCATEGORIZED_SLUG]);
    if ($row) return (int) $row['id'];
    return db_insert('INSERT INTO categories (name, slug) VALUES (?, ?)', ['Uncategorized', UNCATEGORIZED_SLUG]);
}

/** A slug for $title that no other course uses ($excludeId lets a course keep its own). */
function builder_unique_slug(string $title, int $excludeId = 0): string {
    $base = slugify($title) ?: 'course';
    $slug = $base;
    $n = 1;
    while (db_one('SELECT id FROM courses WHERE slug = ? AND id <> ?', [$slug, $excludeId])) {
        $slug = "$base-" . $n++;
    }
    return $slug;
}

/** "01_welcome-and-setup.mp4" -> "Welcome and setup" (falls back to the raw name). */
function builder_lesson_title(string $fileName): string {
    $t = preg_replace('/\.[^.]+$/', '', $fileName);
    $t = trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $t)));
    $t = trim(preg_replace('/^\d+\s*/', '', $t));
    if ($t === '') $t = trim($fileName);
    return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1, 150);
}

/** Closes gaps in modules.sort_order and lessons.sort_order after any move, split, merge or delete. */
function builder_reindex(int $courseId): void {
    $modules = db_all('SELECT id FROM modules WHERE course_id = ? ORDER BY sort_order ASC, id ASC', [$courseId]);
    foreach ($modules as $mi => $m) {
        db_run('UPDATE modules SET sort_order = ? WHERE id = ?', [$mi, $m['id']]);
        $lessons = db_all('SELECT id FROM lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC', [$m['id']]);
        foreach ($lessons as $li => $l) {
            db_run('UPDATE lessons SET sort_order = ? WHERE id = ?', [$li, $l['id']]);
        }
    }
}

function builder_format_bytes(int $b): string {
    if ($b >= 1048576) return round($b / 1048576, $b >= 104857600 ? 0 : 1) . ' MB';
    return max(1, (int) round($b / 1024)) . ' KB';
}

/** Everything the builder page needs to (re)draw itself, as plain arrays ready for json_encode. */
function builder_state(int $courseId): array {
    $c = db_one(
        'SELECT c.*, cat.slug AS category_slug FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id WHERE c.id = ?',
        [$courseId]
    );
    $modules = [];
    foreach (db_all('SELECT id, title FROM modules WHERE course_id = ? ORDER BY sort_order ASC, id ASC', [$courseId]) as $m) {
        $lessons = [];
        foreach (db_all('SELECT id, title, type, file_url, file_name FROM lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC', [$m['id']]) as $l) {
            $path = resolve_private_path($l['file_url']);
            $lessons[] = [
                'id' => (int) $l['id'],
                'title' => $l['title'],
                'type' => $l['type'],
                'fileName' => $l['file_name'],
                'size' => is_file($path) ? builder_format_bytes((int) filesize($path)) : '',
            ];
        }
        $modules[] = ['id' => (int) $m['id'], 'title' => $m['title'], 'lessons' => $lessons];
    }
    return [
        'course' => [
            'id' => (int) $c['id'],
            'status' => $c['status'],
            'title' => $c['title'] === UNTITLED_COURSE ? '' : $c['title'],
            'categoryId' => $c['category_slug'] === UNCATEGORIZED_SLUG ? 0 : (int) $c['category_id'],
            'price' => (float) $c['price'],
            'summary' => $c['summary'],
            'description' => $c['description'],
            'subscriptionIncluded' => (int) $c['subscription_included'],
            'accessDurationDays' => $c['access_duration_days'] === null ? 'lifetime' : (string) (int) $c['access_duration_days'],
            'premiumPrice' => $c['premium_price'] === null ? '' : (string) (float) $c['premium_price'],
            'thumbnailUrl' => $c['thumbnail_url'] ? asset_src($c['thumbnail_url']) : '',
            'rejectionReason' => $c['rejection_reason'],
        ],
        'modules' => $modules,
    ];
}

/** Why a draft can't be submitted yet (empty array = ready). $kind is the creator's explicit "free" choice. */
function builder_submit_problems(int $courseId, bool $creatorHasSubscription, string $kind): array {
    $c = db_one(
        'SELECT c.*, cat.slug AS category_slug FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id WHERE c.id = ?',
        [$courseId]
    );
    $p = [];
    if ($c['title'] === UNTITLED_COURSE || mb_strlen($c['title']) < 4) $p[] = 'Give your course a name (at least 4 characters).';
    if ($c['category_slug'] === UNCATEGORIZED_SLUG) $p[] = 'Pick a category.';
    $subIncluded = $creatorHasSubscription && (int) $c['subscription_included'] === 1;
    if (!$subIncluded && (float) $c['price'] <= 0 && $kind !== 'free') $p[] = 'Set a price, or choose Free.';
    if ($creatorHasSubscription && (int) $c['subscription_included'] === 0 && (float) $c['price'] <= 0) {
        $p[] = 'Set a price, since this course is sold separately from your subscription.';
    }
    if (mb_strlen($c['summary']) < 10) $p[] = 'Write a one-line summary (at least 10 characters).';
    if (mb_strlen($c['description']) < 20) $p[] = 'Describe what learners get (at least 20 characters).';
    $lessonCount = (int) db_one('SELECT COUNT(*) AS n FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = ?', [$courseId])['n'];
    if ($lessonCount === 0) $p[] = 'Add at least one module with a lesson.';
    $unnamed = false;
    $empty = false;
    foreach (db_all('SELECT m.title, (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id) AS n FROM modules m WHERE m.course_id = ?', [$courseId]) as $m) {
        if (trim($m['title']) === '' || in_array($m['title'], UNNAMED_MODULE_TITLES, true)) $unnamed = true;
        if ((int) $m['n'] === 0) $empty = true;
    }
    if ($unnamed) $p[] = 'Give every module a name.';
    if ($empty) $p[] = 'Every module needs at least one lesson. Add one, or delete the empty module.';
    return $p;
}
