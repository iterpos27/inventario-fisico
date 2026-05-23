<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/agencias.php?error=Solicitud invalida');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$estado = (int) ($_POST['estado'] ?? 0) === 1 ? 1 : 0;
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/agencias.php?error=Agencia invalida');
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE agencias SET estado = ? WHERE id = ?');
    $stmt->execute([$estado, $id]);
    header('Location: ' . BASE_URL . '/agencias.php?msg=Agencia actualizada correctamente');
} catch (Throwable $exception) {
    header('Location: ' . BASE_URL . '/agencias.php?error=No se pudo actualizar la agencia');
}
exit;

