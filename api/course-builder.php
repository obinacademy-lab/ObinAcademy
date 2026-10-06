<?php
/**
 * JSON endpoint behind the upload-first course builder. One POST per action;
 * every response is {ok:true, state:{course, modules}} (see builder_state()) so
 * the page can redraw straight from what the server now holds.
 *
 * Most actions send JSON. upload_lesson and upload_thumbnail send multipart
 * form data (a file can't ride in JSON), with the CSRF token as a form field.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/audit.php';
require __DIR__ . '/../includes/course_builder.php';

$user = api_require_login();
if (!in_array($user['role'], ['CREATOR', 'ADMIN'], true)) json_response(['error' => 'Only creators can build courses.'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST only.'], 405);

$isJson = str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');

// A file bigger than PHP's post_max_size arrives with $_POST and $_FILES both
// empty, which would otherwise look like a bad CSRF token. Say what happened.
if (!$isJson && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    json_response(['error' => 'That file is larger than this server accepts (limit ' . ini_get('post_max_size') . '). Try a smaller or compressed file.'], 413);
}

$in = $isJson ? json_body() : $_POST;
api_csrf_verify($in);
$action = (string) ($in['action'] ?? '');

$str = fn(string $k): string => trim((string) ($in[$k] ?? ''));

/** Loads a course the user may edit; 404 otherwise. */
function builder_course(array $user, int $id): array {
    $course = db_one('SELECT * FROM courses WHERE id = ?', [$id]);
    $isAdmin = $user['role'] === 'ADMIN';
    if (!$course || ((int) $course['creator_id'] !== (int) $user['id'] && !$isAdmin)) {
        json_response(['error' => 'Course not found.'], 404);
    }
    return $course;
}

function builder_note_admin(array $user, array $course, string $action, string $detail): void {
    if ((int) $course['creator_id'] === (int) $user['id'] || $user['role'] !== 'ADMIN') return;
    log_admin_action((int) $user['id'], $user['name'] ?: $user['email'], $action, 'Course', $course['title'], $detail);
}

function builder_ok(int $courseId, array $extra = []): never {
    json_response(['ok' => true, 'state' => builder_state($courseId)] + $extra);
}

// ---- create: first file (or "details only") makes the draft -----------------
if ($action === 'create') {
    $id = db_insert(
        "INSERT INTO courses (title, slug, summary, description, price, category_id, subscription_included, creator_id, status)
         VALUES (?, ?, '', '', 0, ?, 1, ?, 'DRAFT')",
        [UNTITLED_COURSE, builder_unique_slug(UNTITLED_COURSE . '-' . bin2hex(random_bytes(3))), uncategorized_category_id(), $user['id']]
    );
    builder_ok($id);
}

$courseId = (int) ($in['courseId'] ?? 0);
$course = builder_course($user, $courseId);
if (!in_array($course['status'], ['DRAFT', 'REJECTED'], true)) {
    json_response(['error' => 'This course is already submitted. Manage it from My Courses.'], 409);
}
$creator = db_one('SELECT pricing_model, school_monthly_price FROM users WHERE id = ?', [$course['creator_id']]);
$creatorHasSubscription = $creator['pricing_model'] === 'MONTHLY_SUBSCRIPTION' && (float) $creator['school_monthly_price'] > 0;

