<?php
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$db = getDB();

$idVino = isset($_GET['id_vino']) ? (int) $_GET['id_vino'] : 0;

$stmt = $db->prepare("
    SELECT *
    FROM vinos
    WHERE id_vino = ?
");
$stmt->execute([$idVino]);
$vino = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php if (!$vino): ?>
    <h1>Wine not found</h1>
    <p><a href="<?= BASE_URL ?>/pages/wines.php">Back to wines</a></p>
<?php else: ?>
    <h1><?= htmlspecialchars($vino['nombre']) ?></h1>

    <?php if (!empty($vino['bodega'])): ?>
        <p><strong>Winery:</strong> <?= htmlspecialchars($vino['bodega']) ?></p>
    <?php endif; ?>

    <?php if (!empty($vino['precio'])): ?>
    <p><strong>Price:</strong> <?= number_format($vino['precio'], 2) ?> €</p>
<?php endif; ?>

    <p><strong>Vintage:</strong> <?= htmlspecialchars($vino['annada']) ?></p>

    <?php if (!empty($vino['tipo'])): ?>
        <p><strong>Type:</strong> <?= htmlspecialchars($vino['tipo']) ?></p>
    <?php endif; ?>

    <?php if (!empty($vino['pais'])): ?>
        <p><strong>Country:</strong> <?= htmlspecialchars($vino['pais']) ?></p>
    <?php endif; ?>

    <?php if (!empty($vino['imagen'])): ?>
    <p>
        <img src="../img/wines/<?= htmlspecialchars($vino['imagen']) ?>"
             alt="Bottle of <?= htmlspecialchars($vino['nombre']) ?>"
             style="max-width:250px; height:auto;">
    </p>
<?php endif; ?>

    <?php if (!empty($vino['ventana_optima_inicio']) || !empty($vino['ventana_optima_fin'])): ?>
        <p><strong>Optimal window:</strong>
            <?= htmlspecialchars($vino['ventana_optima_inicio']) ?>
            –
            <?= htmlspecialchars($vino['ventana_optima_fin']) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($vino['descripcion'])): ?>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($vino['descripcion'])) ?></p>
    <?php endif; ?>

    <p><a href="<?= BASE_URL ?>/pages/wines.php">Back to wines</a></p>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
