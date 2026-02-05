<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

// Solo admins
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

$errores  = [];
$modo     = 'create';
$idVino   = isset($_GET['id_vino']) ? (int) $_GET['id_vino'] : null;

// valores por defecto
$nombre       = '';
$bodega       = '';
$annada       = '';
$idDenom      = 0;
$tipo         = '';
$pais         = '';
$ventInicio   = '';
$ventFin      = '';
$descripcion  = '';
$imagenActual = null;
$precio       = 0.00;
$stock        = 0;

// Si viene id_vino → edit
if ($idVino) {
    $modo = 'edit';
    $stmt = $db->prepare("SELECT * FROM vinos WHERE id_vino = ?");
    $stmt->execute([$idVino]);
    $vino = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vino) {
        $errores[] = "Wine not found.";
    } else {
        $nombre       = $vino['nombre'];
        $bodega       = $vino['bodega'];
        $annada       = $vino['annada'];
        $idDenom      = $vino['id_denominacion'];
        $tipo         = $vino['tipo'];
        $pais         = $vino['pais'];
        $ventInicio   = $vino['ventana_optima_inicio'];
        $ventFin      = $vino['ventana_optima_fin'];
        $descripcion  = $vino['descripcion'];
        $imagenActual = $vino['imagen'];
        $precio       = (float)$vino['precio'];
        $stock        = (int)$vino['stock'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $bodega      = trim($_POST['bodega'] ?? '');
    $annada      = trim($_POST['annada'] ?? '');
    $idDenom     = (int) ($_POST['id_denominacion'] ?? 0);
    $tipo        = trim($_POST['tipo'] ?? '');
    $pais        = trim($_POST['pais'] ?? '');
    $ventInicio  = trim($_POST['ventana_optima_inicio'] ?? '');
    $ventFin     = trim($_POST['ventana_optima_fin'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $stock       = (int) ($_POST['stock'] ?? 0);
    $precio      = (float)($_POST['precio'] ?? 0);

    // Validaciones básicas
    if ($nombre === '') $errores[] = "Name is required.";
    if ($annada === '') $errores[] = "Vintage (year) is required.";
    if ($tipo === '') $errores[] = "Type is required.";
    if ($precio < 0) $errores[] = "Price cannot be negative.";
    if ($stock < 0) $errores[] = "Stock cannot be negative.";

    // --- GESTIÓN DE LA IMAGEN ---
    $nombreImagen = $imagenActual; // por defecto mantenemos la que ya hubiera

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $tmpName  = $_FILES['imagen']['tmp_name'];
            $origName = $_FILES['imagen']['name'];

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $extPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $extPermitidas, true)) {
                $errores[] = "Image must be JPG, PNG, GIF or WEBP.";
            } else {
                $nombreImagen = time() . '_' . preg_replace('/[^A-Za-z0-9_\.-]/', '_', $origName);
                $destino = __DIR__ . '/../img/wines/' . $nombreImagen;

                if (!move_uploaded_file($tmpName, $destino)) {
                    $errores[] = "Error saving the image.";
                }
            }
        } else {
            $errores[] = "Upload error (code " . $_FILES['imagen']['error'] . ").";
        }
    }

    if (!$errores) {
        if ($modo === 'create') {
            // ✅ FIX: placeholders correctos (12)
            $stmt = $db->prepare("
                INSERT INTO vinos
                (nombre, bodega, annada, id_denominacion, tipo, pais,
                 ventana_optima_inicio, ventana_optima_fin, descripcion, imagen, precio, stock)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nombre,
                $bodega !== '' ? $bodega : null,
                $annada,
                $idDenom,
                $tipo,
                $pais !== '' ? $pais : null,
                $ventInicio !== '' ? $ventInicio : null,
                $ventFin !== '' ? $ventFin : null,
                $descripcion !== '' ? $descripcion : null,
                $nombreImagen,
                $precio,
                $stock
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE vinos
                SET nombre = ?, bodega = ?, annada = ?, id_denominacion = ?,
                    tipo = ?, pais = ?, ventana_optima_inicio = ?,
                    ventana_optima_fin = ?, descripcion = ?, imagen = ?, precio = ?, stock = ?
                WHERE id_vino = ?
            ");
            $stmt->execute([
                $nombre,
                $bodega !== '' ? $bodega : null,
                $annada,
                $idDenom,
                $tipo,
                $pais !== '' ? $pais : null,
                $ventInicio !== '' ? $ventInicio : null,
                $ventFin !== '' ? $ventFin : null,
                $descripcion !== '' ? $descripcion : null,
                $nombreImagen,
                $precio,
                $stock,
                $idVino
            ]);
        }

        header("Location: admin_vinos.php");
        exit;
    }
}
?>

