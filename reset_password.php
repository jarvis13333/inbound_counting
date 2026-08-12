<?php

require_once __DIR__ . '/includes/auth.php';

startSecureSession();
header('Location: ' . BASE_URL . '/forgot_password.php');
exit;
