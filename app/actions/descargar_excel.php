<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$sql = "SELECT id, archivo_excel FROM conteos WHERE id = ? AND estado = 'finalizado'";
$params = [$id];
if (current_user_role() !== 'admin') {
    $sql .= ' AND usuario_id = ?';
    $params[] = (int) $_SESSION['usuario_id'];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conteo = $stmt->fetch();

if (!$conteo || empty($conteo['archivo_excel'])) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$storageBase = realpath(STORAGE_PATH . '/conteos');
$legacyBase = realpath(UPLOADS_PATH . '/conteos');
$file = realpath(ROOT_PATH . '/' . $conteo['archivo_excel']);
if (!$file && str_starts_with((string) $conteo['archivo_excel'], 'uploads/')) {
    $file = realpath(PUBLIC_PATH . '/' . $conteo['archivo_excel']);
}
$allowedFile = $file
    && (($storageBase && str_starts_with($file, $storageBase)) || ($legacyBase && str_starts_with($file, $legacyBase)))
    && is_file($file);
if (!$allowedFile) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;


