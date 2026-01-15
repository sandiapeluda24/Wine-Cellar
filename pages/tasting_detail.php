<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();
$u = currentUser();
$db = getDB();

$tastingId = (int)($_GET['id'] ?? 0);
if ($tastingId <= 0) {
    header("Location: tastings.php");
    exit;
}

// ---------- Helpers ----------
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

function pick_col(array $cols, array $candidates, ?string $fallback=null): ?string {
    foreach ($candidates as $c) {
        if (in_array(strtolower($c), $cols, true)) return strtolower($c);
    }
    return $fallback ? strtolower($fallback) : null;
}

// ---------- Detect schema (so it works with english/spanish mixes) ----------
$tastingsCols = table_columns($db, 'tastings');
if (!$tastingsCols) die("Missing table: tastings");

$signupCols = table_columns($db, 'tasting_signups');
if (!$signupCols) die("Missing table: tasting_signups (create it first).");

$winesCols = table_columns($db, 'tasting_wines'); // could be empty if table doesn't exist
$vinosCols = table_columns($db, 'vinos');

// Primary key / foreign keys
$tastingKey = pick_col($tastingsCols, ['tasting_id', 'id_cata'], 'tasting_id');   // default tasting_id
$signupTastingKey = pick_col($signupCols, ['tasting_id', 'id_cata'], 'tasting_id');
$signupUserKey    = pick_col($signupCols, ['user_id', 'id_usuario'], 'user_id');

// Signup table columns that may vary
$signupPk = pick_col($signupCols, ['signup_id', 'id_signup', 'id_inscripcion', 'id_registro', 'id'], null);
$signupRoleCol = pick_col($signupCols, ['role', 'rol'], null);
$signupStatusCol = pick_col($signupCols, ['status', 'estado'], null);
$signupCreatedAtCol = pick_col($signupCols, ['created_at', 'fecha_creacion', 'created', 'fecha_registro', 'fecha'], null);

if (!$signupRoleCol) die("Missing column role/rol in tasting_signups.");
if (!$signupStatusCol) die("Missing column status/estado in tasting_signups.");

// Order-by helpers (avoid errors if created_at doesn't exist)
$signupOrderByWithAlias = $signupCreatedAtCol ? "s.`$signupCreatedAtCol`" : ($signupPk ? "s.`$signupPk`" : "s.`$signupUserKey`");
$signupOrderByNoAlias   = $signupCreatedAtCol ? "`$signupCreatedAtCol`" : ($signupPk ? "`$signupPk`" : "`$signupUserKey`");


// Date column in tastings (fix for your previous error)
$dateCol = pick_col($tastingsCols, ['fecha_cata', 'tasting_date', 'fecha', 'date'], null);

// Optional fields
$feeCol = in_array('sommelier_fee', $tastingsCols, true) ? 'sommelier_fee' : null;

// ---------- Load tasting (sommelier host can be NULL) ----------
$sqlT = "
    SELECT t.*, u.nombre AS sommelier_nombre
    FROM tastings t
    LEFT JOIN usuarios u ON t.id_sommelier = u.id_usuario
    WHERE t.`$tastingKey` = ?
    LIMIT 1
";
$stmt = $db->prepare($sqlT);
$stmt->execute([$tastingId]);
$cata = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cata) die("Tasting not found.");

// ---------- Role logic ----------
$userRole = $u['rol'] ?? '';
$isCertifiedSommelier = ($userRole === 'sommelier') && !empty($u['certificado']);
$canJoin = ($userRole === 'coleccionista') || $isCertifiedSommelier;


// ---------- My signup ----------
$selectParts = [];
if ($signupPk) $selectParts[] = "s.`$signupPk` AS signup_id";
$selectParts[] = "s.`$signupRoleCol` AS role";
$selectParts[] = "s.`$signupStatusCol` AS status";
$mySignupSelect = implode(", ", $selectParts);

