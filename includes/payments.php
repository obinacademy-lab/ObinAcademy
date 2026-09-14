<?php
require_once __DIR__ . '/iotec.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/subscriptions.php';

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
 * Dispatches to the right fetch function by payments.type — course
 * purchases/premium upgrades always have a course_id (fetch_payment_with_course
 * inner-joins on it), a subscription payment never does, so it can't reuse
 * that query as-is. Used anywhere a payment id alone is given and the type
 * isn't already known (poll_payment_status(), called for every payment kind
 * through the one shared api/poll-payment.php endpoint).
 */
function fetch_payment_by_id(int $paymentId): ?array {
    $type = db_one('SELECT type FROM payments WHERE id = ?', [$paymentId]);
    if (!$type) return null;
    return $type['type'] === 'SUBSCRIPTION' ? fetch_payment_with_subscription($paymentId) : fetch_payment_with_course($paymentId);
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
        if ($payment['type'] === 'SUBSCRIPTION') {
            apply_subscription_payment_success($payment, $result['statusMessage'] ?? null);
            send_subscription_receipt_email($payment);
            return ['status' => 'SUCCESS'];
        }

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
            $split = split_sale((float) $payment['amount'], $payment['affiliate_id'] !== null);
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
                    db_insert(
                        'INSERT INTO enrollments (user_id, course_id, expires_at) VALUES (?, ?, ?)',
                        [$payment['user_id'], $payment['course_id'], $expiresAt]
                    );
                }
                db_insert(
                    'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, ?, ?, ?)',
                    [$payment['course_creator_id'], $payment['course_id'], $split['net'], $split['gross'], $split['fee']]
                );
                if ($payment['affiliate_id'] !== null) {
                    db_insert(
                        'INSERT INTO affiliate_earnings (amount, affiliate_id, course_id, payment_id) VALUES (?, ?, ?, ?)',
                        [$split['affiliate_cut'], $payment['affiliate_id'], $payment['course_id'], $paymentId]
                    );
                }
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
 * initiate_payment() and initiate_premium_upgrade() — the one-time course
 * purchase and premium-download-upgrade entry points — were removed once
 * learning any course required a subscription. resolve_payment_with_iotec()
 * above still has to correctly resolve any COURSE_PURCHASE/PREMIUM_UPGRADE
 * payment that was already PENDING at the moment that shipped, so its
 * branches for those two types (and fetch_payment_with_course(),
 * split_sale(), AFFILIATE_COMMISSION_RATE) stay exactly as they are —
 * legacy resolution only, no code path creates a new one of these anymore.
 */

/**
 * Pass $userId for a logged-in learner, or null plus a poll token for a
 * guest — that token is the guest's only proof this payment is theirs,
 * since they have no session identity. (Guest payments themselves are
 * legacy now too — see the note above.)
 * @return array{status: string, statusMessage?: string, accessUrl?: string}
 */
function poll_payment_status(?int $userId, int $paymentId, ?string $pollToken = null): array {
    $payment = fetch_payment_by_id($paymentId);
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
