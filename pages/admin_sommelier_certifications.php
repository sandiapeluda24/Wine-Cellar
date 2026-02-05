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
    echo "<div class='notice notice-error'>sommelier_cert_docs schema not compatible.</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $db->query("SELECT id_usuario, nombre, email, certificado FROM usuarios WHERE rol='sommelier' ORDER BY nombre");
$sommeliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function docBadgeClass(?string $status): string {
    if ($status === null) return 'badge badge-ghost';
    $s = strtolower(trim($status));
    if (in_array($s, ['rejected','rechazado','denied'], true)) return 'badge badge-status--inactive';
    if (in_array($s, ['approved','accepted','aprobado','aceptado'], true)) return 'badge badge-status--active';
    return 'badge badge-ghost';
}
?>

<section class="admin-hero">
  <div class="admin-shell">
    <div class="admin-head">
      <div class="admin-kicker">Administration</div>
      <h1 class="admin-title">Sommelier certifications</h1>
      <p class="admin-subtitle">Review uploaded documents and certify or reject sommeliers.</p>

      <div class="admin-actions" style="margin-top: 14px;">
        <a class="btn btn-sm btn-ghost" href="admin_panel.php">← Back to admin panel</a>
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="notice notice-success"><?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="notice notice-error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <?php if (empty($sommeliers)): ?>
      <div class="notice notice-error">No sommeliers found.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="somm-cert-table">
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

              $isCert = !empty($s['certificado']);
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($s['nombre']) ?></strong></td>
              <td class="cell-mono"><?= htmlspecialchars($s['email']) ?></td>
              <td>
                <?php if ($isCert): ?>
                  <span class="badge badge-status--active">Yes</span>
                <?php else: ?>
                  <span class="badge badge-status--inactive">No</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if (empty($docs)): ?>
                  <em class="doc-empty">No docs</em>
                <?php else: ?>
                  <ul class="doc-list">
                    <?php foreach ($docs as $d): ?>
                      <li class="doc-item">
                        <a class="doc-link" href="download_sommelier_doc.php?doc_id=<?= (int)$d['doc_id'] ?>">
                          <?= htmlspecialchars($d['original_name']) ?>
                        </a>
                        <?php if (isset($d['status'])): ?>
                          <span class="doc-status <?= docBadgeClass((string)$d['status']) ?>">
                            <?= htmlspecialchars((string)$d['status']) ?>
                          </span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </td>

              <td>
                <div class="table-actions">
                  <form method="post" action="certify_sommelier.php" class="inline-form">
                    <input type="hidden" name="user_id" value="<?= (int)$s['id_usuario'] ?>">
                    <button type="submit" name="action" value="certify" class="btn btn-success btn-sm">Certify</button>
                  </form>

                  <form method="post" action="certify_sommelier.php" class="inline-form">
                    <input type="hidden" name="user_id" value="<?= (int)$s['id_usuario'] ?>">
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject docs</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

