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

<section class="admin-hero">
    <div class="admin-shell">
        <div class="admin-head">
            <div class="admin-kicker">Administración</div>
            <h1 class="admin-title">Usuarios</h1>
            <p class="admin-subtitle">Edita roles y controla el acceso (activar/desactivar cuentas).</p>

            <div class="admin-actions" style="margin-top: 14px;">
                <a class="btn btn-sm btn-ghost" href="<?= BASE_URL ?>/pages/admin_panel.php">← Volver al panel</a>
            </div>
        </div>

    <?php if ($message): ?>
        <div class="notice notice-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice notice-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

        <div class="table-wrap">
            <table class="users-table">
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
                        <td class="cell-mono"><?= htmlspecialchars($user['email']) ?></td>
                        <td><span class="badge badge-soft"><?= htmlspecialchars($user['rol']) ?></span></td>
                        <td class="cell-mono"><?= htmlspecialchars($user['created_at']) ?></td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge badge-status--active">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-status--inactive">Desactivado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-secondary btn-sm"
                                   href="<?= BASE_URL ?>/pages/admin_usuario_form.php?id=<?= (int)$user['id_usuario'] ?>">
                                    Editar
                                </a>

                                <?php if ($currentUserId !== null && (int)$user['id_usuario'] === (int)$currentUserId): ?>
                                    <span class="badge badge-status--self">Tú</span>
                                <?php else: ?>
                                    <form method="post" class="inline-form"
                                          onsubmit="return confirm('¿Seguro que quieres cambiar el estado de este usuario?');">
                                        <input type="hidden" name="id_usuario" value="<?= (int)$user['id_usuario'] ?>">

                                        <?php if ($user['is_active']): ?>
                                            <button class="btn btn-danger btn-sm" type="submit" name="action" value="deactivate">
                                                Desactivar
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-success btn-sm" type="submit" name="action" value="activate">
                                                Reactivar
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

            </table>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
