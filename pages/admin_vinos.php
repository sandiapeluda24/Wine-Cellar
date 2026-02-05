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

<section class="admin-hero">
  <div class="admin-shell">
    <div class="admin-head">
      <div class="admin-kicker">Administration</div>
      <h1 class="admin-title">Manage wines</h1>
      <p class="admin-subtitle">Create, edit, and delete wines. Check denomination, optimal window, aging status, price, and stock at a glance.</p>

      <div class="admin-actions" style="margin-top: 14px;">
        <a class="btn btn-sm" href="<?= BASE_URL ?>/pages/admin_vino_form.php">+ Add new wine</a>
        <a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/pages/admin_panel.php">← Back to admin panel</a>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="notice notice-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="wines-table">
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
            <?php
              $aging = $vino['aging_status'] ?? 'Unknown';
              $agingClass = 'badge-aging--unknown';
              if ($aging === 'In window') $agingClass = 'badge-aging--good';
              elseif ($aging === 'Too young') $agingClass = 'badge-aging--young';
              elseif ($aging === 'Past window') $agingClass = 'badge-aging--old';

              $stockVal = (int)($vino['stock'] ?? 0);
              $stockClass = $stockVal > 0 ? 'badge-stock--in' : 'badge-stock--out';

              $winStart = $vino['ventana_optima_inicio'];
              $winEnd   = $vino['ventana_optima_fin'];
              $windowText = ($winStart !== null && $winEnd !== null && $winStart !== '' && $winEnd !== '')
                ? (htmlspecialchars($winStart) . " – " . htmlspecialchars($winEnd))
                : '–';

              $denom = !empty($vino['denomination']) ? denomLabel($vino['denomination']) : '–';
            ?>
            <tr>
              <td><?= (int)$vino['id_vino'] ?></td>
              <td><strong><?= htmlspecialchars($vino['nombre']) ?></strong></td>
              <td><?= htmlspecialchars($vino['bodega']) ?></td>
              <td><?= htmlspecialchars($vino['annada']) ?></td>
              <td><?= htmlspecialchars($vino['tipo']) ?></td>
              <td><?= htmlspecialchars($vino['pais']) ?></td>
              <td>
                <?php if ($denom !== '–'): ?>
                  <span class="badge badge-denom"><?= htmlspecialchars($denom) ?></span>
                <?php else: ?>
                  –
                <?php endif; ?>
              </td>
              <td class="cell-mono"><?= $windowText ?></td>
              <td><span class="badge <?= $agingClass ?>"><?= htmlspecialchars($aging) ?></span></td>
              <td class="cell-mono"><?= number_format((float)$vino['precio'], 2) ?> €</td>
              <td>
                <span class="badge <?= $stockClass ?>"><?= $stockVal ?><?= $stockVal === 1 ? ' bottle' : ' bottles' ?></span>
              </td>
              <td>
                <div class="table-actions">
                  <a class="btn btn-secondary btn-sm"
                     href="<?= BASE_URL ?>/pages/admin_vino_form.php?id_vino=<?= (int)$vino['id_vino'] ?>">
                    Edit
                  </a>

                  <form method="post" class="inline-form"
                        onsubmit="return confirm('Are you sure you want to delete this wine?');">
                    <input type="hidden" name="id_vino" value="<?= (int)$vino['id_vino'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit" name="action" value="delete">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>


<?php include __DIR__ . '/../includes/footer.php'; ?>
