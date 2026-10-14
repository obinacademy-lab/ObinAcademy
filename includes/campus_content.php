<?php
/**
 * A campus's teaching content (the twelve monthly stages, lessons and live classes), changed from two
 * places with exactly the same rules: the admin's campus page (dashboard/admin/campus-edit.php) and the
 * professor's Campus Studio (dashboard/campus-studio.php). Prices, campus details, publishing, pay and
 * members are not here: only Obin Academy changes those, on the admin page.
 *
 * Needs includes/storage.php, includes/campuses.php and includes/audit.php.
 */

const CAMPUS_CONTENT_ACTIONS = ['curriculum', 'lesson_add', 'lesson_delete', 'lesson_move', 'lesson_rename', 'live_add', 'live_delete'];

/**
 * Runs one content action from the posted form for $campus. Returns where to go next as
 * ['tab' => 'curriculum'|'lessons'|'live', 'month' => int|null]. Every change is written to the audit
 * log under the person who made it. Throws RuntimeException with a message for the person on bad input.
 */
function campus_content_handle(string $a, array $campus, array $user): array {
    $id = (int) $campus['id'];
    $log = fn(string $what, string $detail = '') => log_admin_action((int) $user['id'], $user['name'], 'campus.' . $what, 'Campus', $campus['name'], $detail);
    $lesson = fn() => db_one('SELECT * FROM campus_lessons WHERE id = ? AND campus_id = ?', [(int) post('lesson'), $id]);

    if ($a === 'curriculum') {
        for ($m = 1; $m <= CAMPUS_YEAR_MONTHS; $m++) {
            db_run(
                'INSERT INTO campus_stages (campus_id, month_no, title, summary) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary)',
                [$id, $m, mb_substr(trim($_POST['title'][$m] ?? ''), 0, 160), mb_substr(trim($_POST['summary'][$m] ?? ''), 0, 255)]
            );
        }
        $log('curriculum_updated');
        flash_set('success', 'Curriculum saved.');
        return ['tab' => 'curriculum', 'month' => null];
    }
    if ($a === 'lesson_add') {
        $month = max(1, min(CAMPUS_YEAR_MONTHS, (int) post('month')));
        $kind = post('kind') === 'PDF' ? 'PDF' : 'VIDEO';
        if (trim(post('title')) === '') throw new RuntimeException('Give the lesson a title.');
        if (empty($_FILES['file']['name'])) throw new RuntimeException('Choose the ' . ($kind === 'PDF' ? 'PDF' : 'video') . ' file to upload.');
        $key = save_upload($_FILES['file'], $kind === 'PDF' ? 'pdfs' : 'videos');
        $sort = (int) (db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM campus_lessons WHERE campus_id = ? AND month_no = ?', [$id, $month])['n'] ?? 1);
        db_insert(
            'INSERT INTO campus_lessons (campus_id, month_no, title, kind, file_url, file_name, duration_label, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $month, mb_substr(trim(post('title')), 0, 200), $kind, $key, mb_substr($_FILES['file']['name'], 0, 255), mb_substr(trim(post('duration')), 0, 40) ?: null, $sort]
        );
        $log('lesson_added', 'month ' . $month . ': ' . trim(post('title')));
        flash_set('success', 'Lesson added to month ' . $month . '.');
        return ['tab' => 'lessons', 'month' => $month];
    }
    if ($a === 'lesson_rename') {
        $l = $lesson();
        if (!$l) throw new RuntimeException('That lesson no longer exists.');
        if (trim(post('title')) === '') throw new RuntimeException('Give the lesson a title.');
        $month = max(1, min(CAMPUS_YEAR_MONTHS, (int) post('to_month', (string) $l['month_no'])));
        $sort = $month === (int) $l['month_no'] ? (int) $l['sort_order']
            : (int) (db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM campus_lessons WHERE campus_id = ? AND month_no = ?', [$id, $month])['n'] ?? 1);
        db_run('UPDATE campus_lessons SET title = ?, duration_label = ?, month_no = ?, sort_order = ? WHERE id = ?',
            [mb_substr(trim(post('title')), 0, 200), mb_substr(trim(post('duration')), 0, 40) ?: null, $month, $sort, $l['id']]);
        $log('lesson_updated', 'month ' . $month . ': ' . trim(post('title')) . ($month !== (int) $l['month_no'] ? ' (moved from month ' . $l['month_no'] . ')' : ''));
        flash_set('success', $month !== (int) $l['month_no'] ? 'Lesson moved to month ' . $month . '.' : 'Lesson saved.');
        return ['tab' => 'lessons', 'month' => $month];
    }
    if ($a === 'lesson_delete') {
        $l = $lesson();
        if ($l) {
            db_run('DELETE FROM campus_lessons WHERE id = ?', [$l['id']]);
            $path = resolve_private_path($l['file_url']);
            if (is_file($path)) @unlink($path);
            $log('lesson_removed', $l['title']);
            flash_set('success', 'Lesson removed.');
        }
        return ['tab' => 'lessons', 'month' => (int) ($l['month_no'] ?? 1)];
    }
    if ($a === 'lesson_move') {
        $l = $lesson();
        if ($l) {
            $dir = post('dir') === 'up' ? '<' : '>';
            $order = $dir === '<' ? 'DESC' : 'ASC';
            $other = db_one("SELECT * FROM campus_lessons WHERE campus_id = ? AND month_no = ? AND (sort_order $dir ? OR (sort_order = ? AND id $dir ?)) ORDER BY sort_order $order, id $order LIMIT 1", [$id, $l['month_no'], $l['sort_order'], $l['sort_order'], $l['id']]);
            if ($other) {
                db_run('UPDATE campus_lessons SET sort_order = ? WHERE id = ?', [$other['sort_order'], $l['id']]);
                db_run('UPDATE campus_lessons SET sort_order = ? WHERE id = ?', [$l['sort_order'] === $other['sort_order'] ? $l['sort_order'] + ($dir === '<' ? 1 : -1) : $l['sort_order'], $other['id']]);
            }
        }
        return ['tab' => 'lessons', 'month' => (int) ($l['month_no'] ?? 1)];
    }
    if ($a === 'live_add') {
        $when = strtotime(post('date') . ' ' . post('time'));
        if (trim(post('title')) === '' || !$when) throw new RuntimeException('Give the live class a title, date and time.');
        $url = trim(post('url'));
        if ($url !== '' && !preg_match('#^https://#i', $url)) throw new RuntimeException('The join link must start with https:// (a Zoom or Google Meet link).');
        db_insert(
            'INSERT INTO campus_live_classes (campus_id, title, starts_at, duration_minutes, join_url) VALUES (?, ?, ?, ?, ?)',
            [$id, mb_substr(trim(post('title')), 0, 200), date('Y-m-d H:i:s', $when), max(15, min(300, (int) post('minutes') ?: 90)), $url ?: null]
        );
        $log('live_class_added', trim(post('title')) . ' on ' . date('j M, g:i a', $when));
        flash_set('success', 'Live class added. Members see it on their campus page.');
        return ['tab' => 'live', 'month' => null];
    }
    if ($a === 'live_delete') {
        $live = db_one('SELECT * FROM campus_live_classes WHERE id = ? AND campus_id = ?', [(int) post('live'), $id]);
        if ($live) {
            db_run('DELETE FROM campus_live_classes WHERE id = ?', [$live['id']]);
            $log('live_class_removed', $live['title'] . ' on ' . date('j M, g:i a', strtotime($live['starts_at'])));
            flash_set('success', 'Live class removed.');
        }
        return ['tab' => 'live', 'month' => null];
    }
    throw new RuntimeException('Unknown action.');
}
