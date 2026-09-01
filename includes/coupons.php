<?php

// A coupon can never take a course below this — keeps a steep discount from
// accidentally colliding with the separate "free course" enrollment path,
// which has completely different (payment-free) logic.
const MIN_COUPON_PRICE = 100.0;

function normalize_coupon_code(string $code): string {
    return strtoupper(trim($code));
}

/** @return array{valid: bool, error?: string, coupon?: array, discountedPrice?: float} */
function validate_coupon(string $code, int $courseId, float $price): array {
    $code = normalize_coupon_code($code);
    if ($code === '') return ['valid' => false, 'error' => 'Enter a coupon code.'];

    $coupon = db_one('SELECT * FROM coupons WHERE course_id = ? AND code = ?', [$courseId, $code]);
    if (!$coupon) return ['valid' => false, 'error' => 'Invalid coupon code.'];
    if ($coupon['status'] !== 'ACTIVE') return ['valid' => false, 'error' => 'This coupon is no longer active.'];
    if ($coupon['expires_at'] !== null && strtotime($coupon['expires_at']) < time()) {
        return ['valid' => false, 'error' => 'This coupon has expired.'];
    }
    if ($coupon['max_uses'] !== null && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
        return ['valid' => false, 'error' => 'This coupon has reached its usage limit.'];
    }

    return ['valid' => true, 'coupon' => $coupon, 'discountedPrice' => compute_discounted_price($coupon, $price)];
}

function compute_discounted_price(array $coupon, float $price): float {
    if ($coupon['discount_type'] === 'PERCENT') {
        $discounted = $price * (1 - (float) $coupon['discount_value'] / 100);
    } else {
        $discounted = $price - (float) $coupon['discount_value'];
    }
    return round(max(MIN_COUPON_PRICE, $discounted));
}

/** Called once a coupon-discounted payment actually succeeds — not on mere initiation. */
function redeem_coupon(int $couponId): void {
    db_run('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$couponId]);
}
