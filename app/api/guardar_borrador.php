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
        "SELECT c.id
         FROM conteos c
         INNER JOIN tomas_fisicas t ON t.id = c.toma_id
         WHERE c.id = ? AND c.usuario_id = ? AND c.estado = 'borrador' AND t.estado = 'abierta'"
    );
    $stmt->execute([$conteoId, (int) $user['id']]);
    if (!$stmt->fetch()) {
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
