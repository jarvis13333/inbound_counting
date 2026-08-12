<?php
/**
 * Local SMTP test — open only on this PC, then delete on production.
 * Example: http://localhost/inbound_counting/test_smtp.php?to=your@gmail.com
 */
$allowed = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed, true)) {
    http_response_code(403);
    exit('Localhost only. Remove this file on production.');
}

require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/site_footer.php';

$to = trim($_GET['to'] ?? '');
$result = null;

if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
    ensureMailConfig();
    $result = sendAppMail(
        $to,
        'Inbound Counting SMTP test',
        "If you received this, SMTP is configured correctly.\nTime: " . date('Y-m-d H:i:s')
    );
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>SMTP 测试</title>
  <style>body{font-family:sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem} .ok{color:#059669}.err{color:#dc2626} code{background:#f1f5f9;padding:2px 6px}</style>
</head>
<body>
  <h1>Gmail SMTP 测试</h1>
  <p>先改好 <code>config/mail.php</code>，再访问：</p>
  <p><code>?to=你的邮箱@gmail.com</code></p>
  <form method="get">
    <label>收件邮箱：<input type="email" name="to" value="<?= htmlspecialchars($to) ?>" required style="width:260px"></label>
    <button type="submit">发送测试邮件</button>
  </form>
  <?php if ($result !== null): ?>
    <?php if ($result['ok']): ?>
      <p class="ok"><strong>发送成功。</strong>请查收件箱和垃圾邮件（手机 Gmail 同步即可看到）。</p>
    <?php else: ?>
      <p class="err"><strong>发送失败：</strong><?= htmlspecialchars($result['error'] ?? '') ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <hr>
  <p><small>上线后请删除本文件 <code>test_smtp.php</code></small></p>
  <?php renderSiteFooter(); ?>
</body>
</html>
