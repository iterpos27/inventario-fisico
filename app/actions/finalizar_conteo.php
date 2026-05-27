<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/conteo_items.php';
require_once APP_INCLUDES_PATH . '/excel_exports.php';
require_once APP_INCLUDES_PATH . '/toma_window.php';
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

$items = $payload['items'] ?? [];
$conteoId = (int) ($payload['conteo_id'] ?? 0);

if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Ingrese productos']);
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
    $conteoActivo = $conteos->findActiveDraftForUser($conteoId, (int) $_SESSION['usuario_id'], true);
    if (!$conteoActivo) {
        throw new RuntimeException('Conteo no disponible');
    }
    validar_ventana_toma($conteoActivo);
    $tomaId = (int) $conteoActivo['toma_id'];
    if (!$conteos->lockToma($tomaId)) {
        throw new RuntimeException('Toma no disponible');
    }
    if (reemplazar_detalle_conteo($pdo, $conteoId, $items) === 0) {
        throw new RuntimeException('Sin productos validos');
    }

    $relativePath = generar_excel_conteo($pdo, $conteoId);

    $conteos->finalizarConteo($conteoId, $relativePath);
    $conteos->finalizarAsignacion($tomaId, (int) $_SESSION['usuario_id']);
    $conteos->cerrarTomaSiCompleta($tomaId);

    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'conteo_id' => $conteoId,
        'message' => 'Conteo finalizado',
        'download_url' => action_url('descargar_excel', ['id' => $conteoId]),
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo finalizar el conteo']);
}


