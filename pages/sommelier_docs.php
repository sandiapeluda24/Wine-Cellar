<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$uid = (int)($_SESSION['usuario']['id_usuario'] ?? 0);

// Flash
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Estado certificado
$stmt = $db->prepare("SELECT certificado, nombre, email FROM usuarios WHERE id_usuario=? LIMIT 1");
$stmt->execute([$uid]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

$isCertified = !empty($me['certificado']);

// Docs
$docs = [];
if ($db->query("SHOW TABLES LIKE 'sommelier_cert_docs'")->fetchColumn()) {
    $stmt = $db->prepare("
      SELECT id, original_name, status, uploaded_at
      FROM sommelier_cert_docs
      WHERE user_id = ?
      ORDER BY uploaded_at DESC, id DESC
      LIMIT 50
    ");
    $stmt->execute([$uid]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Badge class
$certBadge = $isCertified ? 'badge badge-status--active' : 'badge badge-status--inactive';
$certText  = $isCertified ? 'Certified' : 'Not certified';
?>

<section class="admin-hero">
  <div class="admin-shell">
    <div class="admin-head">
      <div class="admin-kicker">Sommelier</div>
      <h1 class="admin-title">My certification documents</h1>
      <p class="admin-subtitle">Upload your certificates and track the review status.</p>

      <div class="tasting-badges" style="margin-top: 10px;">
        <span class="<?= $certBadge ?>"><?= $certText ?></span>
        <span class="badge badge-ghost"><?= htmlspecialchars($me['email'] ?? '') ?></span>
      </div>

      <div class="admin-actions" style="margin-top: 14px;">
      
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="notice notice-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="notice notice-error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="tasting-grid">
      <!-- Upload card -->
      <div class="form-card">
        <div class="section-head">
          <h2 class="section-title">Upload new documents</h2>
          <p class="section-subtitle">Accepted formats: PDF/JPG/PNG. Max 5 files, 10MB each.</p>
        </div>

        <form method="post" action="upload_sommelier_docs.php" enctype="multipart/form-data" class="doc-upload">
          <div class="field">
  <label for="docs">Select files</label>

  <div class="file-picker">
    <input id="docs" class="file-input-hidden" type="file" name="docs[]" multiple accept=".pdf,image/*">
    <label for="docs" class="file-btn">Choose files</label>
    <span id="fileText" class="file-text">No files selected</span>
  </div>

  <div class="hint">Tip: keep filenames short and clear (e.g., WSET2.pdf).</div>
</div>


          <div class="form-actions">
            <button type="submit" class="btn">Upload</button>
            <a class="btn btn-ghost" href="sommelier_docs.php">Refresh</a>
          </div>
        </form>
      </div>

      <!-- List card -->
      <div class="form-card">
        <div class="section-head">
          <h2 class="section-title">Your uploaded documents</h2>
          <p class="section-subtitle">Review status is updated by the admin.</p>
        </div>

        <?php if (empty($docs)): ?>
          <div class="notice">No documents uploaded yet.</div>
        <?php else: ?>
          <div class="doc-cards">
            <?php foreach ($docs as $d): ?>
              <?php
                $st = strtolower(trim((string)($d['status'] ?? 'pending')));
                $badge = 'badge badge-ghost';
                if (in_array($st, ['approved','accepted'], true)) $badge = 'badge badge-status--active';
                if (in_array($st, ['rejected','denied'], true))  $badge = 'badge badge-status--inactive';

                $when = !empty($d['uploaded_at']) ? date('d/m/Y H:i', strtotime($d['uploaded_at'])) : '';
              ?>
              <div class="doc-row">
                <div class="doc-row__left">
                  <div class="doc-row__name"><?= htmlspecialchars($d['original_name'] ?? 'Document') ?></div>
                  <?php if ($when): ?>
                    <div class="doc-row__meta">Uploaded: <span class="cell-mono"><?= htmlspecialchars($when) ?></span></div>
                  <?php endif; ?>
                </div>

                <div class="doc-row__right">
                  <span class="<?= $badge ?>"><?= htmlspecialchars($d['status'] ?? 'pending') ?></span>
                  <a class="btn btn-secondary btn-sm"
                     href="download_sommelier_doc.php?doc_id=<?= (int)$d['id'] ?>">
                    Download
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</section>

<script>
  (function () {
    const input = document.getElementById('docs');
    const text  = document.getElementById('fileText');
    if (!input || !text) return;

    input.addEventListener('change', () => {
      const n = input.files ? input.files.length : 0;
      if (n === 0) text.textContent = 'No files selected';
      else if (n === 1) text.textContent = input.files[0].name;
      else text.textContent = `${n} files selected`;
    });
  })();
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
