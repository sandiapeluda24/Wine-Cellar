<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

requireRole('admin');

$db = getDB();
$errors = [];
$editing = false;
$wine = [
    'id_vino' => null,
    'nombre' => '',
    'bodega' => '',
    'annada' => '',
    'id_denominacion' => '',
    'tipo' => '',
    'pais' => '',
    'ventana_optima_inicio' => '',
    'ventana_optima_fin' => '',
    'descripcion' => ''
];

// denominaciones para el <select>
$denStmt = $db->query("SELECT id_denominacion, nombre FROM denominaciones ORDER BY nombre");
$denoms = $denStmt->fetchAll(PDO::FETCH_ASSOC);

// editar existente
if (!empty($_GET['id'])) {
    $editing = true;
    $id = (int) $_GET['id'];
    $stmt = $db->prepare("SELECT * FROM vinos WHERE id_vino = ?");
    $stmt->execute([$id]);
    $wine = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wine) {
        echo "<p>Wine not found.</p>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre  = trim($_POST['nombre'] ?? '');
    $bodega  = trim($_POST['bodega'] ?? '');
    $annada  = (int)($_POST['annada'] ?? 0);
    $denId   = (int)($_POST['id_denominacion'] ?? 0);
    $tipo    = trim($_POST['tipo'] ?? '');
    $pais    = trim($_POST['pais'] ?? '');
    $ventIni = $_POST['ventana_optima_inicio'] !== '' ? (int)$_POST['ventana_optima_inicio'] : null;
    $ventFin = $_POST['ventana_optima_fin'] !== '' ? (int)$_POST['ventana_optima_fin'] : null;
    $desc    = trim($_POST['descripcion'] ?? '');

    if ($nombre === '' || !$annada || !$denId) {
        $errors[] = "Name, vintage and denomination are required.";
    }

    if (empty($errors)) {
        if (!empty($_POST['id_vino'])) {
            // update
            $id = (int) $_POST['id_vino'];
            $stmt = $db->prepare("
                UPDATE vinos
                SET nombre = ?, bodega = ?, annada = ?, id_denominacion = ?,
                    tipo = ?, pais = ?, ventana_optima_inicio = ?, ventana_optima_fin = ?, descripcion = ?
                WHERE id_vino = ?
            ");
            $stmt->execute([$nombre, $bodega, $annada, $denId, $tipo, $pais, $ventIni, $ventFin, $desc, $id]);
        } else {
            // insert
            $stmt = $db->prepare("
                INSERT INTO vinos
                (nombre, bodega, annada, id_denominacion, tipo, pais,
                 ventana_optima_inicio, ventana_optima_fin, descripcion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $bodega, $annada, $denId, $tipo, $pais, $ventIni, $ventFin, $desc]);
        }

        header("Location: " . BASE_URL . "/pages/admin_vinos.php");
        exit;
    } else {
        $wine = [
            'id_vino' => $_POST['id_vino'] ?? null,
            'nombre'  => $nombre,
            'bodega'  => $bodega,
            'annada'  => $annada,
            'id_denominacion' => $denId,
            'tipo'    => $tipo,
            'pais'    => $pais,
            'ventana_optima_inicio' => $ventIni,
            'ventana_optima_fin'    => $ventFin,
            'descripcion'           => $desc
        ];
    }
}
?>

<h2><?= $editing ? 'Edit wine' : 'Add new wine' ?></h2>

<?php foreach ($errors as $e): ?>
    <p class="error"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<form method="post">
    <input type="hidden" name="id_vino" value="<?= htmlspecialchars($wine['id_vino']) ?>">

    <label>Name<br>
        <input type="text" name="nombre" value="<?= htmlspecialchars($wine['nombre']) ?>" required>
    </label><br><br>

    <label>Winery<br>
        <input type="text" name="bodega" value="<?= htmlspecialchars($wine['bodega']) ?>">
    </label><br><br>

    <label>Vintage (year)<br>
        <input type="number" name="annada" value="<?= htmlspecialchars($wine['annada']) ?>" required>
    </label><br><br>

    <label>Denomination<br>
        <select name="id_denominacion" required>
            <option value="">-- Select --</option>
            <?php foreach ($denoms as $d): ?>
                <option value="<?= $d['id_denominacion'] ?>"
                    <?= ($d['id_denominacion'] == $wine['id_denominacion']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label><br><br>

    <label>Type<br>
        <input type="text" name="tipo" value="<?= htmlspecialchars($wine['tipo']) ?>">
    </label><br><br>

    <label>Country<br>
        <input type="text" name="pais" value="<?= htmlspecialchars($wine['pais']) ?>">
    </label><br><br>

    <label>Optimal window start (year)<br>
        <input type="number" name="ventana_optima_inicio"
               value="<?= htmlspecialchars($wine['ventana_optima_inicio']) ?>">
    </label><br><br>

    <label>Optimal window end (year)<br>
        <input type="number" name="ventana_optima_fin"
               value="<?= htmlspecialchars($wine['ventana_optima_fin']) ?>">
    </label><br><br>

    <label>Description<br>
        <textarea name="descripcion" rows="4" cols="50"><?= htmlspecialchars($wine['descripcion']) ?></textarea>
    </label><br><br>

    <button type="submit"><?= $editing ? 'Save changes' : 'Create wine' ?></button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
