<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/user_layout.php';
require_once __DIR__ . '/../includes/status.php';

requireUserRole();

$db = getDb();
$user = getCurrentUser();
$userId = (int) $_SESSION['user_id'];
$flash = flashGet();

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM user_count_records WHERE id = ? AND user_id = ?');
    $stmt->execute([$editId, $userId]);
    $editRow = $stmt->fetch() ?: null;
}

$excludeCountedExceptId = $editId > 0 ? $editId : 0;
$adminStmt = $db->prepare(
    'SELECT s.id, s.shipment_number, s.product_name, s.inbound_date, s.total_carton, s.total_quantity
     FROM admin_shipments s
     WHERE NOT EXISTS (
         SELECT 1 FROM user_count_records ucr
         WHERE ucr.admin_shipment_id = s.id AND ucr.id != ?
     )
     ORDER BY s.inbound_date DESC, s.shipment_number ASC'
);
$adminStmt->execute([$excludeCountedExceptId]);
$adminShipments = $adminStmt->fetchAll();

if ($editRow && $editRow['admin_shipment_id']) {
    $linkedId = (int) $editRow['admin_shipment_id'];
    $hasLinked = false;
    foreach ($adminShipments as $as) {
        if ((int) $as['id'] === $linkedId) {
            $hasLinked = true;
            break;
        }
    }
    if (!$hasLinked) {
        $linkStmt = $db->prepare(
            'SELECT id, shipment_number, product_name, inbound_date, total_carton, total_quantity
             FROM admin_shipments WHERE id = ?'
        );
        $linkStmt->execute([$linkedId]);
        $linkedRow = $linkStmt->fetch();
        if ($linkedRow) {
            array_unshift($adminShipments, $linkedRow);
        }
    }
}

$stmt = $db->prepare(
    'SELECT * FROM user_count_records
     WHERE user_id = ?
     ORDER BY counting_date DESC, id DESC'
);
$stmt->execute([$userId]);
$myRecords = $stmt->fetchAll();

$availableShipmentsStmt = $db->prepare(
    'SELECT s.*
     FROM admin_shipments s
     WHERE NOT EXISTS (
         SELECT 1 FROM user_count_records ucr
         WHERE ucr.admin_shipment_id = s.id
     )
     ORDER BY s.inbound_date DESC, s.id DESC'
);
$availableShipmentsStmt->execute();
$availableShipments = $availableShipmentsStmt->fetchAll();

$listUrl = BASE_URL . '/user/dashboard.php';
$addUrl = $listUrl . '?add=1';
$openCountModal = $editRow || isset($_GET['add']);
$initialCountDate = $editRow['counting_date'] ?? '';
$initialStartTimeInput = ($editRow && $editRow['start_time'])
    ? normalizeTimeForInput($editRow['start_time']) : '';
$initialEndTimeInput = ($editRow && $editRow['completion_time'])
    ? normalizeTimeForInput($editRow['completion_time']) : '';
$initialTotalCarton = '';
if ($editRow && !empty($editRow['admin_shipment_id'])) {
    $cartonStmt = $db->prepare('SELECT total_carton FROM admin_shipments WHERE id = ?');
    $cartonStmt->execute([(int) $editRow['admin_shipment_id']]);
    $cartonRow = $cartonStmt->fetch();
    $initialTotalCarton = $cartonRow ? (string) (int) $cartonRow['total_carton'] : '';
}

