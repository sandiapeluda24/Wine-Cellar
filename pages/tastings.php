<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();
$u = currentUser();
$db = getDB();

// Obtener todas las catas programadas
$stmt = $db->query("
    SELECT t.*, u.nombre as sommelier_nombre,
           (SELECT COUNT(*) FROM tasting_participants WHERE id_cata = t.id_cata) as inscritos
    FROM tastings t
    INNER JOIN usuarios u ON t.id_sommelier = u.id_usuario
    WHERE t.estado IN ('programada', 'en_curso')
    ORDER BY t.fecha_cata ASC
");
$catas = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<h1>Wine Tastings</h1>

<?php if (empty($catas)): ?>
    <p>No tastings scheduled at the moment.</p>
<?php else: ?>
    <div class="catas-grid">
        <?php foreach ($catas as $cata): 
            $plazasDisponibles = $cata['max_participantes'] - $cata['inscritos'];
            $fechaCata = new DateTime($cata['fecha_cata']);
        ?>
            <div class="cata-card">
                <h3><?= htmlspecialchars($cata['titulo']) ?></h3>
                
                <p><strong>📅 Date:</strong> <?= $fechaCata->format('d/m/Y H:i') ?></p>
                <p><strong>📍 Location:</strong> <?= htmlspecialchars($cata['ubicacion']) ?></p>
                <p><strong>👨‍🍳 Sommelier:</strong> <?= htmlspecialchars($cata['sommelier_nombre']) ?></p>
                <p><strong>👥 Spots:</strong> <?= $plazasDisponibles ?> / <?= $cata['max_participantes'] ?> available</p>
                
                <?php if ($cata['descripcion']): ?>
                    <p class="descripcion"><?= nl2br(htmlspecialchars($cata['descripcion'])) ?></p>
                <?php endif; ?>
                
                <p>
                    <a href="tasting_detail.php?id=<?= $cata['id_cata'] ?>" class="btn">View details</a>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($u['rol'] === 'sommelier' && $u['certificado']): ?>
    <p style="margin-top: 30px;">
        <a href="create_tasting.php" class="btn btn-primary">Create new tasting</a>
    </p>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>