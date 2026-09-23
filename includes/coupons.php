<?php
/** Coupon codes — a creator-scoped percentage/fixed discount applied at
 * checkout. See migration/add-coupons.sql. */

/** Every coupon a creator has ever made, newest first. */
function get_coupons_for_creator(int $creatorId): array {
    return db_all('SELECT * FROM coupons WHERE creator_id = ? ORDER BY created_at DESC', [$creatorId]);
}

function create_coupon(int $creatorId, string $code, string $discountType, float $discountValue, ?int $courseId, ?int $maxUses, ?string $expiresAt): array {
    $code = strtoupper(trim($code));
    if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
        return ['error' => 'Code must be 3-40 characters: letters, numbers, dashes, or underscores only.'];
    }
    if (!in_array($discountType, ['PERCENT', 'FIXED'], true)) return ['error' => 'Invalid discount type.'];
    if ($discountValue <= 0) return ['error' => 'Discount must be greater than 0.'];
    if ($discountType === 'PERCENT' && $discountValue > 100) return ['error' => 'A percentage discount cannot exceed 100.'];
    if ($courseId !== null) {
        $course = db_one('SELECT id FROM courses WHERE id = ? AND creator_id = ?', [$courseId, $creatorId]);
        if (!$course) return ['error' => 'Course not found.'];
    }
    $existing = db_one('SELECT id FROM coupons WHERE creator_id = ? AND code = ?', [$creatorId, $code]);
    if ($existing) return ['error' => "You already have a coupon with the code \"$code\"."];

    $id = db_insert(
        'INSERT INTO coupons (code, discount_type, discount_value, max_uses, expires_at, creator_id, course_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$code, $discountType, $discountValue, $maxUses, $expiresAt, $creatorId, $courseId]
    );
    return ['id' => $id];
}

function set_coupon_status(int $creatorId, int $couponId, string $status): bool {
    if (!in_array($status, ['ACTIVE', 'DISABLED'], true)) return false;
    return db_run('UPDATE coupons SET status = ? WHERE id = ? AND creator_id = ?', [$status, $couponId, $creatorId]) > 0;
}

function delete_coupon(int $creatorId, int $couponId): bool {
    return db_run('DELETE FROM coupons WHERE id = ? AND creator_id = ?', [$couponId, $creatorId]) > 0;
}

/**
 * Looks up $code for $creatorId and, if valid for $courseId right now,
 * returns the discounted price. Doesn't record a redemption — that only
 * happens once the payment actually succeeds (see
 * record_coupon_redemption()), so an abandoned checkout never burns a
 * learner's one-time use of a code or a coupon's max_uses cap.
 * @return array{couponId?: int, finalPrice?: float, discountAmount?: float, error?: string}
 */
/** $userId is null for a guest checkout — the "already used this coupon" check only applies to a logged-in learner, since a guest has no account to key it on (see coupon_redemptions.user_id). */
function validate_coupon(string $code, int $creatorId, int $courseId, ?int $userId, float $price): array {
    $code = strtoupper(trim($code));
    if ($code === '') return ['error' => 'Enter a coupon code.'];

    $coupon = db_one(
        'SELECT * FROM coupons WHERE creator_id = ? AND code = ? AND (course_id IS NULL OR course_id = ?)',
        [$creatorId, $code, $courseId]
    );
    if (!$coupon) return ['error' => 'That coupon code isn\'t valid for this course.'];
    if ($coupon['status'] !== 'ACTIVE') return ['error' => 'That coupon is no longer active.'];
    if ($coupon['expires_at'] !== null && strtotime($coupon['expires_at']) < time()) return ['error' => 'That coupon has expired.'];
    if ($coupon['max_uses'] !== null && (int) $coupon['uses_count'] >= (int) $coupon['max_uses']) return ['error' => 'That coupon has reached its usage limit.'];

    if ($userId !== null) {
        $alreadyUsed = db_one('SELECT id FROM coupon_redemptions WHERE coupon_id = ? AND user_id = ?', [$coupon['id'], $userId]);
        if ($alreadyUsed) return ['error' => "You've already used this coupon."];
    }

    $discountAmount = $coupon['discount_type'] === 'PERCENT'
        ? round($price * ((float) $coupon['discount_value'] / 100), 2)
        : min($price, (float) $coupon['discount_value']);
    $finalPrice = round($price - $discountAmount, 2);
    if ($finalPrice <= 0) return ['error' => 'That coupon would reduce the price below the minimum. Contact the creator.'];

    return ['couponId' => (int) $coupon['id'], 'finalPrice' => $finalPrice, 'discountAmount' => round($price - $finalPrice, 2)];
}

/** Called only from a payment's SUCCESS path (resolve_payment_with_iotec()) — records the redemption and bumps the coupon's use count, inside the same transaction as the enrollment it unlocked. $userId is null for a guest payment. */
function record_coupon_redemption(int $couponId, ?int $userId, int $paymentId): void {
    db_insert('INSERT INTO coupon_redemptions (coupon_id, user_id, payment_id) VALUES (?, ?, ?)', [$couponId, $userId, $paymentId]);
    // Bounded by max_uses here too, not just at validate_coupon() time — two
    // learners who both start checkout while exactly one use is left can
    // both pass that earlier check (it only runs at checkout start, not at
    // redemption); this keeps the stored count from ever exceeding the cap
    // even though the discount itself was already honored for both.
    db_run('UPDATE coupons SET uses_count = uses_count + 1 WHERE id = ? AND (max_uses IS NULL OR uses_count < max_uses)', [$couponId]);
}
