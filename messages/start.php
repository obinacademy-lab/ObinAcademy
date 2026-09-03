<?php
// Resolves (or creates) the 1:1 conversation with ?to=<userId> and redirects
// straight into it — the "Message" button target from a profile page.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = require_login();
$toId = (int) query_param('to');

if ($toId === (int) $user['id'] || !db_one('SELECT id FROM users WHERE id = ?', [$toId])) {
    redirect('/messages/index.php');
}

$conversationId = get_or_create_conversation((int) $user['id'], $toId);
redirect('/messages/view.php?id=' . $conversationId);
