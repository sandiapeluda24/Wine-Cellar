<?php
// Igual que en el resto de páginas de admin
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Solo admins
requireRole('admin');

// Si en algún sitio guardas el nombre del usuario en sesión, puedes recuperarlo aquí.
// Ajusta esta línea a cómo lo tengas realmente:
$usuario = $_SESSION['username'] ?? 'admin';

include __DIR__ . '/../includes/header.php';
?>

<h1>Panel de administración</h1>
<p>Hola, <?= htmlspecialchars($usuario) ?> (admin)</p>

<nav>
    <ul>
        <li><a href="<?= BASE_URL ?>/pages/admin_usuarios.php">Gestionar usuarios</a></li>
        <li><a href="<?= BASE_URL ?>/pages/admin_usuario_form.php">Nuevo usuario</a></li>
        <li><a href="<?= BASE_URL ?>/pages/admin_vinos.php">Gestionar vinos</a></li>
        <li><a href="<?= BASE_URL ?>/pages/admin_vino_form.php">Nuevo vino</a></li>
    </ul>
</nav>

<?php include __DIR__ . '/../includes/footer.php'; ?>
