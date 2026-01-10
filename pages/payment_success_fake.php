<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireRole('coleccionista');

$idUsuario = $_SESSION['usuario']['id_usuario'] ?? ($_SESSION['usuario']['id'] ?? null);
if (!$idUsuario) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

// Validación servidor
$cardName = trim($_POST['card_name'] ?? '');
$cardNumber = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
$expiry = trim($_POST['expiry'] ?? '');
$cvc = trim($_POST['cvc'] ?? '');

if ($cardName === '') {
    $_SESSION['pay_error'] = "Please enter the cardholder name.";
    header('Location: fake_payment.php');
    exit;
}
if (!preg_match('/^\d{13,19}$/', $cardNumber)) {
    $_SESSION['pay_error'] = "Invalid card number.";
    header('Location: fake_payment.php');
    exit;
}
if (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
    $_SESSION['pay_error'] = "Expiry must be MM/YY.";
    header('Location: fake_payment.php');
    exit;
}
if (!preg_match('/^\d{3,4}$/', $cvc)) {
    $_SESSION['pay_error'] = "Invalid CVC.";
    header('Location: fake_payment.php');
    exit;
}

// Regla interna para poder “fallar” pagos en demo sin decirlo:
if (substr($cardNumber, -4) === '0000') {
    $_SESSION['pay_error'] = "Payment declined. Please try another card.";
    header('Location: fake_payment.php');
    exit;
}

$db = getDB();
$cart = $_SESSION['cart'];

try {
    $db->beginTransaction();

    // Lock filas + revalidar stock
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $db->prepare("
        SELECT id_vino, nombre, stock
        FROM vinos
        WHERE id_vino IN ($placeholders)
        FOR UPDATE
    ");
    $stmt->execute($ids);
    $vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($vinos as $v) $map[(int)$v['id_vino']] = $v;

    foreach ($cart as $idVino => $qty) {
        $idVino = (int)$idVino;
        $qty = (int)$qty;

        if (!isset($map[$idVino])) {
            throw new Exception("A wine in your cart no longer exists.");
        }
        $stock = (int)$map[$idVino]['stock'];
        if ($stock < $qty) {
            $name = $map[$idVino]['nombre'];
            throw new Exception("Not enough stock for \"$name\". Available: $stock bottle(s).");
        }
    }

    // Insert compras + update stock
    $stmtCompra = $db->prepare("INSERT INTO compras (id_usuario, id_vino, cantidad) VALUES (?, ?, ?)");
    $stmtStock  = $db->prepare("UPDATE vinos SET stock = stock - ? WHERE id_vino = ?");

    foreach ($cart as $idVino => $qty) {
        $stmtCompra->execute([$idUsuario, (int)$idVino, (int)$qty]);
        $stmtStock->execute([(int)$qty, (int)$idVino]);
    }

    $db->commit();

    $_SESSION['cart'] = [];

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['pay_error'] = "We could not finalize the order: " . $e->getMessage();
    header('Location: cart.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<h1>Payment successful ✅</h1>
<p>Your purchase has been confirmed.</p>
<p><a href="my_purchases.php">Go to My purchases</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
