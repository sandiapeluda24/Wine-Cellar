<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$db = getDB();

$mensaje = null;
$mensajeError = null;

// Crear carrito si no existe
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // [id_vino => cantidad]
}

// --- Añadir a la cesta ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'add_to_cart') {

    // Solo coleccionistas logueados pueden añadir a carrito
    if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'coleccionista') {
        $mensajeError = "You must be logged in as a collector to add wines to the cart.";
    } else {
        $idVino = (int)($_POST['id_vino'] ?? 0);
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));

        // Validar que exista y ver stock actual
        $stmt = $db->prepare("SELECT nombre, stock FROM vinos WHERE id_vino = ?");
        $stmt->execute([$idVino]);
        $vinoDB = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vinoDB) {
            $mensajeError = "Selected wine does not exist.";
        } else {
            $stock = (int)$vinoDB['stock'];
            $nombreVino = $vinoDB['nombre'];

            // Cantidad total que quedaría en carrito para ese vino
            $enCarrito = (int)($_SESSION['cart'][$idVino] ?? 0);
            $totalDeseado = $enCarrito + $cantidad;

            if ($stock <= 0) {
                $mensajeError = "Sorry, \"$nombreVino\" is out of stock.";
            } elseif ($totalDeseado > $stock) {
                // Mensaje claro (en vez del mensaje feo del navegador)
                $mensajeError = "Only $stock bottle(s) available for \"$nombreVino\". Please reduce the quantity.";
            } else {
                $_SESSION['cart'][$idVino] = $totalDeseado;
                $restante = $stock - $totalDeseado;
$mensaje = "Added to cart. If you checkout, \"$nombreVino\" will have $restante bottle(s) left. <a href='cart.php'>View cart</a>";

            }
        }
    }
}

// --- Filters & sorting (GET) ---
$selectedDenom  = $_GET['denom'] ?? 'all';
$selectedAging  = $_GET['aging'] ?? 'all';
$selectedSort   = $_GET['sort']  ?? 'name_asc';

// Load denominations for dropdown (UI filter)
$denomOptions = [];
try {
    $stmtDenom = $db->query("SELECT id_denominacion, nombre FROM denominaciones ORDER BY nombre");
    $denomOptions = $stmtDenom->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $denomOptions = [];
}

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

$allowedSort = [
    'name_asc'         => 'v.nombre ASC',
    'price_asc'        => 'v.precio ASC',
    'price_desc'       => 'v.precio DESC',
    'stock_desc'       => 'v.stock DESC',
    'vintage_desc'     => 'v.annada DESC',
    'denom_asc'        => 'd.nombre ASC, v.nombre ASC',
    'window_start_asc' => 'v.ventana_optima_inicio ASC, v.nombre ASC',
];
$orderBy = $allowedSort[$selectedSort] ?? $allowedSort['name_asc'];

// --- Load wines (with denomination + aging status) ---
$where  = [];
$params = [];

if ($selectedDenom !== 'all' && $selectedDenom !== '') {
    $where[] = "d.nombre = ?";
    $params[] = $selectedDenom;
}

$sql = "
    SELECT
        v.id_vino, v.nombre, v.bodega, v.annada, v.tipo, v.pais,
        v.ventana_optima_inicio, v.ventana_optima_fin,
        v.precio, v.stock, v.imagen,
        d.nombre AS denominacion,
        CASE
            WHEN v.ventana_optima_inicio IS NULL OR v.ventana_optima_fin IS NULL THEN 'Unknown'
            WHEN YEAR(CURDATE()) < v.ventana_optima_inicio THEN 'Too young'
            WHEN YEAR(CURDATE()) > v.ventana_optima_fin THEN 'Past window'
            ELSE 'In window'
        END AS aging_status
    FROM vinos v
    LEFT JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

if ($selectedAging !== 'all' && $selectedAging !== '') {
    // Filter on computed alias
    $sql .= " HAVING aging_status = ?";
    $params[] = $selectedAging;
}

$sql .= " ORDER BY $orderBy";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// contador carrito
$cartCount = array_sum($_SESSION['cart']);
?>

<h1>Wines</h1>

<?php if ($cartCount > 0): ?>
    <p><strong>Cart:</strong> <?= (int)$cartCount ?> item(s) — <a href="cart.php">Go to cart</a></p>
<?php endif; ?>

<?php if ($mensaje): ?>
    <p style="color:green;"><?= $mensaje ?></p>
<?php endif; ?>

<?php if ($mensajeError): ?>
    <p style="color:red;"><?= htmlspecialchars($mensajeError) ?></p>
<?php endif; ?>


