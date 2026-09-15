<?php
/**
 * Subscriptions were retired — every course is sold individually again
 * (see includes/payments.php). This file stays only so an already-existing
 * SUBSCRIPTION-type payment row (from before the cutover) can still be
 * displayed sensibly in admin payment history — nothing creates a new one
 * of these anymore.
 */

// Kept for resolve_tier_label()'s label lookup on old payments only — not
// used to price anything new.
const SUBSCRIPTION_TIERS = [
    'GO'  => ['label' => 'Go',  'price' => 150000],
    'PRO' => ['label' => 'Pro', 'price' => 250000],
];

function fetch_payment_with_subscription(int $paymentId): ?array {
    return db_one(
        // s.tier is only for a renewal (payments.subscription_tier is NULL
        // by then) — see resolve_tier_label() below for picking the right one.
        'SELECT p.*, s.current_period_ends_at, s.tier AS existing_subscription_tier,
                u.name AS learner_name, u.email AS learner_email
         FROM payments p
         LEFT JOIN subscriptions s ON s.id = p.subscription_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?',
        [$paymentId]
    );
}

/** payments.subscription_tier is set only on a first-ever payment; a renewal payment has it NULL and needs the joined subscriptions.tier instead. */
function resolve_tier_label(array $payment): string {
    $tier = $payment['subscription_tier'] ?? $payment['existing_subscription_tier'] ?? null;
    return $tier !== null ? SUBSCRIPTION_TIERS[$tier]['label'] : 'Subscription';
}
