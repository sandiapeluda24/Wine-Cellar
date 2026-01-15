<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$u  = currentUser();
$db = getDB();

$tastingId = (int)($_GET['id'] ?? 0);
if ($tastingId <= 0) {
    header('Location: tastings.php');
    exit;
}

// ---------------- Helpers ----------------
function table_columns(PDO $db, string $table): array {
    try {
        $stmt = $db->query("DESCRIBE `$table`");
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[] = strtolower($row['Field']);
        }
        return $cols;
    } catch (Throwable $e) {
        return [];
    }
}

function pick_col(array $cols, array $candidates, ?string $fallback = null): ?string {
    foreach ($candidates as $c) {
        $c = strtolower($c);
        if (in_array($c, $cols, true)) return $c;
    }
    return $fallback ? strtolower($fallback) : null;
}

// --------------- Schema detection ---------------
$tastingsCols = table_columns($db, 'tastings');
if (!$tastingsCols) {
    die('Missing table: tastings');
}

$signupCols = table_columns($db, 'tasting_signups');
if (!$signupCols) {
    die('Missing table: tasting_signups');
}

$usersCols = table_columns($db, 'usuarios');
if (!$usersCols) {
    die('Missing table: usuarios');
}

$tastingPk = pick_col($tastingsCols, ['id_cata', 'tasting_id', 'id_tasting', 'id'], 'id_cata');
$dateCol   = pick_col($tastingsCols, ['tasting_date', 'fecha_cata', 'fecha'], null);
$timeCol   = pick_col($tastingsCols, ['hora_cata', 'hora'], null);
$locCol    = pick_col($tastingsCols, ['ubicacion', 'lugar', 'location'], 'ubicacion');
$maxCol    = pick_col($tastingsCols, ['max_participantes', 'capacidad', 'aforo', 'max'], 'max_participantes');
$estadoCol = pick_col($tastingsCols, ['estado', 'status'], null);

$suTastingCol = pick_col($signupCols, ['id_cata', 'tasting_id'], 'id_cata');
$suUserCol    = pick_col($signupCols, ['id_usuario', 'user_id'], 'id_usuario');
$suRoleCol    = pick_col($signupCols, ['rol', 'role'], null);
$suStatusCol  = pick_col($signupCols, ['estado', 'status'], null);
$suPkCol      = pick_col($signupCols, ['id_signup', 'signup_id', 'id', 'id_inscripcion', 'id_registro'], null);
$suCreatedCol = pick_col($signupCols, ['created_at', 'fecha_creacion', 'fecha_registro', 'created'], null);

if (!$suRoleCol || !$suStatusCol) {
    die('tasting_signups must have role/rol and status/estado columns.');
}

$uPk    = pick_col($usersCols, ['id_usuario', 'user_id', 'id'], 'id_usuario');
$uName  = pick_col($usersCols, ['nombre', 'name'], 'nombre');
$uEmail = pick_col($usersCols, ['email', 'correo', 'correo_electronico'], 'email');

$uid = (int)($u['id_usuario'] ?? $u['id'] ?? 0);
$userRole = $u['rol'] ?? '';

// for UPDATE ... ORDER BY ... LIMIT
$orderCol = $suCreatedCol ?: ($suPkCol ?: $suUserCol);

// tasting_wines FK column (needed for admin delete)
$twColsForDelete = table_columns($db, 'tasting_wines');
$twTastingColForDelete = $twColsForDelete ? pick_col($twColsForDelete, ['id_cata', 'tasting_id'], $suTastingCol) : null;

// --------------- Load tasting ---------------
$stmt = $db->prepare("SELECT * FROM tastings WHERE `$tastingPk` = ? LIMIT 1");
$stmt->execute([$tastingId]);
$cata = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cata) {
    die('Tasting not found.');
}

$maxParticipants = (int)($cata[$maxCol] ?? 20);
if ($maxParticipants <= 0) $maxParticipants = 20;

