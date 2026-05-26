<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('usuarios', ['error' => 'Solicitud invalida']));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0 || $id === (int) ($_SESSION['usuario_id'] ?? 0)) {
    header('Location: ' . page_url('usuarios', ['error' => 'Usuario invalido']));
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE usuarios SET estado = 0 WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: ' . page_url('usuarios', ['msg' => 'Usuario desactivado correctamente']));
} catch (Throwable $exception) {
    header('Location: ' . page_url('usuarios', ['error' => 'No se pudo desactivar el usuario']));
}
exit;

