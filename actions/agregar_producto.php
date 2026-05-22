<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/agregar_producto.php?error=Solicitud invalida');
    exit;
}

$codigo = trim((string) ($_POST['codigo'] ?? ''));
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));

if ($codigo === '' || $descripcion === '') {
    header('Location: ' . BASE_URL . '/agregar_producto.php?error=Complete codigo y descripcion');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO productos (codigo, descripcion, estado)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), estado = 1'
    );
    $stmt->execute([$codigo, $descripcion]);
    header('Location: ' . BASE_URL . '/agregar_producto.php?msg=Producto guardado correctamente');
} catch (Throwable $exception) {
    header('Location: ' . BASE_URL . '/agregar_producto.php?error=No se pudo guardar el producto');
}
exit;
