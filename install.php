<?php
/**
 * One-time setup: creates tables and default admin account.
 * Delete this file after installation on production.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/site_footer.php';

$messages = [];
$errors = [];

$sqlFile = __DIR__ . '/sql/schema.sql';
if (!is_file($sqlFile)) {
    die('schema.sql not found.');
}

$raw = file_get_contents($sqlFile);
$raw = preg_replace('/^--.*$/m', '', $raw);
$statements = array_filter(array_map('trim', explode(';', $raw)));

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    foreach ($statements as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }

    require_once __DIR__ . '/includes/migrate.php';
    $db = getDb();

    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute(['admin']);
    if (!$stmt->fetch()) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $ins = $db->prepare(
            "INSERT INTO users (username, email, password_hash, role) VALUES ('admin', 'admin@localhost', ?, 'admin')"
        );
        $ins->execute([$hash]);
        $messages[] = 'Default admin created — username: admin, password: admin123 (change after login).';
    } else {
        $messages[] = 'Admin account already exists.';
    }

    $messages[] = 'Database tables installed successfully.';
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Install - Product Inbound Counting</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(stylesheetHref()) ?>">
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card">
      <h1>Installation</h1>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
      <?php foreach ($messages as $msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach; ?>
      <?php if (!$errors): ?>
        <p class="auth-links"><a href="<?= BASE_URL ?>/login.php">Go to Login</a></p>
      <?php endif; ?>
    </div>
  </div>
  <?php renderSiteFooter(); ?>
</body>
</html>
