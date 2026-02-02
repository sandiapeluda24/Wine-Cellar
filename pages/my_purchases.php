<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();
$u = currentUser();

// Collectors only
if (($u['rol'] ?? '') !== 'coleccionista') {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$db = getDB();
date_default_timezone_set('Europe/Madrid');

/* ---------------- helpers ---------------- */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function tableColumns(PDO $db, string $table): array {
    $cols = [];
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (!empty($r['Field'])) $cols[] = $r['Field'];
        }
    } catch (Exception $e) {
        // ignore
    }
    return $cols;
}

// UI-only label (robust against broken encoding like "Org??nico")
function denomLabel($name) {
    $raw = trim((string)$name);
    $n = strtolower($raw);

    // canonical categories
    if (strpos($n, 'docg') !== false) return 'DOCG';
    if (strpos($n, 'doc')  !== false && strpos($n, 'docg') === false) return 'DOC';
    if (strpos($n, 'igt')  !== false) return 'IGT';

    // Organic: match "org..." even if encoding is broken, or "bio"
    if (strpos($n, 'bio') !== false || strpos($n, 'org') === 0) return 'Organic';

    return $raw;
}

/* ---------------- schema detection ---------------- */
$comprasCols = tableColumns($db, 'compras');

// Detect primary key / purchase id column (schema may vary)
$idCompraCol = null;
foreach (['id_compra','id_compras','compra_id','id'] as $cand) {
    if (in_array($cand, $comprasCols, true)) { $idCompraCol = $cand; break; }
}
$selectIdCompra = $idCompraCol ? "c.`$idCompraCol` AS id_compra" : "NULL AS id_compra";

$statusCol = null;
if (in_array('estado', $comprasCols, true)) $statusCol = 'estado';
elseif (in_array('status', $comprasCols, true)) $statusCol = 'status';

$dateCol = null;
$hasCreatedAt = in_array('created_at', $comprasCols, true);
$hasFecha     = in_array('fecha', $comprasCols, true);
if ($hasCreatedAt && $hasFecha) $dateCol = 'COALESCE(c.created_at, c.fecha)';
elseif ($hasCreatedAt) $dateCol = 'c.created_at';
elseif ($hasFecha)     $dateCol = 'c.fecha';

$hasQty = in_array('cantidad', $comprasCols, true);
$qtyCol = $hasQty ? 'cantidad' : null;

/* ---------------- (optional) auto-status update ----------------
   Only run if the purchases table has a status column.
   If your schema doesn't include it, the page still works. */
if ($statusCol && $dateCol) {
    try {
        $sql = "
            UPDATE compras
            SET `$statusCol` = CASE
                WHEN TIMESTAMPDIFF(HOUR, " . str_replace('c.', '', $dateCol) . ", NOW()) >= 24 THEN 'delivered'
                WHEN TIMESTAMPDIFF(HOUR, " . str_replace('c.', '', $dateCol) . ", NOW()) >= 2  THEN 'shipped'
                ELSE 'pending'
            END
            WHERE id_usuario = ?
              AND `$statusCol` != 'cancelled'
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$u['id_usuario']]);
    } catch (Exception $e) {
        // ignore: this is optional
    }
}

/* ---------------- fetch purchases ---------------- */
if (!$dateCol) {
    // As a last resort, still fetch purchases without purchase_at.
    $dateCol = "NULL";
}

$selectStatus = $statusCol ? "c.`$statusCol` AS purchase_status" : "'-' AS purchase_status";
$selectQty    = $qtyCol ? "c.`$qtyCol` AS quantity" : "1 AS quantity"; // fallback

