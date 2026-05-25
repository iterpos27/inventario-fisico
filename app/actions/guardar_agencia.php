<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('agencias', ['error' => 'Solicitud invalida']));
    exit;
}

$nombre = strtoupper(trim((string) ($_POST['nombre'] ?? '')));
if ($nombre === '') {
    header('Location: ' . page_url('agencias', ['error' => 'Ingrese el nombre de la agencia']));
    exit;
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO agencias (nombre, estado)
         VALUES (?, 1)
         ON DUPLICATE KEY UPDATE estado = 1'
    );
    $stmt->execute([$nombre]);
    header('Location: ' . page_url('agencias', ['msg' => 'Agencia guardada correctamente']));
} catch (Throwable $exception) {
    header('Location: ' . page_url('agencias', ['error' => 'No se pudo guardar la agencia']));
}
exit;


