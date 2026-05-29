<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/repositories/ProductRepository.php';

api_require_user($pdo);

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) < 3) {
    api_json(['ok' => true, 'productos' => []]);
}

$startedAt = microtime(true);
$productos = (new ProductRepository($pdo))->searchActive($q, 30);
monitor_duration($pdo, 'product_search_api', $startedAt, 500, ['q' => $q, 'results' => count($productos)]);

api_json([
    'ok' => true,
    'productos' => $productos,
]);
