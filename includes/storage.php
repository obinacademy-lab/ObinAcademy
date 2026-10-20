<?php

// Videos/PDFs are saved outside the web root (private-uploads/) and are only
// ever reachable through campus-media.php, which checks the campus membership first.
// Thumbnails/avatars and student video stories are marketing material and stay under
// public/uploads/, directly web-accessible (the web server streams story videos itself).
// Class room photos (chat/) are there too, under random names that are only shown to the class.

const UPLOAD_MAX_BYTES = [
    'videos' => 3 * 1024 * 1024 * 1024, // 3GB
    'pdfs' => 500 * 1024 * 1024,        // 500MB
    'thumbnails' => 5 * 1024 * 1024,    // 5MB
    'stories' => 500 * 1024 * 1024,     // 500MB: student video stories
    'chat' => 8 * 1024 * 1024,          // 8MB: photos in a campus class room (shrunk after upload)
];

const UPLOAD_ALLOWED_TYPES = [
    'videos' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
    'pdfs' => ['application/pdf'],
    'thumbnails' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
    'stories' => ['video/mp4', 'video/webm', 'video/quicktime'],
    'chat' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
];

function private_uploads_root(): string {
    return __DIR__ . '/../private-uploads';
}

function public_uploads_root(): string {
    return __DIR__ . '/../uploads';
}

function format_max_size(int $bytes): string {
    if ($bytes >= 1024 * 1024 * 1024) return round($bytes / (1024 * 1024 * 1024), 1) . 'GB';
    return round($bytes / (1024 * 1024)) . 'MB';
}

/**
 * Saves an uploaded file ($_FILES['field']) and returns a reference to it:
 * a web-relative URL for thumbnails, or an opaque private storage key
 * (e.g. "videos/<uuid>.mp4") for lesson videos/pdfs — resolved later via
 * resolve_private_path() and served only through campus-media.php.
 *
 * @throws RuntimeException on validation or filesystem failure.
 */
function save_upload(array $file, string $folder): string {
    if (!isset(UPLOAD_MAX_BYTES[$folder])) {
        throw new RuntimeException("Unknown upload folder: $folder");
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > UPLOAD_MAX_BYTES[$folder]) {
        throw new RuntimeException('File too large. Max size for ' . $folder . ' is ' . format_max_size(UPLOAD_MAX_BYTES[$folder]) . '.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, UPLOAD_ALLOWED_TYPES[$folder], true)) {
        throw new RuntimeException("Unsupported file type \"$mime\" for $folder.");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(16)) . ($ext ? ".$ext" : '');

    if ($folder === 'thumbnails' || $folder === 'stories' || $folder === 'chat') {
        $dir = public_uploads_root() . "/$folder";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $dest = "$dir/$safeName";
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }
        return "/uploads/$folder/$safeName";
    }

    $dir = private_uploads_root() . "/$folder";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $dest = "$dir/$safeName";
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }
    return "$folder/$safeName";
}

/** Resolves a private storage key (as returned by save_upload) to an absolute path. */
function resolve_private_path(string $storageKey): string {
    return private_uploads_root() . '/' . $storageKey;
}
