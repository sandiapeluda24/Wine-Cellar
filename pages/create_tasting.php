<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$u = currentUser();
$db = getDB();

// Solo admins pueden crear catas
if ($u['rol'] !== 'admin') {
    die("Only administrators can create tastings.");
}

$errores = [];
$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tasting_date = trim($_POST['tasting_date'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $max_participantes = (int)($_POST['max_participantes'] ?? 20);
    $id_sommelier = (int)($_POST['id_sommelier'] ?? 0); // El admin selecciona el sommelier
    $vinos_seleccionados = $_POST['vinos'] ?? [];

    // Validaciones
    if (empty($titulo)) {
        $errores[] = "Title is required.";
    }
    
    if (empty($tasting_date)) {
        $errores[] = "Date is required.";
    } else {
        $fechaObj = new DateTime($tasting_date);
        $ahora = new DateTime();
        if ($fechaObj <= $ahora) {
            $errores[] = "Tasting date must be in the future.";
        }
    }
    
    if (empty($ubicacion)) {
        $errores[] = "Location is required.";
    }
    
    if ($max_participantes < 1 || $max_participantes > 100) {
        $errores[] = "Maximum participants must be between 1 and 100.";
    }
    
    if ($id_sommelier <= 0) {
        $errores[] = "You must select a sommelier.";
    }
    
    if (empty($vinos_seleccionados)) {
        $errores[] = "You must select at least one wine for the tasting.";
    }

    // Si no hay errores, crear la cata
    if (empty($errores)) {
        try {
            $db->beginTransaction();
            
            // Insertar la cata
            $stmt = $db->prepare("
                INSERT INTO tastings (titulo, descripcion, tasting_date, ubicacion, id_sommelier, max_participantes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $titulo,
                $descripcion,
                $tasting_date,
                $ubicacion,
                $id_sommelier, // Sommelier seleccionado por el admin
                $max_participantes
            ]);
            
            $idCata = $db->lastInsertId();
            
            // Insertar los vinos seleccionados
            $stmt = $db->prepare("
                INSERT INTO tasting_wines (id_cata, id_vino, orden_degustacion, notas_cata)
                VALUES (?, ?, ?, ?)
            ");
            
            $orden = 1;
            foreach ($vinos_seleccionados as $idVino) {
                $notas = trim($_POST['notas_' . $idVino] ?? '');
                $stmt->execute([$idCata, $idVino, $orden, $notas]);
                $orden++;
            }
            
            $db->commit();
            $mensaje = "Tasting created successfully!";
            
            header("Location: tasting_detail.php?id=" . $idCata);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errores[] = "Error creating tasting: " . $e->getMessage();
        }
    }
}

// Obtener sommeliers certificados disponibles
$stmt = $db->query("
    SELECT id_usuario, nombre, email 
    FROM usuarios 
    WHERE rol = 'sommelier' AND certificado = TRUE AND is_active = TRUE
    ORDER BY nombre
");
$sommeliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de vinos disponibles
$stmt = $db->query("SELECT * FROM vinos WHERE stock > 0 ORDER BY nombre");
$vinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<h1>Create New Tasting</h1>

<?php if ($mensaje): ?>
    <p class="success"><?= htmlspecialchars($mensaje) ?></p>
<?php endif; ?>

<?php foreach ($errores as $error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (empty($sommeliers)): ?>
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
    
    <div class="form-group">
        <label for="id_sommelier">Assign Sommelier: *</label>
        <select id="id_sommelier" name="id_sommelier" required>
            <option value="">-- Select a sommelier --</option>
            <?php foreach ($sommeliers as $som): ?>
                <option value="<?= $som['id_usuario'] ?>" 
                        <?= (isset($_POST['id_sommelier']) && $_POST['id_sommelier'] == $som['id_usuario']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($som['nombre']) ?> (<?= htmlspecialchars($som['email']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label for="max_participantes">Maximum participants: *</label>
        <input type="number" id="max_participantes" name="max_participantes" 
               min="1" max="100" value="<?= htmlspecialchars($_POST['max_participantes'] ?? '20') ?>" required>
    </div>
    
    <div class="form-group">
        <label>Select wines for the tasting: *</label>
        <p class="help-text">Choose the wines in the order they will be tasted</p>
        
        <?php foreach ($vinos as $vino): ?>
            <div class="wine-selection">
                <label>
                    <input type="checkbox" name="vinos[]" value="<?= $vino['id_vino'] ?>"
                           <?= in_array($vino['id_vino'], $_POST['vinos'] ?? []) ? 'checked' : '' ?>>
                    <strong><?= htmlspecialchars($vino['nombre']) ?></strong> 
                    - <?= htmlspecialchars($vino['tipo']) ?> 
                    <?= htmlspecialchars($vino['annada']) ?>
                </label>
                
                <div class="wine-notes">
                    <label>Tasting notes (optional):</label>
                    <input type="text" name="notas_<?= $vino['id_vino'] ?>" 
                           placeholder="e.g., Opening wine, fruity notes..."
                           value="<?= htmlspecialchars($_POST['notas_' . $vino['id_vino']] ?? '') ?>">
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <button type="submit" class="btn btn-primary">Create Tasting</button>
    <a href="admin_panel.php" class="btn">Cancel</a>
</form>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>