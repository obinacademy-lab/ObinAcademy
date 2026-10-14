<?php
/**
 * A campus's teaching content (the twelve monthly stages with their pictures, lessons, live classes, and
 * marking a month complete so the next one opens for the whole class), changed from two
 * places with exactly the same rules: the admin's campus page (dashboard/admin/campus-edit.php) and the
 * professor's Campus Studio (dashboard/campus-studio.php). Prices, campus details, publishing, pay and
 * members are not here: only Obin Academy changes those, on the admin page.
 *
 * Needs includes/storage.php, includes/campuses.php and includes/audit.php.
 */

const CAMPUS_CONTENT_ACTIONS = ['curriculum', 'stage_thumb', 'stage_thumb_remove', 'month_complete', 'month_undo', 'month_back', 'lesson_add', 'lesson_delete', 'lesson_move', 'lesson_rename', 'live_add', 'live_delete'];

/**
 * Runs one content action from the posted form for $campus. Returns where to go next as
 * ['tab' => 'curriculum'|'lessons'|'live', 'month' => int|null]. Every change is written to the audit
 * log under the person who made it. Throws RuntimeException with a message for the person on bad input.
 */
function campus_content_handle(string $a, array $campus, array $user): array {
    $id = (int) $campus['id'];
    $log = fn(string $what, string $detail = '') => log_admin_action((int) $user['id'], $user['name'], 'campus.' . $what, 'Campus', $campus['name'], $detail);
    $backTab = in_array(post('back_tab'), ['curriculum', 'lessons', 'live'], true) ? post('back_tab') : 'curriculum';
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
    if ($a === 'stage_thumb' || $a === 'stage_thumb_remove') {
        if (!campus_progress_ready()) throw new RuntimeException('Month pictures are being set up. Try again shortly.');
        $month = max(1, min(CAMPUS_YEAR_MONTHS, (int) post('month')));
        $old = db_one('SELECT thumbnail_url FROM campus_stages WHERE campus_id = ? AND month_no = ?', [$id, $month])['thumbnail_url'] ?? null;
        $url = null;
        if ($a === 'stage_thumb') {
            if (empty($_FILES['thumb']['name'])) throw new RuntimeException('Choose a picture for month ' . $month . '.');
            $url = save_upload($_FILES['thumb'], 'thumbnails');
        }
        db_run('INSERT INTO campus_stages (campus_id, month_no, title, summary, thumbnail_url) VALUES (?, ?, \'\', \'\', ?) ON DUPLICATE KEY UPDATE thumbnail_url = VALUES(thumbnail_url)', [$id, $month, $url]);
        // The replaced picture is no longer used anywhere: remove the file.
        if ($old && $old !== $url && str_starts_with($old, '/uploads/thumbnails/')) {
            $path = public_uploads_root() . '/' . substr($old, strlen('/uploads/'));
            if (is_file($path)) @unlink($path);
        }
        $log($a === 'stage_thumb' ? 'month_picture_set' : 'month_picture_removed', 'month ' . $month);
        flash_set('success', $a === 'stage_thumb' ? 'Picture saved for month ' . $month . '.' : 'Picture removed from month ' . $month . '.');
        return ['tab' => 'curriculum', 'month' => null];
    }
    if ($a === 'month_complete' && campus_groups_ready()) {
        // One group (the students on this month) moves on, CAMPUS_MONTH_UNDO_MINUTES from now.
        $month = (int) post('month');
        if ($month < 1 || $month > CAMPUS_YEAR_MONTHS) throw new RuntimeException('Choose the month to complete.');
        campus_release_due_months($id);
        $n = db_run('UPDATE campus_memberships SET next_month_at = DATE_ADD(NOW(), INTERVAL ' . CAMPUS_MONTH_UNDO_MINUTES . ' MINUTE) WHERE campus_id = ? AND month_open = ? AND next_month_at IS NULL AND access_until > NOW()', [$id, $month]);
        if (!$n) throw new RuntimeException('No student with an active subscription is on month ' . $month . ' right now.');
        db_run('INSERT INTO campus_stages (campus_id, month_no, title, summary, completed_at) VALUES (?, ?, \'\', \'\', NOW()) ON DUPLICATE KEY UPDATE completed_at = NOW()', [$id, $month]);
        $opens = date('g:i a', strtotime((string) db_one('SELECT MIN(next_month_at) AS t FROM campus_memberships WHERE campus_id = ? AND month_open = ? AND next_month_at IS NOT NULL', [$id, $month])['t']));
        $log('month_completed', 'month ' . $month . ' complete for ' . $n . ' student' . ($n === 1 ? '' : 's') . ($month < CAMPUS_YEAR_MONTHS ? ', month ' . ($month + 1) . ' opens at ' . $opens : ', year finished'));
        flash_set('success', 'Month ' . $month . ' is complete for ' . ($n === 1 ? '1 student' : $n . ' students') . ' with an active subscription. ' . ($month < CAMPUS_YEAR_MONTHS ? 'Month ' . ($month + 1) . ' opens for them at ' . $opens : 'Their year finishes at ' . $opens) . '. Undo before then and they will not notice a thing.');
        return ['tab' => $backTab, 'month' => null];
    }
    if ($a === 'month_undo' && campus_groups_ready()) {
        // Within the undo window: nothing has been shown or sent yet, so cancelling is silent.
        $month = (int) post('month');
        $n = db_run('UPDATE campus_memberships SET next_month_at = NULL WHERE campus_id = ? AND month_open = ? AND next_month_at > NOW()', [$id, $month]);
        if (!$n) throw new RuntimeException('Too late to undo quietly: month ' . ($month + 1) . ' is already open for that group. Use "Move back a month" if you need to.');
        $log('month_complete_undone', 'month ' . $month . ' for ' . $n . ' student' . ($n === 1 ? '' : 's') . ', before anyone saw it');
        flash_set('success', 'Undone. ' . ($n === 1 ? 'Your student stays' : 'All ' . $n . ' students stay') . ' on month ' . $month . ' and nobody was told.');
        return ['tab' => $backTab, 'month' => null];
    }
    if ($a === 'month_back' && campus_groups_ready()) {
        // After the window: put a group back a month. Students may notice the month close again.
        $month = (int) post('month');
        if ($month < 2) throw new RuntimeException('Month 1 is the start of the year.');
        $n = db_run('UPDATE campus_memberships SET month_open = month_open - 1, next_month_at = NULL WHERE campus_id = ? AND month_open = ? AND access_until > NOW()', [$id, $month]);
        if (!$n) throw new RuntimeException('No student is on month ' . $month . '.');
        $log('month_moved_back', ($month > CAMPUS_YEAR_MONTHS ? 'finished year' : 'month ' . $month) . ' back to month ' . ($month - 1) . ' for ' . $n . ' student' . ($n === 1 ? '' : 's'));
        flash_set('success', ($n === 1 ? '1 student is' : $n . ' students are') . ' back on month ' . ($month - 1) . '.');
        return ['tab' => $backTab, 'month' => null];
    }
    if ($a === 'month_complete') { // before migration/add-month-groups.sql: the whole campus moves
        if (!campus_progress_ready()) throw new RuntimeException('Completing months is being set up. Try again shortly.');
        $fresh = get_campus($id);
        $done = campus_months_completed($fresh);
        $month = (int) post('month');
        // Only the month the class is on can be completed: a double click or an old page never skips one.
        if ($done >= CAMPUS_YEAR_MONTHS) throw new RuntimeException('All twelve months are already complete.');
        if ($month !== $done + 1) throw new RuntimeException('The class is on month ' . ($done + 1) . ', so that is the month to complete next.');
        db_run('UPDATE campuses SET months_completed = ? WHERE id = ? AND months_completed = ?', [$month, $id, $done]);
        db_run('INSERT INTO campus_stages (campus_id, month_no, title, summary, completed_at) VALUES (?, ?, \'\', \'\', NOW()) ON DUPLICATE KEY UPDATE completed_at = NOW()', [$id, $month]);
        $stages = get_campus_stages($id);
        $next = $month + 1;
        $students = array_map('intval', array_column(db_all('SELECT user_id FROM campus_memberships WHERE campus_id = ? AND access_until > NOW()', [$id]), 'user_id'));
        if ($next <= CAMPUS_YEAR_MONTHS) {
            $title = trim((string) $stages[$next]['title']);
            foreach ($students as $sid) {
                create_user_notification($sid, (int) $user['id'], 'campus_month_open', 'Month ' . $next . ($title !== '' ? ': ' . $title : '') . ' is now open in the ' . $campus['name'] . ' Campus.', base_url('campus-learn.php?campus=' . $campus['slug'] . '&view=lessons&month=' . $next));
            }
            $log('month_completed', 'month ' . $month . ' complete, month ' . $next . ' opened for ' . count($students) . ' student' . (count($students) === 1 ? '' : 's'));
            flash_set('success', 'Month ' . $month . ' is complete. Month ' . $next . ' is now open for ' . (count($students) === 1 ? 'your 1 student' : (count($students) ? 'all ' . count($students) . ' students' : 'every student who joins')) . '.');
        } else {
            foreach ($students as $sid) {
                create_user_notification($sid, (int) $user['id'], 'campus_year_done', 'Your professor has completed the final month of the ' . $campus['name'] . ' Campus. Finish your lessons to earn your certificate.', base_url('campus-learn.php?campus=' . $campus['slug']));
            }
            $log('month_completed', 'month 12 complete, the year is finished');
            flash_set('success', 'Month 12 is complete. The whole year is finished. Students who finish every lesson get their certificate.');
        }
        return ['tab' => $backTab, 'month' => null];
    }
    if ($a === 'month_undo') { // before migration/add-month-groups.sql
        if (!campus_progress_ready()) throw new RuntimeException('Completing months is being set up. Try again shortly.');
        $done = campus_months_completed(get_campus($id));
        if ($done < 1) throw new RuntimeException('No month has been completed yet.');
        db_run('UPDATE campuses SET months_completed = ? WHERE id = ? AND months_completed = ?', [$done - 1, $id, $done]);
        db_run('UPDATE campus_stages SET completed_at = NULL WHERE campus_id = ? AND month_no = ?', [$id, $done]);
        $log('month_reopened', 'month ' . $done . ' marked not complete' . ($done < CAMPUS_YEAR_MONTHS ? ', month ' . ($done + 1) . ' closed again' : ''));
        flash_set('success', 'Month ' . $done . ' is open again' . ($done < CAMPUS_YEAR_MONTHS ? ' and month ' . ($done + 1) . ' is closed until you complete it' : '') . '.');
        return ['tab' => $backTab, 'month' => null];
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
