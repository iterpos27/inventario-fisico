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
    if (!$conteos->findActiveDraftForUser($conteoId, (int) $user['id'], true)) {
        throw new RuntimeException('Conteo no disponible');
    }

    $lineas = reemplazar_detalle_conteo($pdo, $conteoId, $items);
    if ($lineas === 0) {
        throw new RuntimeException('Sin productos validos');
    }

    $pdo->commit();
    api_json(['ok' => true, 'conteo_id' => $conteoId, 'lineas' => $lineas]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_json(['ok' => false, 'message' => 'No se pudo guardar el borrador'], 500);
}
