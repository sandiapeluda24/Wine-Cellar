<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$u  = currentUser();
$db = getDB();
if (($u['rol'] ?? '') === 'sommelier') {
    $uid = (int)($u['id_usuario'] ?? $u['id'] ?? ($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0));
    if ($uid > 0) {
        $stmt = $db->prepare("SELECT certificado FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$uid]);
        $cert = (int)$stmt->fetchColumn();
        $u['certificado'] = $cert;
        $_SESSION['usuario']['certificado'] = $cert;
    }
}

$isAdmin = (($u['rol'] ?? '') === 'admin');
$isSommelierCertified = (($u['rol'] ?? '') === 'sommelier' && !empty($u['certificado']));

if (!$isAdmin && !$isSommelierCertified) {
    http_response_code(403);
    die("Only administrators or certified sommeliers can create tastings.");
}

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

$errores = [];
$mensaje = null;

// Lista de sommeliers (solo admin)
$sommeliers = [];
if ($isAdmin) {
    $stmt = $db->query("
        SELECT id_usuario, nombre, email
        FROM usuarios
        WHERE rol = 'sommelier' AND certificado = TRUE AND is_active = TRUE
        ORDER BY nombre
    ");
    $sommeliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Vinos disponibles
$stmt = $db->query("SELECT * FROM vinos WHERE stock > 0 ORDER BY nombre");
$vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determinar columnas reales (por compatibilidad con tu DB)
$tastingsCols = tableColumns($db, 'tastings');
$dateCol = in_array('tasting_date', $tastingsCols, true) ? 'tasting_date' : (in_array('fecha_cata', $tastingsCols, true) ? 'fecha_cata' : 'tasting_date');
$timeCol = in_array('hora_cata', $tastingsCols, true) ? 'hora_cata' : null;
$locCol  = in_array('ubicacion', $tastingsCols, true) ? 'ubicacion' : (in_array('lugar', $tastingsCols, true) ? 'lugar' : 'ubicacion');

$maxCol  = in_array('max_participantes', $tastingsCols, true) ? 'max_participantes' : (in_array('capacidad', $tastingsCols, true) ? 'capacidad' : (in_array('aforo', $tastingsCols, true) ? 'aforo' : 'max_participantes'));
$estadoCol = in_array('estado', $tastingsCols, true) ? 'estado' : null;

$hasIdSommelier = in_array('id_sommelier', $tastingsCols, true);
$hasSommelierId = in_array('sommelier_id', $tastingsCols, true);

// Compat para tasting_wines
$twCols = tableColumns($db, 'tasting_wines');
$twCataCol  = in_array('id_cata', $twCols, true) ? 'id_cata' : (in_array('tasting_id', $twCols, true) ? 'tasting_id' : 'id_cata');
$twVinoCol  = in_array('id_vino', $twCols, true) ? 'id_vino' : (in_array('wine_id', $twCols, true) ? 'wine_id' : 'id_vino');
$twOrdenCol = in_array('orden_degustacion', $twCols, true) ? 'orden_degustacion' : (in_array('orden', $twCols, true) ? 'orden' : null);
$twNotasCol = in_array('notas_cata', $twCols, true) ? 'notas_cata' : (in_array('notas', $twCols, true) ? 'notas' : null);

// Compat para tasting_signups (Opción B: asignación/confirmación del sommelier)
$suCols = tableColumns($db, 'tasting_signups');
$suTastingCol = in_array('tasting_id', $suCols, true) ? 'tasting_id' : (in_array('id_cata', $suCols, true) ? 'id_cata' : 'tasting_id');
$suUserCol    = in_array('user_id', $suCols, true) ? 'user_id' : (in_array('id_usuario', $suCols, true) ? 'id_usuario' : 'user_id');
$suRoleCol    = in_array('role', $suCols, true) ? 'role' : (in_array('rol', $suCols, true) ? 'rol' : null);
$suStatusCol  = in_array('status', $suCols, true) ? 'status' : (in_array('estado', $suCols, true) ? 'estado' : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo        = trim($_POST['titulo'] ?? '');
    $descripcion   = trim($_POST['descripcion'] ?? '');
    $tasting_date_raw = trim($_POST['tasting_date'] ?? '');
    $ubicacion     = trim($_POST['ubicacion'] ?? '');
    $max_participantes = (int)($_POST['max_participantes'] ?? 20);
    $vinos_seleccionados = $_POST['vinos'] ?? [];

    // 1) Sommelier responsable (Opción A)
    $id_sommelier = 0;
    if ($isAdmin) {
        $id_sommelier = (int)($_POST['id_sommelier'] ?? 0);
        if ($id_sommelier <= 0) {
            $errores[] = "You must select a certified sommelier.";
        } else {
            // Validar que existe y está certificado/activo
            $chk = $db->prepare("
                SELECT COUNT(*) 
                FROM usuarios 
                WHERE id_usuario = ? AND rol='sommelier' AND certificado=TRUE AND is_active=TRUE
            ");
            $chk->execute([$id_sommelier]);
            if ((int)$chk->fetchColumn() === 0) {
                $errores[] = "Selected sommelier is not certified/active.";
            }
        }
    } else {
        // Sommelier certificado: auto-asignación
        $id_sommelier = (int)($u['id_usuario'] ?? $u['id'] ?? 0);
        if ($id_sommelier <= 0) {
            $errores[] = "Cannot detect your user id (sommelier).";
        }
    }

    // Validaciones generales
    if ($titulo === '') {
        $errores[] = "Title is required.";
    }

    $dt = null;
    if ($tasting_date_raw === '') {
        $errores[] = "Date and time are required.";
    } else {
        try {
            // datetime-local suele venir como 2026-01-07T20:00
            $dt = new DateTime(str_replace('T', ' ', $tasting_date_raw));
            $ahora = new DateTime();
            if ($dt <= $ahora) {
                $errores[] = "Tasting date must be in the future.";
            }
        } catch (Throwable $e) {
            $errores[] = "Invalid date format.";
        }
    }

    if ($ubicacion === '') {
        $errores[] = "Location is required.";
    }

    if ($max_participantes < 1 || $max_participantes > 100) {
        $errores[] = "Maximum participants must be between 1 and 100.";
    }

    if (empty($vinos)) {
        $errores[] = "There are currently no wines in stock, so a tasting cannot be created.";
    } elseif (empty($vinos_seleccionados)) {
        $errores[] = "Please select at least one wine for the tasting.";
    }

    // Crear la cata
    if (empty($errores)) {
        try {
            $db->beginTransaction();

            // Construir INSERT compatible con tus columnas reales
            $cols = ['titulo', 'descripcion', $locCol, $maxCol];
            $vals = [$titulo, $descripcion, $ubicacion, $max_participantes];

            // Fecha/hora
            if ($dt !== null) {
                if ($dateCol === 'fecha_cata') {
                    $cols[] = 'fecha_cata';
                    $vals[] = $dt->format('Y-m-d');
                    if ($timeCol !== null) {
                        $cols[] = $timeCol;
                        $vals[] = $dt->format('H:i:s');
                    }
                } else {
                    $cols[] = $dateCol; // tasting_date
                    $vals[] = $dt->format('Y-m-d H:i:s');
                }
            }

            // Guardar sommelier en los 2 nombres si existen (compat con el resto del proyecto)
            if ($hasIdSommelier) {
                $cols[] = 'id_sommelier';
                $vals[] = $id_sommelier;
            }
            if ($hasSommelierId) {
                $cols[] = 'sommelier_id';
                $vals[] = $id_sommelier;
            }

            // Estado por defecto (si existe)
            if ($estadoCol !== null) {
                $cols[] = $estadoCol;
                $vals[] = 'programada';
            }

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO tastings (" . implode(', ', $cols) . ") VALUES ($placeholders)";
            $stmt = $db->prepare($sql);
            $stmt->execute($vals);

            $idCata = (int)$db->lastInsertId();

            // ---------------- Opción B ----------------
            // Guardar la asignación del sommelier en tasting_signups para que el sommelier pueda confirmarla.
            // - Admin asigna -> status 'pending'
            // - Sommelier crea su propia cata -> status 'confirmed'
            if (!empty($suCols) && $suRoleCol && $suStatusCol) {
                $sommelierStatus = $isAdmin ? 'pending' : 'confirmed';

                // Si ya existiera una asignación (por reintentos), la actualizamos.
                $chk = $db->prepare("SELECT COUNT(*) FROM tasting_signups WHERE `$suTastingCol`=? AND `$suRoleCol`='sommelier'");
                $chk->execute([$idCata]);
                $exists = (int)$chk->fetchColumn() > 0;

                if ($exists) {
                    $up = $db->prepare("UPDATE tasting_signups SET `$suUserCol`=?, `$suStatusCol`=? WHERE `$suTastingCol`=? AND `$suRoleCol`='sommelier'");
                    $up->execute([$id_sommelier, $sommelierStatus, $idCata]);
                } else {
                    $ins = $db->prepare("INSERT INTO tasting_signups (`$suTastingCol`, `$suUserCol`, `$suRoleCol`, `$suStatusCol`) VALUES (?, ?, 'sommelier', ?)");
                    $ins->execute([$idCata, $id_sommelier, $sommelierStatus]);
                }
            }

            // Insertar vinos seleccionados (orden + notas si existen)
            $orden = 1;
            foreach ($vinos_seleccionados as $idVinoRaw) {
                $idVino = (int)$idVinoRaw;
                if ($idVino <= 0) continue;

                $notas = trim($_POST['notas_' . $idVino] ?? '');

                $twInsertCols = [$twCataCol, $twVinoCol];
                $twVals = [$idCata, $idVino];

                if ($twOrdenCol !== null) {
                    $twInsertCols[] = $twOrdenCol;
                    $twVals[] = $orden;
                }

                if ($twNotasCol !== null) {
                    $twInsertCols[] = $twNotasCol;
                    $twVals[] = $notas;
                }

                $twSql = "INSERT INTO tasting_wines (" . implode(', ', $twInsertCols) . ")
                          VALUES (" . implode(',', array_fill(0, count($twInsertCols), '?')) . ")";
                $twStmt = $db->prepare($twSql);
                $twStmt->execute($twVals);

                $orden++;
            }

            $db->commit();

            header("Location: tasting_detail.php?id=" . $idCata);
            exit;

        } catch (Throwable $e) {
            $db->rollBack();
            $errores[] = "Error creating tasting: " . $e->getMessage();
        }
    }
}



include __DIR__ . '/../includes/header.php';
?>

<div class="form-hero">
  <section class="form-shell">
    <div class="form-head">
      <div class="form-kicker"><?= $isAdmin ? 'Admin · Tastings' : 'Sommelier · Tastings' ?></div>
      <h1 class="form-title">Create a new tasting</h1>
      <p class="form-subtitle">Schedule the event, choose the host, set capacity, and pick the wines to taste.</p>
    </div>

    <?php foreach ($errores as $error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <?php if ($isAdmin && empty($sommeliers)): ?>
      <div class="form-card">
        <div class="alert alert-error" style="margin-bottom:14px;">
          No certified sommeliers available. Please certify a sommelier first.
        </div>
        <a class="btn" href="admin_panel.php">Go to Admin Panel</a>
      </div>
    <?php else: ?>

    <form method="post" class="form-card tasting-form">
      <div class="tasting-grid">
        <div class="tasting-col">
          <div class="field">
            <label for="titulo">Title <span class="req">*</span></label>
            <input type="text" id="titulo" name="titulo" required
                   value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
          </div>

          <div class="field">
            <label for="descripcion">Description</label>
            <textarea id="descripcion" name="descripcion" rows="5"
                      placeholder="Optional — agenda, theme, or special notes..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="tasting-col">
          <div class="field">
            <label for="tasting_date">Date & time <span class="req">*</span></label>
            <input type="datetime-local" id="tasting_date" name="tasting_date" required
                   value="<?= htmlspecialchars($_POST['tasting_date'] ?? '') ?>">
          </div>

          <div class="field">
            <label for="ubicacion">Location <span class="req">*</span></label>
            <input type="text" id="ubicacion" name="ubicacion" required
                   value="<?= htmlspecialchars($_POST['ubicacion'] ?? '') ?>">
          </div>

          <?php if ($isAdmin): ?>
            <div class="field">
              <label for="id_sommelier">Assign sommelier <span class="req">*</span></label>
              <select id="id_sommelier" name="id_sommelier" required>
                <option value="">— Select a sommelier —</option>
                <?php foreach ($sommeliers as $som): ?>
                  <option value="<?= (int)$som['id_usuario'] ?>"
                    <?= (isset($_POST['id_sommelier']) && (int)$_POST['id_sommelier'] === (int)$som['id_usuario']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($som['nombre']) ?> (<?= htmlspecialchars($som['email']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="hint">Only certified sommeliers are listed.</div>
            </div>
          <?php else: ?>
            <div class="field">
              <label>Assigned sommelier</label>
              <div class="pill">
                <?= htmlspecialchars($u['nombre'] ?? 'Sommelier') ?> (you)
              </div>
            </div>
          <?php endif; ?>

          <div class="field">
            <label for="max_participantes">Maximum participants <span class="req">*</span></label>
            <input type="number" id="max_participantes" name="max_participantes"
                   min="1" max="100" value="<?= htmlspecialchars($_POST['max_participantes'] ?? '20') ?>" required>
          </div>
        </div>
      </div>

      <div class="divider"></div>

      <div class="tasting-section">
        <div class="section-head">
          <h2 class="section-title">Wines</h2>
          <p class="section-subtitle">Pick the wines in the order they will be tasted (top to bottom).</p>
        </div>

        <?php if (empty($vinos)): ?>
          <div class="alert alert-warn">
            There are currently no wines in stock. Please add wines or update stock before creating a tasting.
          </div>
        <?php else: ?>
          <div class="wine-list">
            <?php foreach ($vinos as $vino): ?>
              <?php
                $idV = (int)$vino['id_vino'];
                $checked = in_array($vino['id_vino'], $_POST['vinos'] ?? [], false);
                $img = trim((string)($vino['imagen'] ?? ''));
                $initials = strtoupper(substr(preg_replace('/\s+/', ' ', trim((string)$vino['nombre'])), 0, 2));
              ?>
              <div class="wine-item <?= $checked ? 'is-checked' : '' ?>">
                <label class="wine-check">
                  <input type="checkbox" name="vinos[]" value="<?= $idV ?>" <?= $checked ? 'checked' : '' ?>>
                  <span class="check-ui"></span>
                </label>

                <div class="wine-media">
                  <?php if ($img !== ''): ?>
                    <img class="wine-thumb" src="/img/wines/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($vino['nombre']) ?>">
                  <?php else: ?>
                    <div class="wine-thumb wine-thumb--ph"><?= htmlspecialchars($initials) ?></div>
                  <?php endif; ?>
                </div>

                <div class="wine-body">
                  <div class="wine-top">
                    <div class="wine-name"><?= htmlspecialchars($vino['nombre']) ?></div>
                    <div class="wine-badges">
                      <?php if (!empty($vino['tipo'])): ?><span class="badge"><?= htmlspecialchars($vino['tipo']) ?></span><?php endif; ?>
                      <?php if (!empty($vino['annada'])): ?><span class="badge badge-ghost"><?= htmlspecialchars($vino['annada']) ?></span><?php endif; ?>
                      <?php if (isset($vino['stock'])): ?><span class="badge badge-ok">Stock: <?= (int)$vino['stock'] ?></span><?php endif; ?>
                    </div>
                  </div>

                  <div class="wine-note">
                    <label for="notas_<?= $idV ?>">Tasting notes (optional)</label>
                    <input type="text" id="notas_<?= $idV ?>" name="notas_<?= $idV ?>"
                      placeholder="e.g., Opening wine, fruity notes..."
                      value="<?= htmlspecialchars($_POST['notas_' . $vino['id_vino']] ?? '') ?>">
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create tasting</button>
        <a class="btn btn-ghost" href="<?= $isAdmin ? 'admin_panel.php' : 'tastings.php' ?>">Cancel</a>
      </div>
    </form>

    <?php endif; ?>
  </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
