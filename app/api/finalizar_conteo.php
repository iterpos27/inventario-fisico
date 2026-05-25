<?php
require_once __DIR__ . '/bootstrap.php';

$user = api_require_user($pdo);
$payload = api_payload();
$items = $payload['items'] ?? [];
$conteoId = (int) ($payload['conteo_id'] ?? 0);

if ($conteoId <= 0 || !is_array($items) || count($items) === 0) {
    api_json(['ok' => false, 'message' => 'Datos incompletos'], 422);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT c.id, c.toma_id
         FROM conteos c
         INNER JOIN tomas_fisicas t ON t.id = c.toma_id
         WHERE c.id = ? AND c.usuario_id = ? AND c.estado = 'borrador' AND t.estado = 'abierta'"
    );
    $stmt->execute([$conteoId, (int) $user['id']]);
    $conteo = $stmt->fetch();
    if (!$conteo) {
        throw new RuntimeException('Conteo no disponible');
    }

    if (reemplazar_detalle_conteo($pdo, $conteoId, $items) === 0) {
        throw new RuntimeException('Sin productos validos');
    }

    $archivoExcel = generar_excel_conteo($pdo, $conteoId);
    $stmt = $pdo->prepare("UPDATE conteos SET estado = 'finalizado', fecha_finalizacion = NOW(), archivo_excel = ? WHERE id = ?");
    $stmt->execute([$archivoExcel, $conteoId]);

    $tomaId = (int) $conteo['toma_id'];
    $stmt = $pdo->prepare("UPDATE toma_usuarios SET estado = 'finalizado' WHERE toma_id = ? AND usuario_id = ?");
    $stmt->execute([$tomaId, (int) $user['id']]);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS asignados,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
         FROM toma_usuarios
         WHERE toma_id = ?"
    );
    $stmt->execute([$tomaId]);
    $avance = $stmt->fetch();
    if ($avance && (int) $avance['asignados'] > 0 && (int) $avance['asignados'] === (int) $avance['finalizados']) {
        $stmt = $pdo->prepare("UPDATE tomas_fisicas SET estado = 'finalizada', fecha_finalizacion = NOW() WHERE id = ?");
        $stmt->execute([$tomaId]);
    }

    $pdo->commit();
    api_json([
        'ok' => true,
        'conteo_id' => $conteoId,
        'download_url' => action_url('descargar_excel', ['id' => $conteoId]),
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_json(['ok' => false, 'message' => 'No se pudo finalizar el conteo'], 500);
}