userPageStart('Counting Records', 'dashboard', $user);
userRenderFlash($flash);
?>

    <section class="panel">
      <div class="panel-head-row">
        <h2>My Counting Records</h2>
        <a href="<?= htmlspecialchars($addUrl) ?>" class="btn btn-primary btn-add-shipment">+ Add Counting Record</a>
      </div>
      <?php renderUserStatusLegend(); ?>
      <div class="data-table-wrap">
        <table class="data-table data-table-compact user-records-table">
          <thead>
            <tr>
              <th>Status</th>
              <th>Inbound #</th>
              <th>Product</th>
              <th>Counting Date</th>
              <th>Start</th>
              <th>End</th>
              <th>Qty</th>
              <th>Counted By</th>
              <th class="col-remarks">Remarks</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$myRecords): ?>
              <tr><td colspan="10" class="empty">No records yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($myRecords as $r):
              $remarks = $r['remarks'] ?? '';
            ?>
            <tr>
              <td><?= userCountRecordStatusDots($db, $r) ?></td>
              <td class="cell-shipment"><?= htmlspecialchars($r['shipment_number']) ?></td>
              <td><?= htmlspecialchars($r['product_name']) ?></td>
              <td><?= htmlspecialchars($r['counting_date']) ?></td>
              <td><?= formatTimeForDisplay($r['start_time'] ?? null) ?></td>
              <td><?= formatTimeForDisplay($r['completion_time'] ?? null) ?></td>
              <td class="cell-qty"><?= (int) $r['total_quantity'] ?></td>
              <td><?= htmlspecialchars($r['counted_by']) ?></td>
              <td class="col-remarks" title="<?= htmlspecialchars($remarks) ?>">
                <?= $remarks !== '' ? htmlspecialchars($remarks) : '—' ?>
              </td>
              <td class="actions-cell">
                <a class="btn btn-sm btn-secondary" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
                <form method="post" action="<?= BASE_URL ?>/user/count_action.php" class="inline-form"
                      onsubmit="return confirm('Delete this record?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Del</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel">
      <h2>Available Inbound Shipments</h2>
      <p class="hint">Shipments not counted yet — you can add a counting record for these.</p>
      <div class="legend">
        <span>🔴 Not counted yet</span>
      </div>
      <div class="data-table-wrap">
        <table class="data-table data-table-compact">
          <thead>
            <tr>
              <th>Status</th>
              <th>Inbound Date</th>
              <th>Product</th>
              <th>Shipment #</th>
              <th>Cartons</th>
              <th>Quantity</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$availableShipments): ?>
              <tr><td colspan="6" class="empty">No available shipments. All have been counted or none added by admin.</td></tr>
            <?php endif; ?>
            <?php foreach ($availableShipments as $s): ?>
            <tr>
              <td><?= renderStatusDots(['red']) ?></td>
              <td><?= htmlspecialchars($s['inbound_date']) ?></td>
              <td><?= htmlspecialchars($s['product_name']) ?></td>
              <td><?= htmlspecialchars($s['shipment_number']) ?></td>
              <td><?= (int) $s['total_carton'] ?></td>
              <td><?= (int) $s['total_quantity'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <div id="count-record-modal" class="modal-overlay<?= $openCountModal ? ' is-open' : '' ?>"
         aria-hidden="<?= $openCountModal ? 'false' : 'true' ?>"
         data-list-url="<?= htmlspecialchars($listUrl) ?>">
      <div class="modal-dialog modal-dialog-wide" role="dialog" aria-labelledby="count-record-modal-title">
        <div class="modal-header">
          <h2 id="count-record-modal-title"><?= $editRow ? 'Edit Counting Record' : 'Add Counting Record' ?></h2>
          <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
        </div>
        <form method="post" action="<?= BASE_URL ?>/user/count_action.php" id="count-form">
          <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
          <?php if ($editRow): ?>
            <input type="hidden" name="id" value="<?= (int) $editRow['id'] ?>">
          <?php endif; ?>

          <?php
            $selectedAdminId = ($editRow && $editRow['admin_shipment_id'])
                ? (int) $editRow['admin_shipment_id'] : 0;
            $selectedAdminLabel = '— Select inbound shipment —';
            if ($selectedAdminId > 0) {
                foreach ($adminShipments as $as) {
                    if ((int) $as['id'] === $selectedAdminId) {
                        $selectedAdminLabel = $as['shipment_number'] . ' — ' . $as['product_name'];
                        break;
                    }
                }
            }
          ?>
          <div class="cr-row cr-row-full">
            <div class="form-group shipment-combobox-group">
              <label id="admin_shipment_label">Inbound Shipment Number <span class="req">*</span></label>
              <div class="shipment-combobox" id="admin_shipment_combobox">
                <input type="hidden" name="admin_shipment_id" id="admin_shipment_id" required
                       value="<?= $selectedAdminId > 0 ? $selectedAdminId : '' ?>">
                <button type="button" class="shipment-combobox-trigger" id="admin_shipment_trigger"
                        aria-expanded="false" aria-haspopup="listbox" aria-labelledby="admin_shipment_label">
                  <span class="shipment-combobox-value" id="admin_shipment_trigger_label"><?= htmlspecialchars($selectedAdminLabel) ?></span>
                  <span class="shipment-combobox-chevron" aria-hidden="true"></span>
                </button>
                <div class="shipment-combobox-panel" id="admin_shipment_panel" hidden>
                  <div class="shipment-combobox-search-wrap">
                    <input type="search" id="admin_shipment_search" class="shipment-combobox-search"
                           placeholder="Search inbound number or product…" autocomplete="off"
                           aria-label="Search inbound shipments">
                  </div>
                  <ul class="shipment-combobox-list" id="admin_shipment_list" role="listbox">
                    <?php foreach ($adminShipments as $as):
                      $optLabel = $as['shipment_number'] . ' — ' . $as['product_name'];
                      $isSel = $selectedAdminId === (int) $as['id'];
                    ?>
                    <li class="shipment-combobox-option<?= $isSel ? ' is-selected' : '' ?>"
                        role="option"
                        data-value="<?= (int) $as['id'] ?>"
                        data-shipment="<?= htmlspecialchars($as['shipment_number']) ?>"
                        data-product="<?= htmlspecialchars($as['product_name']) ?>"
                        data-carton="<?= (int) $as['total_carton'] ?>"
                        data-label="<?= htmlspecialchars($optLabel) ?>"><?= htmlspecialchars($optLabel) ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <p class="shipment-combobox-empty" id="admin_shipment_empty" hidden>No matching shipment</p>
                </div>
              </div>
              <?php if (!$adminShipments): ?>
                <p class="field-hint field-hint-warn">No inbound shipments available. Ask admin to add one first.</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="cr-row cr-row-2">
            <div class="form-group">
              <label for="product_name_display">Product Name</label>
              <input type="text" id="product_name_display" readonly class="input-readonly"
                     value="<?= htmlspecialchars($editRow['product_name'] ?? '') ?>"
                     placeholder="Auto-filled from inbound number">
            </div>
            <div class="form-group">
              <label for="total_carton_display">Total Cartons</label>
              <input type="text" id="total_carton_display" readonly class="input-readonly"
                     value="<?= htmlspecialchars($initialTotalCarton) ?>"
                     placeholder="From admin shipment">
            </div>
          </div>

          <div class="cr-row cr-row-timing">
            <div class="form-group cr-timing-btn-wrap">
              <label>Counting Session</label>
              <button type="button" class="btn btn-stamp btn-counting-session" id="counting_session_btn">Start</button>
              <p class="field-hint">Or pick date &amp; time below</p>
            </div>
            <div class="form-group">
              <label for="counting_date">Counting Date</label>
              <input type="date" id="counting_date" name="counting_date" required
                     value="<?= htmlspecialchars($initialCountDate) ?>">
            </div>
            <div class="form-group">
              <label for="start_time">Counting Start Time</label>
              <input type="time" id="start_time" name="start_time" step="1"
                     value="<?= htmlspecialchars($initialStartTimeInput) ?>">
            </div>
            <div class="form-group">
              <label for="completion_time">Counting Completion Time</label>
              <input type="time" id="completion_time" name="completion_time" step="1"
                     value="<?= htmlspecialchars($initialEndTimeInput) ?>">
            </div>
          </div>

          <div class="cr-row cr-row-3">
            <div class="form-group">
              <label for="total_quantity">Total Counted Quantity <span class="req">*</span></label>
              <input type="number" id="total_quantity" name="total_quantity" min="0" required
                     value="<?= (int) ($editRow['total_quantity'] ?? 0) ?>">
            </div>
            <div class="form-group">
              <label>Counted By</label>
              <div class="cr-account-display" aria-readonly="true"><?= htmlspecialchars($user['username']) ?></div>
            </div>
            <div class="form-group">
              <label for="remarks">Remarks (optional)</label>
              <input type="text" id="remarks" name="remarks"
                     value="<?= htmlspecialchars($editRow['remarks'] ?? '') ?>">
            </div>
          </div>

          <div class="cr-row cr-row-actions">
            <div class="form-actions">
              <button type="submit" class="btn btn-save"><?= $editRow ? 'Update' : 'Save' ?></button>
              <a class="btn btn-secondary" href="<?= htmlspecialchars($listUrl) ?>" data-modal-close>Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
<?php
$jsVer = @filemtime(__DIR__ . '/../assets/js/dashboard.js') ?: 1;
$modalVer = @filemtime(__DIR__ . '/../assets/js/admin-modal.js') ?: 1;
userPageEnd([
    BASE_URL . '/assets/js/dashboard.js?v=' . (int) $jsVer,
    BASE_URL . '/assets/js/admin-modal.js?v=' . (int) $modalVer,
]);
