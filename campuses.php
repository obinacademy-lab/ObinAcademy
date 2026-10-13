<?php
// The separate campuses page was removed: the campuses live on the home page now.
// Old links and bookmarks are forwarded there permanently.
require __DIR__ . '/includes/bootstrap.php';
header('Location: ' . base_url('index.php#campuses'), true, 301);
exit;
