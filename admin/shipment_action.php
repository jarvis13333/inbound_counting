<?php



require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/status.php';
require_once __DIR__ . '/../includes/upload_photo.php';



requireAdmin();



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: ' . BASE_URL . '/admin/shipments.php');

    exit;

}



$action = $_POST['action'] ?? '';

$db = getDb();

$adminId = (int) $_SESSION['user_id'];



function redirectAdmin(string $query = ''): void

{

    header('Location: ' . BASE_URL . '/admin/shipments.php' . $query);

    exit;

}



if ($action === 'delete') {

    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {

        $db->prepare('DELETE FROM admin_shipments WHERE id = ?')->execute([$id]);

        flashSet('success', 'Shipment record deleted.');

    }

    redirectAdmin();

}



if ($action === 'clear_count') {

    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {

        $shipStmt = $db->prepare('SELECT shipment_number FROM admin_shipments WHERE id = ?');

        $shipStmt->execute([$id]);

        $shipRow = $shipStmt->fetch();

        if ($shipRow) {

            $shipNo = $shipRow['shipment_number'];

            $photoStmt = $db->prepare(

                'SELECT photo_path FROM user_count_records

                 WHERE admin_shipment_id = ? OR shipment_number = ?'

            );

            $photoStmt->execute([$id, $shipNo]);

            foreach ($photoStmt->fetchAll() as $photoRow) {

                deleteStoredPhoto($photoRow['photo_path'] ?? null);

            }

            $delStmt = $db->prepare(

                'DELETE FROM user_count_records

                 WHERE admin_shipment_id = ? OR shipment_number = ?'

            );

            $delStmt->execute([$id, $shipNo]);

            $cleared = $delStmt->rowCount();

            if ($cleared > 0) {

                flashSet('success', 'Counting records cleared. Shipment is uncounted again.');

            } else {

                flashSet('error', 'No counting records found for this shipment.');

            }

        } else {

            flashSet('error', 'Shipment not found.');

        }

    }

    redirectAdmin();

}



$inboundDate = trim($_POST['inbound_date'] ?? '');

$productName = trim($_POST['product_name'] ?? '');

$shipmentNumber = trim($_POST['shipment_number'] ?? '');

$totalCarton = (int) ($_POST['total_carton'] ?? 0);

$totalQty = (int) ($_POST['total_quantity'] ?? 0);

$id = (int) ($_POST['id'] ?? 0);



if ($shipmentNumber === '') {

    flashSet('error', 'Inbound Shipment Number is required.');

    redirectAdmin($id ? '?edit=' . $id : '');

}



if ($inboundDate === '') {

    if ($id > 0) {

        $dateStmt = $db->prepare('SELECT inbound_date FROM admin_shipments WHERE id = ?');

        $dateStmt->execute([$id]);

        $dateRow = $dateStmt->fetch();

        $inboundDate = $dateRow['inbound_date'] ?? date('Y-m-d');

    } else {

        $inboundDate = date('Y-m-d');

    }

}



if ($totalCarton < 0) {

    $totalCarton = 0;

}



if ($totalQty < 0) {

    $totalQty = 0;

}



if ($action === 'create') {

    try {

        $stmt = $db->prepare(

            'INSERT INTO admin_shipments

               (inbound_date, product_name, shipment_number, total_carton, total_quantity, created_by, photo_path)

             VALUES (?, ?, ?, ?, ?, ?, NULL)'

        );

        $stmt->execute([$inboundDate, $productName, $shipmentNumber, $totalCarton, $totalQty, $adminId]);

        flashSet('success', 'Shipment record saved.');

    } catch (PDOException $e) {

        if ($e->getCode() == 23000) {

            flashSet('error', 'Shipment number already exists.');

        } else {

            flashSet('error', 'Could not save record.');

        }

    }

    redirectAdmin();

}



if ($action === 'update' && $id > 0) {

    try {

        $stmt = $db->prepare(

            'UPDATE admin_shipments

             SET inbound_date = ?, product_name = ?, shipment_number = ?,

                 total_carton = ?, total_quantity = ?, photo_path = NULL

             WHERE id = ?'

        );

        $stmt->execute([$inboundDate, $productName, $shipmentNumber, $totalCarton, $totalQty, $id]);

        flashSet('success', 'Shipment record updated.');

    } catch (PDOException $e) {

        if ($e->getCode() == 23000) {

            flashSet('error', 'Shipment number already used by another record.');

        } else {

            flashSet('error', 'Could not update record.');

        }

    }

    redirectAdmin();

}



flashSet('error', 'Invalid action.');

redirectAdmin();

