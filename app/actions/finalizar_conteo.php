<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/conteo_items.php';
require_once APP_INCLUDES_PATH . '/excel_exports.php';
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

    $stmt = $pdo->prepare(
        "SELECT c.id, c.toma_id
         FROM conteos c
         INNER JOIN tomas_fisicas t ON t.id = c.toma_id
         WHERE c.id = ? AND c.usuario_id = ? AND c.estado = 'borrador' AND t.estado = 'abierta'"
    );
    $stmt->execute([$conteoId, (int) $_SESSION['usuario_id']]);
    $conteoActivo = $stmt->fetch();
    if (!$conteoActivo) {
        throw new RuntimeException('Conteo no disponible');
    }
    $tomaId = (int) $conteoActivo['toma_id'];
    if (reemplazar_detalle_conteo($pdo, $conteoId, $items) === 0) {
        throw new RuntimeException('Sin productos validos');
    }

    $relativePath = generar_excel_conteo($pdo, $conteoId);

    $stmt = $pdo->prepare("UPDATE conteos SET estado = 'finalizado', fecha_finalizacion = NOW(), archivo_excel = ? WHERE id = ?");
    $stmt->execute([$relativePath, $conteoId]);

    $stmt = $pdo->prepare("UPDATE toma_usuarios SET estado = 'finalizado' WHERE toma_id = ? AND usuario_id = ?");
    $stmt->execute([$tomaId, (int) $_SESSION['usuario_id']]);

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


