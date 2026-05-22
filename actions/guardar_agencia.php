<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/agencias.php?error=Solicitud invalida');
    exit;
}

$nombre = strtoupper(trim((string) ($_POST['nombre'] ?? '')));
if ($nombre === '') {
    header('Location: ' . BASE_URL . '/agencias.php?error=Ingrese el nombre de la agencia');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO agencias (nombre, estado)
         VALUES (?, 1)
         ON DUPLICATE KEY UPDATE estado = 1'
    );
    $stmt->execute([$nombre]);
    header('Location: ' . BASE_URL . '/agencias.php?msg=Agencia guardada correctamente');
} catch (Throwable $exception) {
    header('Location: ' . BASE_URL . '/agencias.php?error=No se pudo guardar la agencia');
}
exit;
