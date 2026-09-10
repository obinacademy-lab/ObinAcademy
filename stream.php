<?php
// Serves lesson videos/PDFs from private-uploads/ only after checking auth +
// enrollment (+ expiry, + premium for downloads). Never a directly-linkable
// static file — lessonId is looked up server-side, never a client-supplied path.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/enrollment.php';

$user = current_user();

$lessonId = (int) query_param('lesson');
$lesson = db_one('
    SELECT l.*, m.course_id, c.creator_id, c.slug AS course_slug, c.price AS course_price, c.premium_price
    FROM lessons l
    JOIN modules m ON m.id = l.module_id
    JOIN courses c ON c.id = m.course_id
    WHERE l.id = ?
', [$lessonId]);
if (!$lesson) { http_response_code(404); exit('Not found'); }

$isOwner = $user && (int) $lesson['creator_id'] === (int) $user['id'];
$isAdmin = $user && $user['role'] === 'ADMIN';
$isPremium = false;

if (!$isOwner && !$isAdmin) {
    $enrollment = $user
        ? db_one('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $lesson['course_id']])
        : guest_enrollment_for_course((int) $lesson['course_id']);
    if (!$enrollment) { http_response_code(403); exit('Forbidden'); }
    if ($enrollment['expires_at'] !== null && strtotime($enrollment['expires_at']) < time()) {
        http_response_code(403);
        exit('Access expired');
    }
    $isPremium = (bool) $enrollment['is_premium'];
}

// A free course the creator never set a premium download price on has no
// paywall to protect — matches the same rule learn.php uses to decide
// whether to show the download button and skip #toolbar=0 in the PDF view.
$isFreeCourseWithNoPremiumTier = (float) $lesson['course_price'] <= 0 && empty($lesson['premium_price']);
$canDownload = $isOwner || $isAdmin || $isPremium || $isFreeCourseWithNoPremiumTier;
$wantsDownload = query_param('download') === '1';
$disposition = ($wantsDownload && $canDownload) ? 'attachment' : 'inline';

// Seed/demo lessons may reference external sample media rather than an
// uploaded file. This used to just redirect once auth passed, but a
// redirect sends the browser straight to the external host — and PDF.js's
// own file loader fetches that URL with CORS, which fails (blank viewer,
// no error shown) against any host that doesn't send an
// Access-Control-Allow-Origin header, like this placeholder's. Proxying the
// bytes through our own origin instead avoids that entirely and keeps the
// same auth-gate/Range-request behavior as a locally-stored file.
if (preg_match('#^https?://#', $lesson['file_url'])) {
    $ch = curl_init($lesson['file_url']);
    $responseHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
            $responseHeaders[] = $header;
            return strlen($header);
        },
    ]);
    if (!empty($_SERVER['HTTP_RANGE'])) {
        curl_setopt($ch, CURLOPT_RANGE, str_replace('bytes=', '', $_SERVER['HTTP_RANGE']));
    }
    $body = curl_exec($ch);
    $upstreamStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $upstreamType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($body === false || $upstreamStatus >= 400) {
        http_response_code(502);
        exit('Upstream file unavailable');
    }

    $externalFileName = $lesson['file_name'] ?: basename((string) parse_url($lesson['file_url'], PHP_URL_PATH));
    http_response_code($upstreamStatus === 206 ? 206 : 200);
    header('Content-Type: ' . ($upstreamType ?: 'application/octet-stream'));
    header('Accept-Ranges: bytes');
    header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($externalFileName) . '"');
    header('Cache-Control: private, no-store');
    foreach ($responseHeaders as $h) {
        if (stripos($h, 'Content-Range:') === 0) header(trim($h));
    }
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}

$filePath = resolve_private_path($lesson['file_url']);
// A stale PHP stat cache (e.g. the file was written by a different process
// moments ago) can make is_file()/filesize() below report on an outdated
// view of the file — clear it explicitly rather than risk serving a wrong
// Content-Length for a file that actually exists and is the right size.
clearstatcache(true, $filePath);
if (!is_file($filePath)) { http_response_code(404); exit('File missing'); }
if (!is_readable($filePath)) {
    // Previously this fell through to fopen(), which returns false on an
    // unreadable file — every call after that (fseek/fread/feof on `false`)
    // is a fatal TypeError in PHP 8, but only *after* the headers below had
    // already gone out with a real Content-Length. The browser then saw a
    // valid-looking response with an empty body — a PDF viewer showing
    // "blank, no error" is exactly what that looks like. Failing here,
    // before any header is sent, turns that into a real, visible error.
    error_log("[stream] file exists but is not readable: $filePath");
    http_response_code(500);
    exit('File could not be read. Please contact support.');
}

$size = filesize($filePath);
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeMap = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg', 'mov' => 'video/quicktime', 'pdf' => 'application/pdf'];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';
$fileName = $lesson['file_name'] ?: basename($filePath);

$start = 0;
$end = $size - 1;
$isRangeRequest = false;

if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $isRangeRequest = true;
    if ($m[1] !== '') $start = (int) $m[1];
    if ($m[2] !== '') $end = (int) $m[2];
    if ($start > $end || $end >= $size) { $start = 0; $end = $size - 1; }
}

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($fileName) . '"');
header('Cache-Control: private, no-store');

$length = $end - $start + 1;

if ($isRangeRequest) {
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
    header('Content-Length: ' . $length);
} else {
    header('Content-Length: ' . $size);
}

set_time_limit(0);
// flush() alone only pushes past PHP's own output buffer — if the server
// (or a proxy in front of it) is still buffering PHP's output internally
// (common default on shared hosting), the browser gets nothing until the
// whole file has been read server-side, which reads as "PDF viewer just
// sits blank" on a large file over a slow connection even though nothing
// is actually broken. ob_flush() empties PHP's own buffer explicitly;
// X-Accel-Buffering tells a reverse proxy in front of PHP not to buffer.
while (ob_get_level() > 0) ob_end_flush();
header('X-Accel-Buffering: no');

$fp = fopen($filePath, 'rb');
if ($fp === false) {
    // Headers are already sent at this point, so the response is unavoidably
    // a "200 OK" with no body from the client's perspective — but at least
    // this stops a fatal TypeError from fseek()/fread() on a false $fp, and
    // leaves a clear trace of what happened instead of nothing at all.
    error_log("[stream] fopen() failed despite is_readable() passing: $filePath");
    exit;
}
fseek($fp, $start);
$bufferSize = 65536;
$bytesLeft = $length;
while ($bytesLeft > 0 && !feof($fp)) {
    $chunk = min($bufferSize, $bytesLeft);
    echo fread($fp, $chunk);
    $bytesLeft -= $chunk;
    flush();
}
fclose($fp);
