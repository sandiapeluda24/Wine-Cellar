<?php
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errores[] = "Email y contraseña son obligatorios.";
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password'])) {
            $_SESSION['usuario'] = [
                'id'   => $usuario['id_usuario'],
                'nombre' => $usuario['nombre'],
                'rol'  => $usuario['rol']
            ];
            // Redirigir según rol si quieres
            header("Location: ../index.php");
            exit;
        } else {
            $errores[] = "Credenciales incorrectas.";
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
