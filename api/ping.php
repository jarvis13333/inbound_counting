<?php

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

startSecureSession();

if (!isLoggedIn() || isSessionExpired()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expired' => true]);
    exit;
}

updateActivity();
echo json_encode(['ok' => true]);
