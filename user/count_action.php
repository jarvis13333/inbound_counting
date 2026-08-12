<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/status.php';
require_once __DIR__ . '/../includes/helpers.php';

requireUserRole();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/user/dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$db = getDb();
$userId = (int) $_SESSION['user_id'];

function redirectUser(string $query = ''): void
{
    header('Location: ' . BASE_URL . '/user/dashboard.php' . $query);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $db->prepare('DELETE FROM user_count_records WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        flashSet('success', 'Counting record deleted.');
    }
    redirectUser();
}

$countingDate = trim($_POST['counting_date'] ?? '');
$startTime = normalizeTimeForInput(trim($_POST['start_time'] ?? '')) ?: null;
$completionTime = normalizeTimeForInput(trim($_POST['completion_time'] ?? '')) ?: null;
$totalQty = (int) ($_POST['total_quantity'] ?? 0);
$currentUser = getCurrentUser();
$countedBy = $currentUser['username'] ?? $_SESSION['username'] ?? '';
$remarks = trim($_POST['remarks'] ?? '') ?: null;
$adminShipmentId = (int) ($_POST['admin_shipment_id'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);

if ($adminShipmentId < 1) {
    flashSet('error', 'Please select an inbound shipment number.');
    redirectUser($id ? '?edit=' . $id : '?add=1');
}

$admStmt = $db->prepare('SELECT id, shipment_number, product_name FROM admin_shipments WHERE id = ?');
$admStmt->execute([$adminShipmentId]);
$adm = $admStmt->fetch();
if (!$adm) {
    flashSet('error', 'Selected inbound shipment was not found.');
    redirectUser($id ? '?edit=' . $id : '?add=1');
}

$shipmentNumber = $adm['shipment_number'];
$productName = $adm['product_name'];

if ($action === 'create' && $countingDate === '') {
    $countingDate = date('Y-m-d');
}

if ($countingDate === '' || $countedBy === '') {
    flashSet('error', 'Please fill all required fields.');
    redirectUser($id ? '?edit=' . $id : '?add=1');
}

if ($startTime === null || $completionTime === null) {
    flashSet('error', 'Please enter counting start time and completion time.');
    redirectUser($id ? '?edit=' . $id : '?add=1');
}

$dupSql = 'SELECT id FROM user_count_records WHERE admin_shipment_id = ?';
$dupParams = [$adminShipmentId];
if ($action === 'update' && $id > 0) {
    $dupSql .= ' AND id != ?';
    $dupParams[] = $id;
}
$dup = $db->prepare($dupSql);
$dup->execute($dupParams);
if ($dup->fetch()) {
    flashSet('error', 'This inbound shipment has already been counted by another record.');
    redirectUser($id ? '?edit=' . $id : '?add=1');
}

$boxCount = 0;

if ($action === 'create') {
    $stmt = $db->prepare(
        'INSERT INTO user_count_records
           (user_id, admin_shipment_id, shipment_number, product_name, counting_date,
            start_time, completion_time, total_quantity, box_count, counted_by, remarks, photo_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId, $adminShipmentId, $shipmentNumber, $productName, $countingDate,
        $startTime, $completionTime, $totalQty, $boxCount, $countedBy, $remarks, null,
    ]);
    flashSet('success', 'Counting record saved.');
    redirectUser();
}

if ($action === 'update' && $id > 0) {
    $stmt = $db->prepare(
        'UPDATE user_count_records
         SET admin_shipment_id = ?, shipment_number = ?, product_name = ?, counting_date = ?,
             start_time = ?, completion_time = ?, total_quantity = ?, box_count = ?,
             counted_by = ?, remarks = ?
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([
        $adminShipmentId, $shipmentNumber, $productName, $countingDate,
        $startTime, $completionTime, $totalQty, $boxCount, $countedBy, $remarks,
        $id, $userId,
    ]);
    flashSet('success', 'Counting record updated.');
    redirectUser();
}

flashSet('error', 'Invalid action.');
redirectUser();
