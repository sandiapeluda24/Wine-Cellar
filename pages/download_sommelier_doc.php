<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Allow: admin OR the owning sommelier
$isLogged = isset($_SESSION['usuario']);
$role = $isLogged ? ($_SESSION['usuario']['rol'] ?? '') : '';
$userIdSession = $isLogged ? (int)($_SESSION['usuario']['id_usuario'] ?? $_SESSION['usuario']['id'] ?? 0) : 0;

if (!$isLogged) {
    http_response_code(403);
    exit('Forbidden');
}

$docId = (int)($_GET['doc_id'] ?? 0);
if ($docId <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$db = getDB();

// detect columns
$docCols = [];
foreach ($db->query("DESCRIBE sommelier_cert_docs")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $docCols[] = $row['Field'];
}
$docIdCol    = in_array('id', $docCols, true) ? 'id' : (in_array('doc_id', $docCols, true) ? 'doc_id' : null);
$docUserCol  = in_array('user_id', $docCols, true) ? 'user_id' : (in_array('id_usuario', $docCols, true) ? 'id_usuario' : null);
$docStoreCol = in_array('stored_name', $docCols, true) ? 'stored_name' : (in_array('nombre_guardado', $docCols, true) ? 'nombre_guardado' : null);
$docOrigCol  = in_array('original_name', $docCols, true) ? 'original_name' : (in_array('nombre_original', $docCols, true) ? 'nombre_original' : null);
$docMimeCol  = in_array('mime_type', $docCols, true) ? 'mime_type' : (in_array('mime', $docCols, true) ? 'mime' : null);

if (!$docIdCol || !$docUserCol || !$docStoreCol || !$docOrigCol) {
    http_response_code(500);
    exit('Schema mismatch');
}

$stmt = $db->prepare(
    "SELECT `$docUserCol` AS user_id, `$docStoreCol` AS stored_name, `$docOrigCol` AS original_name" .
    ($docMimeCol ? ", `$docMimeCol` AS mime_type" : "") .
    " FROM sommelier_cert_docs WHERE `$docIdCol`=?"
);
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    exit('Not found');
}

$ownerUserId = (int)$doc['user_id'];

if ($role !== 'admin' && $ownerUserId !== $userIdSession) {
    http_response_code(403);
    exit('Forbidden');
}

$stored = basename((string)$doc['stored_name']);
$orig   = (string)$doc['original_name'];
$mime   = $doc['mime_type'] ?? 'application/octet-stream';

$path = __DIR__ . '/../uploads/sommelier_docs/' . $ownerUserId . '/' . $stored;
if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found on disk');
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $orig) . '"');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
