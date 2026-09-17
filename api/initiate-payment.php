<?php
// iotec calls (token + collection) can each take up to 15s; guard our own
// budget explicitly rather than depend on the host's ini default, which on
// Hostinger's PHP-FPM/LSAPI may ignore public/.htaccess's max_execution_time.
set_time_limit(45);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/payments.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

// Paying for a course requires an account — guest checkout still exists for
// FREE enrollment (api/enroll-guest.php), but a real money payment needs
// somewhere for a receipt/access/support history to live.
if (!$user) {
    json_response(['error' => 'Please create a free account or log in before paying for a course.'], 401);
}

$courseId = (int) ($body['courseId'] ?? 0);
$phone = trim((string) ($body['phone'] ?? ''));
$couponCode = trim((string) ($body['couponCode'] ?? '')) ?: null;

$result = initiate_payment((int) $user['id'], $courseId, $phone, null, null, $couponCode);
json_response($result, isset($result['error']) ? 400 : 200);
