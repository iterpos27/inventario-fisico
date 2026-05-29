<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_INCLUDES_PATH . '/toma_window.php';
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

    $lineas = reemplazar_detalle_conteo($pdo, $conteoId, $items);
    if ($lineas === 0) {
        throw new RuntimeException('Sin productos validos');
    }
    $conteoVersion = $conteos->bumpVersion($conteoId);

    $pdo->commit();
    api_json(['ok' => true, 'conteo_id' => $conteoId, 'conteo_version' => $conteoVersion, 'lineas' => $lineas]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log($pdo, 'error', 'guardar_borrador_api_failed', 'No se pudo guardar borrador API', ['conteo_id' => $conteoId, 'error' => $exception->getMessage()]);
    $isVersionConflict = str_contains($exception->getMessage(), 'cambio desde otro dispositivo');
    api_json([
        'ok' => false,
        'message' => $isVersionConflict ? $exception->getMessage() : 'No se pudo guardar el borrador',
    ], $isVersionConflict ? 409 : 422);
}
