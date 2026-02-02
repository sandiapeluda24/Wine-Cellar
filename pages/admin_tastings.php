<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

// Solo admins
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Helpers para detectar columnas existentes (evita "Unknown column")
function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}


function getColumns(PDO $db, string $table): array {
    $cols = [];
    $stmt = $db->query("DESCRIBE `$table`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

function pickColumn(array $cols, array $candidates, ?string $fallback = null): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return $fallback;
}

$tastingsTable = 'tastings';
if (!tableExists($db, $tastingsTable)) {
    echo "<h1>Manage tastings</h1>";
    echo "<p class='warning'>Table <b>tastings</b> not found. If your table has another name, tell me and I adapt it.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$cols = getColumns($db, $tastingsTable);

// intenta detectar PK y campos típicos
$idCol    = pickColumn($cols, ['id_tasting', 'id_cata', 'id'], $cols[0] ?? 'id');
$titleCol = pickColumn($cols, ['title', 'titulo', 'nombre', 'name'], null);
$dateCol  = pickColumn($cols, ['tasting_date', 'fecha', 'date', 'scheduled_at', 'created_at'], null);

$orderBy = $dateCol ?: $idCol;

$stmt = $db->query("SELECT * FROM `$tastingsTable` ORDER BY `$orderBy` DESC");
$tastings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Manage tastings</h1>
<p><a href="admin_panel.php">← Back to admin panel</a></p>

<?php if (empty($tastings)): ?>
    <p>No tastings found.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <?php if ($titleCol): ?><th>Title</th><?php endif; ?>
                <?php if ($dateCol): ?><th>Date</th><?php endif; ?>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tastings as $t): ?>
            <tr>
                <td><?= (int)($t[$idCol] ?? 0) ?></td>

                <?php if ($titleCol): ?>
                    <td><?= htmlspecialchars((string)($t[$titleCol] ?? '')) ?></td>
                <?php endif; ?>

                <?php if ($dateCol): ?>
                    <td><?= htmlspecialchars((string)($t[$dateCol] ?? '')) ?></td>
                <?php endif; ?>

                <td>
                    <a href="tasting_detail.php?id=<?= (int)($t[$idCol] ?? 0) ?>">View</a>

                    <form method="post"
                          action="delete_tasting.php"
                          style="display:inline"
                          onsubmit="return confirm('Delete this tasting? This cannot be undone.');">
                        <input type="hidden" name="tasting_id" value="<?= (int)($t[$idCol] ?? 0) ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
