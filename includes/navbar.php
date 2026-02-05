<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
?>
<nav class="navbar">
    <a href="<?= BASE_URL ?>/index.php">Home</a>
    <a href="<?= BASE_URL ?>/pages/wines.php">Wines</a>
    <a href="<?= BASE_URL ?>/pages/tastings.php">Tastings</a>

    <?php if (isLoggedIn()):
        $u = currentUser();
        $db = getDB();

        // Refresh certificado from DB if missing/stale
        $uid = (int)($u['id_usuario'] ?? $u['id'] ?? ($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0));
        if ($uid > 0) {
            $stmt = $db->prepare("SELECT rol, certificado FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $u['rol'] = $row['rol'] ?? ($u['rol'] ?? '');
                $u['certificado'] = (int)($row['certificado'] ?? 0);
                $_SESSION['usuario']['rol'] = $u['rol'];
                $_SESSION['usuario']['certificado'] = $u['certificado'];
            }
        }

        $rol = $u['rol'] ?? '';
        $isCertifiedSommelier = ($rol === 'sommelier' && !empty($u['certificado']));
    ?>

        <?php if ($rol === 'admin'): ?>
            <a href="<?= BASE_URL ?>/pages/admin_panel.php">Admin panel</a>
            <a href="<?= BASE_URL ?>/pages/create_tasting.php">Create Tasting</a>
        <?php endif; ?>

        <?php if ($rol === 'sommelier'): ?>
            <a href="<?= BASE_URL ?>/pages/sommelier_docs.php">My certification</a>
        <?php endif; ?>

        <?php if ($isCertifiedSommelier): ?>
            <a href="<?= BASE_URL ?>/pages/create_tasting.php">Create Tasting</a>
        <?php endif; ?>

        <?php if ($rol === 'coleccionista'): ?>
            <a href="<?= BASE_URL ?>/pages/my_purchases.php">My purchases</a>
        <?php endif; ?>
<a href="<?= BASE_URL ?>/pages/profile.php">My profile</a>
<a class="nav-logout" href="<?= BASE_URL ?>/pages/logout.php">Logout</a>

<span class="nav-greeting">
  Hello, <?= htmlspecialchars(explode(' ', (string)($u['nombre'] ?? 'User'))[0]) ?>
</span>


    <?php else: ?>
        <a href="<?= BASE_URL ?>/pages/login.php">Login</a>
        <a href="<?= BASE_URL ?>/pages/register.php">Register</a>
    <?php endif; ?>
</nav>