$sql = "
    SELECT
        $selectIdCompra,
        $dateCol AS purchase_at,
        $selectQty,
        $selectStatus,

        v.nombre AS wine_name,
        v.tipo AS wine_type,
        v.annada AS vintage,
        v.precio AS unit_price,
        v.imagen AS image,

        v.ventana_optima_inicio AS window_start,
        v.ventana_optima_fin    AS window_end,
        CASE
            WHEN v.ventana_optima_inicio IS NULL OR v.ventana_optima_fin IS NULL THEN 'Unknown'
            WHEN YEAR(CURDATE()) < v.ventana_optima_inicio THEN 'Too young'
            WHEN YEAR(CURDATE()) > v.ventana_optima_fin THEN 'Past window'
            ELSE 'In window'
        END AS aging_status,

        d.nombre AS denomination_name
    FROM compras c
    INNER JOIN vinos v ON c.id_vino = v.id_vino
    LEFT JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
    WHERE c.id_usuario = ?
    ORDER BY purchase_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute([$u['id_usuario']]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// total spent
$totalSpent = 0.0;
foreach ($purchases as $p) {
    $totalSpent += ((float)$p['unit_price']) * ((int)$p['quantity']);
}

include __DIR__ . '/../includes/header.php';
?>

<h1>My Purchases</h1>

<?php if (empty($purchases)): ?>
    <p>You haven't made any purchases yet.</p>
    <p><a href="<?= BASE_URL ?>/pages/wines.php">Browse wines</a></p>
<?php else: ?>

    <p><strong>Total spent:</strong> <?= number_format($totalSpent, 2) ?> €</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date/Time</th>
                <th>Wine</th>
                <th>Qty</th>
                <th>Type</th>
                <th>Denomination</th>
                <th>Vintage</th>
                <th>Aging status</th>
                <th>Cellared</th>
                <th>Unit price</th>
                <th>Subtotal</th>
                <?php if ($statusCol): ?>
                    <th>Status</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($purchases as $p):
                $unit = (float)$p['unit_price'];
                $qty  = (int)$p['quantity'];
                $sub  = $unit * $qty;

                // date display
                $rawDate = $p['purchase_at'] ?? '';
                $dateText = $rawDate ? date('d/m/Y H:i', strtotime($rawDate)) : '-';

                // Cellared time = time since purchase (bottle is assumed unopened)
                $cellaredText = '-';
                if (!empty($rawDate)) {
                    try {
                        $dt  = new DateTime($rawDate);
                        $now = new DateTime();
                        $diff = $dt->diff($now);

                        if ($diff->y > 0) $cellaredText = $diff->y . "y " . $diff->m . "m";
                        elseif ($diff->m > 0) $cellaredText = $diff->m . "m " . $diff->d . "d";
                        else $cellaredText = $diff->d . "d";
                    } catch (Exception $e) {
                        $cellaredText = '-';
                    }
                }

                // status display (only if column exists)
                $statusClass = '';
                $statusText = '';
                if ($statusCol) {
                    $st = (string)($p['purchase_status'] ?? '');
                    switch ($st) {
                        case 'pending':   $statusClass='status-pending';   $statusText='Pending'; break;
                        case 'shipped':   $statusClass='status-shipped';   $statusText='On the way'; break;
                        case 'delivered': $statusClass='status-delivered'; $statusText='Delivered'; break;
                        case 'cancelled': $statusClass='status-cancelled'; $statusText='Cancelled'; break;
                        default:          $statusClass='status-pending';   $statusText=h($st ?: '-'); break;
                    }
                }
            ?>
                <tr>
                    <td><?= h($dateText) ?></td>
                    <td>
                        <?php if (!empty($p['image'])): ?>
                            <img src="../img/wines/<?= h($p['image']) ?>"
                                alt="<?= h($p['wine_name']) ?>"
                                style="max-width:40px;height:auto;vertical-align:middle;margin-right:8px;">
                        <?php endif; ?>
                        <?= h($p['wine_name']) ?>
                    </td>
                    <td><?= (int)$qty ?></td>
                    <td><?= h($p['wine_type']) ?></td>
                    <td>
                        <?php if (!empty($p['denomination_name'])): ?>
                            <span class="badge-denominacion"><?= h(denomLabel($p['denomination_name'])) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= h($p['vintage']) ?></td>
                    <td><?= h($p['aging_status'] ?? 'Unknown') ?></td>
                    <td><?= h($cellaredText) ?></td>
                    <td><?= number_format($unit, 2) ?> €</td>
                    <td><strong><?= number_format($sub, 2) ?> €</strong></td>
                    <?php if ($statusCol): ?>
                        <td><span class="<?= h($statusClass) ?>"><?= $statusText ?></span></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p><a href="<?= BASE_URL ?>/pages/wines.php">Continue shopping</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
