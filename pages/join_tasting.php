<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();
$u = currentUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/tastings.php');
    exit;
}

$tasting_id = (int)($_POST['tasting_id'] ?? 0);
if ($tasting_id <= 0) {
    header('Location: ' . BASE_URL . '/pages/tastings.php');
    exit;
}

// Decide rol de inscripción según el usuario
// (Ajusta si tu rol del coleccionista se llama distinto)
$isSommelier = (($u['rol'] ?? '') === 'sommelier') && !empty($u['certificado']);
$role = $isSommelier ? 'sommelier' : 'collector';

try {
    $db->beginTransaction();

    // Obtener tasting
    $stmt = $db->prepare("SELECT tasting_id, id_sommelier, max_participantes, estado, tasting_date
                          FROM tastings
                          WHERE tasting_id = ?");
    $stmt->execute([$tasting_id]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$t) {
        $db->rollBack();
        die('Tasting not found');
    }

    // (Opcional) bloquear si está finalizado/cancelado
    $estado = $t['estado'] ?? 'programada';
    if (in_array($estado, ['finalizada', 'cancelada'], true)) {
        $db->rollBack();
        header('Location: ' . BASE_URL . "/pages/tasting_detail.php?id=" . $tasting_id);
        exit;
    }

    // Evitar doble inscripción (si ya está confirmed o waitlist)
    $stmt = $db->prepare("SELECT signup_id, status
                          FROM tasting_signups
                          WHERE tasting_id = ? AND user_id = ? AND status <> 'cancelled'
                          LIMIT 1");
    $stmt->execute([$tasting_id, $u['id_usuario']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $db->commit();
        header('Location: ' . BASE_URL . "/pages/tasting_detail.php?id=" . $tasting_id);
        exit;
    }

    // Cálculos de cupos (host cuenta como 1 sommelier “base”)
    $host_id = (int)($t['id_sommelier'] ?? 0);
    $hostCounts = $host_id > 0 ? 1 : 0;

    // Sommeliers confirmados extra (sin contar al host por si apareciera)
    $stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups
                          WHERE tasting_id = ?
                            AND role = 'sommelier'
                            AND status = 'confirmed'
                            AND (? = 0 OR user_id <> ?)");
    $stmt->execute([$tasting_id, $host_id, $host_id]);
    $extraSommeliers = (int)$stmt->fetchColumn();

    $totalSommeliers = $hostCounts + $extraSommeliers;

    // Coleccionistas confirmados actuales
    $stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups
                          WHERE tasting_id = ?
                            AND role = 'collector'
                            AND status = 'confirmed'");
    $stmt->execute([$tasting_id]);
    $confirmedCollectors = (int)$stmt->fetchColumn();

    $maxCollectors = (int)($t['max_participantes'] ?? 0);
    if ($maxCollectors <= 0) $maxCollectors = 20;

    // Cupos disponibles según ratio
    $collectorSlots = min($maxCollectors, $totalSommeliers * 20);

    if ($role === 'sommelier') {
        // Insertar sommelier confirmado
        $ins = $db->prepare("INSERT INTO tasting_signups (tasting_id, user_id, role, status)
                             VALUES (?, ?, 'sommelier', 'confirmed')");
        $ins->execute([$tasting_id, $u['id_usuario']]);

        // Recalcular slots y promocionar waitlist si ahora hay más cupo
        $totalSommeliers += 1;
        $collectorSlots = min($maxCollectors, $totalSommeliers * 20);

        $stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups
                              WHERE tasting_id = ?
                                AND role = 'collector'
                                AND status = 'confirmed'");
        $stmt->execute([$tasting_id]);
        $confirmedCollectors = (int)$stmt->fetchColumn();

        $toPromote = $collectorSlots - $confirmedCollectors;
        if ($toPromote > 0) {
            // Promociona los más antiguos de la waitlist
            $upd = $db->prepare("
                UPDATE tasting_signups
                SET status = 'confirmed'
                WHERE tasting_id = ?
                  AND role = 'collector'
                  AND status = 'waitlist'
                ORDER BY created_at ASC
                LIMIT $toPromote
            ");
            $upd->execute([$tasting_id]);
        }
    } else {
        // Collector: confirmed o waitlist según cupos
        $status = ($collectorSlots > 0 && $confirmedCollectors < $collectorSlots) ? 'confirmed' : 'waitlist';

        $ins = $db->prepare("INSERT INTO tasting_signups (tasting_id, user_id, role, status)
                             VALUES (?, ?, 'collector', ?)");
        $ins->execute([$tasting_id, $u['id_usuario'], $status]);
    }

    $db->commit();
    header('Location: ' . BASE_URL . "/pages/tasting_detail.php?id=" . $tasting_id);
    exit;

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Error: " . $e->getMessage());
}
