<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();
$u = currentUser();

$db = getDB();

// Actualizar automáticamente los estados según el tiempo
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

// Obtener las compras del usuario CON DENOMINACIÓN
$stmt = $db->prepare("
    SELECT c.id_compra, c.fecha, c.cantidad, c.estado,
           v.nombre AS vino_nombre, v.tipo, v.annada,
           d.nombre AS denominacion_nombre
    FROM compras c
    INNER JOIN vinos v ON c.id_vino = v.id_vino
    LEFT JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
    WHERE c.id_usuario = ?
    ORDER BY c.fecha DESC
");
$stmt->execute([$u['id_usuario']]);
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<h1>My purchases</h1>

<?php if (empty($compras)): ?>
    <p>You haven't made any purchases yet.</p>
    <p><a href="<?= BASE_URL ?>/pages/wines.php">Browse wines</a></p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Wine</th>
                <th>Qty</th>
                <th>Type</th>
                <th>Denomination</th>
                <th>Vintage</th>
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
            }
        ?>
            <tr>
                <td><?= htmlspecialchars($compra['fecha']) ?></td>
                <td><?= htmlspecialchars($compra['vino_nombre']) ?></td>
                <td><?= htmlspecialchars($compra['cantidad']) ?></td>
                <td><?= htmlspecialchars($compra['tipo']) ?></td>
                <td>
                    <?php if (!empty($compra['denominacion_nombre'])): ?>
                        <span class="badge-denominacion"><?= htmlspecialchars($compra['denominacion_nombre']) ?></span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($compra['annada']) ?></td>
                <td><span class="<?= $statusClass ?>"><?= $statusText ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="<?= BASE_URL ?>/pages/wines.php">Continue shopping</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>