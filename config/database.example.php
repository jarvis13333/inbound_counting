<?php
/**
 * Copy to database.php on cPanel and fill in values from: MySQL Databases + phpMyAdmin.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_inbound_counting'); // full database name from cPanel
define('DB_USER', 'cpaneluser_dbuser');           // full MySQL username from cPanel
define('DB_PASS', 'your_mysql_password_here');
define('SESSION_TIMEOUT', 300);
define('MAX_SESSIONS', 60);

function appBaseUrl(): string
{
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(dirname(__DIR__)) ?: '';
    if ($docRoot !== '' && $appRoot !== '' && strncmp($appRoot, $docRoot, strlen($docRoot)) === 0) {
        $rel = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        return $rel === '' ? '' : $rel;
    }
    return '/inbound_counting';
}

define('BASE_URL', appBaseUrl());

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        require_once __DIR__ . '/../includes/migrate.php';
        ensureDatabaseSchema($pdo);
    }
    return $pdo;
}
