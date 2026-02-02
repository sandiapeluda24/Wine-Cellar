<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDB();

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Detect columns in doc table
$docCols = [];
foreach ($db->query("DESCRIBE sommelier_cert_docs")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $docCols[] = $row['Field'];
}
$docIdCol     = in_array('id', $docCols, true) ? 'id' : (in_array('doc_id', $docCols, true) ? 'doc_id' : null);
$docUserCol   = in_array('user_id', $docCols, true) ? 'user_id' : (in_array('id_usuario', $docCols, true) ? 'id_usuario' : null);
$docOrigCol   = in_array('original_name', $docCols, true) ? 'original_name' : (in_array('nombre_original', $docCols, true) ? 'nombre_original' : null);
$docStatusCol = in_array('status', $docCols, true) ? 'status' : (in_array('estado', $docCols, true) ? 'estado' : null);

if (!$docIdCol || !$docUserCol || !$docOrigCol) {
    echo "<p class='warning'>sommelier_cert_docs schema not compatible.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $db->query("SELECT id_usuario, nombre, email, certificado FROM usuarios WHERE rol='sommelier' ORDER BY nombre");
$sommeliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Sommelier certifications</h1>
<p><a href="admin_panel.php">&larr; Back to admin panel</a></p>

<?php if ($flashSuccess): ?>
    <p class="success"><?= htmlspecialchars($flashSuccess) ?></p>
<?php endif; ?>
<?php if ($flashError): ?>
    <p class="error"><?= htmlspecialchars($flashError) ?></p>
<?php endif; ?>

<?php if (empty($sommeliers)): ?>
    <p>No sommeliers found.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Sommelier</th>
                <th>Email</th>
                <th>Certified</th>
                <th>Documents</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sommeliers as $s): ?>
            <?php
            $docsStmt = $db->prepare(
                "SELECT `$docIdCol` AS doc_id, `$docOrigCol` AS original_name" .
                ($docStatusCol ? ", `$docStatusCol` AS status" : "") .
                " FROM sommelier_cert_docs WHERE `$docUserCol`=? ORDER BY `$docIdCol` DESC"
            );
            $docsStmt->execute([(int)$s['id_usuario']]);
            $docs = $docsStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <tr>
                <td><?= htmlspecialchars($s['nombre']) ?></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td><?= !empty($s['certificado']) ? 'Yes' : 'No' ?></td>
                <td>
                    <?php if (empty($docs)): ?>
                        <em>No docs</em>
                    <?php else: ?>
                        <ul style="margin:0; padding-left:18px;">
                            <?php foreach ($docs as $d): ?>
                                <li>
                                    <a href="download_sommelier_doc.php?doc_id=<?= (int)$d['doc_id'] ?>">
                                        <?= htmlspecialchars($d['original_name']) ?>
                                    </a>
                                    <?php if (isset($d['status'])): ?>
                                        (<?= htmlspecialchars($d['status']) ?>)
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="certify_sommelier.php" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= (int)$s['id_usuario'] ?>">
                        <button type="submit" name="action" value="certify">Certify</button>
                    </form>

                    <form method="post" action="certify_sommelier.php" style="display:inline; margin-left:8px;">
                        <input type="hidden" name="user_id" value="<?= (int)$s['id_usuario'] ?>">
                        <button type="submit" name="action" value="reject">Reject docs</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
