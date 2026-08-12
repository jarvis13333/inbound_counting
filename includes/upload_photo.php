<?php

const PHOTO_UPLOAD_DIR = 'uploads/photos';
const PHOTO_MAX_BYTES = 5 * 1024 * 1024;
const PHOTO_ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function photoUploadDir(): string
{
    return dirname(__DIR__) . '/' . PHOTO_UPLOAD_DIR;
}

function deleteStoredPhoto(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }
    if (strpos($relativePath, '..') !== false || !str_starts_with($relativePath, PHOTO_UPLOAD_DIR . '/')) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

/** @deprecated use deleteStoredPhoto */
function deleteCountingPhoto(?string $relativePath): void
{
    deleteStoredPhoto($relativePath);
}

/**
 * @return string|null Relative path e.g. uploads/photos/a1_abc.jpg
 */
function saveUploadedPhoto(array $file, string $ownerPrefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed.');
    }
    if (($file['size'] ?? 0) > PHOTO_MAX_BYTES) {
        throw new RuntimeException('Photo must be 5 MB or smaller.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, PHOTO_ALLOWED, true)) {
        throw new RuntimeException('Photo must be JPEG, PNG, WebP, or GIF.');
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'jpg',
    };

    $dir = photoUploadDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Cannot create upload directory.');
    }

    $safePrefix = preg_replace('/[^a-z0-9]/i', '', $ownerPrefix) ?: 'x';
    $name = $safePrefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save photo.');
    }

    return PHOTO_UPLOAD_DIR . '/' . $name;
}

function resolveStoredPhotoPath(?string $existing, array $file, string $ownerPrefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $newPath = saveUploadedPhoto($file, $ownerPrefix);
        if ($existing && $existing !== $newPath) {
            deleteStoredPhoto($existing);
        }
        return $newPath;
    }

    $keep = trim($existing ?? '');
    if ($keep === '' || strpos($keep, '..') !== false || !str_starts_with($keep, PHOTO_UPLOAD_DIR . '/')) {
        return null;
    }
    $full = dirname(__DIR__) . '/' . $keep;
    return is_file($full) ? $keep : null;
}

/** @deprecated use resolveStoredPhotoPath */
function resolveCountingPhotoPath(?string $existing, array $file, int $userId): ?string
{
    return resolveStoredPhotoPath($existing, $file, 'u' . $userId);
}
