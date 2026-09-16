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

// A monthly subscription needs an account — unlike a free course there's no
// guest flow here, since renewal reminders and a subscriptions list need
// somewhere to live.
if (!$user) {
    json_response(['error' => 'Please create a free account or log in before subscribing.'], 401);
}

$creatorId = (int) ($body['creatorId'] ?? 0);
$phone = trim((string) ($body['phone'] ?? ''));

$result = initiate_school_subscription((int) $user['id'], $creatorId, $phone);
json_response($result, isset($result['error']) ? 400 : 200);