<!-- Filters -->
<form method="get" style="margin:14px 0; padding:12px; border:1px solid #eee; border-radius:10px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
    <div>
        <label>Denomination</label><br>
        <select name="denom">
            <option value="all" <?= ($selectedDenom === 'all') ? 'selected' : '' ?>>All</option>
            <?php foreach ($denomOptions as $dOpt): 
                $val = $dOpt['nombre'];
                $lbl = denomLabel($dOpt['nombre']);
            ?>
                <option value="<?= htmlspecialchars($val) ?>" <?= ($selectedDenom === $val) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lbl) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label>Aging status</label><br>
        <select name="aging">
            <option value="all" <?= ($selectedAging === 'all') ? 'selected' : '' ?>>All</option>
            <option value="Too young"   <?= ($selectedAging === 'Too young') ? 'selected' : '' ?>>Too young</option>
            <option value="In window"   <?= ($selectedAging === 'In window') ? 'selected' : '' ?>>In window</option>
            <option value="Past window" <?= ($selectedAging === 'Past window') ? 'selected' : '' ?>>Past window</option>
            <option value="Unknown"     <?= ($selectedAging === 'Unknown') ? 'selected' : '' ?>>Unknown</option>
        </select>
    </div>

    <div>
        <label>Sort by</label><br>
        <select name="sort">
            <option value="name_asc" <?= ($selectedSort === 'name_asc') ? 'selected' : '' ?>>Name (A–Z)</option>
            <option value="denom_asc" <?= ($selectedSort === 'denom_asc') ? 'selected' : '' ?>>Denomination (A–Z)</option>
            <option value="price_asc" <?= ($selectedSort === 'price_asc') ? 'selected' : '' ?>>Price (low → high)</option>
            <option value="price_desc" <?= ($selectedSort === 'price_desc') ? 'selected' : '' ?>>Price (high → low)</option>
            <option value="stock_desc" <?= ($selectedSort === 'stock_desc') ? 'selected' : '' ?>>Stock (high → low)</option>
            <option value="vintage_desc" <?= ($selectedSort === 'vintage_desc') ? 'selected' : '' ?>>Vintage (newest)</option>
            <option value="window_start_asc" <?= ($selectedSort === 'window_start_asc') ? 'selected' : '' ?>>Optimal window starts soonest</option>
        </select>
    </div>

    <div>
        <button type="submit">Apply</button>
        <a href="wines.php" style="margin-left:10px">Reset</a>
    </div>
</form>

<?php if (empty($vinos)): ?>
    <p>No wines available at the moment.</p>
<?php else: ?>
    <div class="wine-list">
        <?php foreach ($vinos as $vino): ?>
            <div class="wine-card">
                <h2><?= htmlspecialchars($vino['nombre']) ?></h2>

<?php if (isset($vino['imagen']) && $vino['imagen'] !== ''): ?>
                    <p>
                        <img src="../img/wines/<?= htmlspecialchars($vino['imagen']) ?>"
                             alt="Bottle of <?= htmlspecialchars($vino['nombre']) ?>"
                             style="max-width:150px; height:auto;">
                    </p>
                <?php endif; ?>

                <?php if (!empty($vino['bodega'])): ?>
                    <p><strong>Winery:</strong> <?= htmlspecialchars($vino['bodega']) ?></p>
                <?php endif; ?>

                <p><strong>Vintage:</strong> <?= htmlspecialchars($vino['annada']) ?></p>

                <?php if (!empty($vino['denominacion'])): ?>
                    <p><strong>Denomination:</strong> <?= htmlspecialchars(denomLabel($vino['denominacion'])) ?></p>
                <?php endif; ?>

                <?php if (!empty($vino['tipo'])): ?>
                    <p><strong>Type:</strong> <?= htmlspecialchars($vino['tipo']) ?></p>
                <?php endif; ?>

                <?php if (!empty($vino['pais'])): ?>
                    <p><strong>Country:</strong> <?= htmlspecialchars($vino['pais']) ?></p>
                <?php endif; ?>

                <p><strong>Price:</strong> <?= number_format((float)$vino['precio'], 2) ?> €</p>
                <p><strong>In stock:</strong> <?= (int)$vino['stock'] ?> bottle(s)</p>

                <?php if (!empty($vino['ventana_optima_inicio']) || !empty($vino['ventana_optima_fin'])): ?>
                    <p><strong>Optimal window:</strong>
                        <?= htmlspecialchars($vino['ventana_optima_inicio']) ?>
                        –
                        <?= htmlspecialchars($vino['ventana_optima_fin']) ?>
                    </p>
                <?php endif; ?>

                                <?php if (!empty($vino['aging_status'])): ?>
                    <p><strong>Aging status:</strong> <?= htmlspecialchars($vino['aging_status']) ?></p>
                <?php endif; ?>

<p>
                    <a href="vinos_detalle.php?id_vino=<?= (int)$vino['id_vino'] ?>">View details</a>
                </p>

                <?php if (isset($_SESSION['usuario']) && ($_SESSION['usuario']['rol'] ?? '') === 'coleccionista'): ?>
                    <?php if ((int)$vino['stock'] > 0): ?>
                        <form method="post" style="margin-top:8px;">
                            <input type="hidden" name="accion" value="add_to_cart">
                            <input type="hidden" name="id_vino" value="<?= (int)$vino['id_vino'] ?>">

                            <label>
                                Qty:
                                <input type="number" name="cantidad" value="1" min="1" style="width:70px;">
                            </label>

                            <button type="submit">Add to cart</button>
                        </form>
                    <?php else: ?>
                        <p style="color:red;"><strong>Out of stock</strong></p>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
