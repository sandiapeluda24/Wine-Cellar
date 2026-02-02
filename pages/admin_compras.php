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

<h1>Admin · Ventas</h1>

<form method="get" style="display:flex; gap:10px; align-items:center; margin: 12px 0 18px;">
    <input
        type="text"
        name="q"
        value="<?= htmlspecialchars($q) ?>"
        placeholder="Buscar por comprador o vino…"
        style="flex:1; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
    >
    <button type="submit" style="padding:10px 14px; border:0; border-radius:10px; cursor:pointer;">
        Buscar
    </button>
    <?php if ($q !== ''): ?>
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="padding:10px 14px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
            Limpiar
        </a>
    <?php endif; ?>
</form>

<div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom: 14px;">
    <div style="padding:12px 14px; border:1px solid #eee; border-radius:14px;">
        <div style="font-size:12px; opacity:.7;">Pedidos</div>
        <div style="font-size:18px; font-weight:700;"><?= count($ventas) ?></div>
    </div>
    <div style="padding:12px 14px; border:1px solid #eee; border-radius:14px;">
        <div style="font-size:12px; opacity:.7;">Unidades</div>
        <div style="font-size:18px; font-weight:700;"><?= (int)$totalItems ?></div>
    </div>
    <div style="padding:12px 14px; border:1px solid #eee; border-radius:14px;">
        <div style="font-size:12px; opacity:.7;">Total vendido</div>
        <div style="font-size:18px; font-weight:700;"><?= number_format($totalVentas, 2) ?> €</div>
    </div>
</div>

<?php if (empty($ventas)): ?>
    <p>No hay ventas todavía.</p>
<?php else: ?>
    <div style="overflow:auto; border:1px solid #eee; border-radius:16px;">
        <table style="width:100%; border-collapse:collapse; min-width: 980px;">
            <thead>
                <tr style="background:#fafafa;">
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Comprador</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Vino</th>
                    <th style="text-align:right; padding:12px; border-bottom:1px solid #eee;">Cantidad</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Fecha</th>
                    <th style="text-align:right; padding:12px; border-bottom:1px solid #eee;">Unitario</th>
                    <th style="text-align:right; padding:12px; border-bottom:1px solid #eee;">Total</th>
                    <th style="text-align:left; padding:12px; border-bottom:1px solid #eee;">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ventas as $row): ?>
                <?php
                    $unit = (float)($row['precio'] ?? 0);
                    $total = (float)($row['total'] ?? 0);

                    $dt = $row['purchase_at'] ?? null;
                    $fechaFmt = $dt ? date('d/m/Y H:i', strtotime($dt)) : '-';

                    $estado = $row['estado'] ?? 'pending';
                    $statusText = ucfirst($estado);
                    $badgeStyle = "display:inline-block; padding:6px 10px; border-radius:999px; font-size:12px; border:1px solid #ddd;";

                    if ($estado === 'pending')   { $badgeStyle .= " background:#fff7e6; border-color:#ffe3a3;"; $statusText = "Pending"; }
                    if ($estado === 'shipped')   { $badgeStyle .= " background:#e8f0ff; border-color:#b6d0ff;"; $statusText = "Shipped"; }
                    if ($estado === 'delivered') { $badgeStyle .= " background:#e9fbef; border-color:#bfe8c9;"; $statusText = "Delivered"; }
                    if ($estado === 'cancelled') { $badgeStyle .= " background:#fff0f0; border-color:#ffc5c5;"; $statusText = "Cancelled"; }

                    $vino = $row['vino_nombre'] ?? '';
                    $den  = $row['denominacion_nombre'] ?? '';
                    $ann  = $row['annada'] ?? '';
                    $tipo = $row['tipo'] ?? '';

                    $comprador = $row['comprador'] ?? ('Usuario #' . ($row['id_usuario'] ?? ''));
                    $compradorEmail = $row['comprador_email'] ?? '';
                ?>
                <tr>
                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;">
                        <div style="font-weight:600;"><?= htmlspecialchars($comprador) ?></div>
                        <?php if (!empty($compradorEmail) && $compradorEmail !== $comprador): ?>
                            <div style="font-size:12px; opacity:.7;"><?= htmlspecialchars($compradorEmail) ?></div>
                        <?php endif; ?>
                        <div style="font-size:12px; opacity:.6;">ID pedido: #<?= htmlspecialchars($row['id_compra']) ?></div>
                    </td>

                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;">
                        <div style="display:flex; gap:10px; align-items:center;">
                            <?php if (!empty($row['imagen'])): ?>
                                <img src="<?= htmlspecialchars($row['imagen']) ?>" alt="" style="width:44px; height:44px; object-fit:cover; border-radius:10px; border:1px solid #eee;">
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars($vino) ?></div>
                                <div style="font-size:12px; opacity:.7;">
                                    <?= htmlspecialchars(trim("$tipo · $ann")) ?>
                                    <?php if ($den): ?> · <?= htmlspecialchars($den) ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <td style="padding:12px; text-align:right; border-bottom:1px solid #f1f1f1;">
                        <?= (int)($row['cantidad'] ?? 0) ?>
                    </td>

                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;">
                        <?= htmlspecialchars($fechaFmt) ?>
                    </td>

                    <td style="padding:12px; text-align:right; border-bottom:1px solid #f1f1f1;">
                        <?= number_format($unit, 2) ?> €
                    </td>

                    <td style="padding:12px; text-align:right; border-bottom:1px solid #f1f1f1;">
                        <strong><?= number_format($total, 2) ?> €</strong>
                    </td>

                    <td style="padding:12px; border-bottom:1px solid #f1f1f1;">
                        <span style="<?= $badgeStyle ?>"><?= htmlspecialchars($statusText) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p style="margin-top:16px;">
    <a href="<?= BASE_URL ?>/pages/admin.php">← Volver al panel admin</a>
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
