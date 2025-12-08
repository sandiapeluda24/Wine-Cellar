<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$errores    = [];
$loginError = null;
$user       = null;
$password   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errores[] = "Email and password are required.";
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $password !== $user['password']) {
    // Email no existe o contraseña incorrecta
    $loginError = "Invalid email or password.";
}
elseif ((int)$user['is_active'] === 0) {
            // Usuario existe, contraseña ok, pero cuenta desactivada
            $loginError = "Your account has been deactivated by the administrators of the web. Please contact this email for further explanation and the possible reactivation of your account.";
                } else {
            // Login correcto
            $_SESSION['usuario'] = [
                'id_usuario' => $user['id_usuario'],
                'nombre'     => $user['nombre'],
                'rol'        => $user['rol']
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

<?php if (!empty($loginError)): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
<?php endif; ?>

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
