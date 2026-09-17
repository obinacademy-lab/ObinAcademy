<?php
// A logged-in buyer pays for one course as a gift for someone else by
// email — the buyer's own account never gets access to it. On payment
// success a course_gifts row is created (PENDING) and the recipient is
// emailed a claim link carrying a bearer token (same make_access_token()
// pattern as a guest's access link — only the hash is stored). Claiming
// requires an account (creating one if needed) so the course lands in a
// real learner dashboard rather than staying an anonymous access token.

function get_gift_by_token(string $token): ?array {
    return db_one(
        "SELECT cg.*, c.title AS course_title, c.slug AS course_slug, c.access_duration_days,
                u.name AS buyer_name
         FROM course_gifts cg
         JOIN courses c ON c.id = cg.course_id
         JOIN users u ON u.id = cg.buyer_id
         WHERE cg.claim_token_hash = ?",
        [hash('sha256', $token)]
    );
}

/** Every gift a buyer has sent, newest first, with the course title joined on. */
function get_gifts_sent_by_buyer(int $buyerId): array {
    return db_all(
        "SELECT cg.*, c.title AS course_title, c.slug AS course_slug
         FROM course_gifts cg JOIN courses c ON c.id = cg.course_id
         WHERE cg.buyer_id = ? ORDER BY cg.created_at DESC",
        [$buyerId]
    );
}

function fetch_payment_with_gift(int $paymentId): ?array {
    return db_one(
        "SELECT p.*, c.title AS course_title, c.slug AS course_slug, c.access_duration_days,
                u.name AS buyer_name, u.email AS buyer_email
         FROM payments p
         JOIN courses c ON c.id = p.course_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?",
        [$paymentId]
    );
}

/**
 * Starts a mobile-money collection for $courseId, paid by $buyerId, as a
 * gift for $recipientEmail. Logged-in buyers only — the buyer's own
 * account is what the receipt and payment history hang off of.
 * @return array{paymentId?: int, error?: string}
 */
function initiate_course_gift(int $buyerId, int $courseId, string $recipientName, string $recipientEmail, string $message, string $phone): array {
    if (!validate_phone($phone)) return ['error' => 'Enter a valid phone number.'];
    $recipientName = trim($recipientName);
    if (strlen($recipientName) < 2) return ['error' => "Enter the recipient's name."];
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) return ['error' => "Enter a valid email address for the recipient."];

    $course = db_one("SELECT * FROM courses WHERE id = ? AND status = 'PUBLISHED'", [$courseId]);
    if (!$course) return ['error' => 'Course not found.'];
    if ((int) $course['creator_id'] === $buyerId) return ['error' => 'Creators cannot gift their own course.'];
    if ((float) $course['price'] <= 0) return ['error' => 'This course is already free — just share the link.'];

    $message = trim($message);
    $paymentId = db_insert(
        "INSERT INTO payments (user_id, course_id, amount, phone, type, status, gift_recipient_name, gift_recipient_email, gift_message)
         VALUES (?, ?, ?, ?, 'COURSE_GIFT', 'PENDING', ?, ?, ?)",
        [$buyerId, $courseId, (float) $course['price'], $phone, $recipientName, $recipientEmail, $message !== '' ? $message : null]
    );

    try {
        $result = iotec_initiate_collection((float) $course['price'], $phone, (string) $paymentId, substr("Obin Academy - Gift: {$course['title']}", 0, 100));
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for gift payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }

    return ['paymentId' => $paymentId];
}

/**
 * Creates the course_gifts row for a successful COURSE_GIFT payment and
 * emails the recipient their claim link. Called only from
 * resolve_payment_with_iotec() — never creates an enrollment itself, that
 * only happens once the recipient actually claims it (claim_course_gift()).
 */
function apply_course_gift_payment_success(array $payment): void {
    [$token, $tokenHash] = make_access_token();

    db()->beginTransaction();
    try {
        $giftId = db_insert(
            "INSERT INTO course_gifts (recipient_name, recipient_email, message, claim_token_hash, buyer_id, course_id) VALUES (?, ?, ?, ?, ?, ?)",
            [$payment['gift_recipient_name'], $payment['gift_recipient_email'], $payment['gift_message'], $tokenHash, $payment['user_id'], $payment['course_id']]
        );
        db_run('UPDATE payments SET gift_id = ? WHERE id = ?', [$giftId, $payment['id']]);

        // The creator earns exactly what a direct purchase would, regardless
        // of who ends up with access to the course.
        $course = db_one('SELECT creator_id FROM courses WHERE id = ?', [$payment['course_id']]);
        $split = split_sale((float) $payment['amount']);
        db_insert(
            'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, ?, ?, ?)',
            [$course['creator_id'], $payment['course_id'], $split['net'], $split['gross'], $split['fee']]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    $claimUrl = base_url('claim-gift.php?token=' . $token);
    send_gift_claim_email($payment['gift_recipient_email'], $payment['gift_recipient_name'], $payment['buyer_name'], $payment['course_title'], $payment['gift_message'], $claimUrl);
    if ($payment['buyer_email']) {
        send_gift_purchase_receipt_email($payment);
    }
}

/**
 * Grants $userId access to a claimed gift's course — a normal
 * PURCHASE-sourced enrollment, same as buying it directly. Idempotent: a
 * gift already CLAIMED by someone else, or a recipient who happens to
 * already own the course, both fail/no-op gracefully rather than error.
 * @return array{ok?: bool, error?: string}
 */
function claim_course_gift(int $userId, int $giftId): array {
    $gift = db_one('SELECT * FROM course_gifts WHERE id = ?', [$giftId]);
    if (!$gift) return ['error' => 'Gift not found.'];
    if ($gift['status'] === 'CLAIMED') return ['error' => 'This gift has already been claimed.'];

    db()->beginTransaction();
    try {
        db_run("UPDATE course_gifts SET status = 'CLAIMED', claimed_by_user_id = ?, claimed_at = NOW() WHERE id = ?", [$userId, $giftId]);

        $existing = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $gift['course_id']]);
        if (!$existing) {
            $course = db_one('SELECT access_duration_days FROM courses WHERE id = ?', [$gift['course_id']]);
            $expiresAt = compute_expires_at($course['access_duration_days'] !== null ? (int) $course['access_duration_days'] : null);
            db_insert('INSERT INTO enrollments (user_id, course_id, expires_at) VALUES (?, ?, ?)', [$userId, $gift['course_id'], $expiresAt]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    return ['ok' => true];
}
