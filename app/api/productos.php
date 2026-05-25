<?php
require_once __DIR__ . '/bootstrap.php';

api_require_user($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    api_json(['ok' => true, 'productos' => []]);
}

$stmt = $pdo->prepare(
    'SELECT id, codigo, descripcion
     FROM productos
     WHERE estado = 1 AND (codigo LIKE ? OR descripcion LIKE ?)
     ORDER BY CASE WHEN codigo = ? THEN 0 WHEN codigo LIKE ? THEN 1 ELSE 2 END, descripcion
     LIMIT 30'
);
$stmt->execute(["%{$q}%", "%{$q}%", $q, "{$q}%"]);

api_json(['ok' => true, 'productos' => $stmt->fetchAll()]);
