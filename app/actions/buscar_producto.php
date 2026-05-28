<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

$isCodeSearch = preg_match('/^\d+$/', $q) === 1;
if ($isCodeSearch) {
    $stmt = $pdo->prepare(
        'SELECT id, codigo, descripcion
         FROM productos
         WHERE estado = 1 AND codigo LIKE ?
         ORDER BY codigo
         LIMIT 20'
    );
    $stmt->execute(["{$q}%"]);
} else {
    $stmt = $pdo->prepare(
        'SELECT id, codigo, descripcion
         FROM productos
         WHERE estado = 1 AND descripcion LIKE ?
         ORDER BY descripcion, codigo
         LIMIT 20'
    );
    $stmt->execute(["{$q}%"]);
}

echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);


