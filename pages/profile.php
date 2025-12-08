<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

// Comprobar que hay usuario loggeado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();

// ID que guardamos en la sesión al hacer login
$idUsuario = $_SESSION['usuario']['id_usuario'] ?? null;

if ($idUsuario === null) {
    echo "<h1>Unable to load profile</h1>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Cargar datos completos desde la BD
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$idUsuario]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo "<h1>Unable to load profile</h1>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<h1>My profile</h1>

<p><strong>Name:</strong> <?= htmlspecialchars($usuario['nombre']) ?></p>
<p><strong>Email:</strong> <?= htmlspecialchars($usuario['email']) ?></p>
<p><strong>Role:</strong> <?= htmlspecialchars($usuario['rol']) ?></p>
<p><strong>Member since:</strong> <?= htmlspecialchars($usuario['created_at']) ?></p>

<?php if ($usuario['rol'] === 'sommelier'): ?>
    <p><strong>Sommelier description:</strong><br>
        <?= nl2br(htmlspecialchars($usuario['sommelier_description'] ?? '')) ?>
    </p>
<?php endif; ?>

<?php if ((int)$usuario['is_active'] === 0): ?>
    <p style="color:red;"><strong>Warning:</strong> Your account is currently deactivated.</p>
<?php endif; ?>


<?php include __DIR__ . '/../includes/footer.php'; ?>
