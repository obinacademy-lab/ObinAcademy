<?php
require_once __DIR__ . '/iotec.php';
require_once __DIR__ . '/email.php';

function validate_phone(string $phone): bool {
    return strlen($phone) >= 9 && preg_match('/^[0-9+\s-]+$/', $phone);
}

function fetch_payment_with_course(int $paymentId): ?array {
    return db_one(
        'SELECT p.*, c.price AS course_price, c.slug AS course_slug, c.title AS course_title, c.creator_id AS course_creator_id, c.access_duration_days,
                u.name AS learner_name, u.email AS learner_email
         FROM payments p
         JOIN courses c ON c.id = p.course_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?',
        [$paymentId]
    );
}

/**
 * Checks a PENDING payment's real status with iotec and applies the same
 * enrollment/earnings side effects a normal poll would on success or failure.
 * Called both by the browser's live polling and by initiate_payment() /
 * initiate_premium_upgrade() to reconcile a leftover PENDING row before
 * deciding whether to reuse it — without this, a payment that actually
 * resolved (either way) after the browser gave up polling stays PENDING
 * forever in our DB and silently blocks every future purchase attempt for
 * that course, since the caller would otherwise just keep handing back the
 * same dead payment instead of starting a new collection.
 * @return array{status: string, statusMessage?: string}
 */
function resolve_payment_with_iotec(array $payment): array {
    $paymentId = (int) $payment['id'];

    if ($payment['status'] !== 'PENDING') {
        return ['status' => $payment['status'], 'statusMessage' => $payment['status_message']];
    }
    if (!$payment['iotec_transaction_id']) {
        return ['status' => 'PENDING'];
    }

    try {
        $result = iotec_check_collection_status($payment['iotec_transaction_id']);
    } catch (Throwable $e) {
        error_log('[iotec] checkCollectionStatus failed for payment ' . $paymentId . ': ' . $e->getMessage());
        return ['status' => 'PENDING'];
    }

    $isGuestPayment = $payment['user_id'] === null;

    if ($result['status'] === 'Success') {
        if ($payment['type'] === 'PREMIUM_UPGRADE') {
            $enrollment = db_one('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?', [$payment['user_id'], $payment['course_id']]);
            if ($enrollment && (int) $enrollment['is_premium'] === 0) {
                $split = split_sale((float) $payment['amount']);
                db()->beginTransaction();
                try {
                    db_run("UPDATE payments SET status = 'SUCCESS', status_message = ? WHERE id = ?", [$result['statusMessage'], $paymentId]);
                    db_run('UPDATE enrollments SET is_premium = 1 WHERE id = ?', [$enrollment['id']]);
                    db_insert(
                        'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, ?, ?, ?)',
                        [$payment['course_creator_id'], $payment['course_id'], $split['net'], $split['gross'], $split['fee']]
                    );
                    db()->commit();
                } catch (Throwable $e) {
                    db()->rollBack();
                    throw $e;
                }
                send_payment_receipt_email($payment, $isGuestPayment, 'Premium Download Upgrade');
            } else {
                db_run("UPDATE payments SET status = 'SUCCESS', status_message = ? WHERE id = ?", [$result['statusMessage'], $paymentId]);
            }
            return ['status' => 'SUCCESS'];
        }

        $existing = $isGuestPayment
            ? db_one('SELECT id FROM enrollments WHERE course_id = ? AND guest_email = ? AND user_id IS NULL', [$payment['course_id'], $payment['guest_email']])
            : db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$payment['user_id'], $payment['course_id']]);

        if (!$existing) {
            // The actual amount charged — reflects any sale price active at
            // checkout, and stays correct even if the course's list price
            // changed while this payment was pending (course_price would not).
            $split = split_sale((float) $payment['amount']);
            $expiresAt = compute_expires_at($payment['access_duration_days'] !== null ? (int) $payment['access_duration_days'] : null);
            db()->beginTransaction();
            try {
                db_run("UPDATE payments SET status = 'SUCCESS', status_message = ? WHERE id = ?", [$result['statusMessage'], $paymentId]);
                if ($isGuestPayment) {
                    db_insert(
                        'INSERT INTO enrollments (user_id, guest_name, guest_email, access_token_hash, course_id, expires_at) VALUES (NULL, ?, ?, ?, ?, ?)',
                        [$payment['guest_name'], $payment['guest_email'], $payment['access_token_hash'], $payment['course_id'], $expiresAt]
                    );
                } else {
                    db_insert('INSERT INTO enrollments (user_id, course_id, expires_at) VALUES (?, ?, ?)', [$payment['user_id'], $payment['course_id'], $expiresAt]);
                }
                db_insert(
                    'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, ?, ?, ?)',
                    [$payment['course_creator_id'], $payment['course_id'], $split['net'], $split['gross'], $split['fee']]
                );
                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                throw $e;
            }
            send_payment_receipt_email($payment, $isGuestPayment, 'Course Enrollment');
        } else {
            db_run("UPDATE payments SET status = 'SUCCESS', status_message = ? WHERE id = ?", [$result['statusMessage'], $paymentId]);
        }

        return ['status' => 'SUCCESS'];
    }

    if ($result['status'] === 'Pending' || $result['status'] === 'SentToVendor') {
        return ['status' => 'PENDING', 'statusMessage' => $result['statusMessage']];
    }

    $msg = $result['statusMessage'] ?? $result['status'];
    db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$msg, $paymentId]);
    return ['status' => 'FAILED', 'statusMessage' => $msg];
}

