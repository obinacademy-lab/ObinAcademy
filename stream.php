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
if (!is_file($filePath)) { http_response_code(404); exit('File missing'); }

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
$fp = fopen($filePath, 'rb');
fseek($fp, $start);
$bufferSize = 8192;
$bytesLeft = $length;
while ($bytesLeft > 0 && !feof($fp)) {
    $chunk = min($bufferSize, $bytesLeft);
    echo fread($fp, $chunk);
    $bytesLeft -= $chunk;
    flush();
}
fclose($fp);
