<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

requireRole('admin');

$db = getDB();
$errors = [];
$success = '';
$user = null;

if (empty($_GET['id'])) {
    echo "<p>User id is required.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$id = (int) $_GET['id'];

$stmt = $db->prepare("
    SELECT id_usuario, nombre, email, rol, certificado, created_at
    FROM usuarios
    WHERE id_usuario = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<p>User not found.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['rol'] ?? 'coleccionista';
    $cert = isset($_POST['certificado']) ? 1 : 0;

    if (!in_array($role, ['admin','coleccionista','sommelier'], true)) {
        $role = 'coleccionista';
    }

    $stmtUp = $db->prepare("
        UPDATE usuarios
        SET rol = ?, certificado = ?
        WHERE id_usuario = ?
    ");
    $stmtUp->execute([$role, $cert, $id]);

    $success = "User updated successfully.";

    // recargar datos
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<h2>Edit user</h2>

<?php if ($success): ?>
    <p class="success"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
    <p class="error"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<p><strong>Name:</strong> <?= htmlspecialchars($user['nombre']) ?></p>
<p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
<p><strong>Member since:</strong> <?= htmlspecialchars($user['created_at']) ?></p>

<form method="post">
    <label>Role<br>
        <select name="rol">
            <option value="coleccionista" <?= $user['rol']==='coleccionista'?'selected':'' ?>>Collector</option>
            <option value="sommelier" <?= $user['rol']==='sommelier'?'selected':'' ?>>Sommelier</option>
            <option value="admin" <?= $user['rol']==='admin'?'selected':'' ?>>Admin</option>
        </select>
    </label>
    <br><br>

    <label>
        <input type="checkbox" name="certificado" <?= $user['certificado'] ? 'checked' : '' ?>>
        Certified sommelier
    </label>
    <br><br>

    <button type="submit">Save changes</button>
</form>

<p><a href="<?= BASE_URL ?>/pages/admin_usuarios.php">Back to users list</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
