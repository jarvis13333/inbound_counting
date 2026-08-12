<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_layout.php';

requireAdmin();

$db = getDb();
$user = getCurrentUser();
$flash = flashGet();

$shipmentCount = (int) $db->query('SELECT COUNT(*) FROM admin_shipments')->fetchColumn();
$countingCount = (int) $db->query('SELECT COUNT(*) FROM user_count_records')->fetchColumn();
$userCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$todayShipments = (int) $db->query(
    'SELECT COUNT(*) FROM admin_shipments WHERE inbound_date = CURDATE()'
)->fetchColumn();
$todayCounting = (int) $db->query(
    'SELECT COUNT(*) FROM user_count_records WHERE counting_date = CURDATE()'
)->fetchColumn();

adminPageStart('Admin Home', 'dashboard', $user);
adminRenderFlash($flash);
?>
    <section class="panel">
      <h2>Admin Home</h2>
      <p class="hint">Choose a section below. Each area has its own page.</p>
      <?php adminShortcutCards(); ?>
    </section>

    <section class="panel admin-stats-panel">
      <h2>Quick Stats</h2>
      <div class="admin-stats">
        <div class="admin-stat">
          <span class="admin-stat-value"><?= $shipmentCount ?></span>
          <span class="admin-stat-label">Total shipments</span>
        </div>
        <div class="admin-stat">
          <span class="admin-stat-value"><?= $todayShipments ?></span>
          <span class="admin-stat-label">Shipments today</span>
        </div>
        <div class="admin-stat">
          <span class="admin-stat-value"><?= $countingCount ?></span>
          <span class="admin-stat-label">Counting records</span>
        </div>
        <div class="admin-stat">
          <span class="admin-stat-value"><?= $todayCounting ?></span>
          <span class="admin-stat-label">Counted today</span>
        </div>
        <div class="admin-stat">
          <span class="admin-stat-value"><?= $userCount ?></span>
          <span class="admin-stat-label">Warehouse users</span>
        </div>
      </div>
    </section>
<?php
adminPageEnd();
