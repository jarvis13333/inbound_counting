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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($username === '' || strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDb();
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare(
                "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')"
            );
            try {
                $stmt->execute([$username, $email, $hash]);
                $success = 'Account created. You can log in now.';
            } catch (PDOException $e) {
                $error = 'Could not create account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Product Inbound Shipment Counting Record</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(stylesheetHref()) ?>">
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card">
      <h1>Create Account</h1>
      <p class="subtitle">Warehouse user registration</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <p class="auth-links"><a href="<?= BASE_URL ?>/login.php" class="btn btn-primary">Go to Login</a></p>
      <?php else: ?>
      <form method="post" action="">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required minlength="3"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <div class="password-wrap">
            <input type="password" id="password" name="password" required minlength="6">
            <button type="button" class="toggle-password" data-target="password">Show</button>
          </div>
        </div>
        <div class="form-group">
          <label for="password_confirm">Confirm Password</label>
          <div class="password-wrap">
            <input type="password" id="password_confirm" name="password_confirm" required minlength="6">
            <button type="button" class="toggle-password" data-target="password_confirm">Show</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
      </form>
      <?php endif; ?>

      <div class="auth-links">
        <p><a href="<?= BASE_URL ?>/login.php">Already have an account? Login</a></p>
      </div>
    </div>
  </div>
  <?php renderSiteFooter(); ?>
  <script src="<?= BASE_URL ?>/assets/js/auth.js"></script>
</body>
</html>
