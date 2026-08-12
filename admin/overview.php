<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/status.php';

requireAdmin();

$db = getDb();
$user = getCurrentUser();
$flash = flashGet();

$ovProduct = trim($_GET['oq_product'] ?? '');
$ovShipment = trim($_GET['oq_shipment'] ?? '');
$ovFilter = $_GET['oq_filter'] ?? 'all';
$ovFilterDate = trim($_GET['oq_filter_date'] ?? date('Y-m-d'));

$countWhere = ['1=1'];
$countParams = [];

if ($ovProduct !== '') {
    $countWhere[] = 'ucr.product_name LIKE ?';
    $countParams[] = '%' . $ovProduct . '%';
}
if ($ovShipment !== '') {
    $countWhere[] = 'ucr.shipment_number LIKE ?';
    $countParams[] = '%' . $ovShipment . '%';
}
if ($ovFilter === 'today') {
    $countWhere[] = 'ucr.counting_date = CURDATE()';
} elseif ($ovFilter === 'daily' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ovFilterDate)) {
    $countWhere[] = 'ucr.counting_date = ?';
    $countParams[] = $ovFilterDate;
} elseif ($ovFilter === 'past7') {
    $countWhere[] = 'ucr.counting_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
}

$countsSql = 'SELECT ucr.*, u.username AS counter_username
              FROM user_count_records ucr
              JOIN users u ON u.id = ucr.user_id
              WHERE ' . implode(' AND ', $countWhere) . '
              ORDER BY ucr.counting_date DESC, ucr.id DESC';
$cStmt = $db->prepare($countsSql);
$cStmt->execute($countParams);
$countRows = $cStmt->fetchAll();

$overviewQs = http_build_query(array_filter([
    'oq_product' => $ovProduct,
    'oq_shipment' => $ovShipment,
    'oq_filter' => $ovFilter !== 'all' ? $ovFilter : null,
    'oq_filter_date' => $ovFilter === 'daily' ? $ovFilterDate : null,
]));

adminPageStart('User Counting Records', 'overview', $user);
adminRenderFlash($flash);
?>
    <section class="panel">
      <h2>User Submitted Counting Records</h2>
      <p class="hint">View all warehouse counting entries with status indicators.</p>
      <form method="get" class="toolbar toolbar-split">
        <div class="toolbar-fields toolbar-inline">
          <input type="text" name="oq_product" placeholder="Search product name"
                 value="<?= htmlspecialchars($ovProduct) ?>">
          <input type="text" name="oq_shipment" placeholder="Search inbound shipment number"
                 value="<?= htmlspecialchars($ovShipment) ?>">
          <select name="oq_filter">
            <option value="all" <?= $ovFilter === 'all' ? 'selected' : '' ?>>All records</option>
            <option value="today" <?= $ovFilter === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="daily" <?= $ovFilter === 'daily' ? 'selected' : '' ?>>Daily (pick date)</option>
            <option value="past7" <?= $ovFilter === 'past7' ? 'selected' : '' ?>>Past 7 days</option>
          </select>
          <input type="date" name="oq_filter_date" value="<?= htmlspecialchars($ovFilterDate) ?>"
                 class="<?= $ovFilter === 'daily' ? '' : 'hidden-field' ?>" id="overview_filter_date_input">
        </div>
        <div class="toolbar-actions">
          <button type="submit" class="btn btn-primary btn-sm">Apply</button>
          <?php if ($overviewQs || $ovFilter !== 'all'): ?>
            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/admin/overview.php">Clear</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="legend">
        <span>🟢 Counted</span>
        <span>🔴 Not counted</span>
        <span>🔵 Qty matches admin</span>
        <span>🟠 Qty mismatch</span>
      </div>

      <div class="data-table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Status</th>
              <th>Product</th>
              <th>Inbound #</th>
              <th>Counting Date</th>
              <th>Start Time</th>
              <th>Completion Time</th>
              <th>Counted Qty</th>
              <th>Counted By</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$countRows): ?>
              <tr><td colspan="9" class="empty">No user counting records yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($countRows as $c):
              $statusDots = matchStatusForCountRecord($db, $c);
            ?>
            <tr>
              <td><?= $statusDots ?></td>
              <td><?= htmlspecialchars($c['product_name']) ?></td>
              <td><?= htmlspecialchars($c['shipment_number']) ?></td>
              <td><?= htmlspecialchars($c['counting_date']) ?></td>
              <td><?= formatTimeForDisplay($c['start_time'] ?? null) ?></td>
              <td><?= formatTimeForDisplay($c['completion_time'] ?? null) ?></td>
              <td><?= (int) $c['total_quantity'] ?></td>
              <td><?= htmlspecialchars($c['counted_by']) ?></td>
              <td><?= htmlspecialchars($c['remarks'] ?? '') ?: '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <script>
    document.querySelector('[name=oq_filter]')?.addEventListener('change', function () {
      var el = document.getElementById('overview_filter_date_input');
      if (el) el.classList.toggle('hidden-field', this.value !== 'daily');
    });
    </script>
<?php
adminPageEnd();
