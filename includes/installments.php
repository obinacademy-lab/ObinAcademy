<?php
// Splits one course's price into a fixed number of payments a learner
// approves one at a time (mobile money has no saved-token recurring
// charge). Full access unlocks after the FIRST installment — the learner
// gets a normal PURCHASE-sourced enrollment right away, same as buying the
// course outright — and stays up while the plan is ACTIVE/GRACE/COMPLETED.
// A missed payment walks ACTIVE -> GRACE -> DEFAULTED (access paused) via
// cron/track-maintenance.php's reminder + sweep sections, mirroring
// includes/school_subscriptions.php's renewal lifecycle.

const INSTALLMENT_INTERVAL_DAYS = 14;
const INSTALLMENT_GRACE_DAYS = 5;
const INSTALLMENT_REMINDER_WINDOW_DAYS = 3;

function get_installment_plan(int $learnerId, int $courseId): ?array {
    return db_one('SELECT * FROM installment_plans WHERE learner_id = ? AND course_id = ?', [$learnerId, $courseId]);
}

/**
 * True while a learner's installment plan for $courseId should keep the
 * access their enrollment row already grants. COMPLETED is always true
 * (fully paid); a plan with no row at all (course never had installments,
 * or was bought outright) means this check doesn't apply — callers only
 * invoke this once a plan is known to exist. ACTIVE/GRACE are computed
 * live from next_due_at + INSTALLMENT_GRACE_DAYS rather than trusting the
 * stored status, so access stays correct even if the cron sweep hasn't run
 * recently — same approach as learner_has_active_school_subscription().
 */
function learner_has_installment_access(array $plan): bool {
    if ($plan['status'] === 'COMPLETED') return true;
    if ($plan['status'] === 'DEFAULTED') return false;
    $graceDeadline = strtotime($plan['next_due_at']) + INSTALLMENT_GRACE_DAYS * 86400;
    return time() < $graceDeadline;
}

/** Every installment plan a learner holds, with the course/creator branding joined on. */
function get_installment_plans_for_learner(int $learnerId): array {
    return db_all(
        "SELECT ip.*, c.title AS course_title, c.slug AS course_slug,
                u.name AS creator_name, u.school_name AS creator_school_name
         FROM installment_plans ip
         JOIN courses c ON c.id = ip.course_id
         JOIN users u ON u.id = ip.creator_id
         WHERE ip.learner_id = ? ORDER BY ip.created_at DESC",
        [$learnerId]
    );
}

function fetch_payment_with_installment(int $paymentId): ?array {
    return db_one(
        "SELECT p.*, c.title AS course_title, c.slug AS course_slug,
                ip.creator_id AS plan_creator_id, ip.installment_count, ip.installments_paid,
                u.name AS learner_name, u.email AS learner_email
         FROM payments p
         JOIN courses c ON c.id = p.course_id
         LEFT JOIN installment_plans ip ON ip.id = p.installment_plan_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?",
        [$paymentId]
    );
}

/**
 * Starts (or resumes) a mobile-money collection for the next installment due
 * on $courseId — creates the plan on a learner's first-ever call for this
 * course, otherwise charges whatever installment comes next. Logged-in
 * learners only, same as bundles — a payment plan needs an account to have
 * somewhere for reminders/renewals to live.
 * @return array{paymentId?: int, error?: string}
 */
