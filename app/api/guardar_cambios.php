<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_INCLUDES_PATH . '/conteo_sync.php';
require_once APP_INCLUDES_PATH . '/toma_window.php';
require_once APP_PATH . '/repositories/ConteoRepository.php';

$user = api_require_user($pdo);
$payload = api_payload();
$upsert = $payload['upsert'] ?? [];
$remove = $payload['remove'] ?? [];
$conteoId = (int) ($payload['conteo_id'] ?? 0);
$expectedVersion = (int) ($payload['conteo_version'] ?? 0);

if ((!is_array($upsert) || count($upsert) === 0) && (!is_array($remove) || count($remove) === 0)) {
    api_json(['ok' => false, 'message' => 'Sin cambios para guardar'], 422);
}
if ($conteoId <= 0) {
    api_json(['ok' => false, 'message' => 'Conteo requerido'], 422);
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

    $lineas = sincronizar_detalle_conteo(
        $pdo,
        $conteoId,
        is_array($upsert) ? $upsert : [],
        is_array($remove) ? $remove : []
    );
    $conteoVersion = $conteos->bumpVersion($conteoId);

    $pdo->commit();
    api_json([
        'ok' => true,
        'conteo_id' => $conteoId,
        'conteo_version' => $conteoVersion,
        'lineas' => $lineas,
        'message' => 'Cambios guardados',
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log($pdo, 'error', 'guardar_cambios_api_failed', 'No se pudo guardar cambios API', [
        'conteo_id' => $conteoId,
        'error' => $exception->getMessage(),
    ]);
    $isVersionConflict = str_contains($exception->getMessage(), 'cambio desde otro dispositivo');
    api_json([
        'ok' => false,
        'message' => $isVersionConflict ? $exception->getMessage() : 'No se pudo guardar los cambios',
    ], $isVersionConflict ? 409 : 422);
}
