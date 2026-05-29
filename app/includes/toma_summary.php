<?php

declare(strict_types=1);

function refresh_toma_summary(PDO $pdo, int $tomaId): void
{
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM toma_usuarios WHERE toma_id = ?) AS usuarios_asignados,
            (SELECT COUNT(*) FROM toma_usuarios WHERE toma_id = ? AND estado = 'finalizado') AS usuarios_finalizados,
            (SELECT COUNT(*) FROM conteos WHERE toma_id = ?) AS conteos_creados,
            (
                SELECT COUNT(DISTINCT c.id)
                FROM conteos c
                INNER JOIN conteo_detalle d ON d.conteo_id = c.id
                WHERE c.toma_id = ?
            ) AS conteos_con_detalle,
            (
                SELECT COALESCE(SUM(d.cantidad), 0)
                FROM conteos c
                INNER JOIN conteo_detalle d ON d.conteo_id = c.id
                WHERE c.toma_id = ?
            ) AS unidades_contadas"
    );
    $stmt->execute([$tomaId, $tomaId, $tomaId, $tomaId, $tomaId]);
    $summary = $stmt->fetch();
    if (!$summary) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO toma_resumen
            (toma_id, usuarios_asignados, usuarios_finalizados, conteos_creados, conteos_con_detalle, unidades_contadas, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            usuarios_asignados = VALUES(usuarios_asignados),
            usuarios_finalizados = VALUES(usuarios_finalizados),
            conteos_creados = VALUES(conteos_creados),
            conteos_con_detalle = VALUES(conteos_con_detalle),
            unidades_contadas = VALUES(unidades_contadas),
            updated_at = NOW()'
    );
    $stmt->execute([
        $tomaId,
        (int) $summary['usuarios_asignados'],
        (int) $summary['usuarios_finalizados'],
        (int) $summary['conteos_creados'],
        (int) $summary['conteos_con_detalle'],
        (float) $summary['unidades_contadas'],
    ]);
}
