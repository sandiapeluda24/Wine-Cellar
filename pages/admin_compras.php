<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();
$u = currentUser();

// Solo admin
$rol = $u['rol'] ?? '';
if (!in_array($rol, ['admin', 'administrador'], true)) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$db = getDB();

/**
 * Devuelve las columnas de una tabla (en minúsculas) para poder adaptar el SQL
 * sin romper si tu esquema tiene nombres distintos.
 */
function tableColumns(PDO $db, string $table): array {
    try {
        $stmt = $db->query("DESCRIBE `$table`");
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[] = strtolower($row['Field']);
        }
        return $cols;
    } catch (Throwable $e) {
        return [];
    }
}

$comprasCols  = tableColumns($db, 'compras');
$usuariosCols = tableColumns($db, 'usuarios');

// Columna de fecha en compras: created_at (recomendada) o fecha (legacy)
// Columna de fecha en compras (elige la primera que exista)
$dateCandidates = [
    'created_at',
    'fecha',
    'fecha_compra',
    'fechacompra',
    'data',
    'data_compra',
    'purchase_at',
    'purchased_at',
];
$dateCol = null;
foreach ($dateCandidates as $cand) {
    if (in_array($cand, $comprasCols, true)) { $dateCol = $cand; break; }
}


// Columna email en usuarios (si existe)
$emailCol = null;
foreach (['email', 'correo', 'correoelectronico', 'correo_electronico'] as $c) {
    if (in_array($c, $usuariosCols, true)) { $emailCol = $c; break; }
}

// Columnas nombre/apellido (si existen)
$hasNombre   = in_array('nombre', $usuariosCols, true);
$hasApellido = in_array('apellido', $usuariosCols, true);

