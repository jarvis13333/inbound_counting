<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/site_footer.php';

startSecureSession();

if (isLoggedIn() && !isSessionExpired()) {
    header('Location: ' . dashboardUrl());
    exit;
}

$error = '';
$timeout = isset($_GET['timeout']);
$loginUsername = 'admin';
$loginPassword = '123456';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = getDb()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            loginUser((int) $user['id'], $user['username'], $user['role']);
            header('Location: ' . dashboardUrl());
            exit;
        }
        $error = 'Invalid username or password.';
    }
    $loginUsername = trim($_POST['username'] ?? '');
    $loginPassword = $_POST['password'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Product Inbound Shipment Counting Record</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(stylesheetHref()) ?>">
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card">
      <h1>Product Inbound Shipment Counting Record</h1>
      <p class="subtitle">Admin &amp; User Login</p>

      <?php if ($timeout): ?>
        <div class="alert alert-info">You were logged out due to 5 minutes of inactivity.</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <div class="form-group">
          <label for="username">Username or Email</label>
          <input type="text" id="username" name="username" required
                 value="<?= htmlspecialchars($loginUsername) ?>">
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="password-wrap">
            <input type="password" id="password" name="password" required
                   value="<?= htmlspecialchars($loginPassword) ?>">
            <button type="button" class="toggle-password" data-target="password">Show</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
      </form>

      <div class="auth-links">
        <p><a href="<?= BASE_URL ?>/forgot_password.php">Forgot password? (email verification code)</a></p>
        <p><a href="<?= BASE_URL ?>/register.php">Create an account — Register</a></p>
      </div>
    </div>
  </div>
  <?php renderSiteFooter(); ?>
  <script src="<?= BASE_URL ?>/assets/js/auth.js"></script>
</body>
</html>
