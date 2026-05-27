<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/excel_exports.php';
require_once APP_PATH . '/repositories/ConteoRepository.php';
require_admin();

$tomaId = (int) ($_POST['toma_id'] ?? 0);
$accion = (string) ($_POST['accion'] ?? '');

if ($tomaId <= 0 || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('reportes'));
    exit;
}

try {
    $conteos = new ConteoRepository($pdo);

    if ($accion === 'cerrar') {
        $pdo->beginTransaction();

        if (!$conteos->lockToma($tomaId)) {
            throw new RuntimeException('Toma no encontrada');
        }

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

        foreach ($conteosConDetalle as $conteo) {
            $archivoExcel = generar_excel_conteo($pdo, (int) $conteo['id']);
            $conteos->finalizarConteo((int) $conteo['id'], $archivoExcel);
            $conteos->finalizarAsignacion($tomaId, (int) $conteo['usuario_id']);
        }

        $pdo->commit();
    } elseif ($accion === 'reabrir') {
        $pdo->beginTransaction();
        if (!$conteos->lockToma($tomaId)) {
            throw new RuntimeException('Toma no encontrada');
        }
        $stmt = $pdo->prepare("UPDATE tomas_fisicas SET estado = 'abierta', fecha_finalizacion = NULL WHERE id = ? AND estado = 'finalizada'");
        $stmt->execute([$tomaId]);
        $pdo->commit();
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'estado']));
    exit;
}

header('Location: ' . page_url('toma_detalle', ['id' => $tomaId]));
exit;