// --------- Actualizar estados automáticamente (como en my_purchases.php) ----------
try {
    $db->exec("
        UPDATE compras
        SET estado = CASE
            WHEN TIMESTAMPDIFF(HOUR, `$dateCol`, NOW()) >= 24 THEN 'delivered'
            WHEN TIMESTAMPDIFF(HOUR, `$dateCol`, NOW()) >= 2  THEN 'shipped'
            ELSE 'pending'
        END
        WHERE estado IS NULL OR (estado NOT IN ('cancelled') )
    ");
} catch (Throwable $e) {
    // Si tu tabla/columna no coincide, no rompemos la página.
}

// --------- Filtros (buscador) ----------
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $conds = ["v.nombre LIKE ?"];
    $params[] = $like;

    if ($hasNombre)   { $conds[] = "u.nombre LIKE ?"; $params[] = $like; }
    if ($hasApellido) { $conds[] = "u.apellido LIKE ?"; $params[] = $like; }
    if ($emailCol)    { $conds[] = "u.`$emailCol` LIKE ?"; $params[] = $like; }

    $where[] = '(' . implode(' OR ', $conds) . ')';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --------- Campos "comprador" adaptativos ----------
if ($hasNombre && $hasApellido) {
    $buyerExpr = "TRIM(CONCAT_WS(' ', u.nombre, u.apellido))";
} elseif ($hasNombre) {
    $buyerExpr = "u.nombre";
} elseif ($emailCol) {
    $buyerExpr = "u.`$emailCol`";
} else {
    $buyerExpr = "CONCAT('Usuario #', u.id_usuario)";
}

$emailExpr = $emailCol ? "u.`$emailCol`" : "NULL";

// --------- Query principal ----------
$sql = "
    SELECT
        c.id_compra,
        c.`$dateCol` AS purchase_at,
        c.cantidad,
        c.estado,
        $buyerExpr AS comprador,
        $emailExpr AS comprador_email,
        v.nombre AS vino_nombre,
        v.tipo,
        v.annada,
        v.precio,
        (v.precio * c.cantidad) AS total,
        v.imagen,
        d.nombre AS denominacion_nombre
    FROM compras c
    INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
    INNER JOIN vinos v ON c.id_vino = v.id_vino
    LEFT JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
    $whereSql
    ORDER BY purchase_at DESC, c.id_compra DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales
$totalVentas = 0.0;
$totalItems  = 0;
foreach ($ventas as $v) {
    $totalVentas += (float)($v['total'] ?? 0);
    $totalItems  += (int)($v['cantidad'] ?? 0);
}

include __DIR__ . '/../includes/header.php';
?>



<section class="admin-hero">
  <div class="admin-shell">
    <div class="admin-head">
      <div class="admin-kicker">Administration</div>
      <h1 class="admin-title">Sales</h1>
      <p class="admin-subtitle">Search orders by buyer or wine, and track totals at a glance.</p>

      <form method="get" class="sales-search">
        <input
          class="sales-search__input"
          type="text"
          name="q"
          value="<?= htmlspecialchars($q) ?>"
          placeholder="Search by buyer or wine…"
        >
        <button class="btn btn-sm" type="submit">Search</button>

        <?php if ($q !== ''): ?>
          <a class="btn btn-sm btn-ghost" href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">Clear</a>
        <?php endif; ?>
      </form>

      <div class="sales-stats">
        <div class="sales-stat">
          <div class="sales-stat__label">Orders</div>
          <div class="sales-stat__value"><?= count($ventas) ?></div>
        </div>
        <div class="sales-stat">
          <div class="sales-stat__label">Units</div>
          <div class="sales-stat__value"><?= (int)$totalItems ?></div>
        </div>
        <div class="sales-stat">
          <div class="sales-stat__label">Revenue</div>
          <div class="sales-stat__value"><?= number_format($totalVentas, 2) ?> €</div>
        </div>
      </div>

      <div class="admin-actions" style="margin-top: 14px;">
        <a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/pages/admin_panel.php">← Back to admin panel</a>
      </div>
    </div>

    <?php if (empty($ventas)): ?>
      <div class="notice notice-error">No sales yet.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="sales-table">
          <thead>
            <tr>
              <th>Buyer</th>
              <th>Wine</th>
              <th class="cell-right">Qty</th>
              <th>Date</th>
              <th class="cell-right">Unit</th>
              <th class="cell-right">Total</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($ventas as $row): ?>
            <?php
              $unit  = (float)($row['precio'] ?? 0);
              $total = (float)($row['total'] ?? 0);

              $dt = $row['purchase_at'] ?? null;
              $fechaFmt = $dt ? date('d/m/Y H:i', strtotime($dt)) : '-';

              $estado = $row['estado'] ?? 'pending';
              $statusClass = 'status-pending';
              $statusText  = 'Pending';

              if ($estado === 'shipped')   { $statusClass = 'status-shipped';   $statusText = 'Shipped'; }
              if ($estado === 'delivered') { $statusClass = 'status-delivered'; $statusText = 'Delivered'; }
              if ($estado === 'cancelled') { $statusClass = 'status-cancelled'; $statusText = 'Cancelled'; }

              $vino = $row['vino_nombre'] ?? '';
              $den  = $row['denominacion_nombre'] ?? '';
              $ann  = $row['annada'] ?? '';
              $tipo = $row['tipo'] ?? '';

              $comprador = $row['comprador'] ?? ('User #' . ($row['id_usuario'] ?? ''));
              $compradorEmail = $row['comprador_email'] ?? '';
            ?>
            <tr>
              <td>
                <div class="buyer">
                  <div class="buyer__name"><?= htmlspecialchars($comprador) ?></div>
                  <?php if (!empty($compradorEmail) && $compradorEmail !== $comprador): ?>
                    <div class="buyer__meta"><?= htmlspecialchars($compradorEmail) ?></div>
                  <?php endif; ?>
                  <div class="buyer__meta">Order ID: #<?= htmlspecialchars($row['id_compra']) ?></div>
                </div>
              </td>

              <td>
                <div class="sale-wine">
                  <?php if (!empty($row['imagen'])): ?>
                    <img class="sale-wine__img" src="<?= htmlspecialchars($row['imagen']) ?>" alt="">
                  <?php else: ?>
                    <div class="sale-wine__img sale-wine__img--empty">🍷</div>
                  <?php endif; ?>

                  <div>
                    <div class="sale-wine__name"><?= htmlspecialchars($vino) ?></div>
                    <div class="sale-wine__meta">
                      <?= htmlspecialchars(trim("$tipo · $ann")) ?>
                      <?php if ($den): ?> · <?= htmlspecialchars($den) ?><?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <td class="cell-right cell-mono"><?= (int)($row['cantidad'] ?? 0) ?></td>
              <td class="cell-mono"><?= htmlspecialchars($fechaFmt) ?></td>
              <td class="cell-right cell-mono"><?= number_format($unit, 2) ?> €</td>
              <td class="cell-right cell-mono"><strong><?= number_format($total, 2) ?> €</strong></td>
              <td><span class="<?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
