<?php
require __DIR__ . '/../includes/bootstrap.php';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (is_bot_user_agent($ua)) {
    json_response(['tracked' => false]);
}

$body = json_body();
$path = substr((string) ($body['path'] ?? $_SERVER['REQUEST_URI'] ?? '/'), 0, 500);
$referrer = is_string($body['referrer'] ?? null) ? $body['referrer'] : null;

$visitorId = ensure_visitor_id();
$referrerSource = classify_referrer_source($referrer);
$parsedUa = parse_user_agent($ua);

$session = get_or_create_session($visitorId, $path, $referrerSource, $parsedUa);
$pageviewId = record_pageview($session['id'], $visitorId, $path);

json_response(['tracked' => true, 'pageviewId' => $pageviewId, 'sessionId' => $session['id'], 'isReturning' => !$session['is_new_visitor']]);
