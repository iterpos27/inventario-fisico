<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$tomaId = (int) ($_POST['toma_id'] ?? 0);
$usuarioId = (int) ($_POST['usuario_id'] ?? 0);

if ($tomaId <= 0 || $usuarioId <= 0 || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('reportes'));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE conteos
         SET estado = 'borrador', fecha_finalizacion = NULL
         WHERE toma_id = ? AND usuario_id = ? AND estado = 'finalizado'"
    );
    $stmt->execute([$tomaId, $usuarioId]);

    $stmt = $pdo->prepare(
        "UPDATE toma_usuarios
         SET estado = 'en_proceso'
         WHERE toma_id = ? AND usuario_id = ?"
    );
    $stmt->execute([$tomaId, $usuarioId]);

    $stmt = $pdo->prepare("UPDATE tomas_fisicas SET estado = 'abierta', fecha_finalizacion = NULL WHERE id = ?");
    $stmt->execute([$tomaId]);

    $pdo->commit();
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'msg' => 'edicion']));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'edicion']));
}
exit;


