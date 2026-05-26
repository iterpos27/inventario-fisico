<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('agencias', ['error' => 'Solicitud invalida']));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$nombre = strtoupper(trim((string) ($_POST['nombre'] ?? '')));
$estado = (int) ($_POST['estado'] ?? 0) === 1 ? 1 : 0;

if ($id <= 0 || $nombre === '') {
    header('Location: ' . page_url('agencias', ['error' => 'Complete los datos de la agencia']));
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE agencias SET nombre = ?, estado = ? WHERE id = ?');
    $stmt->execute([$nombre, $estado, $id]);
    header('Location: ' . page_url('agencias', ['msg' => 'Agencia actualizada correctamente']));
} catch (Throwable $exception) {
    header('Location: ' . page_url('agencias', ['error' => 'No se pudo actualizar la agencia']));
}
exit;

