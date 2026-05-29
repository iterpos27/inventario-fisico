<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_INCLUDES_PATH . '/toma_window.php';
require_once APP_INCLUDES_PATH . '/toma_summary.php';
require_once APP_PATH . '/repositories/ConteoRepository.php';

$user = api_require_user($pdo);
$payload = api_payload();
$items = $payload['items'] ?? [];
$conteoId = (int) ($payload['conteo_id'] ?? 0);
$expectedVersion = (int) ($payload['conteo_version'] ?? 0);

if ($conteoId <= 0 || !is_array($items) || count($items) === 0) {
    api_json(['ok' => false, 'message' => 'Datos incompletos'], 422);
}

try {
    $pdo->beginTransaction();

    $conteos = new ConteoRepository($pdo);
    $conteo = $conteos->findActiveDraftForUser($conteoId, (int) $user['id'], true);
    if (!$conteo) {
        throw new RuntimeException('Conteo no disponible');
    }
    validar_ventana_toma($conteo);
    $conteos->assertExpectedVersion($conteo, $expectedVersion);
    $tomaId = (int) $conteo['toma_id'];
    if (!$conteos->lockToma($tomaId)) {
        throw new RuntimeException('Toma no disponible');
    }

    if (reemplazar_detalle_conteo($pdo, $conteoId, $items) === 0) {
        throw new RuntimeException('Sin productos validos');
    }
    $conteoVersion = $conteos->bumpVersion($conteoId);

    $archivoExcel = generar_excel_conteo($pdo, $conteoId);
    $conteos->finalizarConteo($conteoId, $archivoExcel);
    $conteos->finalizarAsignacion($tomaId, (int) $user['id']);
    $pdo->prepare('UPDATE tomas_fisicas SET archivo_excel = NULL WHERE id = ?')->execute([$tomaId]);
    $conteos->cerrarTomaSiCompleta($tomaId);
    refresh_toma_summary($pdo, $tomaId);

    $pdo->commit();
    audit_log($pdo, 'finalize_api', 'conteo', $conteoId, ['toma_id' => $tomaId, 'usuario_id' => (int) $user['id']]);
    api_json([
        'ok' => true,
        'conteo_id' => $conteoId,
        'conteo_version' => $conteoVersion,
        'download_url' => action_url('descargar_excel', ['id' => $conteoId]),
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $isVersionConflict = str_contains($exception->getMessage(), 'cambio desde otro dispositivo');
    api_json([
        'ok' => false,
        'message' => $isVersionConflict ? $exception->getMessage() : 'No se pudo finalizar el conteo',
    ], $isVersionConflict ? 409 : 422);
}
