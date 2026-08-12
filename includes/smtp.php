<?php

/**
 * Minimal SMTP client (LOGIN + TLS/SSL) — no Composer required.
 */

/**
 * @return array{ok: bool, error?: string}
 */
function sendSmtpMail(string $to, string $subject, string $bodyText): array
{
    ensureMailConfig();

    $host = trim(SMTP_HOST ?? '');
    $port = (int) (SMTP_PORT ?? 587);
    $user = strtolower(trim(SMTP_USER ?? ''));
    $pass = normalizeSmtpPassword(SMTP_PASS ?? '');
    $secure = strtolower(trim(SMTP_SECURE ?? 'tls'));
    $from = strtolower(trim(MAIL_FROM ?? ''));
    $fromName = trim(MAIL_FROM_NAME ?? 'Inbound Counting');

    if ($host === '') {
        return ['ok' => false, 'error' => 'SMTP_HOST is not set in config/mail.php'];
    }
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'MAIL_FROM must be a valid email in config/mail.php'];
    }
    if ($user === '') {
        return ['ok' => false, 'error' => 'SMTP_USER is not set in config/mail.php'];
    }

    $placeholderCheck = smtpPlaceholderConfigError($user, $pass, $from);
    if ($placeholderCheck !== null) {
        return ['ok' => false, 'error' => $placeholderCheck];
    }

    $gmailCheck = smtpGmailConfigError($host, $user, $pass, $from);
    if ($gmailCheck !== null) {
        return ['ok' => false, 'error' => $gmailCheck];
    }

    $subject = str_replace(["\r", "\n"], '', $subject);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $bodyText = str_replace(["\r\n", "\r"], "\n", $bodyText);
    $bodyText = str_replace("\n.", "\n..", $bodyText);

    $message = "Date: " . date('r') . "\r\n"
        . "From: {$encodedName} <{$from}>\r\n"
        . "To: <{$to}>\r\n"
        . "Subject: {$encodedSubject}\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n"
        . "\r\n"
        . $bodyText;

    try {
        $smtp = new SmtpConnection($host, $port, $secure);
        $smtp->connect();
        $smtp->ehlo();
        if ($secure === 'tls') {
            $smtp->startTls();
            $smtp->ehlo();
        }
        if ($pass !== '') {
            $smtp->authLogin($user, $pass);
        }
        $smtp->mailFrom($from);
        $smtp->rcptTo($to);
        $smtp->data($message);
        $smtp->quit();
        $smtp->close();

        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => formatSmtpErrorMessage($e->getMessage())];
    }
}

/** App passwords are often copied with spaces — remove them. */
function normalizeSmtpPassword(string $pass): string
{
    return str_replace(' ', '', trim($pass));
}

function smtpPlaceholderConfigError(string $user, string $pass, string $from): ?string
{
    if (str_contains($user, 'your-email') || str_contains($pass, 'your-app')
        || str_contains($from, 'your-email')) {
        return 'config/mail.php still has placeholder values. Replace MAIL_FROM, SMTP_USER, and SMTP_PASS with your real Gmail and App Password.';
    }
    return null;
}

function smtpGmailConfigError(string $host, string $user, string $pass, string $from): ?string
{
    if (!str_contains(strtolower($host), 'gmail')) {
        return null;
    }
    if ($from !== $user) {
        return 'For Gmail, MAIL_FROM and SMTP_USER must be the same address (e.g. you@gmail.com).';
    }
    if (strlen($pass) < 16) {
        return 'Gmail SMTP_PASS must be a 16-character App Password (not your normal Gmail login password). Create one at: https://myaccount.google.com/apppasswords';
    }
    return null;
}

function formatSmtpErrorMessage(string $raw): string
{
    if (preg_match('/535|5\.7\.8|BadCredentials/i', $raw)) {
        return 'Gmail rejected login (535). Use an App Password — not your normal Gmail password. '
            . 'Steps: enable 2-Step Verification → https://myaccount.google.com/apppasswords → '
            . 'create password for "Mail" → paste 16 characters into SMTP_PASS in config/mail.php (no spaces). '
            . 'SMTP_USER and MAIL_FROM must be your full @gmail.com address. Raw: ' . trim($raw);
    }
    return trim($raw);
}

final class SmtpConnection
{
    /** @var resource|null */
    private $socket;

    public function __construct(
        private string $host,
        private int $port,
        private string $secure
    ) {
    }

    public function connect(): void
    {
        $remote = $this->host . ':' . $this->port;
        if ($this->secure === 'ssl') {
            $remote = 'ssl://' . $remote;
        }

        $errno = 0;
        $errstr = '';
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $this->socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$this->socket) {
            throw new RuntimeException("Cannot connect to SMTP server ({$errno}): {$errstr}");
        }

        stream_set_timeout($this->socket, 30);
        $this->expect(220);
    }

    public function ehlo(): void
    {
        $this->send('EHLO ' . $this->clientHost());
        $this->expect(250);
    }

    public function startTls(): void
    {
        $this->send('STARTTLS');
        $this->expect(220);

        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        if (!stream_socket_enable_crypto($this->socket, true, $crypto)) {
            throw new RuntimeException('STARTTLS negotiation failed.');
        }
    }

    public function authLogin(string $user, string $pass): void
    {
        $this->send('AUTH LOGIN');
        $this->expect(334);
        $this->send(base64_encode($user));
        $this->expect(334);
        $this->send(base64_encode($pass));
        $this->expect(235);
    }

    public function mailFrom(string $from): void
    {
        $this->send('MAIL FROM:<' . $from . '>');
        $this->expect(250);
    }

    public function rcptTo(string $to): void
    {
        $this->send('RCPT TO:<' . $to . '>');
        $this->expect(250);
    }

    public function data(string $message): void
    {
        $this->send('DATA');
        $this->expect(354);
        $lines = explode("\n", str_replace("\r", '', $message));
        foreach ($lines as $line) {
            $this->sendRaw($line . "\r\n");
        }
        $this->sendRaw("\r\n.\r\n");
        $this->expect(250);
    }

    public function quit(): void
    {
        $this->send('QUIT');
        $this->expect(221);
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    private function clientHost(): string
    {
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!preg_match('/^[a-z0-9.-]+$/i', $host)) {
            return 'localhost';
        }
        return $host;
    }

    private function send(string $line): void
    {
        $this->sendRaw($line . "\r\n");
    }

    private function sendRaw(string $data): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP socket is not connected.');
        }
        $written = fwrite($this->socket, $data);
        if ($written === false) {
            throw new RuntimeException('Failed to write to SMTP server.');
        }
    }

    private function expect(int $code): void
    {
        $response = $this->readResponse();
        if ((int) substr($response, 0, 3) !== $code) {
            throw new RuntimeException(trim($response) ?: "SMTP error, expected {$code}");
        }
    }

    private function readResponse(): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP socket is not connected.');
        }

        $data = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($data === '') {
            throw new RuntimeException('Empty response from SMTP server.');
        }

        return $data;
    }
}
