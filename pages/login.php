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
                // 2) Password: supports hashed or plaintext (legacy)
                $dbPass = (string)($user['password'] ?? '');

                $looksHashed =
                    (strncmp($dbPass, '$2y$', 4) === 0) ||
                    (strncmp($dbPass, '$2a$', 4) === 0) ||
                    (strncmp($dbPass, '$argon2', 6) === 0);

                $passwordOk = $looksHashed
                    ? password_verify($password, $dbPass)
                    : hash_equals($dbPass, (string)$password);

                // Optional: if password was plaintext and login is correct, migrate to hash automatically
                if ($passwordOk && !$looksHashed && !empty($user['id_usuario'])) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $db->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
                    $upd->execute([$newHash, (int)$user['id_usuario']]);
                    $user['password'] = $newHash;
                }
                if (!$passwordOk) {
                    $loginError = "Invalid email or password.";
                }
                // 3) is_active opcional (si no existe, asumimos activo)
                elseif (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                    $loginError = "Your account has been deactivated by the administrators of the web. Please contact this email for further explanation and the possible reactivation of your account.";
                } else {
                    // Login correcto
                    $_SESSION['usuario'] = [
                        'id'          => $user['id_usuario'] ?? null,
                        'id_usuario'  => $user['id_usuario'] ?? null,
                        'nombre'      => $user['nombre'] ?? '',
                        'rol'         => $user['rol'] ?? '',
                        'certificado' => (int)($user['certificado'] ?? 0),
                        'email'       => $user['email'] ?? $email
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
    <label>Password:
        <input type="password" name="password" required>
    </label>
    <button type="submit">Login</button>
</form>

<p class="text-center">
    Don't have an account? <a href="<?= BASE_URL ?>/pages/register.php">Register here</a>
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
