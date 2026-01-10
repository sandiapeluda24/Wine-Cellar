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

// --- Cargar lista de vinos ---
$stmt = $db->query("
    SELECT 
        v.id_vino, v.nombre, v.bodega, v.annada, v.tipo, v.pais,
        v.ventana_optima_inicio, v.ventana_optima_fin,
        v.precio, v.stock,
        d.nombre AS denominacion
    FROM vinos v
    LEFT JOIN denominaciones d ON v.id_denominacion = d.id_denominacion
    ORDER BY v.nombre
");
$vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// contador carrito
$cartCount = array_sum($_SESSION['cart']);
?>

<h1>Lista de vinos</h1>

<?php if ($cartCount > 0): ?>
    <p><strong>Cart:</strong> <?= (int)$cartCount ?> item(s) — <a href="cart.php">Go to cart</a></p>
<?php endif; ?>

<?php if ($mensaje): ?>
    <p style="color:green;"><?= $mensaje ?></p>
<?php endif; ?>

<?php if ($mensajeError): ?>
    <p style="color:red;"><?= htmlspecialchars($mensajeError) ?></p>
<?php endif; ?>

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
                    <p><strong>Denomination:</strong> <?= htmlspecialchars($vino['denominacion']) ?></p>
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
