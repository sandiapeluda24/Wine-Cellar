<?php
require_once __DIR__ . '/auth.php';
?>
<nav class="navbar">
    <a href="<?= BASE_URL ?>/index.php">Inicio</a>
    <a href="<?= BASE_URL ?>/pages/vinos.php">Vinos</a>

    <?php if (isLoggedIn()): 
        $u = currentUser();
    ?>
        <?php if ($u['rol'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>/pages/panel_admin.php">Panel admin</a>
        <?php endif; ?>

        <span class="nav-user">Hola, <?= htmlspecialchars($u['nombre']) ?></span>
        <a href="<?= BASE_URL ?>/pages/logout.php">Cerrar sesión</a>
    <?php else: ?>
        <a href="<?= BASE_URL ?>/pages/login.php">Login</a>
    <?php endif; ?>
</nav>
