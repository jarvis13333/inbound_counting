<?php
/**
 * Copy to config/mail.php and fill in your SMTP account.
 *
 * MAIL_DRIVER:
 *   smtp — send to real email (Gmail / QQ / 163 / company / cPanel SMTP)  ← recommended
 *   log  — only write to storage/logs/mail.log (offline dev)
 *   mail — PHP mail() on server (some hosts only)
 */
define('MAIL_DRIVER', 'smtp');

define('MAIL_FROM', 'your-email@gmail.com');       // must match SMTP account or authorized sender
define('MAIL_FROM_NAME', 'Inbound Counting');
define('APP_NAME', 'Product Inbound Shipment Counting Record');

/* ---------- SMTP (required when MAIL_DRIVER = smtp) ---------- */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);              // Gmail/Outlook TLS: 587 | QQ/163 SSL: 465
define('SMTP_SECURE', 'tls');          // tls | ssl | (empty = no encryption, not recommended)
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');  // NOT your login password — use app / authorization code

/* Examples (uncomment one block and comment Gmail above):

// QQ Mail
// define('SMTP_HOST', 'smtp.qq.com');
// define('SMTP_PORT', 465);
// define('SMTP_SECURE', 'ssl');
// define('SMTP_USER', '123456789@qq.com');
// define('SMTP_PASS', '16-digit-authorization-code');

// NetEase 163
// define('SMTP_HOST', 'smtp.163.com');
// define('SMTP_PORT', 465);
// define('SMTP_SECURE', 'ssl');
// define('SMTP_USER', 'you@163.com');
// define('SMTP_PASS', 'authorization-code');

// cPanel domain email
// define('SMTP_HOST', 'mail.yourdomain.com');
// define('SMTP_PORT', 587);
// define('SMTP_SECURE', 'tls');
// define('SMTP_USER', 'noreply@yourdomain.com');
// define('SMTP_PASS', 'mailbox-password');
*/

/** Verification code validity in seconds (default 15 minutes). */
define('PASSWORD_RESET_EXPIRY', 900);
define('PASSWORD_RESET_RATE_WINDOW', 900);
define('PASSWORD_RESET_RATE_MAX', 5);
