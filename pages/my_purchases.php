<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();
$u = currentUser();

// (Opcional pero recomendable) Solo coleccionistas
if (($u['rol'] ?? '') !== 'coleccionista') {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$db = getDB();

date_default_timezone_set('Europe/Madrid');

// Actualizar automáticamente los estados según el tiempo
// (Robusto) Si la columna created_at aún no existe, usamos fecha.
try {
    $stmt = $db->prepare("
        UPDATE compras 
        SET estado = CASE 
            WHEN TIMESTAMPDIFF(HOUR, COALESCE(created_at, fecha), NOW()) >= 24 THEN 'delivered'
            WHEN TIMESTAMPDIFF(HOUR, COALESCE(created_at, fecha), NOW()) >= 2 THEN 'shipped'
            ELSE 'pending'
        END
        WHERE id_usuario = ? AND estado != 'cancelled'
    ");
    $stmt->execute([$u['id_usuario']]);
} catch (PDOException $e) {
    $stmt = $db->prepare("
        UPDATE compras 
        SET estado = CASE 
            WHEN TIMESTAMPDIFF(HOUR, fecha, NOW()) >= 24 THEN 'delivered'
            WHEN TIMESTAMPDIFF(HOUR, fecha, NOW()) >= 2 THEN 'shipped'
            ELSE 'pending'
        END
        WHERE id_usuario = ? AND estado != 'cancelled'
    ");
    $stmt->execute([$u['id_usuario']]);
}

// Obtener las compras del usuario + precio + imagen + denominación
// (Robusto) Si created_at aún no existe, usamos fecha.
try {
    $stmt = $db->prepare("
        SELECT c.id_compra,
               COALESCE(c.created_at, c.fecha) AS purchase_at,
               c.cantidad, c.estado,
               v.nombre AS vino_nombre, v.tipo, v.annada, v.precio, v.imagen,
               d.nombre AS denominacion_nombre
        FROM compras c
        INNER JOIN vinos v ON c.id_vino = v.id_vino
        LEFT JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
        WHERE c.id_usuario = ?
        ORDER BY purchase_at DESC
    ");
    $stmt->execute([$u['id_usuario']]);
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $db->prepare("
        SELECT c.id_compra, c.fecha AS purchase_at, c.cantidad, c.estado,
               v.nombre AS vino_nombre, v.tipo, v.annada, v.precio, v.imagen,
               d.nombre AS denominacion_nombre
        FROM compras c
        INNER JOIN vinos v ON c.id_vino = v.id_vino
        LEFT JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
        WHERE c.id_usuario = ?
        ORDER BY c.fecha DESC
    ");
    $stmt->execute([$u['id_usuario']]);
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calcular total general
$totalGeneral = 0.0;
foreach ($compras as $c) {
    $totalGeneral += ((float)$c['precio']) * ((int)$c['cantidad']);
}

include __DIR__ . '/../includes/header.php';
?>

<h1>My purchases</h1>

<?php if (empty($compras)): ?>
    <p>You haven't made any purchases yet.</p>
    <p><a href="<?= BASE_URL ?>/pages/wines.php">Browse wines</a></p>
<?php else: ?>

    <p><strong>Total spent:</strong> <?= number_format($totalGeneral, 2) ?> €</p>

    <table>
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Wine</th>
                <th>Qty</th>
                <th>Type</th>
                <th>Denomination</th>
                <th>Vintage</th>
                <th>Unit price</th>
                <th>Subtotal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($compras as $compra):
            $statusClass = '';
            $statusText = '';
            switch($compra['estado']) {
                case 'pending':
                    $statusClass = 'status-pending';
                    $statusText = 'Pending';
                    break;
                case 'shipped':
                    $statusClass = 'status-shipped';
                    $statusText = 'On the way';
                    break;
                case 'delivered':
                    $statusClass = 'status-delivered';
                    $statusText = 'Delivered';
                    break;
                case 'cancelled':
                    $statusClass = 'status-cancelled';
                    $statusText = 'Cancelled';
                    break;
                default:
                    $statusClass = 'status-pending';
                    $statusText = htmlspecialchars($compra['estado']);
                    break;
            }

            $unit = (float)$compra['precio'];
            $qty  = (int)$compra['cantidad'];
            $sub  = $unit * $qty;
        ?>
            <tr>
                <?php
                $rawDate = $compra['purchase_at'] ?? ($compra['created_at'] ?? ($compra['fecha'] ?? ''));
                $dateText = $rawDate ? date('d/m/Y H:i', strtotime($rawDate)) : '-';
            ?>
                <td><?= htmlspecialchars($dateText) ?></td>

                <td>
                    <?php if (!empty($compra['imagen'])): ?>
                        <img src="../img/wines/<?= htmlspecialchars($compra['imagen']) ?>"
                             alt="<?= htmlspecialchars($compra['vino_nombre']) ?>"
                             style="max-width:40px;height:auto;vertical-align:middle;margin-right:8px;">
                    <?php endif; ?>
                    <?= htmlspecialchars($compra['vino_nombre']) ?>
                </td>

                <td><?= $qty ?></td>
                <td><?= htmlspecialchars($compra['tipo']) ?></td>

                <td>
                    <?php if (!empty($compra['denominacion_nombre'])): ?>
                        <span class="badge-denominacion"><?= htmlspecialchars($compra['denominacion_nombre']) ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>

                <td><?= htmlspecialchars($compra['annada']) ?></td>
                <td><?= number_format($unit, 2) ?> €</td>
                <td><strong><?= number_format($sub, 2) ?> €</strong></td>

                <td><span class="<?= $statusClass ?>"><?= $statusText ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="<?= BASE_URL ?>/pages/wines.php">Continue shopping</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
