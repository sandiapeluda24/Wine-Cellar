<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';

requireLogin();

$current = currentUser();
$userId = $current['id'] ?? null;

if (!$userId) {
    echo "<p>Unable to load profile.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$db = getDB();

// If the user submits a new description (only sommeliers)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current['rol'] === 'sommelier') {
    $newDesc = trim($_POST['sommelier_description'] ?? '');

    $stmtUpdate = $db->prepare("
        UPDATE usuarios
        SET sommelier_description = ?
        WHERE id_usuario = ?
    ");
    $stmtUpdate->execute([$newDesc, $userId]);

    // Optionally refresh current user (session) – not strictly needed here
    // You could also set a success message
}

$stmt = $db->prepare("
    SELECT nombre, email, rol, certificado, sommelier_description, created_at
    FROM usuarios
    WHERE id_usuario = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<p>User not found.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$roleRaw = $user['rol'];
$roleLabel = $roleRaw;
if ($roleRaw === 'admin') {
    $roleLabel = 'Admin';
} elseif ($roleRaw === 'coleccionista') {
    $roleLabel = 'Collector';
} elseif ($roleRaw === 'sommelier') {
    $roleLabel = 'Sommelier';
}

$isCertified = ($user['certificado'] ?? 0) ? 'Yes' : 'No';
?>

<h2>My profile</h2>

<div class="profile-card">
    <p><strong>Name:</strong> <?= htmlspecialchars($user['nombre']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
    <p><strong>Role:</strong> <?= htmlspecialchars($roleLabel) ?></p>

    <?php if ($roleRaw === 'sommelier'): ?>
        <p><strong>Certified sommelier:</strong> <?= $isCertified ?></p>

        <form method="post">
            <label>Sommelier description<br>
                <textarea name="sommelier_description" rows="5" cols="50"
                    placeholder="Describe your skills and background as a sommelier..."><?= htmlspecialchars($user['sommelier_description'] ?? '') ?></textarea>
            </label>
            <br><br>
            <button type="submit">Update description</button>
        </form>
    <?php endif; ?>

    <?php if (!empty($user['created_at'])): ?>
        <p><strong>Member since:</strong> <?= htmlspecialchars($user['created_at']) ?></p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
