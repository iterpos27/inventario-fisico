<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_INCLUDES_PATH . '/search_cache.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('productos', ['error' => 'Solicitud invalida']));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . page_url('productos', ['error' => 'Producto invalido']));
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE productos SET estado = 0 WHERE id = ?');
    $stmt->execute([$id]);
    search_cache_invalidate();
    audit_log($pdo, 'delete', 'producto', $id);
    header('Location: ' . page_url('productos', ['msg' => 'Producto eliminado correctamente']));
} catch (Throwable $exception) {
    app_log($pdo, 'error', 'producto_delete_failed', 'No se pudo eliminar producto', ['id' => $id, 'error' => $exception->getMessage()]);
    header('Location: ' . page_url('productos', ['error' => 'No se pudo eliminar el producto']));
}
exit;


