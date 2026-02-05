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

<div class="auth-hero">
  <div class="auth-card">
    <div class="auth-kicker">Wine Cellar</div>
    <h2 class="auth-title">Welcome back</h2>
    <p class="auth-subtitle">Sign in to manage your collection, tastings and purchases.</p>

    <?php foreach ($errores as $e): ?>
      <div class="alert alert-error" role="alert"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if (!empty($loginError)): ?>
      <div class="alert alert-error" role="alert"><?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>

    <form method="post" id="formLogin" class="auth-form" novalidate>
      <div class="field">
        <label for="login_email">Email</label>
        <input id="login_email" type="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required>
      </div>

      <div class="field">
        <label for="login_password">Password</label>
        <input id="login_password" type="password" name="password"
               placeholder="Your password" required>
      </div>

      <div class="auth-actions">
        <button type="submit" class="btn btn-lg">Log in</button>
      </div>
    </form>

    <div class="auth-links">
      Don't have an account?
      <a href="<?= BASE_URL ?>/pages/register.php">Create one</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
