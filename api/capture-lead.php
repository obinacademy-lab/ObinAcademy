<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/leads.php';

$body = json_body();
$visitorId = $_COOKIE['oa_visitor'] ?? null;
$referrerSource = classify_referrer_source($body['referrer'] ?? null);

if (!rate_limit_take('lead-ip', get_client_ip(), 20, 3600) || !rate_limit_take('lead-email', strtolower(trim((string) ($body['email'] ?? ''))), 3, 86400)) {
    json_response(['error' => 'Too many requests. Please try again later.'], 429);
}
$result = capture_lead($body, $visitorId, $referrerSource);
json_response($result, isset($result['error']) ? 400 : 200);
