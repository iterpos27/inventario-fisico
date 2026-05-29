<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_INCLUDES_PATH . '/toma_window.php';
require_once APP_INCLUDES_PATH . '/toma_lifecycle.php';

$user = api_require_user($pdo);
$payload = api_payload();
$tomaId = (int) ($payload['toma_id'] ?? 0);
if ($tomaId <= 0) {
    api_json(['ok' => false, 'message' => 'Toma invalida'], 422);
}

try {
    cerrar_tomas_vencidas($pdo, $tomaId);
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT t.id, t.nombre_toma, t.fecha_habilitacion, t.fecha_cierre, t.hora_inicio, t.hora_fin
         FROM tomas_fisicas t
         INNER JOIN toma_usuarios tu ON tu.toma_id = t.id
         WHERE t.id = ? AND tu.usuario_id = ? AND t.estado = 'abierta'"
    );
    $stmt->execute([$tomaId, (int) $user['id']]);
    $toma = $stmt->fetch();
    if (!$toma) {
        throw new RuntimeException('Toma no disponible');
    }
    validar_ventana_toma($toma);

    $stmt = $pdo->prepare('SELECT id FROM conteos WHERE toma_id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$tomaId, (int) $user['id']]);
    $conteoId = (int) ($stmt->fetchColumn() ?: 0);

    if ($conteoId === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO conteos (toma_id, usuario_id, nombre_conteo, estado, fecha_inicio)
             VALUES (?, ?, ?, "borrador", NOW())'
        );
        $stmt->execute([$tomaId, (int) $user['id'], $toma['nombre_toma']]);
        $conteoId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("UPDATE toma_usuarios SET estado = 'en_proceso' WHERE toma_id = ? AND usuario_id = ? AND estado = 'asignado'");
    $stmt->execute([$tomaId, (int) $user['id']]);

    $pdo->commit();
    api_json(['ok' => true, 'conteo_id' => $conteoId]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_json(['ok' => false, 'message' => $exception->getMessage()], 422);
}