$stmt = $db->prepare("
  SELECT $mySignupSelect
  FROM tasting_signups s
  WHERE s.`$signupTastingKey` = ?
    AND s.`$signupUserKey` = ?
    AND s.`$signupStatusCol` <> 'cancelled'
  LIMIT 1
");
$stmt->execute([$tastingId, $u['id_usuario']]);
$mySignup = $stmt->fetch(PDO::FETCH_ASSOC);

// ---------- Counts (ratio 1 sommelier / 20 coleccionistas) ----------
$stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$signupTastingKey`=? AND `$signupRoleCol`='sommelier' AND `$signupStatusCol`='confirmed'");
$stmt->execute([$tastingId]);
$totalSommeliers = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$signupTastingKey`=? AND `$signupRoleCol`='coleccionista' AND `$signupStatusCol`='confirmed'");
$stmt->execute([$tastingId]);
$confirmedCollectors = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$signupTastingKey`=? AND `$signupRoleCol`='coleccionista' AND `$signupStatusCol`='waitlist'");
$stmt->execute([$tastingId]);
$waitlistCollectors = (int)$stmt->fetchColumn();

$totalCollectors = $confirmedCollectors + $waitlistCollectors;

$maxCollectors = (int)($cata['max_participantes'] ?? 20);
if ($maxCollectors <= 0) $maxCollectors = 20;

// Each confirmed sommelier opens 20 confirmable collector seats
$collectorSlots = min($maxCollectors, $totalSommeliers * 20);
$freeNow = max(0, $collectorSlots - $confirmedCollectors);

// Ratio status (informational)
$neededSommeliers = $totalCollectors > 0 ? (int)ceil($totalCollectors / 20) : 0;
$ratioOk = ($totalCollectors === 0) ? true : ($totalSommeliers >= $neededSommeliers);

// List sommeliers joined (names)
$stmt = $db->prepare("
  SELECT u.nombre
  FROM tasting_signups s
  INNER JOIN usuarios u ON u.id_usuario = s.`$signupUserKey`
  WHERE s.`$signupTastingKey` = ?
    AND s.`$signupRoleCol` = 'sommelier'
    AND s.`$signupStatusCol` = 'confirmed'
  ORDER BY $signupOrderByWithAlias ASC
");
$stmt->execute([$tastingId]);
$sommeliers = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ---------- Wines for tasting ----------
$vinos = [];
if ($winesCols && $vinosCols) {
    $twTastingKey = pick_col($winesCols, ['tasting_id', 'id_cata'], $signupTastingKey);
    $twWineKey    = pick_col($winesCols, ['id_vino', 'wine_id'], 'id_vino');

    $hasOrden = in_array('orden_degustacion', $winesCols, true);
    $hasNotas = in_array('notas_cata', $winesCols, true);

    $orderSelect = $hasOrden ? "tw.orden_degustacion," : "NULL AS orden_degustacion,";
    $notesSelect = $hasNotas ? "tw.notas_cata" : "NULL AS notas_cata";

    $sqlW = "
        SELECT v.*, $orderSelect $notesSelect
        FROM tasting_wines tw
        INNER JOIN vinos v ON tw.`$twWineKey` = v.id_vino
        WHERE tw.`$twTastingKey` = ?
        " . ($hasOrden ? "ORDER BY tw.orden_degustacion" : "") . "
    ";
    $stmt = $db->prepare($sqlW);
    $stmt->execute([$tastingId]);
    $vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- Handle join ----------
$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscribir'])) {
    if (!$canJoin) {
        $error = "Only coleccionistas or certified sommeliers can register.";
    } elseif ($mySignup) {
        $error = "You are already registered for this tasting.";
    } else {
        try {
            $db->beginTransaction();

            // Recompute inside transaction
            $stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$signupTastingKey`=? AND `$signupRoleCol`='sommelier' AND `$signupStatusCol`='confirmed'");
            $stmt->execute([$tastingId]);
            $sommeliersNow = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$signupTastingKey`=? AND `$signupRoleCol`='coleccionista' AND `$signupStatusCol`='confirmed'");
            $stmt->execute([$tastingId]);
            $confirmedNow = (int)$stmt->fetchColumn();

            if ($isCertifiedSommelier) {
                // Sommelier joins as confirmed
                $stmt = $db->prepare("INSERT INTO tasting_signups (`$signupTastingKey`, `$signupUserKey`, `$signupRoleCol`, `$signupStatusCol`) VALUES (?, ?, 'sommelier', 'confirmed')");
                $stmt->execute([$tastingId, $u['id_usuario']]);

                // Promote waitlist because we opened 20 seats more (bounded by maxCollectors)
                $sommeliersNow += 1;
                $slotsNow = min($maxCollectors, $sommeliersNow * 20);

                $toPromote = $slotsNow - $confirmedNow;
                if ($toPromote > 0) {
                    $toPromote = (int)$toPromote;
                    $db->exec("
                        UPDATE tasting_signups
                        SET `$signupStatusCol`='confirmed'
                        WHERE `$signupTastingKey`=".(int)$tastingId."
                          AND `$signupRoleCol`='coleccionista'
                          AND `$signupStatusCol`='waitlist'
                        ORDER BY $signupOrderByNoAlias ASC
                        LIMIT $toPromote
                    ");
                }

                $mensaje = "✅ Joined as sommelier!";
            } else {
                // Collector: confirmed if there is a confirmable seat, otherwise waitlist
                $slotsNow = min($maxCollectors, $sommeliersNow * 20);
                $status = ($confirmedNow < $slotsNow) ? 'confirmed' : 'waitlist';

                $stmt = $db->prepare("INSERT INTO tasting_signups (`$signupTastingKey`, `$signupUserKey`, `$signupRoleCol`, `$signupStatusCol`) VALUES (?, ?, 'coleccionista', ?)");
                $stmt->execute([$tastingId, $u['id_usuario'], $status]);

                $mensaje = ($status === 'confirmed')
                    ? "✅ Successfully registered (confirmed)!"
                    : "🕓 Registered, but you're on the waiting list (needs more sommeliers).";
            }

            $db->commit();
            header("Location: tasting_detail.php?id=" . $tastingId);
            exit;

        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = "Error registering: " . $e->getMessage();
        }
    }
}

// ---------- Format date ----------
$fechaFmt = '-';
if ($dateCol && !empty($cata[$dateCol])) {
    try {
        $fechaFmt = (new DateTime($cata[$dateCol]))->format('d/m/Y H:i');
    } catch (Throwable $e) { /* ignore */ }
}

include __DIR__ . '/../includes/header.php';
?>

<h1><?= htmlspecialchars($cata['titulo'] ?? 'Tasting') ?></h1>

<?php if ($mensaje): ?>
    <p class="success"><?= htmlspecialchars($mensaje) ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="cata-info">
    <p><strong>📅 Date:</strong> <?= htmlspecialchars($fechaFmt) ?></p>
    <p><strong>📍 Location:</strong> <?= htmlspecialchars($cata['ubicacion'] ?? '-') ?></p>

    <p><strong>👨‍🍳 Host:</strong>
        <?= !empty($cata['sommelier_nombre']) ? htmlspecialchars($cata['sommelier_nombre']) : 'TBD' ?>
    </p>

    <p><strong>Status:</strong> <?= htmlspecialchars($cata['estado'] ?? '-') ?></p>

    <hr style="margin:12px 0; opacity:.25;">

    <p><strong>👨‍🍳 Sommeliers joined:</strong> <?= (int)$totalSommeliers ?>
        <?php if (!empty($sommeliers)): ?>
            (<?= htmlspecialchars(implode(', ', $sommeliers)) ?>)
        <?php endif; ?>
    </p>

    <p><strong>👥 Collectors:</strong> <?= (int)$confirmedCollectors ?> confirmed / <?= (int)$totalCollectors ?> total (incl. waitlist)</p>
    <p><strong>✅ Confirmable spots now:</strong> <?= (int)$freeNow ?> available (max <?= (int)$maxCollectors ?>)</p>

    <?php if (!$ratioOk): ?>
        <p style="color:#b45309; font-weight:600;">
            Needs <?= max(0, $neededSommeliers - $totalSommeliers) ?> more sommelier(s) to meet the ratio (1 per 20 collectors).
        </p>
    <?php else: ?>
        <p style="color:#166534; font-weight:600;">Ratio OK ✅</p>
    <?php endif; ?>

    <?php if (!empty($cata['descripcion'])): ?>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($cata['descripcion'])) ?></p>
    <?php endif; ?>

    <?php if ($isCertifiedSommelier && $feeCol): ?>
        <hr style="opacity:.25; margin:12px 0;">
        <p><strong>💶 Sommelier fee (only you):</strong>
            <?= number_format((float)($cata[$feeCol] ?? 0), 2) ?> €
        </p>
    <?php endif; ?>
</div>

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

<?php if ($mySignup): ?>
    <?php if (($mySignup['status'] ?? '') === 'confirmed'): ?>
        <p style="color: green; font-weight: bold;">✓ You are registered (confirmed)</p>
    <?php elseif (($mySignup['status'] ?? '') === 'waitlist'): ?>
        <p style="color: #b45309; font-weight: bold;">🕓 You are on the waiting list</p>
    <?php else: ?>
        <p><strong>Your status:</strong> <?= htmlspecialchars($mySignup['status'] ?? '-') ?></p>
    <?php endif; ?>
<?php else: ?>
    <?php if ($canJoin): ?>
        <form method="post" style="margin-top: 20px;">
            <button type="submit" name="inscribir" class="btn btn-primary">
                <?= $isCertifiedSommelier ? 'Join as Sommelier' : 'Register for this tasting' ?>
            </button>
        </form>
    <?php else: ?>
        <p style="color:#666;">Only coleccionistas or certified sommeliers can register.</p>
    <?php endif; ?>
<?php endif; ?>

<p><a href="tastings.php">← Back to tastings list</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
