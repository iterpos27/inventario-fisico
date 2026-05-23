<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/productos.php?error=Solicitud invalida');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$codigo = trim((string) ($_POST['codigo'] ?? ''));
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));

if ($id <= 0 || $codigo === '' || $descripcion === '') {
    header('Location: ' . BASE_URL . '/productos.php?error=Complete codigo y descripcion');
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE productos SET codigo = ?, descripcion = ?, estado = 1 WHERE id = ?');
    $stmt->execute([$codigo, $descripcion, $id]);
    header('Location: ' . BASE_URL . '/productos.php?msg=Producto actualizado correctamente');
} catch (Throwable $exception) {
    header('Location: ' . BASE_URL . '/productos.php?error=No se pudo actualizar el producto. Revise si el codigo ya existe.');
}
exit;

