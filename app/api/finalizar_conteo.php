<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_PATH . '/repositories/ConteoRepository.php';

$user = api_require_user($pdo);
$payload = api_payload();
$items = $payload['items'] ?? [];
$conteoId = (int) ($payload['conteo_id'] ?? 0);

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
    $tomaId = (int) $conteo['toma_id'];
    if (!$conteos->lockToma($tomaId)) {
        throw new RuntimeException('Toma no disponible');
    }

    if (reemplazar_detalle_conteo($pdo, $conteoId, $items) === 0) {
        throw new RuntimeException('Sin productos validos');
    }

    $archivoExcel = generar_excel_conteo($pdo, $conteoId);
    $conteos->finalizarConteo($conteoId, $archivoExcel);
    $conteos->finalizarAsignacion($tomaId, (int) $user['id']);
    $conteos->cerrarTomaSiCompleta($tomaId);

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
