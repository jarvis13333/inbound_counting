<?php

require_once __DIR__ . '/includes/auth.php';

logoutUser();

$timeout = isset($_GET['timeout']);
header('Location: ' . BASE_URL . '/login.php' . ($timeout ? '?timeout=1' : ''));
exit;
