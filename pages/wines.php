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

<div class="container">
  <header class="page-head">
    <div>
      <div class="page-kicker">Fine wine cellar</div>
      <h1 class="page-title">Wines</h1>
      <p class="page-subtitle">Browse the catalog, filter by denomination and aging status, and add bottles to your cart.</p>
    </div>

    <div class="page-actions">
      <?php if ($cartCount > 0): ?>
        <a class="btn btn-secondary" href="cart.php">Cart (<?= (int)$cartCount ?>)</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($mensaje): ?>
    <div class="notice notice-success"><?= $mensaje ?></div>
  <?php endif; ?>

  <?php if ($mensajeError): ?>
    <div class="notice notice-error"><?= htmlspecialchars($mensajeError) ?></div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="get" class="filters-card">
    <div class="filters-grid">
      <div class="filter-group">
        <label for="denom">Denomination</label>
        <select id="denom" name="denom">
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

      <div class="filter-group">
        <label for="aging">Aging status</label>
        <select id="aging" name="aging">
          <option value="all" <?= ($selectedAging === 'all') ? 'selected' : '' ?>>All</option>
          <option value="Too young"   <?= ($selectedAging === 'Too young') ? 'selected' : '' ?>>Too young</option>
          <option value="In window"   <?= ($selectedAging === 'In window') ? 'selected' : '' ?>>In window</option>
          <option value="Past window" <?= ($selectedAging === 'Past window') ? 'selected' : '' ?>>Past window</option>
          <option value="Unknown"     <?= ($selectedAging === 'Unknown') ? 'selected' : '' ?>>Unknown</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="sort">Sort by</label>
        <select id="sort" name="sort">
          <option value="name_asc" <?= ($selectedSort === 'name_asc') ? 'selected' : '' ?>>Name (A–Z)</option>
          <option value="denom_asc" <?= ($selectedSort === 'denom_asc') ? 'selected' : '' ?>>Denomination (A–Z)</option>
          <option value="price_asc" <?= ($selectedSort === 'price_asc') ? 'selected' : '' ?>>Price (low → high)</option>
          <option value="price_desc" <?= ($selectedSort === 'price_desc') ? 'selected' : '' ?>>Price (high → low)</option>
          <option value="stock_desc" <?= ($selectedSort === 'stock_desc') ? 'selected' : '' ?>>Stock (high → low)</option>
          <option value="vintage_desc" <?= ($selectedSort === 'vintage_desc') ? 'selected' : '' ?>>Vintage (newest)</option>
          <option value="window_start_asc" <?= ($selectedSort === 'window_start_asc') ? 'selected' : '' ?>>Optimal window starts soonest</option>
        </select>
      </div>
    </div>

    <div class="filter-actions">
      <button class="btn" type="submit">Apply</button>
      <a class="btn btn-secondary" href="wines.php">Reset</a>
    </div>
  </form>

  <?php if (empty($vinos)): ?>
    <p class="empty-state">No wines available at the moment.</p>
  <?php else: ?>
    <div class="wine-grid">
      <?php foreach ($vinos as $vino):
        $denom = !empty($vino['denominacion']) ? denomLabel($vino['denominacion']) : null;
        $aging = $vino['aging_status'] ?? 'Unknown';

        // PHP 7 compatible (avoid match)
        switch ($aging) {
          case 'In window':
            $agingClass = 'badge-aging--good';
            break;
          case 'Too young':
            $agingClass = 'badge-aging--young';
            break;
          case 'Past window':
            $agingClass = 'badge-aging--old';
            break;
          default:
            $agingClass = 'badge-aging--unknown';
        }

        $stock = (int)($vino['stock'] ?? 0);
        $fallback = strtoupper(substr((string)$vino['nombre'], 0, 2));
      ?>
        <article class="wine-card">
          <div class="wine-media">
            <?php if (!empty($vino['imagen'])): ?>
              <img class="wine-img" src="../img/wines/<?= htmlspecialchars($vino['imagen']) ?>" alt="Bottle of <?= htmlspecialchars($vino['nombre']) ?>">
            <?php else: ?>
              <div class="wine-img wine-img--placeholder" aria-hidden="true">
                <span><?= htmlspecialchars($fallback) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <div class="wine-content">
            <div class="wine-head">
              <h2 class="wine-name"><?= htmlspecialchars($vino['nombre']) ?></h2>
              <div class="wine-badges">
                <?php if ($denom): ?><span class="badge badge-denom"><?= htmlspecialchars($denom) ?></span><?php endif; ?>
                <span class="badge badge-aging <?= $agingClass ?>"><?= htmlspecialchars($aging) ?></span>
                <?php if ($stock <= 0): ?>
                  <span class="badge badge-stock badge-stock--out">Out of stock</span>
                <?php else: ?>
                  <span class="badge badge-stock badge-stock--in"><?= $stock ?> in stock</span>
                <?php endif; ?>
              </div>
            </div>

            <p class="wine-meta">
              <?php if (!empty($vino['bodega'])): ?><span><b>Winery:</b> <?= htmlspecialchars($vino['bodega']) ?></span><?php endif; ?>
              <span><b>Vintage:</b> <?= htmlspecialchars($vino['annada']) ?></span>
              <?php if (!empty($vino['pais'])): ?><span><b>Country:</b> <?= htmlspecialchars($vino['pais']) ?></span><?php endif; ?>
              <?php if (!empty($vino['tipo'])): ?><span><b>Type:</b> <?= htmlspecialchars($vino['tipo']) ?></span><?php endif; ?>
            </p>

            <div class="wine-stats">
              <div class="wine-price">€<?= number_format((float)$vino['precio'], 2) ?></div>
              <?php if (!empty($vino['ventana_optima_inicio']) || !empty($vino['ventana_optima_fin'])): ?>
                <div class="wine-window"><span>Optimal window</span><b><?= htmlspecialchars($vino['ventana_optima_inicio']) ?> – <?= htmlspecialchars($vino['ventana_optima_fin']) ?></b></div>
              <?php endif; ?>
            </div>

            <div class="wine-actions">
              <a class="btn btn-secondary" href="vinos_detalle.php?id_vino=<?= (int)$vino['id_vino'] ?>">View details</a>

              <?php if (isset($_SESSION['usuario']) && ($_SESSION['usuario']['rol'] ?? '') === 'coleccionista'): ?>
                <?php if ($stock > 0): ?>
                  <form method="post" class="add-to-cart-form">
                    <input type="hidden" name="accion" value="add_to_cart">
                    <input type="hidden" name="id_vino" value="<?= (int)$vino['id_vino'] ?>">
                    <label class="qty">
                      <span>Qty</span>
                      <input class="qty-input" type="number" name="cantidad" value="1" min="1">
                    </label>
                    <button class="btn" type="submit">Add</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
