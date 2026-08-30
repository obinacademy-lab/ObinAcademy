<?php
// One-off: imports migration/sqlite-export.json (dumped from the old Next.js
// app's SQLite database) into this app's MySQL database. Run once, after
// `schema.sql` has been imported and BEFORE creating any real data by hand:
//   php migration/import-mysql.php
//
// Old IDs were Prisma cuid strings; this schema uses MySQL auto-increment
// ints, so every table remaps old-id -> new-id and rewrites foreign keys
// using that map as it goes. Safe to re-run: truncates all app tables first.

require __DIR__ . '/../includes/db.php';

$data = json_decode(file_get_contents(__DIR__ . '/sqlite-export.json'), true);
if (!$data) { fwrite(STDERR, "Could not read sqlite-export.json\n"); exit(1); }

$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['reviews','testimonials','audit_log','withdrawal_requests','earnings','payments','enrollments','lessons','modules','courses','creator_applications','password_reset_tokens','users','categories'] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$idMap = []; // "categories" => [oldId => newId], etc.

function remap(string $table, $oldId) {
    global $idMap;
    return $oldId === null ? null : ($idMap[$table][$oldId] ?? null);
}

function dt(?string $iso): ?string {
    if (!$iso) return null;
    return date('Y-m-d H:i:s', strtotime($iso));
}

// 1. Categories
foreach ($data['categories'] as $c) {
    $newId = db_insert('INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)', [$c['name'], $c['slug'], $c['icon'] ?? 'sparkles']);
    $idMap['categories'][$c['id']] = $newId;
}
echo 'Categories: ' . count($data['categories']) . "\n";

// 2. Users
foreach ($data['users'] as $u) {
    $newId = db_insert(
        'INSERT INTO users (name, email, phone, password_hash, role, headline, bio, avatar_url, facebook_url, instagram_url, youtube_url, tiktok_url, linkedin_url, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$u['name'], $u['email'], $u['phone'], $u['passwordHash'], $u['role'], $u['headline'], $u['bio'], $u['avatarUrl'], $u['facebookUrl'], $u['instagramUrl'], $u['youtubeUrl'], $u['tiktokUrl'], $u['linkedinUrl'], dt($u['createdAt'])]
    );
    $idMap['users'][$u['id']] = $newId;
}
echo 'Users: ' . count($data['users']) . "\n";

// 3. Creator applications
foreach ($data['creatorApplications'] as $a) {
    $newId = db_insert(
        'INSERT INTO creator_applications (user_id, status, expertise, motivation, rejection_reason, created_at, reviewed_at) VALUES (?,?,?,?,?,?,?)',
        [remap('users', $a['userId']), $a['status'], $a['expertise'], $a['motivation'], $a['rejectionReason'], dt($a['createdAt']), dt($a['reviewedAt'])]
    );
    $idMap['creatorApplications'][$a['id']] = $newId;
}
echo 'Creator applications: ' . count($data['creatorApplications']) . "\n";

// 4. Courses
foreach ($data['courses'] as $c) {
    $newId = db_insert(
        'INSERT INTO courses (title, slug, summary, description, thumbnail_url, price, access_duration_days, premium_price, status, rejection_reason, submitted_at, reviewed_at, created_at, updated_at, creator_id, category_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$c['title'], $c['slug'], $c['summary'], $c['description'], $c['thumbnailUrl'], $c['price'], $c['accessDurationDays'], $c['premiumPrice'], $c['status'], $c['rejectionReason'], dt($c['submittedAt']), dt($c['reviewedAt']), dt($c['createdAt']), dt($c['updatedAt']), remap('users', $c['creatorId']), remap('categories', $c['categoryId'])]
    );
    $idMap['courses'][$c['id']] = $newId;
}
echo 'Courses: ' . count($data['courses']) . "\n";

// 5. Modules
foreach ($data['modules'] as $m) {
    $newId = db_insert('INSERT INTO modules (title, sort_order, course_id) VALUES (?,?,?)', [$m['title'], $m['order'], remap('courses', $m['courseId'])]);
    $idMap['modules'][$m['id']] = $newId;
}
echo 'Modules: ' . count($data['modules']) . "\n";

// 6. Lessons (fileUrl format — "videos/x.mp4"/"pdfs/x.pdf" or a full https:// URL — is unchanged)
foreach ($data['lessons'] as $l) {
    $newId = db_insert(
        'INSERT INTO lessons (title, type, file_url, file_name, duration, sort_order, module_id) VALUES (?,?,?,?,?,?,?)',
        [$l['title'], $l['type'], $l['fileUrl'], $l['fileName'], $l['duration'], $l['order'], remap('modules', $l['moduleId'])]
    );
    $idMap['lessons'][$l['id']] = $newId;
}
echo 'Lessons: ' . count($data['lessons']) . "\n";

