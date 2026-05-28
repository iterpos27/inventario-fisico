<?php
require_once __DIR__ . '/bootstrap.php';

api_require_user($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    api_json(['ok' => true, 'productos' => []]);
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
     LIMIT 30"
);
$stmt->execute(array_merge($params, [$q, "{$q}%"]));

api_json(['ok' => true, 'productos' => $stmt->fetchAll()]);
