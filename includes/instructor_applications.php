<?php
/**
 * Applications to teach a campus (public/teach.php), reviewed by admins on
 * dashboard/admin/instructor-applications.php. Accepting one makes the applicant the professor of
 * an existing campus, or of a new draft campus built from their proposal, on the standard share.
 */
require_once __DIR__ . '/campuses.php';
require_once __DIR__ . '/notifications.php';

const INSTRUCTOR_APP_STATUSES = ['NEW' => 'New', 'SHORTLISTED' => 'Shortlisted', 'ACCEPTED' => 'Accepted', 'DECLINED' => 'Declined'];

function instructor_applications_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT 1 FROM instructor_applications LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

/**
 * Validates and stores an application, then tells the admins.
 * @return array{ok?: true, id?: int, error?: string, field?: string}
 */
function submit_instructor_application(array $in, ?array $user, string $ip): array {
    $name = mb_substr(trim($in['name'] ?? ''), 0, 120);
    $email = strtolower(mb_substr(trim($in['email'] ?? ''), 0, 190));
    $phone = mb_substr(trim($in['phone'] ?? ''), 0, 32);
    $campusChoice = (string) ($in['campus'] ?? '');
    $proposed = mb_substr(trim($in['proposed_campus'] ?? ''), 0, 160);
    $headline = mb_substr(trim($in['headline'] ?? ''), 0, 200);
    $experience = mb_substr(trim($in['experience'] ?? ''), 0, 5000);
    $results = mb_substr(trim($in['results'] ?? ''), 0, 3000);
    $links = mb_substr(trim($in['links'] ?? ''), 0, 500);
    $sample = mb_substr(trim($in['sample_url'] ?? ''), 0, 500);

    // 'field' names the input at fault, so the form can open the step that holds it.
    if (mb_strlen($name) < 2) return ['error' => 'Enter your full name.', 'field' => 'name'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => 'Enter a valid email address.', 'field' => 'email'];
    if (strlen(preg_replace('/\D/', '', $phone)) < 9) return ['error' => 'Enter a phone number we can call or WhatsApp.', 'field' => 'phone'];
    if (mb_strlen($headline) < 5) return ['error' => 'Tell us what you do, in one line.', 'field' => 'headline'];
    $campusId = null;
    if ($campusChoice === 'new') {
        if (mb_strlen($proposed) < 3) return ['error' => 'Name the campus you would like to teach.', 'field' => 'proposed_campus'];
    } else {
        $campus = ctype_digit($campusChoice) ? get_campus((int) $campusChoice) : null;
        if (!$campus) return ['error' => 'Choose the campus you want to teach, or propose a new one.', 'field' => 'campus'];
        $campusId = (int) $campus['id'];
        $proposed = '';
    }
    if (mb_strlen($experience) < 40) return ['error' => 'Tell us a little more about your experience (a few sentences).', 'field' => 'experience'];
    if ($sample !== '' && !preg_match('#^https?://#i', $sample)) return ['error' => 'The sample link must start with http:// or https://.', 'field' => 'sample_url'];

    $recent = (int) (db_one('SELECT COUNT(*) AS n FROM instructor_applications WHERE (email = ? OR ip = ?) AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)', [$email, $ip])['n'] ?? 0);
    if ($recent >= 3) return ['error' => 'We already have your application. We will be in touch.'];

    $appId = db_insert(
        'INSERT INTO instructor_applications (name, email, phone, campus_id, proposed_campus, headline, experience, results, links, sample_url, user_id, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$name, $email, $phone, $campusId, $proposed ?: null, $headline, $experience, $results ?: null, $links ?: null, $sample ?: null, $user['id'] ?? null, mb_substr($ip, 0, 64)]
    );

    $what = $campusId ? 'the ' . get_campus($campusId)['name'] . ' Campus' : 'a new campus: ' . $proposed;
    try { create_admin_notification('instructor_application', $name . ' applied to teach ' . $what . '.'); } catch (Throwable $e) {}
    $url = base_url('dashboard/admin/instructor-applications.php');
    foreach (db_all("SELECT email FROM users WHERE role = 'ADMIN' AND email IS NOT NULL AND email != ''") as $a) {
        resend_send($a['email'], 'New application to teach: ' . $name, campus_email_html(
            $name . ' wants to teach',
            e($name . ' (' . $headline . ') applied to teach ' . $what . '.'),
            'Review the application', $url
        ), ['reply_to' => $email]);
    }
    resend_send($email, 'We received your application to teach at Obin Academy', campus_email_html(
        'Thank you, ' . campus_first_name($name),
        'We received your application to teach ' . e($what) . '. We read every one, and if it is a fit we will call or WhatsApp you on ' . e($phone) . ' to talk it through.',
        'See the campuses', base_url('index.php#campuses')
    ));
    return ['ok' => true, 'id' => $appId];
}

function get_instructor_applications(string $status = ''): array {
    $sql = 'SELECT a.*, c.name AS campus_name FROM instructor_applications a LEFT JOIN campuses c ON c.id = a.campus_id';
    $params = [];
    if (isset(INSTRUCTOR_APP_STATUSES[$status])) { $sql .= ' WHERE a.status = ?'; $params[] = $status; }
    return db_all($sql . " ORDER BY FIELD(a.status, 'NEW', 'SHORTLISTED', 'ACCEPTED', 'DECLINED'), a.created_at DESC LIMIT 200", $params);
}

/**
 * Makes the applicant the professor of $campusId, or of a new draft campus named after their
 * proposal when $campusId is null. Links their account when one uses their email, and sets their
 * payout number to the phone they applied with. Returns the campus.
 */
function accept_instructor_application(array $app, ?int $campusId, int $adminId): array {
    if ($campusId === null) {
        $name = trim(preg_replace('/\s+campus$/i', '', (string) $app['proposed_campus']));
        $slug = slugify($name) ?: 'campus';
        $base = $slug; $n = 2;
        while (db_one('SELECT id FROM campuses WHERE slug = ?', [$slug])) $slug = $base . '-' . $n++;
        $sort = (int) (db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM campuses')['n'] ?? 99);
        $campusId = db_insert("INSERT INTO campuses (slug, name, tagline, icon, status, sort_order) VALUES (?, ?, '', 'book', 'DRAFT', ?)", [$slug, mb_substr($name, 0, 120), $sort]);
    }
    $acct = db_one('SELECT id FROM users WHERE email = ?', [$app['email']]);
    $before = get_campus($campusId);
    if ($before && $before['professor_name'] && $before['professor_name'] !== $app['name']) {
        // A different person: the previous professor's bio and photo must not carry over.
        db_run('UPDATE campuses SET professor_bio = NULL, professor_photo_url = NULL WHERE id = ?', [$campusId]);
    }
    db_run(
        'UPDATE campuses SET professor_name = ?, professor_title = ?, professor_user_id = ?, professor_payout_phone = ?, professor_payout_name = ? WHERE id = ?',
        [$app['name'], mb_substr($app['headline'], 0, 160), $acct['id'] ?? null, $app['phone'], $app['name'], $campusId]
    );
    db_run("UPDATE instructor_applications SET status = 'ACCEPTED', campus_id = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?", [$campusId, $adminId, $app['id']]);
    return get_campus($campusId);
}
