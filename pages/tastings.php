<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';


requireLogin();
$u = currentUser();
$db = getDB();

function tableColumns(PDO $db, string $table): array {
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

// Detectar nombres reales (por si tienes mezcla inglés/español)
$tastingsCols = tableColumns($db, 'tastings');
$signupsCols  = tableColumns($db, 'tasting_signups');

// PK de tastings
$tastingKey = in_array('tasting_id', $tastingsCols, true) ? 'tasting_id' : 'id_cata';

// Columna de fecha (ESTO corrige tu error)
$dateCol = null;
foreach (['fecha_cata', 'tasting_date', 'fecha', 'date', 'created_at'] as $c) {
    if (in_array($c, $tastingsCols, true)) { $dateCol = $c; break; }
}
if ($dateCol === null) {
    // fallback ultra-seguro para no romper (no debería pasar)
    $dateCol = $tastingKey;
}

// FK en tasting_signups hacia tastings
$signupTastingKey = null;
if (in_array('tasting_id', $signupsCols, true)) $signupTastingKey = 'tasting_id';
if (in_array('id_cata', $signupsCols, true))    $signupTastingKey = 'id_cata';

$useSignups = !empty($signupsCols) && $signupTastingKey !== null;

// Query de listados
if ($useSignups) {
    $sql = "
        SELECT
            t.*,
            u.nombre AS host_sommelier_nombre,
            (SELECT COUNT(*)
             FROM tasting_signups s
             WHERE s.`$signupTastingKey` = t.`$tastingKey`
               AND s.role = 'sommelier'
               AND s.status = 'confirmed'
            ) AS sommeliers,
            (SELECT COUNT(*)
             FROM tasting_signups s
             WHERE s.`$signupTastingKey` = t.`$tastingKey`
               AND s.role = 'coleccionista'
               AND s.status = 'confirmed'
            ) AS collectors_confirmed,
            (SELECT COUNT(*)
             FROM tasting_signups s
             WHERE s.`$signupTastingKey` = t.`$tastingKey`
               AND s.role = 'coleccionista'
               AND s.status = 'waitlist'
            ) AS collectors_waitlist
        FROM tastings t
        LEFT JOIN usuarios u ON t.id_sommelier = u.id_usuario
        WHERE t.estado IN ('programada', 'en_curso')
        ORDER BY t.`$dateCol` ASC
    ";
    $stmt = $db->query($sql);
    $catas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Fallback si no existiera signups (por si acaso)
    $tpCols = tableColumns($db, 'tasting_participants');
    $tpKey = in_array('tasting_id', $tpCols, true) ? 'tasting_id' : 'id_cata';

    $sql = "
        SELECT
            t.*,
            u.nombre AS host_sommelier_nombre,
            (SELECT COUNT(*) FROM tasting_participants p WHERE p.`$tpKey` = t.`$tastingKey`) AS inscritos
        FROM tastings t
        LEFT JOIN usuarios u ON t.id_sommelier = u.id_usuario
        WHERE t.estado IN ('programada', 'en_curso')
        ORDER BY t.`$dateCol` ASC
    ";
    $stmt = $db->query($sql);
    $catas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>

<h1>Wine Tastings</h1>

<?php if (empty($catas)): ?>
    <p>No tastings scheduled at the moment.</p>
<?php else: ?>
    <div class="catas-grid">
        <?php foreach ($catas as $cata): ?>
            <?php
                $max = (int)($cata['max_participantes'] ?? 20);
                if ($max <= 0) $max = 20;

                $fechaCata = $cata[$dateCol] ?? null;
                $fechaFmt = '-';
                if (!empty($fechaCata)) {
                    try {
                        $fechaFmt = (new DateTime($fechaCata))->format('d/m/Y H:i');
                    } catch (Throwable $e) { /* ignore */ }
                }

                $hostName = $cata['host_sommelier_nombre'] ?? null;

                if ($useSignups) {
                    $soms      = (int)($cata['sommeliers'] ?? 0);
                    $confirmed = (int)($cata['collectors_confirmed'] ?? 0);
                    $waitlist  = (int)($cata['collectors_waitlist'] ?? 0);

                    $slotsNow = min($max, $soms * 20);
                    $freeNow  = max(0, $slotsNow - $confirmed);
                } else {
                    $soms = 0;
                    $confirmed = (int)($cata['inscritos'] ?? 0);
                    $waitlist = 0;
                    $freeNow  = 0;
                }

                // Badge estado
                $badgeText = "Open";
                $badgeStyle = "display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;border:1px solid #ddd;";

                if ($useSignups && $soms === 0) {
                    $badgeText = "Needs sommeliers";
                    $badgeStyle .= " background:#fff7e6;border-color:#ffe3a3;";
                } elseif ($useSignups && $confirmed >= $max) {
                    $badgeText = "Full";
                    $badgeStyle .= " background:#fff0f0;border-color:#ffc5c5;";
                } elseif ($useSignups && $freeNow === 0 && $waitlist > 0) {
                    $badgeText = "Waitlist";
                    $badgeStyle .= " background:#e8f0ff;border-color:#b6d0ff;";
                } else {
                    $badgeStyle .= " background:#e9fbef;border-color:#bfe8c9;";
                }

                // Fee visible SOLO para sommelier certificado
                $isSommelierCertified = (($u['rol'] ?? '') === 'sommelier') && !empty($u['certificado']);
                $fee = array_key_exists('sommelier_fee', $cata) ? (float)$cata['sommelier_fee'] : null;
            ?>

            <div class="cata-card">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                    <h3 style="margin:0;"><?= htmlspecialchars($cata['titulo'] ?? 'Tasting') ?></h3>
                    <span style="<?= $badgeStyle ?>"><?= htmlspecialchars($badgeText) ?></span>
                </div>

                <p style="margin-top:10px;"><strong>📅</strong> <?= htmlspecialchars($fechaFmt) ?></p>
                <p><strong>📍</strong> <?= htmlspecialchars($cata['ubicacion'] ?? '-') ?></p>

                <p><strong>👨‍🍳 Host:</strong> <?= $hostName ? htmlspecialchars($hostName) : 'TBD' ?></p>

                <?php if ($useSignups): ?>
                    <p><strong>👨‍🍳 Sommeliers joined:</strong> <?= (int)$soms ?></p>
                    <p><strong>👥 Collectors:</strong> <?= (int)$confirmed ?> confirmed<?php if ($waitlist > 0) echo " / " . (int)$waitlist . " waitlist"; ?></p>
                    <p><strong>✅ Confirmable spots now:</strong> <?= (int)$freeNow ?> (max <?= (int)$max ?>)</p>
                <?php else: ?>
                    <p><strong>👥 Registered:</strong> <?= (int)$confirmed ?> / <?= (int)$max ?></p>
                <?php endif; ?>

                <?php if ($isSommelierCertified && $fee !== null): ?>
                    <p style="margin-top:10px;">
                        <strong>💶 Sommelier fee (only you):</strong> <?= number_format($fee, 2) ?> €
                    </p>
                <?php endif; ?>

                <?php if (!empty($cata['descripcion'])): ?>
                    <p style="opacity:.85;">
                        <?= nl2br(htmlspecialchars(mb_strimwidth($cata['descripcion'], 0, 180, '...'))) ?>
                    </p>
                <?php endif; ?>

                <p style="margin-top: 12px;">
                    <a href="tasting_detail.php?id=<?= (int)$cata[$tastingKey] ?>" class="btn">View details</a>
                </p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
