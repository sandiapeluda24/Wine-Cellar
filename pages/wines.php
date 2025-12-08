<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$db = getDB();

$mensaje = null;
$mensajeError = null;

// --- Gestión de compra ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'comprar') {

    if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'coleccionista') {
        $mensajeError = "You must be logged in as a collector to buy wines.";
    } else {
        $idUsuario = $_SESSION['usuario']['id_usuario'] ?? ($_SESSION['usuario']['id'] ?? null);

        if ($idUsuario === null) {
            $mensajeError = "Could not get your user id. Please log in again.";
        } else {
            $idVino   = (int) ($_POST['id_vino'] ?? 0);
            $cantidad = max(1, (int) ($_POST['cantidad'] ?? 1));

            try {
                $db->beginTransaction();

                // Bloquear fila del vino y leer stock actual
                $stmt = $db->prepare("SELECT stock FROM vinos WHERE id_vino = ? FOR UPDATE");
                $stmt->execute([$idVino]);
                $stockActual = $stmt->fetchColumn();

                if ($stockActual === false) {
                    $mensajeError = "Selected wine does not exist.";
                    $db->rollBack();
                } elseif ($stockActual < $cantidad) {
                    $mensajeError = "Not enough stock. Only $stockActual bottles left.";
                    $db->rollBack();
                } else {
                    // Restar stock
                    $stmt = $db->prepare("UPDATE vinos SET stock = stock - ? WHERE id_vino = ?");
                    $stmt->execute([$cantidad, $idVino]);

                    // Registrar compra
                    $stmt = $db->prepare("
                        INSERT INTO compras (id_usuario, id_vino, cantidad)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$idUsuario, $idVino, $cantidad]);

                    $db->commit();
                    $mensaje = "Wine added to your purchases.";
                }

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $mensajeError = "An error occurred while processing your purchase.";
            }
        }
    }
}

// --- Cargar lista de vinos ---
$stmt = $db->query("
    SELECT id_vino, nombre, bodega, annada, tipo, pais,
           ventana_optima_inicio, ventana_optima_fin, imagen, precio, stock
    FROM vinos
    ORDER BY nombre
");
$vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<h1>Lista de vinos</h1>

<?php if ($mensaje): ?>
    <p style="color:green;"><?= htmlspecialchars($mensaje) ?></p>
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

                <?php if (!empty($vino['bodega'])): ?>
                    <p><strong>Winery:</strong> <?= htmlspecialchars($vino['bodega']) ?></p>
                <?php endif; ?>

                <p><strong>Vintage:</strong> <?= htmlspecialchars($vino['annada']) ?></p>

                <?php if (!empty($vino['tipo'])): ?>
                    <p><strong>Type:</strong> <?= htmlspecialchars($vino['tipo']) ?></p>
                <?php endif; ?>

                <?php if (isset($vino['precio'])): ?>
    <p><strong>Price:</strong> <?= number_format($vino['precio'], 2) ?> €</p>
<?php endif; ?>

                <?php if (!empty($vino['pais'])): ?>
                    <p><strong>Country:</strong> <?= htmlspecialchars($vino['pais']) ?></p>
                <?php endif; ?>

                <?php if (!empty($vino['imagen'])): ?>
                    <p>
                        <img src="../img/wines/<?= htmlspecialchars($vino['imagen']) ?>"
                             alt="Bottle of <?= htmlspecialchars($vino['nombre']) ?>"
                             style="max-width:150px; height:auto;">
                    </p>
                <?php endif; ?>

                <?php if (!empty($vino['ventana_optima_inicio']) || !empty($vino['ventana_optima_fin'])): ?>
                    <p><strong>Optimal window:</strong>
                        <?= htmlspecialchars($vino['ventana_optima_inicio']) ?>
                        –
                        <?= htmlspecialchars($vino['ventana_optima_fin']) ?>
                    </p>
                <?php endif; ?>

                <p>
                    <a href="vinos_detalle.php?id_vino=<?= $vino['id_vino'] ?>">
                        View details
                    </a>
                </p>

                <p><strong>In stock:</strong> <?= (int)$vino['stock'] ?> bottles</p>


                <?php if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol'] === 'coleccionista' && $vino['stock'] > 0): ?>
    <form method="post" style="margin-top:8px;">
        <input type="hidden" name="accion" value="comprar">
        <input type="hidden" name="id_vino" value="<?= $vino['id_vino'] ?>">
        <label>
            Qty:
            <input type="number" name="cantidad" value="1" min="1" max="<?= (int)$vino['stock'] ?>" style="width:60px;">
        </label>
        <button type="submit">Buy</button>
    </form>
<?php elseif ($vino['stock'] <= 0): ?>
    <p style="color:red;"><strong>Out of stock</strong></p>
<?php endif; ?>


            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
