<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

// Solo usuarios loggeados
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// Intentamos obtener el id del usuario con ambas claves posibles
$idUsuario = $_SESSION['usuario']['id_usuario'] ?? ($_SESSION['usuario']['id'] ?? null);

if ($idUsuario === null) {
    // No tenemos id en sesión -> algo raro, mejor pedir login de nuevo
    echo "<h1>My purchases</h1>";
    echo "<p>Could not load your user data. Please log in again.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT c.id_compra, c.cantidad, c.fecha, c.estado,
           v.nombre, v.bodega, v.annada, v.tipo
    FROM compras c
    JOIN vinos v ON c.id_vino = v.id_vino
    WHERE c.id_usuario = ?
    ORDER BY c.fecha DESC
");
$stmt->execute([$idUsuario]);
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>My purchases</h1>

<?php if (empty($compras)): ?>
    <p>You have not bought any wines yet.</p>
<?php else: ?>
    <table border="1" cellpadding="6">
        <thead>
        <tr>
            <th>Date</th>
            <th>Wine</th>
            <th>Qty</th>
            <th>Type</th>
            <th>Vintage</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($compras as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['fecha']) ?></td>
                <td>
                    <?= htmlspecialchars($c['nombre']) ?>
                    <?php if (!empty($c['bodega'])): ?>
                        (<?= htmlspecialchars($c['bodega']) ?>)
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($c['cantidad']) ?></td>
                <td><?= htmlspecialchars($c['tipo']) ?></td>
                <td><?= htmlspecialchars($c['annada']) ?></td>
                <td><?= htmlspecialchars($c['estado']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
