<?php
// "Create Course" now starts in the upload-first builder; this keeps old links working.
require __DIR__ . '/../../includes/bootstrap.php';
require_role(['CREATOR', 'ADMIN']);
redirect('/dashboard/creator/course-build.php');
