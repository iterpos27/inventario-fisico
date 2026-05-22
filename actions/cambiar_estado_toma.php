<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$tomaId = (int) ($_POST['toma_id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');

if ($tomaId <= 0 || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/reportes.php');
    exit;
}

try {
    if ($accion === 'cerrar') {
        $stmt = $pdo->prepare("UPDATE tomas_fisicas SET estado = 'finalizada', fecha_finalizacion = NOW() WHERE id = ? AND estado = 'abierta'");
        $stmt->execute([$tomaId]);
    } elseif ($accion === 'reabrir') {
        $stmt = $pdo->prepare("UPDATE tomas_fisicas SET estado = 'abierta', fecha_finalizacion = NULL WHERE id = ? AND estado = 'finalizada'");
        $stmt->execute([$tomaId]);
    }
} catch (Throwable $exception) {
    header('Location: ' . BASE_URL . '/toma_detalle.php?id=' . $tomaId . '&error=estado');
    exit;
}

header('Location: ' . BASE_URL . '/toma_detalle.php?id=' . $tomaId);
exit;
