<?php
// Replaced by the Community module — kept as a redirect so any bookmarked
// or indexed links to the old per-creator profile page (?id=creatorId)
// land on that creator's real new community instead of 404ing.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/community.php';

$creatorId = (int) query_param('id');
$community = $creatorId ? get_community_by_creator($creatorId) : null;
redirect($community ? '/community/view.php?slug=' . $community['slug'] : '/community/index.php');
