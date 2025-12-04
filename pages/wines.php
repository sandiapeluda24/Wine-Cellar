<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Recuperar usuario si quieres mostrarlo
$usuario = $_SESSION['username'] ?? 'usuario';
$tipoUsuario = $_SESSION['tipoUsuario'] ?? null;

include __DIR__ . '/../includes/header.php';
?>

<h1>Lista de vinos</h1>

<p>Aquí mostraremos los vinos desde la base de datos más adelante.</p>

<p>
    Hola, <?= htmlspecialchars($usuario) ?>.
    <?php if ($tipoUsuario === 'admin'): ?>
        <a href="<?= BASE_URL ?>/pages/admin_panel.php">Ir al panel de administración</a>
    <?php endif; ?>
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
