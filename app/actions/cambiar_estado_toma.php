<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/excel_exports.php';
require_admin();

$tomaId = (int) ($_POST['toma_id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');

if ($tomaId <= 0 || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/reportes.php');
    exit;
}

try {
    if ($accion === 'cerrar') {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE tomas_fisicas SET estado = 'finalizada', fecha_finalizacion = NOW() WHERE id = ? AND estado = 'abierta'");
        $stmt->execute([$tomaId]);

        $stmt = $pdo->prepare(
            'SELECT c.id, c.usuario_id
             FROM conteos c
             INNER JOIN conteo_detalle d ON d.conteo_id = c.id
             WHERE c.toma_id = ?
             GROUP BY c.id, c.usuario_id'
        );
        $stmt->execute([$tomaId]);
        $conteosConDetalle = $stmt->fetchAll();

        $stmtFinalizarConteo = $pdo->prepare(
            "UPDATE conteos
             SET estado = 'finalizado', fecha_finalizacion = COALESCE(fecha_finalizacion, NOW()), archivo_excel = ?
             WHERE id = ?"
        );
        $stmtFinalizarUsuario = $pdo->prepare(
            "UPDATE toma_usuarios SET estado = 'finalizado' WHERE toma_id = ? AND usuario_id = ?"
        );

        foreach ($conteosConDetalle as $conteo) {
            $archivoExcel = generar_excel_conteo($pdo, (int) $conteo['id']);
            $stmtFinalizarConteo->execute([$archivoExcel, (int) $conteo['id']]);
            $stmtFinalizarUsuario->execute([$tomaId, (int) $conteo['usuario_id']]);
        }

        $pdo->commit();
    } elseif ($accion === 'reabrir') {
        $stmt = $pdo->prepare("UPDATE tomas_fisicas SET estado = 'abierta', fecha_finalizacion = NULL WHERE id = ? AND estado = 'finalizada'");
        $stmt->execute([$tomaId]);
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . BASE_URL . '/toma_detalle.php?id=' . $tomaId . '&error=estado');
    exit;
}

header('Location: ' . BASE_URL . '/toma_detalle.php?id=' . $tomaId);
exit;

