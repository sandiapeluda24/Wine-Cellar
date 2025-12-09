<?php
require_once __DIR__ . '/auth.php';
?>
<nav class="navbar">
    <a href="<?= BASE_URL ?>/index.php">Home</a>
    <a href="<?= BASE_URL ?>/pages/wines.php">Wines</a>
    <a href="<?= BASE_URL ?>/pages/tastings.php">Tastings</a>

    <?php if (isLoggedIn()):
        $u = currentUser();
    ?>
        <?php if ($u['rol'] === 'admin'): ?>
    <a href="<?= BASE_URL ?>/pages/admin_panel.php">Admin panel</a>
    <a href="<?= BASE_URL ?>/pages/create_tasting.php">Create Tasting</a>
<?php endif; ?>
        
        <?php if ($u['rol'] === 'sommelier' && $u['certificado']): ?>
            <a href="<?= BASE_URL ?>/pages/create_tasting.php">Create Tasting</a>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol'] === 'coleccionista'): ?>
            <a href="<?= BASE_URL ?>/pages/my_purchases.php">My purchases</a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/pages/profile.php">My profile</a>

        <span class="nav-user">Hello, <?= htmlspecialchars(explode(' ', $u['nombre'])[0]) ?></span>
        <a href="<?= BASE_URL ?>/pages/logout.php">Logout</a>

    <?php else: ?>
        <a href="<?= BASE_URL ?>/pages/login.php">Login</a>
        <a href="<?= BASE_URL ?>/pages/register.php">Register</a>
    <?php endif; ?>
</nav>