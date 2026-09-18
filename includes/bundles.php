<?php
/** Course bundles — 2+ of a creator's own courses sold together for one
 * discounted one-time price. See migration/add-bundles.sql. */

/** Every bundle a creator has made (any status), newest first, with its course count and the
 * combined individual price of its courses (so the dashboard can show the learner's savings). */
function get_bundles_for_creator(int $creatorId): array {
    return db_all(
        "SELECT b.*,
                (SELECT COUNT(*) FROM bundle_courses bc WHERE bc.bundle_id = b.id) AS course_count,
                (SELECT COALESCE(SUM(c.price), 0) FROM bundle_courses bc JOIN courses c ON c.id = bc.course_id WHERE bc.bundle_id = b.id) AS courses_total_price
         FROM bundles b WHERE b.creator_id = ? ORDER BY b.created_at DESC",
        [$creatorId]
    );
}

/** The courses inside one bundle, in the order the creator added them. */
function get_bundle_courses(int $bundleId): array {
    return db_all(
        "SELECT c.id, c.title, c.slug, c.thumbnail_url, c.price, c.access_duration_days
         FROM bundle_courses bc JOIN courses c ON c.id = bc.course_id
         WHERE bc.bundle_id = ? ORDER BY c.title",
        [$bundleId]
    );
}

/** A published bundle by slug, with the creator's name/school branding joined on — for the public bundle page. */
function get_bundle_by_slug(string $slug): ?array {
    return db_one(
        "SELECT b.*, u.id AS creator_user_id, u.name AS creator_name, u.avatar_url AS creator_avatar_url,
                u.school_name AS creator_school_name
         FROM bundles b JOIN users u ON u.id = b.creator_id
         WHERE b.slug = ? AND b.status = 'PUBLISHED'",
        [$slug]
    );
}

function get_bundle_for_creator(int $creatorId, int $bundleId): ?array {
    return db_one('SELECT * FROM bundles WHERE id = ? AND creator_id = ?', [$bundleId, $creatorId]);
}

/**
 * @param int[] $courseIds Must all belong to $creatorId and be PUBLISHED —
 *   checked here, not trusted from the form.
 * @return array{id?: int, error?: string}
 */