try {
    switch ($action) {
        case 'state':
            builder_ok($courseId);

        case 'details': {
            $sets = [];
            $params = [];
            if (array_key_exists('title', $in)) {
                $title = mb_substr($str('title'), 0, 191);
                if ($title === '') $title = UNTITLED_COURSE;
                $sets[] = 'title = ?'; $params[] = $title;
                // Nobody has the link to a draft yet, so its URL can follow its name.
                if (empty($course['submitted_at'])) {
                    $sets[] = 'slug = ?';
                    $params[] = builder_unique_slug($title === UNTITLED_COURSE ? UNTITLED_COURSE . '-' . $courseId : $title, $courseId);
                }
            }
            if (!empty($in['categoryId'])) {
                $cat = db_one('SELECT id FROM categories WHERE id = ? AND slug <> ?', [(int) $in['categoryId'], UNCATEGORIZED_SLUG]);
                if ($cat) { $sets[] = 'category_id = ?'; $params[] = (int) $cat['id']; }
            }
            if (array_key_exists('summary', $in)) { $sets[] = 'summary = ?'; $params[] = mb_substr($str('summary'), 0, 500); }
            if (array_key_exists('description', $in)) { $sets[] = 'description = ?'; $params[] = $str('description'); }

            // Pricing: a subscription school's included course never carries its own price.
            $included = $creatorHasSubscription && (int) ($in['subscriptionIncluded'] ?? $course['subscription_included']) === 0 ? 0 : 1;
            if (array_key_exists('subscriptionIncluded', $in) || array_key_exists('price', $in) || array_key_exists('kind', $in)) {
                $price = array_key_exists('price', $in) ? max(0.0, (float) $in['price']) : (float) $course['price'];
                if (($in['kind'] ?? '') === 'free' || ($creatorHasSubscription && $included === 1)) $price = 0.0;
                $sets[] = 'subscription_included = ?'; $params[] = $included;
                $sets[] = 'price = ?'; $params[] = $price;
                // Installments are the creator's choice (course page); here they can only be switched off.
                if ($price <= 0 || ($creatorHasSubscription && $included === 1)) $sets[] = 'installments_enabled = 0';
                $sets[] = 'installment_count = ?'; $params[] = 2;
                if ($price <= 0 || ($course['sale_price'] !== null && (float) $course['sale_price'] >= $price)) {
                    $sets[] = 'sale_price = NULL'; $sets[] = 'sale_ends_at = NULL';
                }
            }
            if (array_key_exists('accessDurationDays', $in)) {
                $raw = $str('accessDurationDays');
                $sets[] = 'access_duration_days = ?'; $params[] = ($raw === '' || $raw === 'lifetime') ? null : max(1, (int) $raw);
            }
            if (array_key_exists('premiumPrice', $in)) {
                $raw = $str('premiumPrice');
                $sets[] = 'premium_price = ?'; $params[] = $raw === '' ? null : max(0.0, (float) $raw);
            }
            if ($sets) {
                $params[] = $courseId;
                db_run('UPDATE courses SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
            }
            builder_ok($courseId);
        }

        case 'upload_lesson': {
            if (empty($_FILES['file']['name'])) json_response(['error' => 'No file received.'], 400);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
            finfo_close($finfo);
            $type = $mime === 'application/pdf' ? 'PDF' : 'VIDEO';
            $fileUrl = save_upload($_FILES['file'], $type === 'VIDEO' ? 'videos' : 'pdfs');

            // Lessons go into the module the creator picked; with none picked they join the last
            // module, and the very first upload creates a default one.
            $wantModule = (int) ($in['moduleId'] ?? 0);
            if ($wantModule) {
                $module = db_one('SELECT id FROM modules WHERE id = ? AND course_id = ?', [$wantModule, $courseId]);
                if (!$module) json_response(['error' => 'That module no longer exists.'], 404);
            } else {
                $module = db_one('SELECT id FROM modules WHERE course_id = ? ORDER BY sort_order DESC, id DESC LIMIT 1', [$courseId]);
            }
            if ($module) {
                $moduleId = (int) $module['id'];
            } else {
                $moduleId = db_insert('INSERT INTO modules (title, sort_order, course_id) VALUES (?, 0, ?)', [DEFAULT_MODULE_TITLE, $courseId]);
            }
            $next = (int) db_one('SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM lessons WHERE module_id = ?', [$moduleId])['n'];
            $lessonId = db_insert(
                'INSERT INTO lessons (title, type, file_url, file_name, sort_order, module_id) VALUES (?, ?, ?, ?, ?, ?)',
                [builder_lesson_title($_FILES['file']['name']), $type, $fileUrl, mb_substr($_FILES['file']['name'], 0, 255), $next, $moduleId]
            );
            builder_ok($courseId, ['lessonId' => $lessonId]);
        }

        case 'add_module': {
            $title = mb_substr($str('title'), 0, 191);
            if ($title === '') $title = DEFAULT_MODULE_TITLE;
            $next = (int) db_one('SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM modules WHERE course_id = ?', [$courseId])['n'];
            $newId = db_insert('INSERT INTO modules (title, sort_order, course_id) VALUES (?, ?, ?)', [$title, $next, $courseId]);
            builder_ok($courseId, ['newModuleId' => $newId]);
        }

        case 'delete_module':
            // Its lessons go with it (the old manage page did the same, with a confirmation).
            db_run('DELETE FROM modules WHERE id = ? AND course_id = ?', [(int) $in['id'], $courseId]);
            builder_reindex($courseId);
            builder_ok($courseId);

        case 'upload_thumbnail': {
            if (empty($_FILES['file']['name'])) json_response(['error' => 'No image received.'], 400);
            $url = save_upload($_FILES['file'], 'thumbnails');
            db_run('UPDATE courses SET thumbnail_url = ? WHERE id = ?', [$url, $courseId]);
            builder_ok($courseId);
        }

        case 'rename': {
            $title = mb_substr($str('title'), 0, 191);
            if ($title === '') json_response(['error' => 'A title can\'t be empty.'], 400);
            if (($in['kind'] ?? '') === 'module') {
                db_run('UPDATE modules SET title = ? WHERE id = ? AND course_id = ?', [$title, (int) $in['id'], $courseId]);
            } else {
                db_run('UPDATE lessons JOIN modules ON modules.id = lessons.module_id SET lessons.title = ? WHERE lessons.id = ? AND modules.course_id = ?', [$title, (int) $in['id'], $courseId]);
            }
            builder_ok($courseId);
        }

        case 'remove_lesson':
            db_run('DELETE lessons FROM lessons JOIN modules ON modules.id = lessons.module_id WHERE lessons.id = ? AND modules.course_id = ?', [(int) $in['id'], $courseId]);
            // An empty module left behind goes too, unless it's the only one.
            db_run('DELETE m FROM modules m WHERE m.course_id = ? AND NOT EXISTS (SELECT 1 FROM lessons l WHERE l.module_id = m.id) AND (SELECT COUNT(*) FROM (SELECT id FROM modules WHERE course_id = ?) t) > 1', [$courseId, $courseId]);
            builder_reindex($courseId);
            builder_ok($courseId);

        case 'save_order': {
            // Full structure from the page: [{id: moduleId, lessons: [lessonId, ...]}, ...]
            $structure = $in['modules'] ?? [];
            if (!is_array($structure)) json_response(['error' => 'Bad order.'], 400);
            $ownModules = array_column(db_all('SELECT id FROM modules WHERE course_id = ?', [$courseId]), 'id');
            $ownLessons = array_column(db_all('SELECT l.id FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = ?', [$courseId]), 'id');
            $ownModules = array_map('intval', $ownModules);
            $ownLessons = array_map('intval', $ownLessons);
            $seen = [];
            db()->beginTransaction();
            foreach ($structure as $mi => $m) {
                $mid = (int) ($m['id'] ?? 0);
                if (!in_array($mid, $ownModules, true)) { db()->rollBack(); json_response(['error' => 'Bad module.'], 400); }
                db_run('UPDATE modules SET sort_order = ? WHERE id = ?', [$mi, $mid]);
                foreach (($m['lessons'] ?? []) as $li => $lid) {
                    $lid = (int) $lid;
                    if (!in_array($lid, $ownLessons, true) || isset($seen[$lid])) { db()->rollBack(); json_response(['error' => 'Bad lesson.'], 400); }
                    $seen[$lid] = true;
                    db_run('UPDATE lessons SET module_id = ?, sort_order = ? WHERE id = ?', [$mid, $li, $lid]);
                }
            }
            db()->commit();
            builder_ok($courseId);
        }

        case 'split': {
            // Everything after this lesson, within its module, moves to a new module right after it.
            $lesson = db_one('SELECT l.id, l.sort_order, l.module_id, m.sort_order AS m_sort FROM lessons l JOIN modules m ON m.id = l.module_id WHERE l.id = ? AND m.course_id = ?', [(int) $in['id'], $courseId]);
            if (!$lesson) json_response(['error' => 'Lesson not found.'], 404);
            $after = db_all('SELECT id FROM lessons WHERE module_id = ? AND sort_order > ? ORDER BY sort_order ASC, id ASC', [$lesson['module_id'], $lesson['sort_order']]);
            if (!$after) json_response(['error' => 'There is nothing after this lesson to move into a new module.'], 400);
            db_run('UPDATE modules SET sort_order = sort_order + 1 WHERE course_id = ? AND sort_order > ?', [$courseId, $lesson['m_sort']]);
            $newId = db_insert('INSERT INTO modules (title, sort_order, course_id) VALUES (?, ?, ?)', ['New module', (int) $lesson['m_sort'] + 1, $courseId]);
            foreach ($after as $i => $row) {
                db_run('UPDATE lessons SET module_id = ?, sort_order = ? WHERE id = ?', [$newId, $i, $row['id']]);
            }
            builder_reindex($courseId);
            builder_ok($courseId, ['newModuleId' => $newId]);
        }

        case 'merge': {
            // Folds a module's lessons into the one above it and removes the module.
            $mods = db_all('SELECT id FROM modules WHERE course_id = ? ORDER BY sort_order ASC, id ASC', [$courseId]);
            $ids = array_map('intval', array_column($mods, 'id'));
            $at = array_search((int) $in['id'], $ids, true);
            if ($at === false || $at === 0) json_response(['error' => 'The first module can\'t be merged upward.'], 400);
            $prev = $ids[$at - 1];
            $base = (int) db_one('SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM lessons WHERE module_id = ?', [$prev])['n'];
            foreach (db_all('SELECT id FROM lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC', [$ids[$at]]) as $i => $row) {
                db_run('UPDATE lessons SET module_id = ?, sort_order = ? WHERE id = ?', [$prev, $base + $i, $row['id']]);
            }
            db_run('DELETE FROM modules WHERE id = ? AND course_id = ?', [$ids[$at], $courseId]);
            builder_reindex($courseId);
            builder_ok($courseId);
        }

        case 'submit': {
            $problems = builder_submit_problems($courseId, $creatorHasSubscription, (string) ($in['kind'] ?? ''));
            if ($problems) json_response(['error' => 'Almost there.', 'problems' => $problems], 422);
            db_run("UPDATE courses SET status = 'PENDING_REVIEW', submitted_at = NOW(), rejection_reason = NULL WHERE id = ?", [$courseId]);
            builder_note_admin($user, $course, 'course.submitted', 'via course builder');
            builder_ok($courseId, ['submitted' => true]);
        }

        default:
            json_response(['error' => 'Unknown action.'], 400);
    }
} catch (RuntimeException $e) {
    // save_upload() throws these with messages meant for the creator (too large, wrong type...).
    json_response(['error' => $e->getMessage()], 400);
}
