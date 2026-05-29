<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/permissions.php';
require_once APP_INCLUDES_PATH . '/product_codes.php';
require_once APP_PATH . '/repositories/ProductRepository.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assert_true(role_can('admin', 'admin'), 'admin can administer');
assert_true(role_can('supervisor', 'reports'), 'supervisor can view reports');
assert_true(role_can('reportes', 'reports'), 'reportes can view reports');
assert_true(role_can('usuario', 'api_count'), 'usuario can use count API');
assert_true(!role_can('reportes', 'api_count'), 'reportes cannot use count API');
assert_true(normalizar_codigo_producto(' 100024 ') === '100024', 'product code normalizes spaces');

$requiredTables = ['app_logs', 'audit_logs', 'import_jobs', 'toma_resumen'];
foreach ($requiredTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    assert_true((bool) $stmt->fetchColumn(), "table {$table} exists");
}

$repo = new ProductRepository($pdo);
$results = $repo->searchActive('6202', 5);
assert_true(is_array($results), 'product search returns array');

echo "OK basic checks\n";
