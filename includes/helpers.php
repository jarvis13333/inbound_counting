<?php

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }
    return false;
}

function appAbsoluteUrl(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $scheme = isHttpsRequest() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL . $path;
}

/** @return array{id: int}|null */
function findUserByResetToken(PDO $db, string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT id FROM users
         WHERE reset_token = ?
           AND reset_token IS NOT NULL
           AND reset_token_expires IS NOT NULL
           AND reset_token_expires > NOW()'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function normalizeResetToken(string $raw): string
{
    $token = trim(urldecode($raw));
    if (preg_match('/^[a-f0-9]{64}/i', $token, $m)) {
        return strtolower($m[0]);
    }
    return '';
}

/** Normalize MySQL TIME / HH:MM to HH:MM:SS for form input. */
function normalizeTimeForInput(?string $time): string
{
    if ($time === null || trim($time) === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', trim($time), $m)) {
        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
    }
    return '';
}

/** 12-hour display with seconds and AM/PM, e.g. 2:45:30 PM */
function formatTimeForDisplay(?string $time): string
{
    if ($time === null || trim($time) === '') {
        return '—';
    }
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', trim($time), $m)) {
        return $time;
    }
    $h = (int) $m[1];
    $min = $m[2];
    $sec = $m[3] ?? '00';
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12 ?: 12;

    return sprintf('%d:%s:%s %s', $h12, $min, $sec, $ampm);
}

function stylesheetHref(): string
{
    $path = dirname(__DIR__) . '/assets/css/style.css';
    $ver = is_file($path) ? (int) @filemtime($path) : 1;

    return BASE_URL . '/assets/css/style.css?v=' . $ver;
}