<section class="form-hero">
  <div class="form-shell">
    <div class="form-head">
      <div class="form-kicker">Administration</div>
      <h1 class="form-title"><?= $modo === 'create' ? 'Add new wine' : 'Edit wine' ?></h1>
      <p class="form-subtitle">Complete the details, upload a bottle image, and set price/stock.</p>

      <div class="admin-actions" style="margin-top: 14px;">
        <a class="btn btn-sm btn-ghost" href="admin_vinos.php">← Back to wine list</a>
        
      </div>
    </div>

    <?php if ($errores): ?>
      <div class="notice notice-error">
        <strong>Please fix:</strong>
        <ul style="margin:10px 0 0 18px;">
          <?php foreach ($errores as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form class="tasting-form wine-form" method="post" enctype="multipart/form-data">
      <div class="tasting-grid">
        <!-- LEFT CARD -->
        <div class="form-card">
          <div class="section-head">
            <h2 class="section-title">Wine details</h2>
            <p class="section-subtitle">Main info shown on the product page.</p>
          </div>

          <div class="wine-form-grid">
            <div class="field">
              <label for="nombre">Name <span class="req">*</span></label>
              <input id="nombre" type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
            </div>

            <div class="field">
              <label for="bodega">Winery</label>
              <input id="bodega" type="text" name="bodega" value="<?= htmlspecialchars($bodega) ?>">
            </div>

            <div class="field">
              <label for="annada">Vintage (year) <span class="req">*</span></label>
              <input id="annada" type="number" name="annada" value="<?= htmlspecialchars($annada) ?>" required>
            </div>

            <div class="field">
              <label for="id_denominacion">Denomination ID</label>
              <input id="id_denominacion" type="number" name="id_denominacion" value="<?= htmlspecialchars($idDenom) ?>">
              <div class="hint">Use 0 if none / unknown.</div>
            </div>

            <div class="field">
              <label for="tipo">Type <span class="req">*</span></label>
              <input id="tipo" type="text" name="tipo" value="<?= htmlspecialchars($tipo) ?>" required>
            </div>

            <div class="field">
              <label for="pais">Country</label>
              <input id="pais" type="text" name="pais" value="<?= htmlspecialchars($pais) ?>">
            </div>
          </div>

          <div class="divider"></div>

          <div class="field">
            <label for="descripcion">Description</label>
            <textarea id="descripcion" name="descripcion" rows="5"><?= htmlspecialchars($descripcion) ?></textarea>
            <div class="hint">A short, punchy description works best.</div>
          </div>
        </div>

        <!-- RIGHT CARD -->
        <div class="form-card">
          <div class="section-head">
            <h2 class="section-title">Inventory & media</h2>
            <p class="section-subtitle">Pricing, stock, and image.</p>
          </div>

          <div class="field">
            <label for="ventana_optima_inicio">Optimal window start (year)</label>
            <input id="ventana_optima_inicio" type="number" name="ventana_optima_inicio" value="<?= htmlspecialchars($ventInicio) ?>">
          </div>

          <div class="field">
            <label for="ventana_optima_fin">Optimal window end (year)</label>
            <input id="ventana_optima_fin" type="number" name="ventana_optima_fin" value="<?= htmlspecialchars($ventFin) ?>">
          </div>

          <div class="divider"></div>

          <div class="field">
            <label for="imagen">Wine image</label>
            <input id="imagen" type="file" name="imagen" accept="image/*">
            <div class="hint">JPG/PNG/WEBP recommended. Square images look best.</div>

            <div class="wine-image-box">
              <?php if ($imagenActual): ?>
                <img class="wine-image-preview"
                     src="../img/wines/<?= htmlspecialchars($imagenActual) ?>"
                     alt="Wine image">
              <?php else: ?>
                <div class="wine-image-placeholder">No image yet</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="wine-form-grid">
            <div class="field">
              <label for="stock">Stock (bottles)</label>
              <input id="stock" type="number" name="stock" min="0" value="<?= htmlspecialchars($stock) ?>">
            </div>

            <div class="field">
              <label for="precio">Price (€)</label>
              <input id="precio" type="number" name="precio" step="0.01" min="0" value="<?= htmlspecialchars($precio) ?>">
            </div>
          </div>

          <div class="form-actions">
            <button class="btn" type="submit">
              <?= $modo === 'create' ? 'Create wine' : 'Save changes' ?>
            </button>
            <a class="btn btn-ghost" href="admin_vinos.php">Cancel</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
