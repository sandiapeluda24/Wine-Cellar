<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireRole('admin');

$db = getDB();

$message = null;
$error = null;

// Id del usuario logueado (ajusta según cómo guardes la sesión)
$currentUserId = $_SESSION['user']['id_usuario'] ?? null;

// Gestionar activar / desactivar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'], $_POST['action'])) {
    $userId = (int) $_POST['id_usuario'];
    $action = $_POST['action'];

    if ($currentUserId !== null && $userId === $currentUserId) {
        $error = "No puedes desactivar tu propia cuenta.";
    } else {
        if ($action === 'deactivate') {
            $stmt = $db->prepare("UPDATE usuarios SET is_active = 0 WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            $message = "Usuario desactivado correctamente.";
        } elseif ($action === 'activate') {
            $stmt = $db->prepare("UPDATE usuarios SET is_active = 1 WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            $message = "Usuario reactivado correctamente.";
        }
    }
}

// Obtener todos los usuarios
$stmt = $db->query("SELECT id_usuario, nombre, email, rol, created_at, is_active FROM usuarios ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<h1>Gestionar usuarios</h1>

<?php if ($message): ?>
    <p style="color: green;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Email</th>
        <th>Rol</th>
        <th>Miembro desde</th>
        <th>Estado</th>
        <th>Acciones</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['id_usuario']) ?></td>
            <td><?= htmlspecialchars($user['nombre']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['rol']) ?></td>
            <td><?= htmlspecialchars($user['created_at']) ?></td>
            <td><?= $user['is_active'] ? 'Activo' : 'Desactivado' ?></td>
            <td>
                <?php if ($currentUserId !== null && $user['id_usuario'] === $currentUserId): ?>
                    (Tú)
                <?php else: ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="id_usuario" value="<?= $user['id_usuario'] ?>">
                        <?php if ($user['is_active']): ?>
                            <button type="submit" name="action" value="deactivate">
                                Desactivar
                            </button>
                        <?php else: ?>
                            <button type="submit" name="action" value="activate">
                                Reactivar
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p><a href="admin_panel.php">Volver al panel de administración</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
