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
$nombre      = '';
$bodega      = '';
$annada      = '';
$idDenom     = 0;
$tipo        = '';
$pais        = '';
$ventInicio  = '';
$ventFin     = '';
$descripcion = '';
$imagenActual = null;
$precio      = 0.00;
$stock       = 0;  


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
        $precio       = $vino['precio'];   
        $stock        = $vino['stock'];

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
    $stock  = (int) ($_POST['stock'] ?? 0);  // NUEVO


    // Validaciones básicas
    if ($nombre === '')  $errores[] = "Name is required.";
    if ($annada === '')  $errores[] = "Vintage (year) is required.";
    if ($tipo === '')    $errores[] = "Type is required.";
    if ($precio < 0) {
        $errores[] = "Price cannot be negative.";
    }
    if ($stock < 0) {
    $errores[] = "Stock cannot be negative.";
}



    // --- GESTIÓN DE LA IMAGEN ---
    $nombreImagen = $imagenActual; // por defecto mantenemos la que ya hubiera

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['imagen']['tmp_name'];
            $origName = $_FILES['imagen']['name'];

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $extPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $extPermitidas, true)) {
                $errores[] = "Image must be JPG, PNG, GIF or WEBP.";
            } else {
                // nombre único
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
            $stmt = $db->prepare("
                INSERT INTO vinos
                (nombre, bodega, annada, id_denominacion, tipo, pais,
                 ventana_optima_inicio, ventana_optima_fin, descripcion, imagen, precio, stock)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $precio
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

<h1><?= $modo === 'create' ? 'Add new wine' : 'Edit wine' ?></h1>

<?php foreach ($errores as $e): ?>
    <p class="error"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
    <label>Name:
        <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
    </label><br>

    <label>Winery:
        <input type="text" name="bodega" value="<?= htmlspecialchars($bodega) ?>">
    </label><br>

    <label>Vintage (year):
        <input type="number" name="annada" value="<?= htmlspecialchars($annada) ?>" required>
    </label><br>

    <label>Denomination ID:
        <input type="number" name="id_denominacion" value="<?= htmlspecialchars($idDenom) ?>">
    </label><br>

    <label>Type:
        <input type="text" name="tipo" value="<?= htmlspecialchars($tipo) ?>" required>
    </label><br>

    <label>Country:
        <input type="text" name="pais" value="<?= htmlspecialchars($pais) ?>">
    </label><br>

    <label>Optimal window start (year):
        <input type="number" name="ventana_optima_inicio" value="<?= htmlspecialchars($ventInicio) ?>">
    </label><br>

    <label>Optimal window end (year):
        <input type="number" name="ventana_optima_fin" value="<?= htmlspecialchars($ventFin) ?>">
    </label><br>

    <label>Description:
        <textarea name="descripcion" rows="4"><?= htmlspecialchars($descripcion) ?></textarea>
    </label><br>

    <label>Wine image:
        <input type="file" name="imagen" accept="image/*">
    </label><br>
    
    <label>Stock (bottles):
    <input type="number" name="stock" min="0"
           value="<?= htmlspecialchars($stock) ?>">
</label><br>

    <label>Price (€):
    <input type="number" name="precio" step="0.01" min="0"
           value="<?= htmlspecialchars($precio) ?>">
</label><br>

    <?php if ($imagenActual): ?>
        <p>Current image:<br>
            <img src="../img/wines/<?= htmlspecialchars($imagenActual) ?>" alt="Wine image"
                 style="max-width:150px; height:auto;">
        </p>
    <?php endif; ?>

    <button type="submit">
        <?= $modo === 'create' ? 'Create wine' : 'Save changes' ?>
    </button>
</form>

<p><a href="admin_vinos.php">Back to wine list</a></p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
