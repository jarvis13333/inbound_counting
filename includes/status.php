<?php

/**
 * Status for an admin shipment row.
 * Returns: counted (bool), qty_match (bool|null), dots array of legend keys.
 */
function shipmentCountStatus(PDO $db, array $shipment): array
{
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(total_quantity), 0) AS qty_sum, COUNT(*) AS cnt
         FROM user_count_records
         WHERE shipment_number = ?'
    );
    $stmt->execute([$shipment['shipment_number']]);
    $agg = $stmt->fetch();
    $counted = (int) ($agg['cnt'] ?? 0) > 0;
    $qtySum = (int) ($agg['qty_sum'] ?? 0);
    $adminQty = (int) $shipment['total_quantity'];

    $dots = [];
    if ($counted) {
        $dots[] = 'green';
        if ($qtySum === $adminQty) {
            $dots[] = 'blue';
            $qtyMatch = true;
        } else {
            $dots[] = 'orange';
            $qtyMatch = false;
        }
    } else {
        $dots[] = 'red';
        $qtyMatch = null;
    }

    return [
        'counted' => $counted,
        'qty_match' => $qtyMatch,
        'qty_sum' => $qtySum,
        'dots' => $dots,
    ];
}

/** Admin shipment row used to compute Match for a user counting record. */
function adminShipmentForCountRecord(PDO $db, array $record): ?array
{
    if (!empty($record['admin_shipment_id'])) {
        $stmt = $db->prepare(
            'SELECT id, shipment_number, total_quantity FROM admin_shipments WHERE id = ?'
        );
        $stmt->execute([(int) $record['admin_shipment_id']]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    $shipNo = trim($record['shipment_number'] ?? '');
    if ($shipNo === '') {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT id, shipment_number, total_quantity FROM admin_shipments WHERE shipment_number = ? LIMIT 1'
    );
    $stmt->execute([$shipNo]);

    return $stmt->fetch() ?: null;
}

/** Match column HTML for overview: status dots or explanation when no admin link. */
function matchStatusForCountRecord(PDO $db, array $record): string
{
    $adm = adminShipmentForCountRecord($db, $record);
    if (!$adm) {
        return '<span class="match-na" title="No admin inbound shipment matches this shipment number">—</span>';
    }

    return renderStatusDots(shipmentCountStatus($db, $adm)['dots']);
}

/** User view: only green (OK) or red (not counted / mismatch). */
function userSimpleStatusDots(PDO $db, array $shipment): string
{
    $st = shipmentCountStatus($db, $shipment);
    if (!$st['counted']) {
        return renderStatusDots(['red']);
    }
    if ($st['qty_match'] === true) {
        return renderStatusDots(['green']);
    }

    return renderStatusDots(['red']);
}

/** Status for a user's counting record: green once they have counted. */
function userCountRecordStatusDots(PDO $db, array $record): string
{
    return renderStatusDots(['green']);
}

function renderUserStatusLegend(): void
{
    ?>
    <div class="legend">
      <span>🟢 Counted by you</span>
      <span>🔴 Not counted yet (available shipments)</span>
    </div>
    <?php
}

function renderStatusDots(array $dots): string
{
    $map = [
        'green' => ['🟢', 'Counted'],
        'red' => ['🔴', 'Not counted'],
        'blue' => ['🔵', 'Qty matches admin'],
        'orange' => ['🟠', 'Qty mismatch'],
    ];
    $html = '<span class="status-dots">';
    foreach ($dots as $key) {
        if (isset($map[$key])) {
            $html .= '<span class="dot dot-' . $key . '" title="' . htmlspecialchars($map[$key][1]) . '">'
                . $map[$key][0] . '</span>';
        }
    }
    $html .= '</span>';
    return $html;
}

function flashGet(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function flashSet(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