function initiate_installment_payment(int $learnerId, int $courseId, string $phone): array {
    if (!validate_phone($phone)) return ['error' => 'Enter a valid phone number.'];

    $course = db_one("SELECT * FROM courses WHERE id = ? AND status = 'PUBLISHED'", [$courseId]);
    if (!$course) return ['error' => 'Course not found.'];
    if ((int) $course['creator_id'] === $learnerId) return ['error' => 'Creators cannot buy their own course.'];
    if (!$course['installments_enabled'] || (int) $course['installment_count'] < 2 || (float) $course['price'] <= 0) {
        return ['error' => 'This course does not offer a payment plan.'];
    }

    $plan = get_installment_plan($learnerId, $courseId);
    if ($plan && $plan['status'] === 'COMPLETED') return ['error' => 'You already own this course.'];
    if (!$plan) {
        $alreadyOwned = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$learnerId, $courseId]);
        if ($alreadyOwned) return ['error' => 'You already own this course.'];
    }

    $existingPending = db_one(
        "SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND type = 'INSTALLMENT_PAYMENT' AND status = 'PENDING' ORDER BY created_at DESC LIMIT 1",
        [$learnerId, $courseId]
    );
    if ($existingPending) {
        $resolved = resolve_payment_with_iotec(fetch_payment_with_installment((int) $existingPending['id']));
        if ($resolved['status'] === 'SUCCESS') {
            $plan = get_installment_plan($learnerId, $courseId);
            if ($plan && $plan['status'] === 'COMPLETED') return ['error' => 'You already own this course.'];
        } elseif ($resolved['status'] === 'PENDING') {
            return ['paymentId' => (int) $existingPending['id']];
        }
        // FAILED — fall through and start a fresh collection below.
    }

    if (!$plan) {
        $installmentCount = (int) $course['installment_count'];
        $installmentAmount = round((float) $course['price'] / $installmentCount, 2);
        $planId = db_insert(
            "INSERT INTO installment_plans (total_amount, installment_count, installment_amount, phone, next_due_at, learner_id, creator_id, course_id) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)",
            [(float) $course['price'], $installmentCount, $installmentAmount, $phone, $learnerId, $course['creator_id'], $courseId]
        );
        $installmentNumber = 1;
        $amountDue = $installmentAmount;
    } else {
        $planId = (int) $plan['id'];
        $installmentNumber = (int) $plan['installments_paid'] + 1;
        $isLast = $installmentNumber >= (int) $plan['installment_count'];
        // The last installment collects whatever is actually left, so
        // per-installment rounding never leaves a stray shilling uncollected.
        $amountDue = $isLast
            ? round((float) $plan['total_amount'] - ((float) $plan['installment_amount'] * $plan['installments_paid']), 2)
            : (float) $plan['installment_amount'];
    }

    $paymentId = db_insert(
        "INSERT INTO payments (user_id, course_id, installment_plan_id, amount, phone, type, status) VALUES (?, ?, ?, ?, ?, 'INSTALLMENT_PAYMENT', 'PENDING')",
        [$learnerId, $courseId, $planId, $amountDue, $phone]
    );

    try {
        $label = "Obin Academy - {$course['title']} (Installment {$installmentNumber} of {$course['installment_count']})";
        $result = iotec_initiate_collection($amountDue, $phone, (string) $paymentId, substr($label, 0, 100));
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for installment payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }

    return ['paymentId' => $paymentId];
}

/**
 * Records a successful installment payment, granting a normal
 * PURCHASE-sourced enrollment on the very first one (same access a full
 * one-time purchase would create — this plan's status is what keeps or
 * pauses it from here), advancing next_due_at otherwise, and marking the
 * plan COMPLETED once every installment is in. Called only from
 * resolve_payment_with_iotec().
 */
