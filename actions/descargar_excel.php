<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
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

$base = realpath(dirname(__DIR__) . '/uploads/conteos');
$file = realpath(dirname(__DIR__) . '/' . $conteo['archivo_excel']);
if (!$base || !$file || !str_starts_with($file, $base) || !is_file($file)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
