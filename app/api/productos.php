<?php
require_once __DIR__ . '/bootstrap.php';

api_require_user($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 3) {
    api_json(['ok' => true, 'productos' => []]);
}

$isCodeSearch = preg_match('/^\d+$/', $q) === 1;
if ($isCodeSearch) {
    $stmt = $pdo->prepare(
        'SELECT id, codigo, descripcion
         FROM productos
         WHERE estado = 1 AND codigo LIKE ?
         ORDER BY codigo
         LIMIT 30'
    );
    $stmt->execute(["{$q}%"]);
} else {
    $stmt = $pdo->prepare(
        'SELECT id, codigo, descripcion
         FROM productos
         WHERE estado = 1 AND descripcion LIKE ?
         ORDER BY descripcion, codigo
         LIMIT 30'
    );
    $stmt->execute(["{$q}%"]);
}

api_json(['ok' => true, 'productos' => $stmt->fetchAll()]);
