<?php

require_once __DIR__ . '/mail.php';

/** Generic message — do not reveal whether email exists. */
function passwordResetRequestMessage(): string
{
    return 'If an account exists for this email, a 6-digit verification code was sent. '
        . 'Check your inbox and spam folder, then enter the code below.';
}

function passwordResetLogHint(): string
{
    if (mailDriver() !== 'log') {
        return '';
    }
    return 'Development mode: the code is in storage/logs/mail.log (not sent to a real inbox).';
}

function isLocalDevRequest(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

function passwordResetExpirySeconds(): int
{
    return max(300, mailConfigInt('PASSWORD_RESET_EXPIRY', 900));
}

function passwordResetExpiryMinutes(): int
{
    return max(5, (int) ceil(passwordResetExpirySeconds() / 60));
}

function passwordResetThrottleOk(): bool
{
    $window = mailConfigInt('PASSWORD_RESET_RATE_WINDOW', 900);
    $max = mailConfigInt('PASSWORD_RESET_RATE_MAX', 5);
    $now = time();
    $attempts = $_SESSION['pwd_reset_attempts'] ?? [];
    $attempts = array_values(array_filter($attempts, static fn (int $t) => ($now - $t) < $window));
    $_SESSION['pwd_reset_attempts'] = $attempts;

    return count($attempts) < $max;
}

function recordPasswordResetAttempt(): void
{
    $_SESSION['pwd_reset_attempts'] = $_SESSION['pwd_reset_attempts'] ?? [];
    $_SESSION['pwd_reset_attempts'][] = time();
}

function passwordVerifyThrottleOk(): bool
{
    $window = 900;
    $max = 10;
    $now = time();
    $attempts = $_SESSION['pwd_verify_attempts'] ?? [];
    $attempts = array_values(array_filter($attempts, static fn (int $t) => ($now - $t) < $window));
    $_SESSION['pwd_verify_attempts'] = $attempts;

    return count($attempts) < $max;
}

function recordPasswordVerifyAttempt(): void
{
    $_SESSION['pwd_verify_attempts'] = $_SESSION['pwd_verify_attempts'] ?? [];
    $_SESSION['pwd_verify_attempts'][] = time();
}

function generateVerificationCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function hashResetCode(string $code, int $userId): string
{
    $code = preg_replace('/\D/', '', $code);
    return hash('sha256', $code . '|' . $userId);
}

/**
 * Send 6-digit code by email for any registered account (admin or user).
 *
 * @return array{ok: bool, error?: string}
 */
function issuePasswordResetForUser(PDO $db, int $userId, string $email): array
{
    ensureMailConfig();
    $code = generateVerificationCode();
    $codeHash = hashResetCode($code, $userId);
    $expires = date('Y-m-d H:i:s', time() + passwordResetExpirySeconds());

    $stmt = $db->prepare(
        'UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?'
    );
    $stmt->execute([$codeHash, $expires, $userId]);

    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Could not save verification code.'];
    }

    $appName = APP_NAME ?? 'Inbound Counting';
    $minutes = passwordResetExpiryMinutes();

    $subject = $appName . ' — Password reset code';
    $body = "Hello,\n\n"
        . "Your password reset verification code for {$appName} is:\n\n"
        . "    {$code}\n\n"
        . "This code expires in {$minutes} minutes.\n\n"
        . "Enter this code on the forgot password page with your new password.\n\n"
        . "If you did not request this, ignore this email.\n\n"
        . "— {$appName}\n";

    $sent = sendAppMail($email, $subject, $body);
    if (!$sent['ok']) {
        error_log('Password reset mail failed: ' . ($sent['error'] ?? 'unknown'));
        $db->prepare(
            'UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE id = ?'
        )->execute([$userId]);
        return $sent;
    }

    return ['ok' => true];
}

/**
 * Step 1: request verification code by email.
 *
 * @return array{ok: bool, message: string, hint?: string, error?: string, show_verify?: bool, email?: string}
 */
function processPasswordResetRequest(PDO $db, string $email): array
{
    $email = trim(strtolower($email));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => '', 'error' => 'Please enter a valid email address.'];
    }

    if (!passwordResetThrottleOk()) {
        return [
            'ok' => false,
            'message' => '',
            'error' => 'Too many requests. Please wait about 15 minutes and try again.',
        ];
    }

    recordPasswordResetAttempt();

    $stmt = $db->prepare('SELECT id, role FROM users WHERE LOWER(email) = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    $devHint = null;
    $localDev = isLocalDevRequest();
    $codeSent = false;

    if (!$row) {
        if ($localDev) {
            $devHint = 'Local hint: no account has this email — no code was sent.';
        }
    } else {
        $mailResult = issuePasswordResetForUser($db, (int) $row['id'], $email);
        if (!$mailResult['ok'] && mailDriver() === 'smtp') {
            return [
                'ok' => false,
                'message' => '',
                'error' => 'Could not send email: ' . ($mailResult['error'] ?? 'SMTP error')
                    . '. Check config/mail.php.',
            ];
        }
        $codeSent = (bool) ($mailResult['ok'] ?? false);
        if ($localDev && $codeSent && mailDriver() === 'smtp') {
            $devHint = 'Local hint: code emailed to ' . $email . '. Check inbox and spam on your phone.';
        }
    }

    $hint = $devHint ?? passwordResetLogHint();
    if (($hint === null || $hint === '') && mailDriver() === 'smtp') {
        $hint = 'Check your Gmail inbox and spam folder for the 6-digit code.';
    }

    return [
        'ok' => true,
        'message' => passwordResetRequestMessage(),
        'hint' => $hint !== '' && $hint !== null ? $hint : null,
        'show_verify' => true,
        'email' => $email,
        'code_sent' => $codeSent,
    ];
}

/**
 * Step 2: verify code and set new password.
 *
 * @return array{ok: bool, message?: string, error?: string}
 */
function processPasswordResetVerify(
    PDO $db,
    string $email,
    string $code,
    string $newPassword,
    string $confirmPassword
): array {
    $email = trim(strtolower($email));
    $code = preg_replace('/\D/', '', trim($code));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }

    if (strlen($code) !== 6) {
        return ['ok' => false, 'error' => 'Please enter the 6-digit verification code from your email.'];
    }

    if (!passwordVerifyThrottleOk()) {
        return ['ok' => false, 'error' => 'Too many incorrect attempts. Please wait and try again.'];
    }

    recordPasswordVerifyAttempt();

    if (strlen($newPassword) < 6) {
        return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'error' => 'Passwords do not match.'];
    }

    $stmt = $db->prepare(
        'SELECT id, reset_token, reset_token_expires FROM users WHERE LOWER(email) = ?'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || empty($row['reset_token']) || empty($row['reset_token_expires'])) {
        return ['ok' => false, 'error' => 'Invalid or expired code. Request a new code.'];
    }

    if (strtotime($row['reset_token_expires']) < time()) {
        return ['ok' => false, 'error' => 'This code has expired. Request a new code.'];
    }

    $expected = hashResetCode($code, (int) $row['id']);
    if (!hash_equals($row['reset_token'], $expected)) {
        return ['ok' => false, 'error' => 'Incorrect verification code.'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $db->prepare(
        'UPDATE users
         SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL
         WHERE id = ?'
    );
    $upd->execute([$hash, (int) $row['id']]);

    if ($upd->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Could not update password.'];
    }

    unset($_SESSION['pwd_verify_attempts']);

    return ['ok' => true, 'message' => 'Password updated. You can log in now.'];
}
