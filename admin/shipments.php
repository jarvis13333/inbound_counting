<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/status.php';

requireAdmin();

$db = getDb();
$user = getCurrentUser();
$flash = flashGet();

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM admin_shipments WHERE id = ?');
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch() ?: null;
}

$searchProduct = trim($_GET['q_product'] ?? '');
$searchShipment = trim($_GET['q_shipment'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$filterDate = trim($_GET['filter_date'] ?? date('Y-m-d'));

$where = ['1=1'];
$params = [];

if ($searchProduct !== '') {
    $where[] = 's.product_name LIKE ?';
    $params[] = '%' . $searchProduct . '%';
}
if ($searchShipment !== '') {
    $where[] = 's.shipment_number LIKE ?';
    $params[] = '%' . $searchShipment . '%';
}
if ($filter === 'today') {
    $where[] = 's.inbound_date = CURDATE()';
} elseif ($filter === 'daily' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $where[] = 's.inbound_date = ?';
    $params[] = $filterDate;
} elseif ($filter === 'past7') {
    $where[] = 's.inbound_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
}

$sql = 'SELECT s.* FROM admin_shipments s WHERE ' . implode(' AND ', $where)
    . ' ORDER BY s.inbound_date DESC, s.id DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$shipments = $stmt->fetchAll();

$filterQs = http_build_query(array_filter([
    'q_product' => $searchProduct,
    'q_shipment' => $searchShipment,
    'filter' => $filter !== 'all' ? $filter : null,
    'filter_date' => $filter === 'daily' ? $filterDate : null,
]));

$openShipmentModal = $editRow || isset($_GET['add']);
$listUrl = BASE_URL . '/admin/shipments.php' . ($filterQs ? '?' . $filterQs : '');
$addUrl = $listUrl . ($filterQs ? '&' : '?') . 'add=1';

adminPageStart('Inbound Shipments', 'shipments', $user);
adminRenderFlash($flash);
?>
    <section class="panel">
      <div class="panel-head-row">
        <h2>Admin Shipment Records</h2>
        <a href="<?= htmlspecialchars($addUrl) ?>" class="btn btn-primary btn-add-shipment">+ Add Inbound Shipment</a>
      </div>
      <form method="get" class="toolbar toolbar-split">
        <div class="toolbar-fields toolbar-inline">
          <input type="text" name="q_product" placeholder="Search product name"
                 value="<?= htmlspecialchars($searchProduct) ?>">
          <input type="text" name="q_shipment" placeholder="Search shipment number"
                 value="<?= htmlspecialchars($searchShipment) ?>">
          <select name="filter">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All records</option>
            <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="daily" <?= $filter === 'daily' ? 'selected' : '' ?>>Daily (pick date)</option>
            <option value="past7" <?= $filter === 'past7' ? 'selected' : '' ?>>Past 7 days</option>
          </select>
          <input type="date" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>"
                 class="<?= $filter === 'daily' ? '' : 'hidden-field' ?>" id="filter_date_input">
          <?php if ($editId > 0): ?>
            <input type="hidden" name="edit" value="<?= $editId ?>">
          <?php endif; ?>
        </div>
        <div class="toolbar-actions">
          <button type="submit" class="btn btn-primary btn-sm">Apply</button>
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
              <th>Inbound Date</th>
              <th>Product</th>
              <th>Shipment #</th>
              <th>Cartons</th>
              <th>Quantity</th>
              <th>Counted Sum</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$shipments): ?>
              <tr><td colspan="8" class="empty">No records found.</td></tr>
            <?php endif; ?>
            <?php foreach ($shipments as $s):
              $st = shipmentCountStatus($db, $s);
            ?>
            <tr>
              <td><?= renderStatusDots($st['dots']) ?></td>
              <td><?= htmlspecialchars($s['inbound_date']) ?></td>
              <td><?= htmlspecialchars($s['product_name']) ?></td>
              <td><?= htmlspecialchars($s['shipment_number']) ?></td>
              <td><?= (int) $s['total_carton'] ?></td>
              <td><?= (int) $s['total_quantity'] ?></td>
              <td><?= (int) $st['qty_sum'] ?></td>
              <td class="actions-cell">
                <a class="btn btn-sm btn-secondary"
                   href="?edit=<?= (int) $s['id'] ?><?= $filterQs ? '&' . $filterQs : '' ?>">Edit</a>
                <?php if ($st['counted']): ?>
                <form method="post" action="<?= BASE_URL ?>/admin/shipment_action.php" class="inline-form"
                      onsubmit="return confirm('Clear all counting records for this shipment? It will show as not counted again.');">
                  <input type="hidden" name="action" value="clear_count">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-secondary">Clear Count</button>
                </form>
                <?php endif; ?>
                <form method="post" action="<?= BASE_URL ?>/admin/shipment_action.php" class="inline-form"
                      onsubmit="return confirm('Delete this shipment record?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <div id="shipment-modal" class="modal-overlay<?= $openShipmentModal ? ' is-open' : '' ?>"
         aria-hidden="<?= $openShipmentModal ? 'false' : 'true' ?>"
         data-list-url="<?= htmlspecialchars($listUrl) ?>">
      <div class="modal-dialog" role="dialog" aria-labelledby="shipment-modal-title">
        <div class="modal-header">
          <h2 id="shipment-modal-title"><?= $editRow ? 'Edit Inbound Shipment' : 'Add Inbound Shipment' ?></h2>
          <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <form method="post" action="<?= BASE_URL ?>/admin/shipment_action.php" class="form-grid modal-form">
          <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
          <?php if ($editRow): ?>
            <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
          <?php endif; ?>
          <div class="form-group form-full">
            <label for="shipment_number">Inbound Shipment Number <span class="req">*</span></label>
            <input type="text" id="shipment_number" name="shipment_number" required
                   value="<?= htmlspecialchars($editRow['shipment_number'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="inbound_date">Inbound Date</label>
            <?php if ($editRow): ?>
            <input type="date" id="inbound_date" name="inbound_date"
                   value="<?= htmlspecialchars($editRow['inbound_date']) ?>">
            <?php else: ?>
            <input type="date" id="inbound_date" name="inbound_date" readonly
                   value="<?= htmlspecialchars(date('Y-m-d')) ?>">
            <p class="field-hint">Auto-filled with today&apos;s date</p>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label for="product_name">Product Name</label>
            <input type="text" id="product_name" name="product_name"
                   value="<?= htmlspecialchars($editRow['product_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="total_carton">Total Cartons</label>
            <input type="number" id="total_carton" name="total_carton" min="0"
                   value="<?= isset($editRow['total_carton']) ? (int) $editRow['total_carton'] : '' ?>"
                   placeholder="0">
          </div>
          <div class="form-group">
            <label for="total_quantity">Quantity</label>
            <input type="number" id="total_quantity" name="total_quantity" min="0"
                   value="<?= isset($editRow['total_quantity']) ? (int) $editRow['total_quantity'] : '' ?>"
                   placeholder="0">
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-save"><?= $editRow ? 'Update' : 'Save' ?></button>
            <a class="btn btn-secondary" href="<?= htmlspecialchars($listUrl) ?>" data-modal-close>Cancel</a>
          </div>
        </form>
      </div>
    </div>
    <script>
    document.querySelector('[name=filter]')?.addEventListener('change', function () {
      var el = document.getElementById('filter_date_input');
      if (el) el.classList.toggle('hidden-field', this.value !== 'daily');
    });
    </script>
<?php
adminPageEnd([
    BASE_URL . '/assets/js/admin-modal.js',
]);
