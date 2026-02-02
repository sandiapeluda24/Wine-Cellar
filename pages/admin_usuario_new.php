<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

requireRole('admin');

$db = getDB();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $role   = $_POST['rol'] ?? 'coleccionista';
    $cert   = isset($_POST['certificado']) ? 1 : 0;

    // (Opcional pero recomendado para crear) password
    $password = $_POST['password'] ?? '';

    if ($nombre === '') $errors[] = "Name is required.";
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";

    if (!in_array($role, ['admin','coleccionista','sommelier'], true)) {
        $role = 'coleccionista';
    }

    // Comprueba email duplicado
    if (!$errors) {
        $stmtChk = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $stmtChk->execute([$email]);
        if ((int)$stmtChk->fetchColumn() > 0) {
            $errors[] = "Email already exists.";
        }
    }

    if (!$errors) {
        // Si quieres obligar password, valida aquí:
        // if ($password === '') $errors[] = "Password is required.";

        $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

        // ⚠️ AJUSTA COLUMNAS según tu tabla:
        // - Si tu tabla NO tiene password_hash, quita esa parte.
        // - Si tu columna se llama "password" o "contrasena", cambia el nombre.
        $stmtIns = $db->prepare("
            INSERT INTO usuarios (nombre, email, rol, certificado, password_hash, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmtIns->execute([$nombre, $email, $role, $cert, $passwordHash]);

        $success = "User created successfully.";
    }
}
?>

<h2>New user</h2>

<?php if ($success): ?>
  <p class="success"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
  <p class="error"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<form method="post">
  <label>Name<br>
    <input type="text" name="nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
  </label>
  <br><br>

  <label>Email<br>
    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
  </label>
  <br><br>

  <label>Password (optional)<br>
    <input type="password" name="password" value="">
  </label>
  <br><br>

  <label>Role<br>
    <select name="rol">
      <option value="coleccionista">Collector</option>
      <option value="sommelier">Sommelier</option>
      <option value="admin">Admin</option>
    </select>
  </label>
  <br><br>

  <label>
    <input type="checkbox" name="certificado" <?= isset($_POST['certificado']) ? 'checked' : '' ?>>
    Certified sommelier
  </label>
  <br><br>

  <button type="submit">Create user</button>
</form>

<p><a href="<?= BASE_URL ?>/pages/admin_usuarios.php">Back to users list</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
