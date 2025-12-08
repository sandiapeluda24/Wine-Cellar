<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireRole('admin');

$db = getDB();

$message = null;
$error   = null;

// Borrar vino (DELETE real; si prefieres soft delete luego lo cambiamos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_vino'], $_POST['action'])) {
    $idVino = (int) $_POST['id_vino'];
    $action = $_POST['action'];

    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM vinos WHERE id_vino = ?");
        $stmt->execute([$idVino]);
        $message = "Wine deleted correctly.";
    }
}

// Obtener todos los vinos
$stmt = $db->query("
    SELECT id_vino, nombre, bodega, annada, tipo, pais, ventana_optima_inicio, ventana_optima_fin, precio, stock
    FROM vinos
    ORDER BY nombre
");
$vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<h1>Manage wines</h1>

<?php if ($message): ?>
    <p style="color: green;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<p>
    <a href="<?= BASE_URL ?>/pages/admin_vino_form.php">+ Add new wine</a>
</p>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Winery</th>
        <th>Vintage</th>
        <th>Type</th>
        <th>Country</th>
        <th>Optimal window</th>
        <th>Price (€)</th>
        <th>Stock</th>
        <th>Actions</th>
        
    </tr>
    </thead>
    <tbody>
    <?php foreach ($vinos as $vino): ?>
        <tr>
            <td><?= htmlspecialchars($vino['id_vino']) ?></td>
            <td><?= htmlspecialchars($vino['nombre']) ?></td>
            <td><?= htmlspecialchars($vino['bodega']) ?></td>
            <td><?= htmlspecialchars($vino['annada']) ?></td>
            <td><?= htmlspecialchars($vino['tipo']) ?></td>
            <td><?= htmlspecialchars($vino['pais']) ?></td>
            <td>
                <?= htmlspecialchars($vino['ventana_optima_inicio']) ?>
                –
                <?= htmlspecialchars($vino['ventana_optima_fin']) ?>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/pages/admin_vino_form.php?id_vino=<?= $vino['id_vino'] ?>">
                    Edit
                </a>

                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this wine?');">
                    <input type="hidden" name="id_vino" value="<?= $vino['id_vino'] ?>">
                    <button type="submit" name="action" value="delete">
                        Delete
                    </button>
                </form>
            </td>
            <td><?= number_format($vino['precio'], 2) ?> €</td>
            <td><?= (int)$vino['stock'] ?></td>

        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p><a href="<?= BASE_URL ?>/pages/admin_panel.php">Back to admin panel</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
