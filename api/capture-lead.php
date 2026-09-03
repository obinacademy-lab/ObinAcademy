<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/leads.php';

$body = json_body();
$visitorId = $_COOKIE['oa_visitor'] ?? null;
$referrerSource = classify_referrer_source($body['referrer'] ?? null);

$result = capture_lead($body, $visitorId, $referrerSource);
json_response($result, isset($result['error']) ? 400 : 200);
