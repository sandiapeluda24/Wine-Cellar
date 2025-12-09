<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();
$u = currentUser();
$db = getDB();

$idCata = (int)($_GET['id'] ?? 0);

// Obtener datos de la cata
$stmt = $db->prepare("
    SELECT t.*, u.nombre as sommelier_nombre
    FROM tastings t
    INNER JOIN usuarios u ON t.id_sommelier = u.id_usuario
    WHERE t.id_cata = ?
");
$stmt->execute([$idCata]);
$cata = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cata) {
    die("Tasting not found.");
}

// Verificar si el usuario ya está inscrito
$stmt = $db->prepare("SELECT * FROM tasting_participants WHERE id_cata = ? AND id_usuario = ?");
$stmt->execute([$idCata, $u['id_usuario']]);
$yaInscrito = $stmt->fetch(PDO::FETCH_ASSOC);

// Contar inscritos
$stmt = $db->prepare("SELECT COUNT(*) FROM tasting_participants WHERE id_cata = ?");
$stmt->execute([$idCata]);
$inscritos = $stmt->fetchColumn();

// Obtener vinos de la cata
$stmt = $db->prepare("
    SELECT v.*, tw.orden_degustacion, tw.notas_cata
    FROM tasting_wines tw
    INNER JOIN vinos v ON tw.id_vino = v.id_vino
    WHERE tw.id_cata = ?
    ORDER BY tw.orden_degustacion
");
$stmt->execute([$idCata]);
$vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar inscripción
$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscribir'])) {
    if ($u['rol'] !== 'coleccionista') {
        $error = "Only collectors can register for tastings.";
    } elseif ($yaInscrito) {
        $error = "You are already registered for this tasting.";
    } elseif ($inscritos >= $cata['max_participantes']) {
        $error = "This tasting is full.";
    } else {
        $stmt = $db->prepare("INSERT INTO tasting_participants (id_cata, id_usuario) VALUES (?, ?)");
        $stmt->execute([$idCata, $u['id_usuario']]);
        $mensaje = "Successfully registered!";
        $yaInscrito = true;
        $inscritos++;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<h1><?= htmlspecialchars($cata['titulo']) ?></h1>

<?php if ($mensaje): ?>
    <p class="success"><?= htmlspecialchars($mensaje) ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="cata-info">
    <p><strong>📅 Date:</strong> <?= date('d/m/Y H:i', strtotime($cata['fecha_cata'])) ?></p>
    <p><strong>📍 Location:</strong> <?= htmlspecialchars($cata['ubicacion']) ?></p>
    <p><strong>👨‍🍳 Sommelier:</strong> <?= htmlspecialchars($cata['sommelier_nombre']) ?></p>
    <p><strong>👥 Registered:</strong> <?= $inscritos ?> / <?= $cata['max_participantes'] ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($cata['estado']) ?></p>
    
    <?php if ($cata['descripcion']): ?>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($cata['descripcion'])) ?></p>
    <?php endif; ?>
</div>

<?php if (!empty($vinos)): ?>
    <h2>Wines to taste</h2>
    <div class="vinos-cata">
        <?php foreach ($vinos as $vino): ?>
            <div class="vino-item">
                <h4><?= $vino['orden_degustacion'] ?>. <?= htmlspecialchars($vino['nombre']) ?></h4>
                <p><?= htmlspecialchars($vino['tipo']) ?> - <?= htmlspecialchars($vino['annada']) ?></p>
                <?php if ($vino['notas_cata']): ?>
                    <p><em><?= htmlspecialchars($vino['notas_cata']) ?></em></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($u['rol'] === 'coleccionista' && !$yaInscrito && $inscritos < $cata['max_participantes']): ?>
    <form method="post" style="margin-top: 20px;">
        <button type="submit" name="inscribir" class="btn btn-primary">Register for this tasting</button>
    </form>
<?php elseif ($yaInscrito): ?>
    <p style="color: green; font-weight: bold;">✓ You are registered for this tasting</p>
<?php elseif ($inscritos >= $cata['max_participantes']): ?>
    <p style="color: red; font-weight: bold;">This tasting is full</p>
<?php endif; ?>

<p><a href="tastings.php">← Back to tastings list</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>