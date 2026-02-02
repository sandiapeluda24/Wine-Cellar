<?php
// Panel de administración sencillo usando la sesión actual
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

// Solo admins
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    // si no es admin, fuera
    header('Location: login.php');
    exit;
}

// Datos del usuario logueado
$usuarioNombre = $_SESSION['usuario']['nombre'] ?? 'admin';
?>

<h1>Panel de administración</h1>
<p>Hola, <?= htmlspecialchars($usuarioNombre) ?> (admin)</p>

<nav>
    <ul>
        <li><a href="admin_usuarios.php">Manage users</a></li>
        <li><a href="admin_usuario_new.php">New user</a></li>
        <li><a href="admin_vinos.php">Manage wines</a></li>
        <li><a href="admin_vino_form.php">New wine</a></li>
        <li><a href="admin_compras.php">Purchase history</a></li>
        <li><a href="admin_tastings.php">Manage tastings</a></li>
        <li><a href="admin_sommelier_certifications.php">Sommelier certifications</a></li>
        <li><a href="admin_reports.php">Reports</a></li>



    </ul>
</nav>

<?php include __DIR__ . '/../includes/footer.php'; ?>