function create_bundle(int $creatorId, string $title, float $price, ?string $description, array $courseIds): array {
    $title = trim($title);
    if (strlen($title) < 4) return ['error' => 'Title must be at least 4 characters.'];
    if ($price <= 0) return ['error' => 'Set a bundle price greater than 0.'];
    $courseIds = array_values(array_unique(array_map('intval', $courseIds)));
    if (count($courseIds) < 2) return ['error' => 'Pick at least 2 courses for the bundle.'];

    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
    $owned = db_all(
        "SELECT id FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' AND id IN ($placeholders)",
        array_merge([$creatorId], $courseIds)
    );
    if (count($owned) !== count($courseIds)) return ['error' => 'One or more selected courses are invalid.'];

    $baseSlug = slugify($title);
    $slug = $baseSlug;
    $n = 1;
    while (db_one('SELECT id FROM bundles WHERE slug = ?', [$slug])) { $slug = "$baseSlug-" . $n++; }

    db()->beginTransaction();
    try {
        $bundleId = db_insert(
            'INSERT INTO bundles (title, slug, description, price, creator_id) VALUES (?, ?, ?, ?, ?)',
            [$title, $slug, $description !== '' ? $description : null, $price, $creatorId]
        );
        foreach ($courseIds as $courseId) {
            db_insert('INSERT INTO bundle_courses (bundle_id, course_id) VALUES (?, ?)', [$bundleId, $courseId]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    return ['id' => $bundleId];
}

function set_bundle_status(int $creatorId, int $bundleId, string $status): bool {
    if (!in_array($status, ['DRAFT', 'PUBLISHED'], true)) return false;
    return db_run('UPDATE bundles SET status = ? WHERE id = ? AND creator_id = ?', [$status, $bundleId, $creatorId]) > 0;
}

function delete_bundle(int $creatorId, int $bundleId): bool {
    return db_run('DELETE FROM bundles WHERE id = ? AND creator_id = ?', [$bundleId, $creatorId]) > 0;
}

/**
 * Starts a mobile-money collection for a bundle purchase. Blocks only when
 * the learner already owns every course in the bundle — owning some but
 * not all is fine, apply_bundle_payment_success() only enrolls the ones
 * they're missing.
 * @return array{paymentId?: int, error?: string}
 */
function initiate_bundle_purchase(?int $userId, int $bundleId, string $phone): array {
    if (!validate_phone($phone)) return ['error' => 'Enter a valid phone number.'];
    if (!$userId) return ['error' => 'Please create a free account or log in before buying a bundle.'];

    $bundle = db_one("SELECT * FROM bundles WHERE id = ? AND status = 'PUBLISHED'", [$bundleId]);
    if (!$bundle) return ['error' => 'Bundle not found.'];
    if ((int) $bundle['creator_id'] === $userId) return ['error' => 'Creators cannot buy their own bundle.'];

    $courses = get_bundle_courses($bundleId);
    $courseIds = array_column($courses, 'id');
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
    $ownedCount = (int) db_one(
        "SELECT COUNT(*) AS n FROM enrollments WHERE user_id = ? AND course_id IN ($placeholders)",
        array_merge([$userId], $courseIds)
    )['n'];
    if ($ownedCount === count($courseIds)) return ['error' => 'You already own every course in this bundle.'];

    $existingPending = db_one(
        "SELECT id FROM payments WHERE user_id = ? AND bundle_id = ? AND type = 'BUNDLE_PURCHASE' AND status = 'PENDING' ORDER BY created_at DESC LIMIT 1",
        [$userId, $bundleId]
    );
    if ($existingPending) {
        $resolved = resolve_payment_with_iotec(fetch_payment_with_bundle((int) $existingPending['id']));
        if ($resolved['status'] === 'SUCCESS') return ['error' => 'You already own every course in this bundle.'];
        if ($resolved['status'] === 'PENDING') return ['paymentId' => (int) $existingPending['id']];
        // FAILED — fall through and start a fresh collection below.
    }

    $price = (float) $bundle['price'];
    $paymentId = db_insert(
        "INSERT INTO payments (user_id, bundle_id, amount, phone, type, status) VALUES (?, ?, ?, ?, 'BUNDLE_PURCHASE', 'PENDING')",
        [$userId, $bundleId, $price, $phone]
    );

    try {
        $result = iotec_initiate_collection($price, $phone, (string) $paymentId, substr("Obin Academy - {$bundle['title']}", 0, 100));
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for bundle payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }

    return ['paymentId' => $paymentId];
}

function fetch_payment_with_bundle(int $paymentId): ?array {
    return db_one(
        'SELECT p.*, b.title AS bundle_title, b.slug AS bundle_slug, b.creator_id AS bundle_creator_id,
                u.name AS learner_name, u.email AS learner_email
         FROM payments p
         JOIN bundles b ON b.id = p.bundle_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?',
        [$paymentId]
    );
}

/**
 * Enrolls the learner in every bundle course they don't already own,
 * splitting the bundle price evenly across them for per-course earnings
 * reporting. Called only from resolve_payment_with_iotec(). A course
 * they already owned individually is left untouched — no double-charge,
 * no overwritten enrollment.
 */
function apply_bundle_payment_success(array $payment): void {
    $userId = (int) $payment['user_id'];
    $bundleId = (int) $payment['bundle_id'];
    $courses = get_bundle_courses($bundleId);
    if (!$courses) return;

    $perCoursePrice = round((float) $payment['amount'] / count($courses), 2);

    db()->beginTransaction();
    try {
        foreach ($courses as $course) {
            $existing = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $course['id']]);
            if ($existing) continue;
            $expiresAt = compute_expires_at($course['access_duration_days'] !== null ? (int) $course['access_duration_days'] : null);
            db_insert(
                'INSERT INTO enrollments (user_id, course_id, expires_at) VALUES (?, ?, ?)',
                [$userId, $course['id'], $expiresAt]
            );
            $split = split_sale($perCoursePrice);
            db_insert(
                'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, ?, ?, ?)',
                [$payment['bundle_creator_id'], $course['id'], $split['net'], $split['gross'], $split['fee']]
            );
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}
