<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Solo admins
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$tasting_id = (int)($_POST['tasting_id'] ?? 0);
if ($tasting_id <= 0) {
    header('Location: admin_tastings.php');
    exit;
}

// Helpers
function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
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

try {
    $db->beginTransaction();

    // 1) tasting_wines (si existe)
    if (tableExists($db, 'tasting_wines')) {
        $c = getColumns($db, 'tasting_wines');
        $fk = pickColumn($c, ['tasting_id', 'id_tasting', 'id_cata'], null);
        if ($fk) {
            $stmt = $db->prepare("DELETE FROM tasting_wines WHERE `$fk` = ?");
            $stmt->execute([$tasting_id]);
        }
    }

    // 2) tasting_signups (si existe)
    if (tableExists($db, 'tasting_signups')) {
        $c = getColumns($db, 'tasting_signups');
        $fk = pickColumn($c, ['tasting_id', 'id_tasting', 'id_cata'], null);
        if ($fk) {
            $stmt = $db->prepare("DELETE FROM tasting_signups WHERE `$fk` = ?");
            $stmt->execute([$tasting_id]);
        }
    }

    // 3) tastings
    if (!tableExists($db, 'tastings')) {
        throw new Exception('tastings table not found');
    }
    $c = getColumns($db, 'tastings');
    $pk = pickColumn($c, ['id_tasting', 'id_cata', 'id'], $c[0] ?? 'id');

    $stmt = $db->prepare("DELETE FROM tastings WHERE `$pk` = ?");
    $stmt->execute([$tasting_id]);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    // Si quieres, puedes loguear $e->getMessage()
}

header('Location: admin_tastings.php');
exit;
