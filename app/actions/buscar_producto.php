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

$stmt = $pdo->prepare(
    'SELECT id, codigo, descripcion
     FROM productos
     WHERE estado = 1 AND (codigo LIKE ? OR descripcion LIKE ?)
     ORDER BY CASE WHEN codigo = ? THEN 0 WHEN codigo LIKE ? THEN 1 ELSE 2 END, descripcion
     LIMIT 20'
);
$stmt->execute(["%{$q}%", "%{$q}%", $q, "{$q}%"]);

echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);


