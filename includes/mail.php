<?php

/**
 * Mail helper: log (dev), smtp (real inbox), or mail() (cPanel).
 */

require_once __DIR__ . '/smtp.php';

function mailConfigInt(string $name, int $default): int
{
    ensureMailConfig();
    return defined($name) ? (int) constant($name) : $default;
}

function mailDriver(): string
{
    ensureMailConfig();
    return defined('MAIL_DRIVER') ? strtolower((string) MAIL_DRIVER) : 'log';
}

function ensureMailConfig(): void
{
    if (defined('MAIL_DRIVER') && defined('PASSWORD_RESET_RATE_WINDOW')) {
        return;
    }
    $path = dirname(__DIR__) . '/config/mail.php';
    if (is_file($path)) {
        require $path;
        return;
    }
    $example = dirname(__DIR__) . '/config/mail.example.php';
    if (is_file($example)) {
        require $example;
        return;
    }
    define('MAIL_DRIVER', 'log');
    define('MAIL_FROM', 'noreply@localhost');
    define('MAIL_FROM_NAME', 'Inbound Counting');
    define('APP_NAME', 'Inbound Counting');
    define('PASSWORD_RESET_EXPIRY', 3600);
    define('PASSWORD_RESET_RATE_WINDOW', 900);
    define('PASSWORD_RESET_RATE_MAX', 5);
}

function mailFromHeader(): string
{
    ensureMailConfig();
    $name = MAIL_FROM_NAME ?? 'Inbound Counting';
    $from = MAIL_FROM ?? 'noreply@localhost';
    if (preg_match('/[\r\n]/', $name . $from)) {
        return 'From: noreply@localhost';
    }
    return 'From: ' . sprintf('%s <%s>', $name, $from);
}

/**
 * @return array{ok: bool, error?: string}
 */
function sendAppMail(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): array
{
    ensureMailConfig();
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email.'];
    }

    $driver = mailDriver();

    if ($driver === 'log') {
        $result = logAppMail($to, $subject, $bodyText, $bodyHtml);
        logMailAttempt('log', $to, $subject, $result);
        return $result;
    }

    if ($driver === 'smtp') {
        $result = sendSmtpMail($to, $subject, $bodyText);
        logMailAttempt('smtp', $to, $subject, $result);
        return $result;
    }

    if ($driver === 'mail') {
        $result = sendPhpMail($to, $subject, $bodyText, $bodyHtml);
        logMailAttempt('mail', $to, $subject, $result);
        return $result;
    }

    return ['ok' => false, 'error' => 'Unknown MAIL_DRIVER: ' . $driver . ' (use log, smtp, or mail)'];
}

/** Append send result to storage/logs/mail_attempts.log (for debugging). */
function logMailAttempt(string $driver, string $to, string $subject, array $result): void
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $status = ($result['ok'] ?? false) ? 'OK' : ('FAIL: ' . ($result['error'] ?? 'unknown'));
    $line = date('Y-m-d H:i:s')
        . " | driver={$driver} | To: {$to} | Subject: " . str_replace(["\r", "\n"], ' ', $subject)
        . " | {$status}\n";
    @file_put_contents($dir . '/mail_attempts.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * @return array{ok: bool, error?: string}
 */
function logAppMail(string $to, string $subject, string $bodyText, ?string $bodyHtml): array
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create log directory.'];
        }
    }
    $file = $dir . '/mail.log';
    $entry = str_repeat('-', 72) . "\n"
        . date('Y-m-d H:i:s') . " | To: {$to} | Subject: {$subject}\n"
        . $bodyText . "\n";
    if ($bodyHtml) {
        $entry .= "[HTML body omitted in log]\n";
    }
    $entry .= "\n";

    if (@file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Could not write mail log.'];
    }

    return ['ok' => true];
}

/**
 * @return array{ok: bool, error?: string}
 */
function sendPhpMail(string $to, string $subject, string $bodyText, ?string $bodyHtml): array
{
    $headers = [
        mailFromHeader(),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $subject = str_replace(["\r", "\n"], '', $subject);
    $ok = @mail($to, $subject, $bodyText, implode("\r\n", $headers));
    if (!$ok) {
        return ['ok' => false, 'error' => 'mail() failed. Check server mail settings or use MAIL_DRIVER=log for testing.'];
    }

    return ['ok' => true];
}
