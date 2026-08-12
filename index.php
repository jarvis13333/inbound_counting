<?php

require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn() && !isSessionExpired()) {
    header('Location: ' . dashboardUrl());
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
