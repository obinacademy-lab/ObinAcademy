<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/follows.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$creatorId = (int) ($body['creatorId'] ?? 0);
$creator = db_one("SELECT id FROM users WHERE id = ? AND role IN ('CREATOR','ADMIN')", [$creatorId]);
if (!$creator) json_response(['error' => 'School not found.'], 404);
if ((int) $creator['id'] === (int) $user['id']) json_response(['error' => 'You cannot follow your own school.'], 400);

$following = toggle_school_follow((int) $user['id'], $creatorId);
json_response(['following' => $following, 'followerCount' => get_school_follower_count($creatorId)]);
