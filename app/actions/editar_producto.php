<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_INCLUDES_PATH . '/product_codes.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('productos', ['error' => 'Solicitud invalida']));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$codigo = normalizar_codigo_producto($_POST['codigo'] ?? '');
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));

if ($id <= 0 || $codigo === '' || $descripcion === '') {
    header('Location: ' . page_url('productos', ['error' => 'Complete codigo y descripcion']));
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE productos SET codigo = ?, descripcion = ?, estado = 1 WHERE id = ?');
    $stmt->execute([$codigo, $descripcion, $id]);
    audit_log($pdo, 'update', 'producto', $id, ['codigo' => $codigo]);
    header('Location: ' . page_url('productos', ['msg' => 'Producto actualizado correctamente']));
} catch (Throwable $exception) {
    app_log($pdo, 'error', 'producto_update_failed', 'No se pudo actualizar producto', ['id' => $id, 'error' => $exception->getMessage()]);
    header('Location: ' . page_url('productos', ['error' => 'No se pudo actualizar el producto. Revise si el codigo ya existe.']));
}
exit;


