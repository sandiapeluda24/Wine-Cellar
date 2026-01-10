<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config.php';

$errores    = [];
$loginError = null;
$user       = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errores[] = "Email and password are required.";
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // 1) Usuario no existe
            if (!$user) {
                $loginError = "Invalid email or password.";
            } else {
                // 2) Password: soporta hash o texto plano
                $dbPass = (string)($user['password'] ?? '');
                $looksHashed = str_starts_with($dbPass, '$2y$') || str_starts_with($dbPass, '$2a$') || str_starts_with($dbPass, '$argon2');

                $passwordOk = $looksHashed ? password_verify($password, $dbPass) : ($password === $dbPass);

                if (!$passwordOk) {
                    $loginError = "Invalid email or password.";
                }
                // 3) is_active opcional (si no existe, asumimos activo)
                elseif (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                    $loginError = "Your account has been deactivated by the administrators of the web. Please contact this email for further explanation and the possible reactivation of your account.";
                } else {
                    // Login correcto
                    $_SESSION['usuario'] = [
                        'id_usuario' => $user['id_usuario'] ?? null,
                        'nombre'     => $user['nombre'] ?? '',
                        'rol'        => $user['rol'] ?? 'coleccionista',
                        'email'      => $user['email'] ?? $email
                    ];

                    header('Location: ' . BASE_URL . '/index.php');
                    exit;
                }
            }

        } catch (PDOException $e) {
            $loginError = "Database error: " . $e->getMessage();
        }
    }
}

// IMPORTANTE: incluir header DESPUÉS de procesar el POST (para no romper header())
include __DIR__ . '/../includes/header.php';
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
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </label>
    <label>Contraseña:
        <input type="password" name="password" required>
    </label>
    <button type="submit">Entrar</button>
</form>

<p class="text-center">
    Don't have an account? <a href="<?= BASE_URL ?>/pages/register.php">Register here</a>
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
