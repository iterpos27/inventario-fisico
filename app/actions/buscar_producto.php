<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_PATH . '/repositories/ProductRepository.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

echo json_encode((new ProductRepository($pdo))->searchActive($q, 20), JSON_UNESCAPED_UNICODE);


