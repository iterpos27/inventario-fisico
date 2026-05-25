<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$tomaId = (int) ($_GET['toma_id'] ?? 0);
if ($tomaId <= 0) {
    http_response_code(404);
    exit('Toma no encontrada');
}

$stmt = $pdo->prepare('SELECT id, numero_toma, nombre_toma, archivo_excel FROM tomas_fisicas WHERE id = ?');
$stmt->execute([$tomaId]);
$toma = $stmt->fetch();
if (!$toma) {
    http_response_code(404);
    exit('Toma no encontrada');
}
if (empty($toma['archivo_excel'])) {
    http_response_code(404);
    exit('Consolidado no generado');
}

$storageBase = realpath(STORAGE_PATH . '/conteos');
$legacyBase = realpath(UPLOADS_PATH . '/conteos');
$fullPath = realpath(ROOT_PATH . '/' . $toma['archivo_excel']);
if (!$fullPath && str_starts_with((string) $toma['archivo_excel'], 'uploads/')) {
    $fullPath = realpath(PUBLIC_PATH . '/' . $toma['archivo_excel']);
}
$allowedFile = $fullPath
    && (($storageBase && str_starts_with($fullPath, $storageBase)) || ($legacyBase && str_starts_with($fullPath, $legacyBase)))
    && is_file($fullPath);
if (!$allowedFile) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;


