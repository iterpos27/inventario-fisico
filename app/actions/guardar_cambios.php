<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/conteo_sync.php';
require_once APP_INCLUDES_PATH . '/toma_window.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_PATH . '/repositories/ConteoRepository.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if (current_user_role() === 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'El administrador solo crea conteos']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !verify_csrf($payload['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Solicitud invalida']);
    exit;
}

$upsert = $payload['upsert'] ?? [];
$remove = $payload['remove'] ?? [];
$conteoId = (int) ($payload['conteo_id'] ?? 0);
$expectedVersion = (int) ($payload['conteo_version'] ?? 0);

if ((!is_array($upsert) || count($upsert) === 0) && (!is_array($remove) || count($remove) === 0)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Sin cambios para guardar']);
    exit;
}
if ($conteoId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Seleccione un conteo creado por el administrador']);
    exit;
}

try {
    $pdo->beginTransaction();

    $conteos = new ConteoRepository($pdo);
    $conteo = $conteos->findActiveDraftForUser($conteoId, (int) $_SESSION['usuario_id'], true);
    if (!$conteo) {
        throw new RuntimeException('Conteo no disponible');
    }
    validar_ventana_toma($conteo);
    $conteos->assertExpectedVersion($conteo, $expectedVersion);
    $lineas = sincronizar_detalle_conteo($pdo, $conteoId, is_array($upsert) ? $upsert : [], is_array($remove) ? $remove : []);
    $conteoVersion = $conteos->bumpVersion($conteoId);

    $pdo->commit();
    echo json_encode([
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
    app_log($pdo, 'error', 'guardar_cambios_failed', 'No se pudo guardar cambios web', ['conteo_id' => $conteoId, 'error' => $exception->getMessage()]);
    $isVersionConflict = str_contains($exception->getMessage(), 'cambio desde otro dispositivo');
    http_response_code($isVersionConflict ? 409 : 500);
    echo json_encode(['ok' => false, 'message' => $isVersionConflict ? $exception->getMessage() : 'No se pudo guardar los cambios']);
}
