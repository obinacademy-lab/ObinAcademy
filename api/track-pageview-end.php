<?php
require __DIR__ . '/../includes/bootstrap.php';

$body = json_body();
$pageviewId = (int) ($body['pageviewId'] ?? 0);
$timeOnPage = (int) ($body['timeOnPageSeconds'] ?? 0);
$scrollDepth = (int) ($body['scrollDepthPct'] ?? 0);

if ($pageviewId > 0) {
    finalize_pageview($pageviewId, $timeOnPage, $scrollDepth);
}

json_response(['ok' => true]);
