<?php
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errores[] = "Email and password are required.";
    } else {
        $db = getDB();
        // Primero buscar el usuario SIN verificar si está activo
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            // El email no existe
            $errores[] = "Incorrect credentials.";
        } elseif (!password_verify($password, $usuario['password'])) {
            // Email existe pero contraseña incorrecta
            $errores[] = "Incorrect credentials.";
        } elseif ((int)$usuario['is_active'] === 0) {
            // Usuario existe, contraseña correcta, pero cuenta desactivada
            $errores[] = "Your account has been deactivated by the administrators of the web. Please contact support@winecellar.com for further explanation and the possible reactivation of your account.";
        } else {
            // Todo correcto - iniciar sesión
            $_SESSION['usuario'] = [
                'id'   => $usuario['id_usuario'],
                'nombre' => $usuario['nombre'],
                'rol'  => $usuario['rol']
            ];
            header("Location: ../index.php");
            exit;
        }
    }
}
?>

<h2>Login</h2>
<?php foreach ($errores as $e): ?>
    <p class="error"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<form method="post" id="formLogin">
    <label>Email:
        <input type="email" name="email" required>
    </label>
    <label>Contraseña:
        <input type="password" name="password" required>
    </label>
    <button type="submit">Entrar</button>
</form>

<p class="text-center">
    Don't have an account? <a href="register.php">Register here</a>
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>