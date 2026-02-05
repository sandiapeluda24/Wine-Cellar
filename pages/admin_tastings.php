<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

// Solo admins
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Asegura $db
if (!isset($db) || !$db) {
    $db = getDB();
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

if (!tableExists($db, $tastingsTable)) : ?>
    <section class="admin-hero">
      <div class="admin-shell">
        <div class="admin-head">
          <div class="admin-kicker">Administration</div>
          <h1 class="admin-title">Manage tastings</h1>
          <p class="admin-subtitle">Table <b>tastings</b> not found. If your table has another name, tell me and I adapt it.</p>

          <div class="admin-actions" style="margin-top: 14px;">
            <a class="btn btn-sm btn-ghost" href="admin_panel.php">← Back to admin panel</a>
          </div>
        </div>

        <div class="notice notice-error">Table <b>tastings</b> not found.</div>
      </div>
    </section>
<?php
    include __DIR__ . '/../includes/footer.php';
    exit;
endif;

$cols = getColumns($db, $tastingsTable);

// intenta detectar PK y campos típicos
$idCol    = pickColumn($cols, ['id_tasting', 'id_cata', 'id'], $cols[0] ?? 'id');
$titleCol = pickColumn($cols, ['title', 'titulo', 'nombre', 'name'], null);
$dateCol  = pickColumn($cols, ['tasting_date', 'fecha', 'date', 'scheduled_at', 'created_at'], null);

$orderBy = $dateCol ?: $idCol;

$stmt = $db->query("SELECT * FROM `$tastingsTable` ORDER BY `$orderBy` DESC");
$tastings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="admin-hero">
  <div class="admin-shell">
    <div class="admin-head">
      <div class="admin-kicker">Administration</div>
      <h1 class="admin-title">Manage tastings</h1>
      <p class="admin-subtitle">View tastings and delete those no longer needed.</p>

      <div class="admin-actions" style="margin-top: 14px;">
        <a class="btn btn-sm btn-ghost" href="admin_panel.php">← Back to admin panel</a>
      </div>
    </div>

    <?php if (empty($tastings)): ?>
      <div class="notice notice-error">No tastings found.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tastings-table">
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
            <?php
              $id = (int)($t[$idCol] ?? 0);
              $dateRaw = $dateCol ? (string)($t[$dateCol] ?? '') : '';
              $dateNice = $dateRaw ? date('d/m/Y H:i', strtotime($dateRaw)) : '';
            ?>
            <tr>
              <td class="cell-mono"><?= $id ?></td>

              <?php if ($titleCol): ?>
                <td><strong><?= htmlspecialchars((string)($t[$titleCol] ?? '')) ?></strong></td>
              <?php endif; ?>

              <?php if ($dateCol): ?>
                <td class="cell-mono"><?= htmlspecialchars($dateNice ?: $dateRaw) ?></td>
              <?php endif; ?>

              <td>
                <div class="table-actions">
                  <a class="btn btn-secondary btn-sm" href="tasting_detail.php?id=<?= $id ?>">View</a>

                  <form method="post"
                        action="delete_tasting.php"
                        class="inline-form"
                        onsubmit="return confirm('Delete this tasting? This cannot be undone.');">
                    <input type="hidden" name="tasting_id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
