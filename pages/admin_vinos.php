<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireRole('admin');

$db = getDB();

$message = null;
$error   = null;

// Helper to display denomination names in English (UI-only)
function denomLabel($name) {
    $raw = trim((string)$name);
    $n = strtolower($raw);

    // Normalize to handle broken encodings like "Org??nico"
    $n_ascii = preg_replace('/[^a-z]/', '', $n);

    // Treat any "org..." or "bio..." denomination as Organic (display only)
    if (strpos($n, 'org') === 0 || strpos($n_ascii, 'org') === 0) return 'Organic';
    if (strpos($n, 'bio') === 0 || strpos($n_ascii, 'bio') === 0) return 'Organic';

    // Keep known Italian denomination abbreviations in uppercase
    if (in_array($n_ascii, ['docg','doc','igt'], true)) return strtoupper($n_ascii);

    return $raw;
}

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
    SELECT v.id_vino, v.nombre, v.bodega, v.annada, v.tipo, v.pais,
           v.ventana_optima_inicio, v.ventana_optima_fin, v.precio, v.stock,
           d.nombre AS denomination,
           CASE
               WHEN v.ventana_optima_inicio IS NULL OR v.ventana_optima_fin IS NULL THEN 'Unknown'
               WHEN YEAR(CURDATE()) < v.ventana_optima_inicio THEN 'Too young'
               WHEN YEAR(CURDATE()) > v.ventana_optima_fin THEN 'Past window'
               ELSE 'In window'
           END AS aging_status
    FROM vinos v
    LEFT JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
    ORDER BY v.nombre
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
        <th>Denomination</th>
        <th>Optimal window</th>
        <th>Aging status</th>
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
  <td><?= !empty($vino['denomination']) ? htmlspecialchars(denomLabel($vino['denomination'])) : '-' ?></td>
  <td>
    <?= htmlspecialchars($vino['ventana_optima_inicio']) ?> – <?= htmlspecialchars($vino['ventana_optima_fin']) ?>
  </td>
  <td><?= htmlspecialchars($vino['aging_status'] ?? 'Unknown') ?></td>
  <td><?= number_format((float)$vino['precio'], 2) ?> €</td>
  <td><?= (int)$vino['stock'] ?></td>
  <td>
    <a href="<?= BASE_URL ?>/pages/admin_vino_form.php?id_vino=<?= (int)$vino['id_vino'] ?>">Edit</a>

    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this wine?');">
      <input type="hidden" name="id_vino" value="<?= (int)$vino['id_vino'] ?>">
      <button type="submit" name="action" value="delete">Delete</button>
    </form>
  </td>
</tr>

    <?php endforeach; ?>
    </tbody>
</table>

<p><a href="<?= BASE_URL ?>/pages/admin_panel.php">Back to admin panel</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
