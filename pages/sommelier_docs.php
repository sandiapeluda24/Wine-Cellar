<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

requireLogin();
$u  = currentUser();
$db = getDB();

if (($u['rol'] ?? '') !== 'sommelier') {
    http_response_code(403);
    echo "<p class='error'>Only sommeliers can access this page.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Refresh certificado from DB to avoid stale session
$userId = (int)($u['id_usuario'] ?? $u['id'] ?? ($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0));
if ($userId > 0) {
    $stmt = $db->prepare("SELECT certificado FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $cert = (int)$stmt->fetchColumn();
    $u['certificado'] = $cert;
    $_SESSION['usuario']['certificado'] = $cert;
}

$errores = [];
$success = null;

// Detect columns
$docCols = [];
foreach ($db->query("DESCRIBE sommelier_cert_docs")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $docCols[] = $row['Field'];
}
$docIdCol     = in_array('id', $docCols, true) ? 'id' : (in_array('doc_id', $docCols, true) ? 'doc_id' : null);
$docUserCol   = in_array('user_id', $docCols, true) ? 'user_id' : (in_array('id_usuario', $docCols, true) ? 'id_usuario' : null);
$docOrigCol   = in_array('original_name', $docCols, true) ? 'original_name' : (in_array('nombre_original', $docCols, true) ? 'nombre_original' : null);
$docStoreCol  = in_array('stored_name', $docCols, true) ? 'stored_name' : (in_array('nombre_guardado', $docCols, true) ? 'nombre_guardado' : null);
$docMimeCol   = in_array('mime_type', $docCols, true) ? 'mime_type' : (in_array('mime', $docCols, true) ? 'mime' : null);
$docSizeCol   = in_array('file_size', $docCols, true) ? 'file_size' : (in_array('tamano', $docCols, true) ? 'tamano' : null);
$docStatusCol = in_array('status', $docCols, true) ? 'status' : (in_array('estado', $docCols, true) ? 'estado' : null);

if (!$docUserCol || !$docOrigCol || !$docStoreCol) {
    echo "<p class='error'>sommelier_cert_docs table schema is not compatible.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $files = $_FILES['cert_docs'] ?? null;

    $hasAnyFile = $files && isset($files['name']) && is_array($files['name'])
        && count(array_filter(array_map('trim', $files['name']))) > 0;

    if (!$hasAnyFile) {
        $errores[] = "Please select at least one file.";
    }

    if (empty($errores)) {
        $allowedMime = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
        ];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        $maxFiles = 5;

        $names    = $files['name'] ?? [];
        $tmpNames = $files['tmp_name'] ?? [];
        $sizes    = $files['size'] ?? [];
        $errorsUp = $files['error'] ?? [];

        $indices = [];
        for ($i = 0; $i < count($names); $i++) {
            $origName = trim((string)($names[$i] ?? ''));
            if ($origName !== '') $indices[] = $i;
        }

        if (count($indices) > $maxFiles) {
            $errores[] = "Too many files. Maximum: $maxFiles";
        }

        if (empty($errores)) {
            $baseUploadDir = __DIR__ . '/../uploads/sommelier_docs';
            if (!is_dir($baseUploadDir) && !mkdir($baseUploadDir, 0775, true)) {
                $errores[] = "Could not create upload directory.";
            }
            $userUploadDir = $baseUploadDir . '/' . $userId;
            if (!is_dir($userUploadDir) && !mkdir($userUploadDir, 0775, true)) {
                $errores[] = "Could not create user upload directory.";
            }
        }

        if (empty($errores)) {
            $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

            try {
                $db->beginTransaction();

                foreach ($indices as $i) {
                    $origName = (string)($names[$i] ?? '');
                    $err = (int)($errorsUp[$i] ?? UPLOAD_ERR_NO_FILE);
                    if ($err !== UPLOAD_ERR_OK) {
                        throw new Exception("Upload error for file: $origName");
                    }

                    $tmp  = (string)($tmpNames[$i] ?? '');
                    $size = (int)($sizes[$i] ?? 0);

                    if ($size <= 0 || $size > $maxFileSize) {
                        throw new Exception("Invalid file size for: $origName");
                    }

                    $mime = $finfo ? $finfo->file($tmp) : (function_exists('mime_content_type') ? mime_content_type($tmp) : '');
                    if (!isset($allowedMime[$mime])) {
                        throw new Exception("File type not allowed: $origName");
                    }

                    $ext = $allowedMime[$mime];
                    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
                    $dest = $userUploadDir . '/' . $storedName;

                    if (!move_uploaded_file($tmp, $dest)) {
                        throw new Exception("Could not save file: $origName");
                    }

                    $cols = [$docUserCol, $docOrigCol, $docStoreCol];
                    $vals = [$userId, $origName, $storedName];

                    if ($docMimeCol)  { $cols[] = $docMimeCol;  $vals[] = $mime; }
                    if ($docSizeCol)  { $cols[] = $docSizeCol;  $vals[] = $size; }
                    if ($docStatusCol){ $cols[] = $docStatusCol; $vals[] = 'pending'; }

                    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                    $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));

                    $stmt = $db->prepare("INSERT INTO sommelier_cert_docs ($colList) VALUES ($placeholders)");
                    $stmt->execute($vals);
                }

                $db->commit();
                $success = 'Documents uploaded. An admin will review them.';

            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $errores[] = 'Upload failed: ' . $e->getMessage();
            }
        }
    }
}

// Fetch docs to show
$docsStmt = $db->prepare(
    "SELECT `$docIdCol` AS doc_id, `$docOrigCol` AS original_name, `$docStoreCol` AS stored_name" .
    ($docStatusCol ? ", `$docStatusCol` AS status" : "") .
    " FROM sommelier_cert_docs WHERE `$docUserCol`=? ORDER BY `$docIdCol` DESC"
);
$docsStmt->execute([$userId]);
$docs = $docsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>My certification documents</h1>

<p>
    Status: <strong><?= !empty($u['certificado']) ? 'Certified' : 'Not certified yet' ?></strong>
</p>

<?php if ($success): ?>
    <p class="success"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<?php foreach ($errores as $e): ?>
    <p class="error"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
    <label>Upload new documents (PDF/JPG/PNG):</label><br>
    <input type="file" name="cert_docs[]" accept="application/pdf,image/jpeg,image/png" multiple required>
    <p style="margin-top:6px; font-size:0.9em;">Max 5 files, 10MB each.</p>
    <button type="submit">Upload</button>
</form>

<h2>Your uploaded documents</h2>
<?php if (empty($docs)): ?>
    <p>No documents uploaded yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($docs as $d): ?>
            <li>
                <?= htmlspecialchars($d['original_name']) ?>
                <?php if (isset($d['status'])): ?>
                    (<?= htmlspecialchars($d['status']) ?>)
                <?php endif; ?>
                — <a href="download_sommelier_doc.php?doc_id=<?= (int)$d['doc_id'] ?>">Download</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
