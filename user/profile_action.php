<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/status.php';

requireUserRole();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action !== 'change_password') {
    flashSet('error', 'Invalid action.');
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    flashSet('error', 'Please fill in all password fields.');
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

if (strlen($new) < 6) {
    flashSet('error', 'New password must be at least 6 characters.');
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

if ($new !== $confirm) {
    flashSet('error', 'New passwords do not match.');
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

$db = getDb();
$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? AND role = ?');
$stmt->execute([$userId, 'user']);
$row = $stmt->fetch();

if (!$row || !password_verify($current, $row['password_hash'])) {
    flashSet('error', 'Current password is incorrect.');
    header('Location: ' . BASE_URL . '/user/profile.php');
    exit;
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);

flashSet('success', 'Password updated successfully.');
header('Location: ' . BASE_URL . '/user/profile.php');
exit;
