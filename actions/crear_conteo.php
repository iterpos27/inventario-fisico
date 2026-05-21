<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !verify_csrf($payload['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Solicitud invalida']);
    exit;
}

$nombre = trim((string) ($payload['nombre_conteo'] ?? ''));
if ($nombre === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Ingrese el nombre del conteo']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO conteos (usuario_id, nombre_conteo, estado, fecha_inicio) VALUES (?, ?, "borrador", NOW())');
    $stmt->execute([(int) $_SESSION['usuario_id'], $nombre]);
    echo json_encode([
        'ok' => true,
        'conteo_id' => (int) $pdo->lastInsertId(),
        'message' => 'Conteo creado',
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo crear el conteo']);
}
