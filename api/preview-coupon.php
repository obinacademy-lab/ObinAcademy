<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/coupons.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$code = trim((string) ($body['code'] ?? ''));

$course = db_one("SELECT * FROM courses WHERE id = ? AND status = 'PUBLISHED'", [$courseId]);
if (!$course) json_response(['error' => 'Course not found.'], 404);

$unitPrice = (float) $course['price'];
if (course_has_active_sale($course)) $unitPrice = (float) $course['sale_price'];

$result = validate_coupon($code, (int) $course['creator_id'], $courseId, $user ? (int) $user['id'] : null, $unitPrice);
if (isset($result['error'])) json_response(['error' => $result['error']], 400);

json_response([
    'finalPrice' => $result['finalPrice'],
    'discountAmount' => $result['discountAmount'],
    'finalPriceFormatted' => format_money($result['finalPrice']),
]);