/**
 * Starts a mobile-money collection for a course purchase. Pass $userId for a
 * logged-in learner, or null plus $guestName/$guestEmail for guest checkout
 * (no account). A guest payment gets a one-time poll token (plaintext
 * returned here, only its hash stored) so the browser can keep polling
 * poll_payment_status() without a session identity.
 * @return array{paymentId?: int, pollToken?: string, error?: string}
 */
function initiate_payment(?int $userId, int $courseId, string $phone, ?string $guestName = null, ?string $guestEmail = null): array {
    if (!validate_phone($phone)) return ['error' => 'Enter a valid phone number.'];

    $isGuest = $userId === null;
    if ($isGuest) {
        $guestName = trim((string) $guestName);
        $guestEmail = strtolower(trim((string) $guestEmail));
        if ($guestName === '') return ['error' => 'Enter your name.'];
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) return ['error' => 'Enter a valid email address.'];
    }

    $course = db_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
    if (!$course || $course['status'] !== 'PUBLISHED') return ['error' => 'Course not found.'];
    if (!$isGuest && (int) $course['creator_id'] === $userId) return ['error' => 'Creators cannot enroll in their own course.'];
    if ((float) $course['price'] <= 0) return ['error' => 'This course is free — use the enroll button instead.'];

    // A sale price only takes effect if it's actually a discount — a stale
    // sale_price left >= the current price (e.g. after the creator lowered
    // price directly) is silently ignored rather than overcharging or
    // no-oping strangely.
    $finalPrice = (float) $course['price'];
    $originalAmount = null;
    if ($course['sale_price'] !== null && (float) $course['sale_price'] > 0 && (float) $course['sale_price'] < $finalPrice) {
        $originalAmount = $finalPrice;
        $finalPrice = (float) $course['sale_price'];
    }

    $existingEnrollment = $isGuest
        ? db_one('SELECT id FROM enrollments WHERE course_id = ? AND guest_email = ? AND user_id IS NULL', [$courseId, $guestEmail])
        : db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    if ($existingEnrollment) return ['error' => "You're already enrolled in this course."];

    $existingPending = $isGuest
        ? db_one("SELECT id FROM payments WHERE course_id = ? AND guest_email = ? AND status = 'PENDING' ORDER BY created_at DESC LIMIT 1", [$courseId, $guestEmail])
        : db_one("SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND status = 'PENDING' ORDER BY created_at DESC LIMIT 1", [$userId, $courseId]);

    if ($existingPending) {
        // Reconcile against iotec's real status before reusing this row — it
        // may have already succeeded or failed after the browser gave up
        // polling it, in which case reusing it as-is would either strand a
        // paying learner with no access, or block every retry forever.
        $resolved = resolve_payment_with_iotec(fetch_payment_with_course((int) $existingPending['id']));

        if ($resolved['status'] === 'SUCCESS') {
            return ['error' => $isGuest
                ? "You've already paid for this course — check your email for the access link."
                : "You're already enrolled in this course."];
        }

        if ($resolved['status'] === 'PENDING') {
            if (!$isGuest) return ['paymentId' => (int) $existingPending['id']];
            // Reissue a fresh poll token bound to the same pending row.
            [$pollToken, $pollTokenHash] = make_access_token();
            db_run('UPDATE payments SET access_token_hash = ? WHERE id = ?', [$pollTokenHash, $existingPending['id']]);
            return ['paymentId' => (int) $existingPending['id'], 'pollToken' => $pollToken];
        }

        // FAILED — fall through and start a fresh collection below.
    }

    $pollToken = null;
    if ($isGuest) {
        [$pollToken, $pollTokenHash] = make_access_token();
        $paymentId = db_insert(
            "INSERT INTO payments (user_id, guest_name, guest_email, access_token_hash, course_id, amount, original_amount, phone, type, status) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 'COURSE_PURCHASE', 'PENDING')",
            [$guestName, $guestEmail, $pollTokenHash, $courseId, $finalPrice, $originalAmount, $phone]
        );
    } else {
        $paymentId = db_insert(
            "INSERT INTO payments (user_id, course_id, amount, original_amount, phone, type, status) VALUES (?, ?, ?, ?, ?, ?, 'COURSE_PURCHASE', 'PENDING')",
            [$userId, $courseId, $finalPrice, $originalAmount, $phone]
        );
    }

    try {
        $result = iotec_initiate_collection($finalPrice, $phone, (string) $paymentId, substr("Obin Academy - {$course['title']}", 0, 100));
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }

    return $isGuest ? ['paymentId' => $paymentId, 'pollToken' => $pollToken] : ['paymentId' => $paymentId];
}

