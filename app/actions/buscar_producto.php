<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$isCodeSearch = preg_match('/^\d+$/', $q) === 1;
$where = $isCodeSearch
    ? 'estado = 1 AND (codigo = ? OR codigo LIKE ?)'
    : 'estado = 1 AND (codigo = ? OR codigo LIKE ? OR descripcion LIKE ?)';
$params = $isCodeSearch ? [$q, "{$q}%"] : [$q, "{$q}%", "{$q}%"];

$stmt = $pdo->prepare(
    "SELECT id, codigo, descripcion
     FROM productos
     WHERE {$where}
     ORDER BY CASE WHEN codigo = ? THEN 0 WHEN codigo LIKE ? THEN 1 ELSE 2 END, descripcion
     LIMIT 20"
);
$stmt->execute(array_merge($params, [$q, "{$q}%"]));

echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);


