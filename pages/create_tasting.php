<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$u  = currentUser();
$db = getDB();

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

<h1>Create New Tasting</h1>

<?php foreach ($errores as $error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if ($isAdmin && empty($sommeliers)): ?>
    <p class="error">No certified sommeliers available. Please certify a sommelier first.</p>
    <p><a href="admin_panel.php">Go to Admin Panel</a></p>
<?php else: ?>

<form method="post" class="form-cata">
    <div class="form-group">
        <label for="titulo">Title: *</label>
        <input type="text" id="titulo" name="titulo" required
               value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="descripcion">Description:</label>
        <textarea id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="tasting_date">Date and time: *</label>
        <input type="datetime-local" id="tasting_date" name="tasting_date" required
               value="<?= htmlspecialchars($_POST['tasting_date'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="ubicacion">Location: *</label>
        <input type="text" id="ubicacion" name="ubicacion" required
               value="<?= htmlspecialchars($_POST['ubicacion'] ?? '') ?>">
    </div>

    <?php if ($isAdmin): ?>
        <div class="form-group">
            <label for="id_sommelier">Assign Sommelier: *</label>
            <select id="id_sommelier" name="id_sommelier" required>
                <option value="">-- Select a sommelier --</option>
                <?php foreach ($sommeliers as $som): ?>
                    <option value="<?= (int)$som['id_usuario'] ?>"
                        <?= (isset($_POST['id_sommelier']) && (int)$_POST['id_sommelier'] === (int)$som['id_usuario']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($som['nombre']) ?> (<?= htmlspecialchars($som['email']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php else: ?>
        <div class="form-group">
            <label>Assigned Sommelier:</label>
            <div style="padding:10px;border:1px solid #ddd;border-radius:8px;">
                <?= htmlspecialchars($u['nombre'] ?? 'Sommelier') ?> (you)
            </div>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label for="max_participantes">Maximum participants: *</label>
        <input type="number" id="max_participantes" name="max_participantes"
               min="1" max="100" value="<?= htmlspecialchars($_POST['max_participantes'] ?? '20') ?>" required>
    </div>

    <div class="form-group">
        <label>Select wines for the tasting: *</label>
        <?php if (empty($vinos)): ?>
            <p class="warning">
                There are currently no wines in stock. Please add wines or update stock before creating a tasting.
            </p>
        <?php else: ?>
            <p class="help-text">Choose the wines in the order they will be tasted</p>

            <?php foreach ($vinos as $vino): ?>
                <div class="wine-selection">
                    <label>
                        <input type="checkbox" name="vinos[]" value="<?= (int)$vino['id_vino'] ?>"
                               <?= in_array($vino['id_vino'], $_POST['vinos'] ?? [], false) ? 'checked' : '' ?>>
                        <strong><?= htmlspecialchars($vino['nombre']) ?></strong>
                        - <?= htmlspecialchars($vino['tipo'] ?? '') ?>
                        <?= htmlspecialchars($vino['annada'] ?? '') ?>
                    </label>

                    <div class="wine-notes">
                        <label>Tasting notes (optional):</label>
                        <input type="text" name="notas_<?= (int)$vino['id_vino'] ?>"
                               placeholder="e.g., Opening wine, fruity notes..."
                               value="<?= htmlspecialchars($_POST['notas_' . $vino['id_vino']] ?? '') ?>">
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <button type="submit" class="btn btn-primary">Create Tasting</button>
    <a href="<?= $isAdmin ? 'admin_panel.php' : 'tastings.php' ?>" class="btn">Cancel</a>
</form>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
                