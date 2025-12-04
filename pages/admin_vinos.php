<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

requireRole('admin');

$db = getDB();

// Delete via GET ?delete=id
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmtDel = $db->prepare("DELETE FROM vinos WHERE id_vino = ?");
    $stmtDel->execute([$id]);
    header("Location: " . BASE_URL . "/pages/admin_vinos.php");
    exit;
}

$sql = "SELECT v.*, d.nombre AS denominacion
        FROM vinos v
        JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
        ORDER BY v.nombre";
$stmt = $db->query($sql);
$wines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Manage wines</h2>

<p><a href="<?= BASE_URL ?>/pages/admin_vino_form.php">+ Add new wine</a></p>

<table class="tabla">
    <thead>
        <tr>
            <th>Name</th>
            <th>Winery</th>
            <th>Vintage</th>
            <th>Denomination</th>
            <th>Country</th>
            <th>Type</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($wines as $wine): ?>
        <tr>
            <td><?= htmlspecialchars($wine['nombre']) ?></td>
            <td><?= htmlspecialchars($wine['bodega']) ?></td>
            <td><?= htmlspecialchars($wine['annada']) ?></td>
            <td><?= htmlspecialchars($wine['denominacion']) ?></td>
            <td><?= htmlspecialchars($wine['pais']) ?></td>
            <td><?= htmlspecialchars($wine['tipo']) ?></td>
            <td>
                <a href="<?= BASE_URL ?>/pages/admin_vino_form.php?id=<?= $wine['id_vino'] ?>">Edit</a> |
                <a href="<?= BASE_URL ?>/pages/admin_vinos.php?delete=<?= $wine['id_vino'] ?>"
                   onclick="return confirm('Are you sure you want to delete this wine?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
