<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_INCLUDES_PATH . '/product_codes.php';
require_once APP_INCLUDES_PATH . '/search_cache.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('productos', ['error' => 'Solicitud invalida']));
    exit;
}

$codigo = normalizar_codigo_producto($_POST['codigo'] ?? '');
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));

if ($codigo === '' || $descripcion === '') {
    header('Location: ' . page_url('productos', ['error' => 'Complete codigo y descripcion']));
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO productos (codigo, descripcion, estado)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), estado = 1'
    );
    $stmt->execute([$codigo, $descripcion]);
    search_cache_invalidate();
    audit_log($pdo, 'save', 'producto', null, ['codigo' => $codigo]);
    header('Location: ' . page_url('productos', ['msg' => 'Producto guardado correctamente']));
} catch (Throwable $exception) {
    app_log($pdo, 'error', 'producto_save_failed', 'No se pudo guardar producto', ['codigo' => $codigo, 'error' => $exception->getMessage()]);
    header('Location: ' . page_url('productos', ['error' => 'No se pudo guardar el producto']));
}
exit;


