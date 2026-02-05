<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../includes/db.php';

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


<div class="container">
  <div class="page-head">
    <div>
      <div class="page-kicker">Events</div>
      <h1 class="page-title">Wine Tastings</h1>
      <p class="page-subtitle">Browse upcoming tastings, check availability, and open each event for full details.</p>
    </div>
  </div>

  <?php if (empty($catas)): ?>
    <div class="notice">No tastings scheduled at the moment.</div>
  <?php else: ?>
    <div class="tastings-grid">
      <?php foreach ($catas as $cata): ?>
        <?php
          $max = (int)($cata['max_participantes'] ?? 20);
          if ($max <= 0) $max = 20;

          $fechaCata = $cata[$dateCol] ?? null;
          $fechaFmt = 'TBD';
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
              $freeNow  = max(0, $max - $confirmed);
              $slotsNow = $max;
          }

          // Badge state
          $badgeText  = "Open";
          $badgeClass = "badge-status--open";

          if ($useSignups && $soms === 0) {
              $badgeText  = "Needs sommeliers";
              $badgeClass = "badge-status--needs";
          } elseif ($confirmed >= $max) {
              $badgeText  = "Full";
              $badgeClass = "badge-status--full";
          } elseif ($useSignups && $freeNow === 0 && $waitlist > 0) {
              $badgeText  = "Waitlist";
              $badgeClass = "badge-status--waitlist";
          }

          // Fee visible only for certified sommelier
          $isSommelierCertified = (($u['rol'] ?? '') === 'sommelier') && !empty($u['certificado']);
          $fee = array_key_exists('sommelier_fee', $cata) ? (float)$cata['sommelier_fee'] : null;

          $title = $cata['titulo'] ?? 'Tasting';
          $location = trim((string)($cata['ubicacion'] ?? ''));
          if ($location === '') $location = '—';
          $desc = $cata['descripcion'] ?? '';
        ?>

        <article class="tasting-card">
          <header class="tasting-card__top">
            <div class="tasting-card__head">
              <h3 class="tasting-card__title"><?= htmlspecialchars($title) ?></h3>
              <div class="tasting-card__sub">
                <span class="tasting-meta__icon" aria-hidden="true">📍</span>
                <span><?= htmlspecialchars($location) ?></span>
              </div>
            </div>

            <div class="tasting-badges">
              <span class="badge <?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($badgeText) ?></span>
            </div>
          </header>

          <div class="tasting-meta">
            <div class="tasting-meta__item">
              <span class="tasting-meta__icon" aria-hidden="true">📅</span>
              <span><?= htmlspecialchars($fechaFmt) ?></span>
            </div>

            <div class="tasting-meta__item">
              <span class="tasting-meta__icon" aria-hidden="true">🍷</span>
              <span><strong>Host:</strong> <?= $hostName ? htmlspecialchars($hostName) : 'TBD' ?></span>
            </div>

            <div class="tasting-meta__item">
              <span class="tasting-meta__icon" aria-hidden="true">👥</span>
              <span><strong>Capacity:</strong> <?= (int)$max ?></span>
            </div>

            <?php if ($useSignups): ?>
              <div class="tasting-meta__item">
                <span class="tasting-meta__icon" aria-hidden="true">🧑‍🍳</span>
                <span><strong>Sommeliers joined:</strong> <?= (int)$soms ?></span>
              </div>
            <?php else: ?>
              <div class="tasting-meta__item">
                <span class="tasting-meta__icon" aria-hidden="true">✅</span>
                <span><strong>Registered:</strong> <?= (int)$confirmed ?> / <?= (int)$max ?></span>
              </div>
            <?php endif; ?>
          </div>

          <div class="tasting-kpis">
            <?php if ($useSignups): ?>
              <div class="tasting-kpi">
                <div class="tasting-kpi__label">Collectors confirmed</div>
                <div class="tasting-kpi__value"><?= (int)$confirmed ?></div>
              </div>

              <div class="tasting-kpi">
                <div class="tasting-kpi__label">Confirmable spots now</div>
                <div class="tasting-kpi__value"><?= (int)$freeNow ?></div>
              </div>

              <div class="tasting-kpi">
                <div class="tasting-kpi__label">Waitlist</div>
                <div class="tasting-kpi__value"><?= (int)$waitlist ?></div>
              </div>
            <?php else: ?>
              <div class="tasting-kpi">
                <div class="tasting-kpi__label">Registered</div>
                <div class="tasting-kpi__value"><?= (int)$confirmed ?></div>
              </div>

              <div class="tasting-kpi">
                <div class="tasting-kpi__label">Available</div>
                <div class="tasting-kpi__value"><?= (int)$freeNow ?></div>
              </div>

              <div class="tasting-kpi">
                <div class="tasting-kpi__label">Capacity</div>
                <div class="tasting-kpi__value"><?= (int)$max ?></div>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($isSommelierCertified && $fee !== null): ?>
            <div class="tasting-fee">
              <span class="tasting-fee__label">Sommelier fee (certified only)</span>
              <span class="tasting-fee__value"><?= number_format($fee, 2) ?> €</span>
            </div>
          <?php endif; ?>

          <?php if (!empty($desc)): ?>
            <p class="tasting-desc"><?= nl2br(htmlspecialchars(mb_strimwidth($desc, 0, 200, '…'))) ?></p>
          <?php endif; ?>

          <div class="tasting-card__actions">
            <a href="tasting_detail.php?id=<?= (int)$cata[$tastingKey] ?>" class="btn">View details</a>
          </div>
        </article>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>
