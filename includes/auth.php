<?php

require_once __DIR__ . '/../config/database.php';

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function updateActivity(): void
{
    $_SESSION['last_activity'] = time();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

function isAdmin(): bool
{
    return currentRole() === 'admin';
}

function isUser(): bool
{
    return currentRole() === 'user';
}

function dashboardUrl(): string
{
    return isAdmin()
        ? BASE_URL . '/admin/dashboard.php'
        : BASE_URL . '/user/dashboard.php';
}

function requireLogin(): void
{
    startSecureSession();
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if (isSessionExpired()) {
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?timeout=1');
        exit;
    }
    updateActivity();
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/user/dashboard.php');
        exit;
    }
}

function requireUserRole(): void
{
    requireLogin();
    if (!isUser()) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

function isSessionExpired(): bool
{
    if (empty($_SESSION['last_activity'])) {
        return true;
    }
    return (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT;
}

function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    $stmt = getDb()->prepare('SELECT id, username, email, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function loginUser(int $userId, string $username, string $role): void
{
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    updateActivity();
}

function logoutUser(): void
{
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
