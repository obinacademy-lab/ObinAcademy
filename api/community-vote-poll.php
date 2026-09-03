<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$pollId = (int) ($body['pollId'] ?? 0);
$optionId = (int) ($body['optionId'] ?? 0);

$poll = db_one('SELECT cp.id, p.community_id FROM community_polls cp JOIN community_posts p ON p.id = cp.post_id WHERE cp.id = ?', [$pollId]);
if (!$poll) json_response(['error' => 'Poll not found.'], 404);
if (!is_community_member((int) $poll['community_id'], (int) $user['id'])) {
    json_response(['error' => 'Join this community to vote.'], 403);
}

$voted = vote_poll($pollId, $optionId, (int) $user['id']);
if (!$voted) json_response(['error' => 'You already voted on this poll.'], 409);

$options = db_all('SELECT id, vote_count FROM community_poll_options WHERE poll_id = ?', [$pollId]);
$total = array_sum(array_column($options, 'vote_count'));

json_response(['ok' => true, 'options' => $options, 'totalVotes' => $total, 'myOptionId' => $optionId]);