// --------------- Assigned sommelier (Option B) ---------------
$stmt = $db->prepare("
    SELECT s.`$suUserCol` AS sommelier_id,
           s.`$suStatusCol` AS sommelier_status,
           u.`$uName` AS sommelier_nombre,
           u.`$uEmail` AS sommelier_email
    FROM tasting_signups s
    JOIN usuarios u ON u.`$uPk` = s.`$suUserCol`
    WHERE s.`$suTastingCol` = ?
      AND s.`$suRoleCol` = 'sommelier'
    ORDER BY s.`$orderCol` ASC
    LIMIT 1
");
$stmt->execute([$tastingId]);
$assignedSommelier = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

$sommelierStatus = strtolower((string)($assignedSommelier['sommelier_status'] ?? ''));
// Support both english + spanish status values
$sommelierConfirmed = in_array($sommelierStatus, ['confirmed', 'accepted', 'confirmado', 'aceptado'], true);
$sommelierPending   = in_array($sommelierStatus, ['pending', 'pendiente'], true);

// --------------- My collector signup ---------------
$stmt = $db->prepare("
    SELECT s.`$suStatusCol` AS status
    FROM tasting_signups s
    WHERE s.`$suTastingCol` = ?
      AND s.`$suUserCol` = ?
      AND s.`$suRoleCol` = 'coleccionista'
    LIMIT 1
");
$stmt->execute([$tastingId, $uid]);
$myCollector = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

// --------------- Flash messages ---------------
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// --------------- Handle actions (POST) ---------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // -------- Admin: delete tasting --------
        if ($action === 'admin_delete_tasting') {
            if (($userRole ?? '') !== 'admin') {
                throw new RuntimeException('Only administrators can delete tastings.');
            }

            $db->beginTransaction();

            // Delete wines link table (if exists)
            if ($twTastingColForDelete) {
                $del = $db->prepare("DELETE FROM tasting_wines WHERE `$twTastingColForDelete` = ?");
                $del->execute([$tastingId]);
            }

            // Delete signups
            if (table_columns($db, 'tasting_signups')) {
                $del = $db->prepare("DELETE FROM tasting_signups WHERE `$suTastingCol` = ?");
                $del->execute([$tastingId]);
            }

            // Delete the tasting itself
            $del = $db->prepare("DELETE FROM tastings WHERE `$tastingPk` = ?");
            $del->execute([$tastingId]);

            $db->commit();

            $_SESSION['flash_success'] = '🗑️ Tasting deleted successfully.';
            header('Location: tastings.php');
            exit;
        }

        if ($action === 'sommelier_accept' || $action === 'sommelier_decline') {
            if (!$assignedSommelier || (int)$assignedSommelier['sommelier_id'] !== $uid) {
                throw new RuntimeException('You are not the assigned sommelier for this tasting.');
            }

            $newStatus = ($action === 'sommelier_accept') ? 'confirmed' : 'declined';

            $db->beginTransaction();

            $up = $db->prepare("UPDATE tasting_signups SET `$suStatusCol`=? WHERE `$suTastingCol`=? AND `$suUserCol`=? AND `$suRoleCol`='sommelier'");
            $up->execute([$newStatus, $tastingId, $uid]);

            // If accepted, promote waitlist collectors up to max capacity
            if ($newStatus === 'confirmed') {
                $cnt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$suTastingCol`=? AND `$suRoleCol`='coleccionista' AND `$suStatusCol`='confirmed'");
                $cnt->execute([$tastingId]);
                $confirmedNow = (int)$cnt->fetchColumn();

                $toPromote = max(0, $maxParticipants - $confirmedNow);
                if ($toPromote > 0) {
                    $toPromote = (int)$toPromote;
                    $orderSql = $orderCol ? " ORDER BY `$orderCol` ASC" : '';
                    $db->exec(
                        "UPDATE tasting_signups\n".
                        "SET `$suStatusCol`='confirmed'\n".
                        "WHERE `$suTastingCol`=".(int)$tastingId."\n".
                        "  AND `$suRoleCol`='coleccionista'\n".
                        "  AND `$suStatusCol`='waitlist'\n".
                        $orderSql."\n".
                        "LIMIT $toPromote"
                    );
                }
            }

            $db->commit();

            $_SESSION['flash_success'] = ($newStatus === 'confirmed')
                ? '✅ Tasting accepted. Collectors can now be confirmed.'
                : '❌ Tasting declined.';

        } elseif ($action === 'collector_join') {
            if ($userRole !== 'coleccionista') {
                throw new RuntimeException('Only collectors can register for a tasting.');
            }
            if ($uid <= 0) {
                throw new RuntimeException('Cannot detect your user id.');
            }

            // Already registered?
            if ($myCollector) {
                throw new RuntimeException('You are already registered for this tasting.');
            }

            $db->beginTransaction();

            // Re-check assigned sommelier status inside transaction
            $stmt = $db->prepare("SELECT `$suStatusCol` FROM tasting_signups WHERE `$suTastingCol`=? AND `$suRoleCol`='sommelier' LIMIT 1");
            $stmt->execute([$tastingId]);
            $sStatus = strtolower((string)$stmt->fetchColumn());
            $somOk = in_array($sStatus, ['confirmed', 'accepted', 'confirmado', 'aceptado'], true);

            // Seats only open when sommelier is confirmed
            $status = 'waitlist';
            if ($somOk) {
                $cnt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$suTastingCol`=? AND `$suRoleCol`='coleccionista' AND `$suStatusCol`='confirmed'");
                $cnt->execute([$tastingId]);
                $confirmedNow = (int)$cnt->fetchColumn();
                $status = ($confirmedNow < $maxParticipants) ? 'confirmed' : 'waitlist';
            }

            $ins = $db->prepare("INSERT INTO tasting_signups (`$suTastingCol`, `$suUserCol`, `$suRoleCol`, `$suStatusCol`) VALUES (?, ?, 'coleccionista', ?)");
            $ins->execute([$tastingId, $uid, $status]);

            $db->commit();

            $_SESSION['flash_success'] = ($status === 'confirmed')
                ? '✅ Successfully registered (confirmed).'
                : '🕓 Registered on the waiting list (pending sommelier confirmation or full capacity).';

        } elseif ($action === 'collector_cancel') {
            if ($userRole !== 'coleccionista') {
                throw new RuntimeException('Only collectors can cancel their registration.');
            }

            $db->beginTransaction();

            $del = $db->prepare("DELETE FROM tasting_signups WHERE `$suTastingCol`=? AND `$suUserCol`=? AND `$suRoleCol`='coleccionista'");
            $del->execute([$tastingId, $uid]);

            // If sommelier already confirmed, promote one from waitlist to confirmed (fill gap)
            $stmt = $db->prepare("SELECT `$suStatusCol` FROM tasting_signups WHERE `$suTastingCol`=? AND `$suRoleCol`='sommelier' LIMIT 1");
            $stmt->execute([$tastingId]);
            $sStatus = strtolower((string)$stmt->fetchColumn());
            $somOk = in_array($sStatus, ['confirmed', 'accepted', 'confirmado', 'aceptado'], true);

            if ($somOk) {
                $cnt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$suTastingCol`=? AND `$suRoleCol`='coleccionista' AND `$suStatusCol`='confirmed'");
                $cnt->execute([$tastingId]);
                $confirmedNow = (int)$cnt->fetchColumn();

                if ($confirmedNow < $maxParticipants) {
                    $orderSql = $orderCol ? " ORDER BY `$orderCol` ASC" : '';
                    $db->exec(
                        "UPDATE tasting_signups\n".
                        "SET `$suStatusCol`='confirmed'\n".
                        "WHERE `$suTastingCol`=".(int)$tastingId."\n".
                        "  AND `$suRoleCol`='coleccionista'\n".
                        "  AND `$suStatusCol`='waitlist'\n".
                        $orderSql."\n".
                        "LIMIT 1"
                    );
                }
            }

            $db->commit();
            $_SESSION['flash_success'] = '✅ Registration cancelled.';
        }

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['flash_error'] = $e->getMessage();
    }

    header('Location: tasting_detail.php?id=' . $tastingId);
    exit;
}

// --------------- Stats (for display) ---------------
$stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$suTastingCol`=? AND `$suRoleCol`='coleccionista' AND `$suStatusCol`='confirmed'");
$stmt->execute([$tastingId]);
$confirmedCollectors = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$suTastingCol`=? AND `$suRoleCol`='coleccionista' AND `$suStatusCol`='waitlist'");
$stmt->execute([$tastingId]);
$waitlistCollectors = (int)$stmt->fetchColumn();

$freeSpots = $sommelierConfirmed ? max(0, $maxParticipants - $confirmedCollectors) : 0;

// --------------- Wines ---------------
$vinos = [];
$winesCols = table_columns($db, 'tasting_wines');
$vinosCols = table_columns($db, 'vinos');
if ($winesCols && $vinosCols) {
    $twTastingCol = pick_col($winesCols, ['id_cata', 'tasting_id'], $suTastingCol);
    $twWineCol    = pick_col($winesCols, ['id_vino', 'wine_id'], 'id_vino');
    $vinoPk       = pick_col($vinosCols, ['id_vino', 'wine_id', 'id'], 'id_vino');

    $twOrdenCol = pick_col($winesCols, ['orden_degustacion', 'orden'], null);
    $twNotasCol = pick_col($winesCols, ['notas_cata', 'notas'], null);

    $orderSelect = $twOrdenCol ? "tw.`$twOrdenCol` AS orden_degustacion," : "NULL AS orden_degustacion,";
    $notesSelect = $twNotasCol ? "tw.`$twNotasCol` AS notas_cata" : "NULL AS notas_cata";
    $orderBy     = $twOrdenCol ? "ORDER BY tw.`$twOrdenCol`" : '';

    $sqlW = "
        SELECT v.*, $orderSelect $notesSelect
        FROM tasting_wines tw
        JOIN vinos v ON v.`$vinoPk` = tw.`$twWineCol`
        WHERE tw.`$twTastingCol` = ?
        $orderBy
    ";
    $stmt = $db->prepare($sqlW);
    $stmt->execute([$tastingId]);
    $vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --------------- Date formatting ---------------
$fechaFmt = '-';
try {
    if ($dateCol && !empty($cata[$dateCol])) {
        if ($dateCol === 'fecha_cata' && $timeCol && !empty($cata[$timeCol])) {
            $fechaFmt = (new DateTime($cata[$dateCol] . ' ' . $cata[$timeCol]))->format('d/m/Y H:i');
        } else {
            $fechaFmt = (new DateTime($cata[$dateCol]))->format('d/m/Y H:i');
        }
    }
} catch (Throwable $e) {
    $fechaFmt = (string)($cata[$dateCol] ?? '-');
}

include __DIR__ . '/../includes/header.php';
?>

<h1><?= htmlspecialchars($cata['titulo'] ?? 'Tasting') ?></h1>

<?php if ($flashSuccess): ?>
    <p class="success"><?= htmlspecialchars($flashSuccess) ?></p>
<?php endif; ?>

<?php if ($flashError): ?>
    <p class="error"><?= htmlspecialchars($flashError) ?></p>
<?php endif; ?>

<div class="cata-info">
    <p><strong>📅 Date:</strong> <?= htmlspecialchars($fechaFmt) ?></p>
    <p><strong>📍 Location:</strong> <?= htmlspecialchars($cata[$locCol] ?? '-') ?></p>

    <p><strong>👨‍🍳 Assigned sommelier:</strong>
        <?php if (!$assignedSommelier): ?>
            <span style="color:#666;">Not assigned yet</span>
        <?php else: ?>
            <?= htmlspecialchars($assignedSommelier['sommelier_nombre'] ?? 'Sommelier') ?>
            <span style="color:#666;">(<?= htmlspecialchars($assignedSommelier['sommelier_status'] ?? '-') ?>)</span>
        <?php endif; ?>
    </p>

    <?php if ($estadoCol): ?>
        <p><strong>Status:</strong> <?= htmlspecialchars($cata[$estadoCol] ?? '-') ?></p>
    <?php endif; ?>

    <hr style="margin:12px 0; opacity:.25;">

    <p><strong>👥 Collectors:</strong> <?= (int)$confirmedCollectors ?> confirmed / <?= (int)($confirmedCollectors + $waitlistCollectors) ?> total</p>
    <p><strong>🪑 Available spots now:</strong> <?= (int)$freeSpots ?> (max <?= (int)$maxParticipants ?>)</p>

    <?php if (!$sommelierConfirmed): ?>
        <p style="color:#b45309; font-weight:600;">
            Sommelier confirmation is pending. Collectors will be placed on the waiting list until it is confirmed.
        </p>
    <?php endif; ?>

    <?php if (!empty($cata['descripcion'])): ?>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($cata['descripcion'])) ?></p>
    <?php endif; ?>
</div>

<?php if (($userRole ?? '') === 'admin'): ?>
    <h2>Admin actions</h2>
    <form method="post" onsubmit="return confirm('Delete this tasting permanently? This will also remove related signups and wines.');" style="margin: 10px 0;">
        <button type="submit" name="action" value="admin_delete_tasting" class="btn btn-danger">
            Delete tasting
        </button>
    </form>
<?php endif; ?>

<?php if ($assignedSommelier && (int)$assignedSommelier['sommelier_id'] === $uid && $sommelierPending): ?>
    <h2>Sommelier confirmation</h2>
    <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <button type="submit" name="action" value="sommelier_accept" class="btn btn-primary">Accept tasting</button>
        <button type="submit" name="action" value="sommelier_decline" class="btn btn-secondary">Decline</button>
    </form>
<?php endif; ?>

<?php if (!empty($vinos)): ?>
    <h2>Wines to taste</h2>
    <div class="vinos-cata">
        <?php foreach ($vinos as $vino): ?>
            <div class="vino-item">
                <?php
                    $orden = $vino['orden_degustacion'] ?? null;
                    $ordenTxt = ($orden !== null && $orden !== '') ? ((int)$orden . '. ') : '';
                ?>
                <h4><?= htmlspecialchars($ordenTxt . ($vino['nombre'] ?? 'Wine')) ?></h4>
                <p><?= htmlspecialchars(($vino['tipo'] ?? '') . ' - ' . ($vino['annada'] ?? '')) ?></p>
                <?php if (!empty($vino['notas_cata'])): ?>
                    <p><em><?= htmlspecialchars($vino['notas_cata']) ?></em></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($userRole === 'coleccionista'): ?>
    <h2>Your registration</h2>

    <?php if ($myCollector): ?>
        <?php $st = strtolower((string)($myCollector['status'] ?? '')); ?>
        <?php if (in_array($st, ['confirmed','confirmado'], true)): ?>
            <p style="color: green; font-weight: bold;">✓ You are registered (confirmed)</p>
        <?php elseif (in_array($st, ['waitlist','lista_espera','lista de espera','espera'], true)): ?>
            <p style="color: #b45309; font-weight: bold;">🕓 You are on the waiting list</p>
        <?php else: ?>
            <p><strong>Your status:</strong> <?= htmlspecialchars($myCollector['status'] ?? '-') ?></p>
        <?php endif; ?>

        <form method="post" style="margin-top: 10px;">
            <button type="submit" name="action" value="collector_cancel" class="btn btn-secondary">Cancel registration</button>
        </form>

    <?php else: ?>
        <form method="post" style="margin-top: 10px;">
            <button type="submit" name="action" value="collector_join" class="btn btn-primary">Register for this tasting</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<p><a href="tastings.php">← Back to tastings list</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
