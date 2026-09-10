<?php
declare(strict_types=1);

// Obin Academy's actual audience/business is Uganda — every timestamp the
// platform stores or displays (logins, enrollments, payments...) needs to
// read in East Africa Time, not whatever timezone the host's PHP defaults
// to (which varies by server and has nothing to do with where users are).
// See also the matching `SET time_zone` on the DB connection in db.php —
// both need to agree, since DATETIME columns store no timezone of their
// own; the same digits mean whatever zone is active when they're written
// and read.
date_default_timezone_set('Africa/Kampala');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/affiliates.php';