function apply_installment_payment_success(array $payment): void {
    $plan = db_one('SELECT * FROM installment_plans WHERE id = ?', [$payment['installment_plan_id']]);
    if (!$plan) return;

    $paidCount = (int) $plan['installments_paid'] + 1;
    $isFirst = (int) $plan['installments_paid'] === 0;
    $isFinal = $paidCount >= (int) $plan['installment_count'];

    db()->beginTransaction();
    try {
        if ($isFinal) {
            db_run(
                "UPDATE installment_plans SET installments_paid = ?, status = 'COMPLETED', completed_at = NOW(), grace_ends_at = NULL, reminder_sent_at = NULL WHERE id = ?",
                [$paidCount, $plan['id']]
            );
        } else {
            $nextDueAt = date('Y-m-d H:i:s', strtotime('+' . INSTALLMENT_INTERVAL_DAYS . ' days'));
            db_run(
                "UPDATE installment_plans SET installments_paid = ?, status = 'ACTIVE', next_due_at = ?, grace_ends_at = NULL, reminder_sent_at = NULL WHERE id = ?",
                [$paidCount, $nextDueAt, $plan['id']]
            );
        }

        if ($isFirst) {
            $existingEnrollment = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$plan['learner_id'], $plan['course_id']]);
            if (!$existingEnrollment) {
                $course = db_one('SELECT access_duration_days FROM courses WHERE id = ?', [$plan['course_id']]);
                $expiresAt = compute_expires_at($course['access_duration_days'] !== null ? (int) $course['access_duration_days'] : null);
                db_insert('INSERT INTO enrollments (user_id, course_id, expires_at) VALUES (?, ?, ?)', [$plan['learner_id'], $plan['course_id'], $expiresAt]);
            }
        }

        $split = split_sale((float) $payment['amount']);
        db_insert(
            'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, ?, ?, ?)',
            [$plan['creator_id'], $plan['course_id'], $split['net'], $split['gross'], $split['fee']]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

/**
 * Emails a "next installment due" reminder for every ACTIVE plan due within
 * INSTALLMENT_REMINDER_WINDOW_DAYS that hasn't been reminded yet for this
 * installment (reminder_sent_at is cleared on every successful payment, so
 * this fires again next time). Called from cron/track-maintenance.php.
 */
function send_due_installment_reminders(): int {
    $due = db_all(
        "SELECT ip.id, ip.course_id, ip.installments_paid, ip.installment_count, ip.installment_amount,
                c.title AS course_title, c.slug AS course_slug,
                learner.name AS learner_name, learner.email AS learner_email
         FROM installment_plans ip
         JOIN courses c ON c.id = ip.course_id
         JOIN users learner ON learner.id = ip.learner_id
         WHERE ip.status = 'ACTIVE' AND ip.reminder_sent_at IS NULL
           AND ip.next_due_at <= DATE_ADD(NOW(), INTERVAL ? DAY)",
        [INSTALLMENT_REMINDER_WINDOW_DAYS]
    );
    foreach ($due as $plan) {
        if (!$plan['learner_email']) continue;
        $nextNumber = (int) $plan['installments_paid'] + 1;
        $payUrl = base_url('dashboard/learner/payment-plans.php');
        send_installment_reminder_email(
            $plan['learner_email'], $plan['learner_name'], $plan['course_title'],
            $nextNumber, (int) $plan['installment_count'], (float) $plan['installment_amount'], $payUrl
        );
        db_run('UPDATE installment_plans SET reminder_sent_at = NOW() WHERE id = ?', [$plan['id']]);
    }
    return count($due);
}

/**
 * Walks plan status forward for display purposes: ACTIVE rows past their
 * due date move to GRACE, GRACE rows past their own grace window move to
 * DEFAULTED. Not load-bearing for access control (see the live computation
 * in learner_has_installment_access()) — this keeps status current for the
 * learner's payment-plans list and creator reporting even if cron runs
 * late. Called from cron/track-maintenance.php.
 * @return array{to_grace: int, to_defaulted: int}
 */
function sweep_installment_expirations(): array {
    $toGrace = db_all("SELECT id FROM installment_plans WHERE status = 'ACTIVE' AND next_due_at <= NOW()");
    foreach ($toGrace as $row) {
        $graceEndsAt = date('Y-m-d H:i:s', strtotime('+' . INSTALLMENT_GRACE_DAYS . ' days'));
        db_run("UPDATE installment_plans SET status = 'GRACE', grace_ends_at = ? WHERE id = ?", [$graceEndsAt, $row['id']]);
    }
    $toDefaulted = db_all("SELECT id FROM installment_plans WHERE status = 'GRACE' AND grace_ends_at <= NOW()");
    foreach ($toDefaulted as $row) {
        db_run("UPDATE installment_plans SET status = 'DEFAULTED' WHERE id = ?", [$row['id']]);
    }
    return ['to_grace' => count($toGrace), 'to_defaulted' => count($toDefaulted)];
}
