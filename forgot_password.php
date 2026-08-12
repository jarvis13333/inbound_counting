<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_reset.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/site_footer.php';

startSecureSession();

if (isLoggedIn() && !isSessionExpired()) {
    header('Location: ' . dashboardUrl());
    exit;
}

$error = '';
$success = '';
$hint = '';
$showVerify = false;
$prefillEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send_code';

    if ($action === 'verify') {
        $result = processPasswordResetVerify(
            getDb(),
            $_POST['email'] ?? '',
            $_POST['code'] ?? '',
            $_POST['new_password'] ?? '',
            $_POST['confirm_password'] ?? ''
        );
        if ($result['ok']) {
            $success = $result['message'] ?? 'Password updated.';
        } else {
            $error = $result['error'] ?? 'Could not reset password.';
            $showVerify = true;
            $prefillEmail = trim($_POST['email'] ?? '');
        }
    } else {
        $result = processPasswordResetRequest(getDb(), $_POST['email'] ?? '');
        if ($result['ok']) {
            $success = $result['message'];
            $hint = $result['hint'] ?? '';
            $showVerify = (bool) ($result['show_verify'] ?? true);
            $prefillEmail = $result['email'] ?? trim($_POST['email'] ?? '');
        } else {
            $error = $result['error'] ?? 'Could not process request.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Product Inbound Shipment Counting Record</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(stylesheetHref()) ?>">
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card auth-card-wide">
      <h1>Forgot Password</h1>

      <?php if ($success && !$showVerify): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <p class="auth-links"><a href="<?= BASE_URL ?>/login.php" class="btn btn-primary">Go to Login</a></p>
      <?php else: ?>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success && $showVerify): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php if ($hint): ?>
          <p class="auth-hint"><?= htmlspecialchars($hint) ?></p>
        <?php endif; ?>
      <?php endif; ?>

      <?php if (!$showVerify): ?>
      <p class="subtitle">Enter your registered email. We will send a 6-digit verification code.</p>
      <form method="post" action="">
        <input type="hidden" name="action" value="send_code">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Send Verification Code</button>
      </form>
      <?php else: ?>
      <p class="subtitle">Enter the 6-digit code from your email and choose a new password.</p>
      <form method="post" action="" class="verify-reset-form">
        <input type="hidden" name="action" value="verify">
        <div class="form-group">
          <label for="email_verify">Email</label>
          <input type="email" id="email_verify" name="email" required autocomplete="email"
                 value="<?= htmlspecialchars($prefillEmail) ?>">
        </div>
        <div class="form-group">
          <label for="code">Verification Code</label>
          <input type="text" id="code" name="code" required
                 inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6"
                 placeholder="000000" autocomplete="one-time-code"
                 class="input-code">
        </div>
        <div class="form-group">
          <label for="new_password">New Password</label>
          <div class="password-wrap">
            <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
            <button type="button" class="toggle-password" data-target="new_password">Show</button>
          </div>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <div class="password-wrap">
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
            <button type="button" class="toggle-password" data-target="confirm_password">Show</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </form>
      <p class="auth-hint"><a href="<?= BASE_URL ?>/forgot_password.php">Did not receive a code? Send again</a></p>
      <?php endif; ?>

      <?php endif; ?>

      <div class="auth-links">
        <p><a href="<?= BASE_URL ?>/login.php">Back to Login</a></p>
      </div>
    </div>
  </div>
  <?php renderSiteFooter(); ?>
  <script src="<?= BASE_URL ?>/assets/js/auth.js"></script>
</body>
</html>
