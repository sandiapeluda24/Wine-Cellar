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

<?php
  $totalCollectors = (int)$confirmedCollectors + (int)$waitlistCollectors;

  $tStatus = $estadoCol ? trim((string)($cata[$estadoCol] ?? '')) : '';
  $tStatusLower = strtolower($tStatus);

  // badge para status "bonito"
  $statusBadgeClass = 'badge badge-ghost';
  if ($tStatusLower) {
    if (in_array($tStatusLower, ['open','abierta','confirmada','confirmed','programada','scheduled'], true)) $statusBadgeClass = 'badge badge-status--open';
    if (in_array($tStatusLower, ['pending','pendiente'], true)) $statusBadgeClass = 'badge badge-status--needs';
    if (in_array($tStatusLower, ['full','completa','llena'], true)) $statusBadgeClass = 'badge badge-status--full';
  }

  // badge para sommelier
  $somBadgeClass = !$assignedSommelier ? 'badge badge-status--needs' : ($sommelierConfirmed ? 'badge badge-status--open' : 'badge badge-status--needs');
  $somBadgeText  = !$assignedSommelier ? 'Not assigned' : ($sommelierConfirmed ? 'Confirmed' : 'Pending');
?>

<section class="admin-hero">
  <div class="admin-shell">

    <div class="admin-head">
      <div class="admin-kicker">Administration</div>

      <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
        <div>
          <h1 class="admin-title"><?= htmlspecialchars($cata['titulo'] ?? 'Tasting') ?></h1>
          <p class="admin-subtitle">Details, assigned sommelier, collectors, and wines for this tasting.</p>
        </div>

        <div class="tasting-badges">
          <span class="badge badge-ghost">ID #<?= (int)$tastingId ?></span>
          <?php if ($tStatus): ?>
            <span class="<?= $statusBadgeClass ?>"><?= htmlspecialchars($tStatus) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="admin-actions" style="margin-top:14px;">
        <a href="tastings.php" class="btn btn-ghost btn-sm">← Back to tastings list</a>
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="notice notice-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="notice notice-error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="tasting-grid">

      <!-- LEFT: info + wines -->
      <div class="form-card">

        <div class="tasting-meta">
          <div class="tasting-meta__item">
            <span class="tasting-meta__icon">📅</span>
            <span><b>Date</b>&nbsp;&nbsp;<?= htmlspecialchars($fechaFmt) ?></span>
          </div>

          <div class="tasting-meta__item">
            <span class="tasting-meta__icon">📍</span>
            <span><b>Location</b>&nbsp;&nbsp;<?= htmlspecialchars($cata[$locCol] ?? '-') ?></span>
          </div>

          <div class="tasting-meta__item">
            <span class="tasting-meta__icon">👨‍🍳</span>
            <span>
              <b>Sommelier</b>&nbsp;&nbsp;
              <?php if (!$assignedSommelier): ?>
                <span style="color: rgba(43,15,20,70);">Not assigned yet</span>
              <?php else: ?>
                <?= htmlspecialchars($assignedSommelier['sommelier_nombre'] ?? 'Sommelier') ?>
              <?php endif; ?>
              &nbsp;&nbsp;<span class="<?= $somBadgeClass ?>"><?= htmlspecialchars($somBadgeText) ?></span>
            </span>
          </div>

          <div class="tasting-meta__item">
            <span class="tasting-meta__icon">👥</span>
            <span><b>Collectors</b>&nbsp;&nbsp;<?= (int)$confirmedCollectors ?> confirmed / <?= (int)$totalCollectors ?> total</span>
          </div>
        </div>

        <div class="tasting-kpis">
          <div class="tasting-kpi">
            <div class="tasting-kpi__label">Confirmed</div>
            <div class="tasting-kpi__value"><?= (int)$confirmedCollectors ?></div>
          </div>
          <div class="tasting-kpi">
            <div class="tasting-kpi__label">Total</div>
            <div class="tasting-kpi__value"><?= (int)$totalCollectors ?></div>
          </div>
          <div class="tasting-kpi">
            <div class="tasting-kpi__label">Available spots</div>
            <div class="tasting-kpi__value"><?= $sommelierConfirmed ? (int)$freeSpots : 0 ?></div>
          </div>
        </div>

        <?php if (!$sommelierConfirmed): ?>
          <div class="alert alert-warn">
            Sommelier confirmation is pending. Collectors will be placed on the waiting list until it is confirmed.
          </div>
        <?php endif; ?>

        <?php if (!empty($cata['descripcion'])): ?>
          <p class="tasting-desc"><?= nl2br(htmlspecialchars($cata['descripcion'])) ?></p>
        <?php endif; ?>

        <?php if (!empty($vinos)): ?>
          <div class="divider"></div>

          <div class="tasting-section">
            <div class="section-head">
              <h2 class="section-title">Wines to taste</h2>
              <p class="section-subtitle">Order and basic info at a glance.</p>
            </div>

            <div class="wine-list">
              <?php foreach ($vinos as $vino): ?>
                <?php
                  $orden = $vino['orden_degustacion'] ?? null;
                  $ordenNum = ($orden !== null && $orden !== '') ? (int)$orden : null;
                  $tipo = trim((string)($vino['tipo'] ?? ''));
                  $annada = trim((string)($vino['annada'] ?? ''));
                ?>
                <div class="wine-item">
                  <div class="pill" style="padding:8px 10px; border-radius:14px; text-align:center; font-weight:900;">
                    <?= $ordenNum ? $ordenNum : '•' ?>
                  </div>

                  <div class="wine-thumb wine-thumb--ph">🍷</div>

                  <div>
                    <div class="wine-top">
                      <div class="wine-name"><?= htmlspecialchars($vino['nombre'] ?? 'Wine') ?></div>
                      <div class="wine-badges">
                        <?php if ($tipo): ?><span class="badge badge-ghost"><?= htmlspecialchars($tipo) ?></span><?php endif; ?>
                        <?php if ($annada): ?><span class="badge badge-ghost"><?= htmlspecialchars($annada) ?></span><?php endif; ?>
                      </div>
                    </div>

                    <?php if (!empty($vino['notas_cata'])): ?>
                      <div class="wine-note">
                        <div class="section-subtitle">Notes</div>
                        <div><?= htmlspecialchars($vino['notas_cata']) ?></div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <!-- RIGHT: actions -->
      <aside class="form-card">

        <div class="section-head">
          <h2 class="section-title">Actions</h2>
          <p class="section-subtitle">Manage this tasting based on your role.</p>
        </div>

        <?php if (($userRole ?? '') === 'admin'): ?>
          <form method="post"
                onsubmit="return confirm('Delete this tasting permanently? This will also remove related signups and wines.');"
                style="margin: 6px 0 14px;">
            <button type="submit" name="action" value="admin_delete_tasting" class="btn" style="width:100%; justify-content:center;">
              🗑️ Delete tasting
            </button>
          </form>
        <?php endif; ?>

        <?php if ($assignedSommelier && (int)$assignedSommelier['sommelier_id'] === $uid && $sommelierPending): ?>
          <div class="divider"></div>
          <div class="section-head" style="margin-top: 2px;">
            <h3 class="section-title" style="font-size:16px;">Sommelier confirmation</h3>
            <p class="section-subtitle">Accept or decline this tasting.</p>
          </div>

          <form method="post" style="display:grid; gap:10px;">
            <button type="submit" name="action" value="sommelier_accept" class="btn">✅ Accept tasting</button>
            <button type="submit" name="action" value="sommelier_decline" class="btn btn-secondary">Decline</button>
          </form>
        <?php endif; ?>

        <?php if ($userRole === 'coleccionista'): ?>
          <div class="divider"></div>
          <div class="section-head" style="margin-top: 2px;">
            <h3 class="section-title" style="font-size:16px;">Your registration</h3>
            <p class="section-subtitle">Join or cancel your spot.</p>
          </div>

          <?php if ($myCollector): ?>
            <?php $st = strtolower((string)($myCollector['status'] ?? '')); ?>
            <?php if (in_array($st, ['confirmed','confirmado'], true)): ?>
              <div class="notice notice-success">✓ You are registered (confirmed)</div>
            <?php elseif (in_array($st, ['waitlist','lista_espera','lista de espera','espera'], true)): ?>
              <div class="notice" style="border-color: rgba(211,160,74,35); background: rgba(211,160,74,10);">
                🕓 You are on the waiting list
              </div>
            <?php else: ?>
              <div class="notice">Your status: <?= htmlspecialchars($myCollector['status'] ?? '-') ?></div>
            <?php endif; ?>

            <form method="post" style="margin-top: 10px;">
              <button type="submit" name="action" value="collector_cancel" class="btn btn-secondary" style="width:100%; justify-content:center;">
                Cancel registration
              </button>
            </form>

          <?php else: ?>
            <form method="post" style="margin-top: 10px;">
              <button type="submit" name="action" value="collector_join" class="btn" style="width:100%; justify-content:center;">
                Register for this tasting
              </button>
            </form>
          <?php endif; ?>
        <?php endif; ?>

        <div class="divider"></div>
        <a href="tastings.php" class="btn btn-ghost btn-sm" style="width:100%;">← Back to tastings list</a>

      </aside>

    </div>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
