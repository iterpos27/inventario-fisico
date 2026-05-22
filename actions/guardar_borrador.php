<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
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
        "SELECT c.id
         FROM conteos c
         INNER JOIN tomas_fisicas t ON t.id = c.toma_id
         WHERE c.id = ? AND c.usuario_id = ? AND c.estado = 'borrador' AND t.estado = 'abierta'"
    );
    $stmt->execute([$conteoId, (int) $_SESSION['usuario_id']]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Conteo no disponible');
    }
    $pdo->prepare('DELETE FROM conteo_detalle WHERE conteo_id = ?')->execute([$conteoId]);

    $stmtProducto = $pdo->prepare('SELECT codigo, descripcion FROM productos WHERE id = ? AND estado = 1');
    $stmtDetalle = $pdo->prepare(
        'INSERT INTO conteo_detalle (conteo_id, producto_id, codigo, descripcion, cantidad)
         VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($items as $item) {
        $productoId = (int) ($item['producto_id'] ?? 0);
        $cantidad = (float) ($item['cantidad'] ?? 0);
        if ($productoId <= 0 || $cantidad < 0) {
            continue;
        }
        $stmtProducto->execute([$productoId]);
        $producto = $stmtProducto->fetch();
        if (!$producto) {
            continue;
        }
        $stmtDetalle->execute([$conteoId, $productoId, $producto['codigo'], $producto['descripcion'], $cantidad]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'conteo_id' => $conteoId, 'message' => 'Borrador guardado']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo guardar el borrador']);
}
