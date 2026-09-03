<?php
// Replaced by the Community module — kept as a redirect so any bookmarked
// or indexed links to the old creator-directory page still land somewhere
// real instead of 404ing.
require __DIR__ . '/includes/bootstrap.php';
redirect('/community/index.php' . (query_param('q') !== '' ? '?q=' . urlencode(query_param('q')) : ''));
