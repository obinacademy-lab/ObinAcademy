<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/coupons.php';

$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$code = trim((string) ($body['code'] ?? ''));

$course = db_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
if (!$course || $course['status'] !== 'PUBLISHED') {
    json_response(['valid' => false, 'error' => 'Course not found.'], 404);
}

$result = validate_coupon($code, $courseId, (float) $course['price']);
if (!$result['valid']) {
    json_response(['valid' => false, 'error' => $result['error']]);
}

$discounted = $result['discountedPrice'];
json_response([
    'valid' => true,
    'discountedPrice' => $discounted,
    'discountedPriceFormatted' => format_money($discounted),
    'savingsFormatted' => format_money((float) $course['price'] - $discounted),
]);
