<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';

$errores = [];

// If user is already logged in, no need to register again
if (isLoggedIn()) {
    $u = currentUser();
    echo "<p>You are already logged in as <strong>" . htmlspecialchars($u['nombre']) . "</strong>.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol      = $_POST['rol'] ?? 'coleccionista';
    $somDesc  = trim($_POST['sommelier_description'] ?? '');
    $files    = $_FILES['cert_docs'] ?? null;

    // Never allow admin from form
    if ($rol !== 'coleccionista' && $rol !== 'sommelier') {
        $rol = 'coleccionista';
    }

    // Basic validation
    if ($nombre === '' || $email === '' || $password === '') {
        $errores[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Email is not valid.";
    }

    // Sommeliers must upload docs for admin review
    if ($rol === 'sommelier') {
        $hasAnyFile = $files
            && isset($files['name'])
            && is_array($files['name'])
            && count(array_filter(array_map('trim', $files['name']))) > 0;

        if (!$hasAnyFile) {
            $errores[] = "To register as a sommelier, please upload at least one certification document (PDF/JPG/PNG).";
        }
    }

    if (empty($errores)) {
        $movedFiles = [];

        try {
            $db = getDB();

            // Check if email already exists
            $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errores[] = "There is already a user with this email.";
            } else {
                $db->beginTransaction();

                // IMPORTANT: certification is only set by an admin review
                $certificado = 0;
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $somDescToSave = ($rol === 'sommelier') ? $somDesc : null;

                // Passwords are stored hashed (password_hash)
                $stmt = $db->prepare(
                    "INSERT INTO usuarios (nombre, email, password, rol, certificado, sommelier_description)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$nombre, $email, $hash, $rol, $certificado, $somDescToSave]);
                $userId = (int)$db->lastInsertId();

                // If sommelier: store certification docs for admin review
                if ($rol === 'sommelier') {
                    // Ensure docs table exists
                    $tableExists = $db->query("SHOW TABLES LIKE 'sommelier_cert_docs'")->fetchColumn();
                    if (!$tableExists) {
                        throw new Exception("Missing table sommelier_cert_docs. Please run the SQL script first.");
                    }

                    // Detect columns (so it works whether you used user_id or id_usuario)
                    $docCols = [];
                    foreach ($db->query("DESCRIBE sommelier_cert_docs")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $docCols[] = $row['Field'];
                    }

                    $docUserCol   = in_array('user_id', $docCols, true) ? 'user_id' : (in_array('id_usuario', $docCols, true) ? 'id_usuario' : null);
                    $docOrigCol   = in_array('original_name', $docCols, true) ? 'original_name' : (in_array('nombre_original', $docCols, true) ? 'nombre_original' : null);
                    $docStoreCol  = in_array('stored_name', $docCols, true) ? 'stored_name' : (in_array('nombre_guardado', $docCols, true) ? 'nombre_guardado' : null);
                    $docMimeCol   = in_array('mime_type', $docCols, true) ? 'mime_type' : (in_array('mime', $docCols, true) ? 'mime' : null);
                    $docSizeCol   = in_array('file_size', $docCols, true) ? 'file_size' : (in_array('tamano', $docCols, true) ? 'tamano' : null);
                    $docStatusCol = in_array('status', $docCols, true) ? 'status' : (in_array('estado', $docCols, true) ? 'estado' : null);

                    if (!$docUserCol || !$docOrigCol || !$docStoreCol) {
                        throw new Exception("sommelier_cert_docs schema does not match expected columns.");
                    }

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
                    if (is_array($names)) {
                        for ($i = 0; $i < count($names); $i++) {
                            $origName = trim((string)($names[$i] ?? ''));
                            if ($origName !== '') $indices[] = $i;
                        }
                    }

                    if (count($indices) < 1) {
                        throw new Exception("Please upload at least one certification document (PDF/JPG/PNG).");
                    }
                    if (count($indices) > $maxFiles) {
                        throw new Exception("Too many files. Maximum: $maxFiles");
                    }

                    // Prepare upload dirs
                    $baseUploadDir = __DIR__ . '/../uploads/sommelier_docs';
                    if (!is_dir($baseUploadDir) && !mkdir($baseUploadDir, 0775, true)) {
                        throw new Exception("Could not create upload directory.");
                    }
                    $userUploadDir = $baseUploadDir . '/' . $userId;
                    if (!is_dir($userUploadDir) && !mkdir($userUploadDir, 0775, true)) {
                        throw new Exception("Could not create user upload directory.");
                    }

                    $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

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
                        $movedFiles[] = $dest;

                        // Build INSERT dynamically (status may or may not exist)
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
                }

                $db->commit();

                // Auto login after register
                $_SESSION['usuario'] = [
                    'id'          => $userId,
                    'id_usuario'  => $userId,
                    'nombre'      => $nombre,
                    'rol'         => $rol,
                    'certificado' => 0,
                ];

                if ($rol === 'sommelier') {
                    $_SESSION['flash_success'] = 'Your sommelier account was created. An admin will review your documents to certify you.';
                }

                header("Location: " . BASE_URL . "/index.php");
                exit;
            }

        } catch (Exception $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            // Remove any files we already moved
            if (!empty($movedFiles)) {
                foreach ($movedFiles as $p) {
                    if (is_string($p) && $p !== '' && file_exists($p)) @unlink($p);
                }
            }
            $errores[] = "Error while registering: " . $e->getMessage();
        }
    }
}
?>

<div class="auth-hero">
  <div class="auth-card auth-card-lg">
    <div class="auth-kicker">Wine Cellar</div>
    <h2 class="auth-title">Create your account</h2>
    <p class="auth-subtitle">Register as a collector or request a sommelier account.</p>

    <?php if (!empty($errores)): ?>
      <?php foreach ($errores as $e): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" id="formRegistro" enctype="multipart/form-data" class="auth-form auth-form-grid" novalidate>

      <div class="field">
        <label for="reg_name">Name</label>
        <input id="reg_name" type="text" name="nombre"
               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
               placeholder="Your name" required>
      </div>

      <div class="field">
        <label for="reg_email">Email</label>
        <input id="reg_email" type="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required>
      </div>

      <div class="field">
        <label for="reg_password">Password</label>
        <input id="reg_password" type="password" name="password"
               placeholder="Create a password" required>
      </div>

      <div class="field">
        <label>User type</label>
        <div class="segmented">
          <label class="seg-item">
            <input type="radio" name="rol" value="coleccionista" <?= (($_POST['rol'] ?? 'coleccionista') === 'coleccionista') ? 'checked' : '' ?>>
            Collector
          </label>
          <label class="seg-item">
            <input type="radio" name="rol" value="sommelier" <?= (($_POST['rol'] ?? '') === 'sommelier') ? 'checked' : '' ?>>
            Sommelier
          </label>
        </div>
      </div>

      <div id="sommelier-description-wrapper" style="display:none; grid-column: 1 / -1;">
        <div class="field">
          <label for="som_desc">Sommelier description (optional)</label>
          <textarea id="som_desc" name="sommelier_description" rows="4"
                    placeholder="Describe your skills and background..."><?= htmlspecialchars($_POST['sommelier_description'] ?? '') ?></textarea>
        </div>

        <div class="field">
          <label for="cert_docs">Upload certification documents (required)</label>
          <input type="file" name="cert_docs[]" id="cert_docs"
                 accept="application/pdf,image/jpeg,image/png" multiple>
          <div class="help">Accepted: PDF, JPG, PNG. Max 5 files, 10MB each.</div>
        </div>
      </div>

      <div class="auth-actions" style="grid-column: 1 / -1;">
        <button type="submit" class="btn btn-lg">Create account</button>
      </div>

      <div class="auth-links" style="grid-column: 1 / -1;">
        Already have an account?
        <a href="<?= BASE_URL ?>/pages/login.php">Log in</a>
      </div>

    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleInputs = document.querySelectorAll('input[name="rol"], select[name="rol"]');
    const descWrapper = document.getElementById('sommelier-description-wrapper');

    if (!roleInputs.length || !descWrapper) return;

    function updateDescriptionVisibility() {
        let selectedRole = null;

        roleInputs.forEach(function (el) {
            if (el.tagName === 'SELECT') {
                selectedRole = el.value;
            } else if ((el.type === 'radio' || el.type === 'checkbox') && el.checked) {
                selectedRole = el.value;
            }
        });

        const fileInput = document.getElementById('cert_docs');

        if (selectedRole === 'sommelier') {
            descWrapper.style.display = '';
            if (fileInput) fileInput.required = true;
        } else {
            descWrapper.style.display = 'none';
            const textarea = descWrapper.querySelector('textarea');
            if (textarea) textarea.value = '';
            if (fileInput) {
                fileInput.required = false;
                fileInput.value = '';
            }
        }
    }

    roleInputs.forEach(function (el) {
        el.addEventListener('change', updateDescriptionVisibility);
    });

    updateDescriptionVisibility();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
