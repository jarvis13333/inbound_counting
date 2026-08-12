<?php
/**
 * One-time server check. Open in browser, read output, then DELETE this file.
 */
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

function line(string $label, string $value, bool $ok = true): void
{
    $mark = $ok ? 'OK' : 'FAIL';
    $color = $ok ? '#0a0' : '#c00';
    echo '<tr><td>' . htmlspecialchars($label) . '</td><td>' . htmlspecialchars($value) . '</td>';
    echo '<td style="color:' . $color . ';font-weight:bold">' . $mark . '</td></tr>';
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Diagnose</title>';
echo '<style>body{font-family:sans-serif;max-width:720px;margin:2rem auto}';
echo 'table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:8px;text-align:left}';
echo 'pre{background:#f5f5f5;padding:12px;overflow:auto}.warn{color:#c00}</style></head><body>';
echo '<h1>Inbound Counting — 诊断</h1>';
echo '<p>根据代码分析：登录页能打开但点登录 500，是因为 <strong>POST 会连接数据库</strong>，GET 不会。</p>';
echo '<table><tr><th>检查项</th><th>结果</th><th></th></tr>';

$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
line('PHP 版本', PHP_VERSION . ($phpOk ? '' : ' (需要 8.0+)'), $phpOk);
line('PDO', extension_loaded('pdo') ? '已安装' : '未安装', extension_loaded('pdo'));
line('pdo_mysql', extension_loaded('pdo_mysql') ? '已安装' : '未安装', extension_loaded('pdo_mysql'));

require_once __DIR__ . '/config/database.php';

$isLocalConfig = (DB_USER === 'root' && DB_PASS === '' && DB_NAME === 'inbound_counting');
line('database.php', DB_USER . '@' . DB_HOST . ' / ' . DB_NAME, !$isLocalConfig);
if ($isLocalConfig) {
    echo '</table><p class="warn"><strong>很可能的原因 #1：</strong>仍是 XAMPP 默认配置（root、空密码、库名 inbound_counting）。';
    echo 'cPanel 上必须改成 cPanel 创建的 MySQL 库名、用户名和密码。</p><table>';
}

$pdo = null;
$dbError = '';
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    line('连接 MySQL', '成功', true);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    line('连接 MySQL', $dbError, false);
    echo '</table>';
    echo '<h2>结论</h2><p class="warn">无法连上数据库。请修改 <code>config/database.php</code> 为 cPanel 里的库名/用户/密码，并在 phpMyAdmin 确认该库存在。</p>';
    echo '<p><strong>完成后删除本文件 diagnose.php</strong></p></body></html>';
    exit;
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$hasUsers = in_array('users', $tables, true);
line('users 表', $hasUsers ? '存在' : '不存在', $hasUsers);

if (!$hasUsers) {
    echo '</table>';
    echo '<h2>结论</h2><p class="warn"><strong>很可能的原因 #2：</strong>数据库已连上，但没有 users 表。';
    echo '请访问 <code>install.php</code> 一次，或在 phpMyAdmin 导入 <code>sql/schema.sql</code>。</p>';
    echo '<p><strong>完成后删除本文件 diagnose.php</strong></p></body></html>';
    exit;
}

$cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
$required = ['username', 'email', 'password_hash', 'role'];
$missing = array_diff($required, $cols);
line('users 字段', $missing === [] ? '完整' : '缺少: ' . implode(', ', $missing), $missing === []);

$loginSqlOk = true;
$loginSqlErr = '';
try {
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? OR email = ?');
    $stmt->execute(['admin', 'admin']);
    $stmt->fetch();
} catch (Throwable $e) {
    $loginSqlOk = false;
    $loginSqlErr = $e->getMessage();
}
line('登录 SQL（同 login.php）', $loginSqlOk ? '可执行' : $loginSqlErr, $loginSqlOk);

$migrateOk = true;
$migrateErr = '';
try {
    require_once __DIR__ . '/includes/migrate.php';
    ensureDatabaseSchema($pdo);
} catch (Throwable $e) {
    $migrateOk = false;
    $migrateErr = $e->getMessage();
}
line('自动迁移 migrate.php', $migrateOk ? '成功' : $migrateErr, $migrateOk);

$admin = $pdo->query("SELECT id, username, role FROM users WHERE username = 'admin' LIMIT 1")->fetch();
line('admin 账号', $admin ? ('存在, role=' . $admin['role']) : '不存在（请运行 install.php）', (bool) $admin);

echo '</table>';

if ($isLocalConfig) {
    echo '<h2>结论</h2><p class="warn">请先在 cPanel 修改 <code>config/database.php</code>。</p>';
} elseif (!$migrateOk) {
    echo '<h2>结论</h2><p class="warn">数据库连接正常，但 migrate 失败（常见于无 CREATE/ALTER 权限或表引擎不对）：<br>';
    echo '<pre>' . htmlspecialchars($migrateErr) . '</pre></p>';
} elseif ($missing !== []) {
    echo '<h2>结论</h2><p class="warn">users 表结构过旧，请运行 install.php 或导入 schema.sql。</p>';
} elseif (!$admin) {
    echo '<h2>结论</h2><p class="warn">请访问 install.php 创建 admin 账号。</p>';
} else {
    echo '<h2>结论</h2><p style="color:#0a0">本机检查均通过。若仍 500，请看 cPanel Errors 或 error_log；也可能是 session 目录权限问题。</p>';
}

echo '<p><strong>诊断完成后请删除 diagnose.php</strong></p></body></html>';
