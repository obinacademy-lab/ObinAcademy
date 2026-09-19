<?php
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/functions.php';

/**
 * Two jobs for one problem: a mobile-money checkout that never resolved.
 *
 * 1. Reconciliation (sweep_stale_pending_payments) — a payment can go
 *    PENDING and then just sit there forever if the learner closes the tab
 *    before the browser's own polling (see api/poll-payment.php) catches
 *    the result. Without this sweep, a payment that actually SUCCEEDED on
 *    iotec's side after the browser gave up would silently never enroll the
 *    learner even though they paid. Reuses resolve_payment_with_iotec()
 *    directly — same enrollment/earnings/receipt side effects as a live
 *    poll, just fired from cron instead of the browser.
 *
 * 2. Recovery email (send_due_payment_recovery_emails) — once a stale
 *    payment is confirmed FAILED (wrong PIN, insufficient balance, timed
 *    out), send one "try again" email. Scoped to COURSE_PURCHASE and
 *    BUNDLE_PURCHASE only — the two first-time-buy flows where a learner
 *    showing real intent (they already entered their phone number) is
 *    worth winning back. No opt-out token: this is about the learner's own
 *    abandoned checkout, not a marketing broadcast, same treatment as the
 *    installment reminders in includes/installments.php.
 */

function sweep_stale_pending_payments(int $staleMinutes = 20, int $limit = 50): array {
    $limit = max(1, min(200, $limit));
    $stale = db_all(
        "SELECT id FROM payments
         WHERE status = 'PENDING' AND iotec_transaction_id IS NOT NULL
           AND created_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
         ORDER BY created_at ASC LIMIT $limit",
        [$staleMinutes]
    );

    $counts = ['success' => 0, 'failed' => 0, 'still_pending' => 0];
    foreach ($stale as $row) {
        $payment = fetch_payment_by_id((int) $row['id']);
        if (!$payment) continue;
        $result = resolve_payment_with_iotec($payment);
        $key = match ($result['status']) {
            'SUCCESS' => 'success',
            'FAILED' => 'failed',
            default => 'still_pending',
        };
        $counts[$key]++;
    }
    return $counts;
}

function get_due_course_payment_recoveries(): array {
    return db_all(
        "SELECT p.id, p.amount, p.user_id, p.guest_name, p.guest_email,
                c.slug, c.title,
                u.name AS learner_name, u.email AS learner_email
         FROM payments p
         JOIN courses c ON c.id = p.course_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.status = 'FAILED' AND p.type = 'COURSE_PURCHASE'
           AND p.recovery_email_sent_at IS NULL
           AND NOT EXISTS (
             SELECT 1 FROM enrollments e WHERE e.course_id = p.course_id
               AND ((p.user_id IS NOT NULL AND e.user_id = p.user_id) OR (p.user_id IS NULL AND e.guest_email = p.guest_email))
           )"
    );
}

/**
 * No "already owns it" guard here, unlike the course query above — a bundle
 * purchase spreads across several enrollments with no single row to check,
 * and a learner re-succeeding on a retry before this fires is rare enough
 * that an occasional harmless "complete your purchase" email to someone who
 * already did isn't worth the extra query complexity.
 */
function get_due_bundle_payment_recoveries(): array {
    return db_all(
        "SELECT p.id, p.amount, p.user_id, p.guest_name, p.guest_email,
                b.slug, b.title,
                u.name AS learner_name, u.email AS learner_email
         FROM payments p
         JOIN bundles b ON b.id = p.bundle_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.status = 'FAILED' AND p.type = 'BUNDLE_PURCHASE'
           AND p.recovery_email_sent_at IS NULL"
    );
}

/** @return array{name: string, email: ?string} */
function payment_recovery_recipient(array $row): array {
    $isGuest = $row['user_id'] === null;
    return [
        'name' => $isGuest ? ($row['guest_name'] ?: 'there') : $row['learner_name'],
        'email' => $isGuest ? $row['guest_email'] : $row['learner_email'],
    ];
}

function send_one_course_payment_recovery(array $row): void {
    $recipient = payment_recovery_recipient($row);
    if ($recipient['email']) {
        $resumeUrl = base_url('courses/view.php?slug=' . $row['slug']);
        send_payment_recovery_email($recipient['email'], $recipient['name'], $row['title'], 'course', $resumeUrl, (float) $row['amount']);
    }
    db_run('UPDATE payments SET recovery_email_sent_at = NOW() WHERE id = ?', [$row['id']]);
}

function send_one_bundle_payment_recovery(array $row): void {
    $recipient = payment_recovery_recipient($row);
    if ($recipient['email']) {
        $resumeUrl = base_url('bundle.php?slug=' . $row['slug']);
        send_payment_recovery_email($recipient['email'], $recipient['name'], $row['title'], 'bundle', $resumeUrl, (float) $row['amount']);
    }
    db_run('UPDATE payments SET recovery_email_sent_at = NOW() WHERE id = ?', [$row['id']]);
}

/** Called from cron/track-maintenance.php. Returns counts sent per type, for the cron log line. */
function send_due_payment_recovery_emails(): array {
    $courseDue = get_due_course_payment_recoveries();
    foreach ($courseDue as $row) {
        send_one_course_payment_recovery($row);
    }

    $bundleDue = get_due_bundle_payment_recoveries();
    foreach ($bundleDue as $row) {
        send_one_bundle_payment_recovery($row);
    }

    return ['course' => count($courseDue), 'bundle' => count($bundleDue)];
}
