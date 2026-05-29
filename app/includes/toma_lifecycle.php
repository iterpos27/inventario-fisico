<?php

declare(strict_types=1);

require_once APP_INCLUDES_PATH . '/excel_exports.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_INCLUDES_PATH . '/toma_summary.php';
require_once APP_PATH . '/repositories/ConteoRepository.php';

function cerrar_tomas_vencidas(PDO $pdo, ?int $onlyTomaId = null): int
{
    $where = "estado = 'abierta'
        AND fecha_cierre IS NOT NULL
        AND TIMESTAMP(fecha_cierre, COALESCE(hora_fin, '23:59:59')) < NOW()";
    $params = [];
    if ($onlyTomaId !== null) {
        $where .= ' AND id = ?';
        $params[] = $onlyTomaId;
    }

    $stmt = $pdo->prepare("SELECT id FROM tomas_fisicas WHERE {$where} ORDER BY id");
    $stmt->execute($params);
    $tomaIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$tomaIds) {
        return 0;
    }

    $cerradas = 0;
    $conteos = new ConteoRepository($pdo);
    foreach ($tomaIds as $tomaId) {
        try {
            $pdo->beginTransaction();
            if (!$conteos->lockToma($tomaId)) {
                $pdo->rollBack();
                continue;
            }

            $stmt = $pdo->prepare(
                "UPDATE tomas_fisicas
                 SET estado = 'finalizada', fecha_finalizacion = COALESCE(fecha_finalizacion, NOW()), archivo_excel = NULL
                 WHERE id = ? AND estado = 'abierta'"
            );
            $stmt->execute([$tomaId]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                continue;
            }

            $stmt = $pdo->prepare(
                'SELECT c.id, c.usuario_id
                 FROM conteos c
                 INNER JOIN conteo_detalle d ON d.conteo_id = c.id
                 WHERE c.toma_id = ? AND c.estado = "borrador"
                 GROUP BY c.id, c.usuario_id'
            );
            $stmt->execute([$tomaId]);
            $conteosConDetalle = $stmt->fetchAll();

            foreach ($conteosConDetalle as $conteo) {
                $archivoExcel = generar_excel_conteo($pdo, (int) $conteo['id']);
                $conteos->finalizarConteo((int) $conteo['id'], $archivoExcel);
                $conteos->finalizarAsignacion($tomaId, (int) $conteo['usuario_id']);
            }

            refresh_toma_summary($pdo, $tomaId);
            $pdo->commit();
            $cerradas++;
            audit_log($pdo, 'auto_close', 'toma', $tomaId, ['reason' => 'expired_window']);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log($pdo, 'error', 'auto_close_toma_failed', 'No se pudo cerrar toma vencida', [
                'toma_id' => $tomaId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    return $cerradas;
}
