<?php
// Serves a campus lesson's video or PDF from private-uploads/, only to a member whose access is open and whose
// year has reached that lesson's month (or an admin). Same byte-range streaming as stream.php.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/campuses.php';

$user = current_user();
if (!campuses_ready()) { http_response_code(404); exit('Not found'); }
$lesson = get_campus_lesson((int) query_param('lesson'));
if (!$lesson) { http_response_code(404); exit('Not found'); }
if (!campus_can_open_lesson($user, $lesson)) { http_response_code(403); exit('Forbidden'); }

$filePath = resolve_private_path($lesson['file_url']);
clearstatcache(true, $filePath);
if (!is_file($filePath) || !is_readable($filePath)) { http_response_code(404); exit('File missing'); }

$size = filesize($filePath);
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mime = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg', 'mov' => 'video/quicktime', 'pdf' => 'application/pdf'][$ext] ?? 'application/octet-stream';
$fileName = $lesson['file_name'] ?: basename($filePath);
$disposition = ($lesson['kind'] === 'PDF' && query_param('download') === '1') ? 'attachment' : 'inline';

$start = 0;
$end = $size - 1;
$isRange = false;
if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $isRange = true;
    if ($m[1] !== '') $start = (int) $m[1];
    if ($m[2] !== '') $end = (int) $m[2];
    if ($start > $end || $end >= $size) { $start = 0; $end = $size - 1; }
}
$length = $end - $start + 1;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($fileName) . '"');
header('Cache-Control: private, no-store');
if ($isRange) {
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($isRange ? $length : $size));

set_time_limit(0);
while (ob_get_level() > 0) ob_end_flush();
header('X-Accel-Buffering: no');
$fp = fopen($filePath, 'rb');
if ($fp === false) { error_log("[campus-media] fopen failed: $filePath"); exit; }
fseek($fp, $start);
$left = $length;
while ($left > 0 && !feof($fp)) {
    $chunk = min(65536, $left);
    echo fread($fp, $chunk);
    $left -= $chunk;
    flush();
}
fclose($fp);