/** @return array{paymentId?: int, error?: string} */
function initiate_premium_upgrade(int $userId, int $courseId, string $phone): array {
    if (!validate_phone($phone)) return ['error' => 'Enter a valid phone number.'];

    $course = db_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
    if (!$course || $course['status'] !== 'PUBLISHED') return ['error' => 'Course not found.'];
    if (empty($course['premium_price']) || (float) $course['premium_price'] <= 0) {
        return ['error' => "This course doesn't offer a premium download upgrade."];
    }

    $enrollment = db_one('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    if (!$enrollment) return ['error' => 'Enroll in this course before upgrading to premium.'];
    if ((int) $enrollment['is_premium'] === 1) return ['error' => 'You already have premium access to this course.'];

    $existingPending = db_one(
        "SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND type = 'PREMIUM_UPGRADE' AND status = 'PENDING' ORDER BY created_at DESC LIMIT 1",
        [$userId, $courseId]
    );
    if ($existingPending) {
        $resolved = resolve_payment_with_iotec(fetch_payment_with_course((int) $existingPending['id']));
        if ($resolved['status'] === 'SUCCESS') return ['error' => 'You already have premium access to this course.'];
        if ($resolved['status'] === 'PENDING') return ['paymentId' => (int) $existingPending['id']];
        // FAILED — fall through and start a fresh collection below.
    }

    $paymentId = db_insert(
        "INSERT INTO payments (user_id, course_id, amount, phone, type, status) VALUES (?, ?, ?, ?, 'PREMIUM_UPGRADE', 'PENDING')",
        [$userId, $courseId, $course['premium_price'], $phone]
    );

    try {
        $result = iotec_initiate_collection((float) $course['premium_price'], $phone, (string) $paymentId, substr("Obin Academy - {$course['title']} (Premium)", 0, 100));
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for premium payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }

    return ['paymentId' => $paymentId];
}

/**
 * Pass $userId for a logged-in learner, or null plus the $pollToken returned
 * by initiate_payment() for a guest — that token is the guest's only proof
 * this payment is theirs, since they have no session identity.
 * @return array{status: string, statusMessage?: string, accessUrl?: string}
 */
function poll_payment_status(?int $userId, int $paymentId, ?string $pollToken = null): array {
    $payment = fetch_payment_with_course($paymentId);
    if (!$payment) throw new RuntimeException('Payment not found.');

    $isGuestPayment = $payment['user_id'] === null;
    if ($isGuestPayment) {
        if (!$pollToken || !$payment['access_token_hash'] || !hash_equals((string) $payment['access_token_hash'], hash('sha256', $pollToken))) {
            throw new RuntimeException('Not authorized.');
        }
    } elseif ($userId === null || (int) $payment['user_id'] !== $userId) {
        throw new RuntimeException('Not authorized.');
    }

    $wasPending = $payment['status'] === 'PENDING';
    $result = resolve_payment_with_iotec($payment);

    if ($result['status'] === 'SUCCESS' && $isGuestPayment) {
        $accessUrl = base_url('access.php?token=' . $pollToken);
        if ($wasPending) {
            send_guest_access_email($payment['guest_email'], $payment['guest_name'], $payment['course_title'], $accessUrl);
        }
        $result['accessUrl'] = $accessUrl;
    }

    return $result;
}
