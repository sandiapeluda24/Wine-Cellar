<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireRole('coleccionista');

$db = getDB();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // [id_vino => cantidad]
}

$mensaje = null;
$mensajeError = null;

// Actualizar cantidades / eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'update') {
    foreach (($_POST['qty'] ?? []) as $idVino => $qty) {
        $idVino = (int)$idVino;
        $qty = (int)$qty;

        if ($qty <= 0) {
            unset($_SESSION['cart'][$idVino]);
        } else {
            $_SESSION['cart'][$idVino] = $qty;
        }
    }
    $mensaje = "Cart updated.";
}

// Cargar vinos del carrito
$cart = $_SESSION['cart'];
$vinosCarrito = [];
$total = 0.0;

if (!empty($cart)) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $db->prepare("
        SELECT id_vino, nombre, precio, imagen, stock
        FROM vinos
        WHERE id_vino IN ($placeholders)
    ");
    $stmt->execute($ids);
    $vinosCarrito = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vinosCarrito as &$v) {
        $id = (int)$v['id_vino'];
        $qty = (int)$cart[$id];

        $v['qty'] = $qty;
        $v['line_total'] = $qty * (float)$v['precio'];
        $total += $v['line_total'];

        // stock restante si se compra lo del carrito
        $v['after_checkout'] = (int)$v['stock'] - $qty;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<h1>Your cart</h1>

<?php if ($mensaje): ?>
  <p style="color:green;"><?= htmlspecialchars($mensaje) ?></p>
<?php endif; ?>

<?php if ($mensajeError): ?>
  <p style="color:red;"><?= htmlspecialchars($mensajeError) ?></p>
<?php endif; ?>

<?php if (!empty($_SESSION['pay_error'])): ?>
  <p style="color:red;"><?= htmlspecialchars($_SESSION['pay_error']) ?></p>
  <?php unset($_SESSION['pay_error']); ?>
<?php endif; ?>

<?php if (empty($cart)): ?>
  <p>Your cart is empty.</p>
  <p><a href="wines.php">Back to wines</a></p>
<?php else: ?>

  <form method="post">
    <input type="hidden" name="accion" value="update">

    <table border="1" cellpadding="8" cellspacing="0">
      <thead>
        <tr>
          <th>Wine</th>
          <th>Price</th>
          <th>Qty</th>
          <th>In stock now</th>
          <th>After checkout</th>
          <th>Line total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($vinosCarrito as $v): ?>
          <tr>
            <td>
              <?php if (!empty($v['imagen'])): ?>
                <img src="../img/wines/<?= htmlspecialchars($v['imagen']) ?>"
                     style="max-width:50px;height:auto;vertical-align:middle;">
              <?php endif; ?>
              <?= htmlspecialchars($v['nombre']) ?>
            </td>
            <td><?= number_format((float)$v['precio'], 2) ?> €</td>
            <td>
              <input type="number" name="qty[<?= (int)$v['id_vino'] ?>]" min="0"
                     value="<?= (int)$v['qty'] ?>" style="width:70px;">
              <small>(0 = remove)</small>
            </td>
            <td><?= (int)$v['stock'] ?></td>
            <td><?= (int)$v['after_checkout'] ?></td>
            <td><?= number_format((float)$v['line_total'], 2) ?> €</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p><strong>Total:</strong> <?= number_format((float)$total, 2) ?> €</p>

    <button type="submit">Update cart</button>
  </form>

  <form method="get" action="fake_payment.php" style="margin-top:12px;">
    <button type="submit">Pay with card</button>
  </form>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
