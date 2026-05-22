<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/productos.php?error=Solicitud invalida');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/productos.php?error=Producto invalido');
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE productos SET estado = 0 WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: ' . BASE_URL . '/productos.php?msg=Producto eliminado correctamente');
} catch (Throwable $exception) {
    header('Location: ' . BASE_URL . '/productos.php?error=No se pudo eliminar el producto');
}
exit;