// 7. Enrollments
foreach ($data['enrollments'] as $e) {
    db_insert(
        'INSERT INTO enrollments (progress, enrolled_at, expires_at, is_premium, user_id, course_id) VALUES (?,?,?,?,?,?)',
        [$e['progress'], dt($e['enrolledAt']), dt($e['expiresAt']), $e['isPremium'] ? 1 : 0, remap('users', $e['userId']), remap('courses', $e['courseId'])]
    );
}
echo 'Enrollments: ' . count($data['enrollments']) . "\n";

// 8. Payments
foreach ($data['payments'] as $p) {
    $newId = db_insert(
        'INSERT INTO payments (iotec_transaction_id, amount, phone, type, status, status_message, created_at, updated_at, user_id, course_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$p['iotecTransactionId'], $p['amount'], $p['phone'], $p['type'], $p['status'], $p['statusMessage'], dt($p['createdAt']), dt($p['updatedAt']), remap('users', $p['userId']), remap('courses', $p['courseId'])]
    );
    $idMap['payments'][$p['id']] = $newId;
}
echo 'Payments: ' . count($data['payments']) . "\n";

// 9. Earnings
foreach ($data['earnings'] as $e) {
    db_insert(
        'INSERT INTO earnings (amount, gross_amount, platform_fee, created_at, creator_id, course_id) VALUES (?,?,?,?,?,?)',
        [$e['amount'], $e['grossAmount'], $e['platformFee'], dt($e['createdAt']), remap('users', $e['creatorId']), remap('courses', $e['courseId'])]
    );
}
echo 'Earnings: ' . count($data['earnings']) . "\n";

// 10. Withdrawal requests
foreach ($data['withdrawalRequests'] as $w) {
    db_insert(
        'INSERT INTO withdrawal_requests (amount, phone, status, note, requested_at, resolved_at, creator_id) VALUES (?,?,?,?,?,?,?)',
        [$w['amount'], $w['phone'], $w['status'], $w['note'], dt($w['requestedAt']), dt($w['resolvedAt']), remap('users', $w['creatorId'])]
    );
}
echo 'Withdrawal requests: ' . count($data['withdrawalRequests']) . "\n";

// 11. Audit log (actorId may be null)
foreach ($data['auditLogs'] as $a) {
    db_insert(
        'INSERT INTO audit_log (action, target_type, target_label, detail, created_at, actor_id, actor_name) VALUES (?,?,?,?,?,?,?)',
        [$a['action'], $a['targetType'], $a['targetLabel'], $a['detail'], dt($a['createdAt']), remap('users', $a['actorId']), $a['actorName']]
    );
}
echo 'Audit log: ' . count($data['auditLogs']) . "\n";

// 12. Testimonials
foreach ($data['testimonials'] as $t) {
    db_insert(
        'INSERT INTO testimonials (quote, rating, status, rejection_reason, created_at, reviewed_at, author_id) VALUES (?,?,?,?,?,?,?)',
        [$t['quote'], $t['rating'], $t['status'], $t['rejectionReason'], dt($t['createdAt']), dt($t['reviewedAt']), remap('users', $t['authorId'])]
    );
}
echo 'Testimonials: ' . count($data['testimonials']) . "\n";

// 13. Reviews
foreach ($data['reviews'] as $r) {
    db_insert(
        'INSERT INTO reviews (rating, comment, created_at, updated_at, course_id, author_id) VALUES (?,?,?,?,?,?)',
        [$r['rating'], $r['comment'], dt($r['createdAt']), dt($r['updatedAt']), remap('courses', $r['courseId']), remap('users', $r['authorId'])]
    );
}
echo 'Reviews: ' . count($data['reviews']) . "\n";

// 14. Password reset tokens
foreach ($data['passwordResetTokens'] as $p) {
    db_insert(
        'INSERT INTO password_reset_tokens (token_hash, expires_at, used_at, created_at, user_id) VALUES (?,?,?,?,?)',
        [$p['tokenHash'], dt($p['expiresAt']), dt($p['usedAt']), dt($p['createdAt']), remap('users', $p['userId'])]
    );
}
echo 'Password reset tokens: ' . count($data['passwordResetTokens']) . "\n";

echo "\nImport complete.\n";
