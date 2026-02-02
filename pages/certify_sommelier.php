<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Admin only
if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$userId = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($userId <= 0 || !in_array($action, ['certify', 'reject'], true)) {
    $_SESSION['flash_error'] = 'Invalid request.';
    header('Location: admin_sommelier_certifications.php');
    exit;
}

$db = getDB();

// Detect columns in doc table (supports both EN/ES schemas)
$docCols = [];
foreach ($db->query("DESCRIBE sommelier_cert_docs")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $docCols[] = $row['Field'];
}
$docUserCol   = in_array('user_id', $docCols, true) ? 'user_id' : (in_array('id_usuario', $docCols, true) ? 'id_usuario' : null);
$docStatusCol = in_array('status', $docCols, true) ? 'status' : (in_array('estado', $docCols, true) ? 'estado' : null);

try {
    $db->beginTransaction();

    if ($action === 'certify') {
        $stmt = $db->prepare("UPDATE usuarios SET certificado = 1 WHERE id_usuario = ? AND rol='sommelier'");
        $stmt->execute([$userId]);

        if ($docUserCol && $docStatusCol) {
            $stmt = $db->prepare("UPDATE sommelier_cert_docs SET `$docStatusCol`='approved' WHERE `$docUserCol`=?");
            $stmt->execute([$userId]);
        }

        $_SESSION['flash_success'] = 'Sommelier certified successfully.';
    } else {
        $stmt = $db->prepare("UPDATE usuarios SET certificado = 0 WHERE id_usuario = ? AND rol='sommelier'");
        $stmt->execute([$userId]);

        if ($docUserCol && $docStatusCol) {
            $stmt = $db->prepare("UPDATE sommelier_cert_docs SET `$docStatusCol`='rejected' WHERE `$docUserCol`=?");
            $stmt->execute([$userId]);
        }

        $_SESSION['flash_success'] = 'Documents rejected (sommelier remains not certified).';
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['flash_error'] = 'Could not update certification: ' . $e->getMessage();
}

header('Location: admin_sommelier_certifications.php');
exit;
