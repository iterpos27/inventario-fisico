<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('conteo', ['error' => 'Solicitud invalida']));
    exit;
}

$tomaId = (int) ($_POST['toma_id'] ?? 0);
if ($tomaId <= 0) {
    header('Location: ' . page_url('conteo', ['error' => 'Toma invalida']));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM conteo_detalle d
         INNER JOIN conteos c ON c.id = d.conteo_id
         WHERE c.toma_id = ?'
    );
    $stmt->execute([$tomaId]);
    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('La toma tiene conteos con detalle');
    }

    $stmt = $pdo->prepare('DELETE FROM toma_usuarios WHERE toma_id = ?');
    $stmt->execute([$tomaId]);

    $stmt = $pdo->prepare('DELETE FROM conteos WHERE toma_id = ?');
    $stmt->execute([$tomaId]);

    $stmt = $pdo->prepare('DELETE FROM tomas_fisicas WHERE id = ?');
    $stmt->execute([$tomaId]);

    $pdo->commit();
    audit_log($pdo, 'delete', 'toma', $tomaId);
    header('Location: ' . page_url('conteo', ['msg' => 'Toma eliminada correctamente']));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log($pdo, 'error', 'delete_toma_failed', 'No se pudo eliminar toma', ['toma_id' => $tomaId, 'error' => $exception->getMessage()]);
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'eliminar_toma']));
}
exit;
