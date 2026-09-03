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
    SELECT l.*, m.course_id, c.creator_id, c.slug AS course_slug
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

$canDownload = $isOwner || $isAdmin || $isPremium;
$wantsDownload = query_param('download') === '1';
$disposition = ($wantsDownload && $canDownload) ? 'attachment' : 'inline';

// Seed/demo lessons may reference external sample media rather than an
// uploaded file — nothing to protect there, so just redirect once auth passes.
if (preg_match('#^https?://#', $lesson['file_url'])) {
    header('Location: ' . $lesson['file_url']);
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
// Private (never shared/CDN-cached) but reusable in the viewer's own browser
// for a while — a PDF/video viewer makes many byte-range requests as the
// learner scrolls or seeks, and "no-store" forced every single one of those
// back through this script (fresh DB lookup + file I/O) instead of letting
// the browser reuse bytes it already downloaded. That's exactly what made
// scrolling a PDF feel stuck, especially scrolling back up to an
// already-seen page. Uploaded files get a fresh random name per upload
// (save_upload()), so a given path's content never changes underneath a
// cached copy.
header('Cache-Control: private, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');

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
