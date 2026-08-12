<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/status.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

$action = $_POST['action'] ?? '';
$db = getDb();
$adminId = (int) $_SESSION['user_id'];

function redirectAdminUsers(string $query = ''): void
{
    header('Location: ' . BASE_URL . '/admin/users.php' . $query);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0 && $id !== $adminId) {
        $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && $row['role'] === 'user') {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flashSet('success', 'User account deleted.');
        } else {
            flashSet('error', 'Cannot delete this account.');
        }
    }
    redirectAdminUsers();
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

if ($action === 'create') {
    if ($username === '' || $email === '' || $password === '') {
        flashSet('error', 'Username, email and password are required.');
        redirectAdminUsers();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flashSet('error', 'Please enter a valid email address.');
        redirectAdminUsers();
    }
    if (strlen($password) < 6) {
        flashSet('error', 'Password must be at least 6 characters.');
        redirectAdminUsers();
    }
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')"
        );
        $stmt->execute([$username, $email, $hash]);
        flashSet('success', 'User account created.');
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            flashSet('error', 'Username or email already exists.');
        } else {
            flashSet('error', 'Could not create user.');
        }
    }
    redirectAdminUsers();
}

if ($action === 'update' && $id > 0) {
    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || $row['role'] !== 'user') {
        flashSet('error', 'User not found.');
        redirectAdminUsers();
    }
    if ($username === '' || $email === '') {
        flashSet('error', 'Username and email are required.');
        redirectAdminUsers('?user_edit=' . $id);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flashSet('error', 'Please enter a valid email address.');
        redirectAdminUsers('?user_edit=' . $id);
    }
    if ($newPassword !== '' && strlen($newPassword) < 6) {
        flashSet('error', 'New password must be at least 6 characters.');
        redirectAdminUsers('?user_edit=' . $id);
    }
    try {
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare(
                'UPDATE users SET username = ?, email = ?, password_hash = ? WHERE id = ?'
            );
            $stmt->execute([$username, $email, $hash, $id]);
        } else {
            $stmt = $db->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
            $stmt->execute([$username, $email, $id]);
        }
        flashSet('success', 'User account updated.');
        redirectAdminUsers();
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            flashSet('error', 'Username or email already exists.');
        } else {
            flashSet('error', 'Could not update user.');
        }
        redirectAdminUsers('?user_edit=' . $id);
    }
}

flashSet('error', 'Invalid action.');
redirectAdminUsers();
