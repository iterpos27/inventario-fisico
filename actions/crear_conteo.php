<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !verify_csrf($payload['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Solicitud invalida']);
    exit;
}

$numeroToma = strtoupper(trim((string) ($payload['numero_toma'] ?? '')));
$agencia = strtoupper(trim((string) ($payload['agencia'] ?? '')));
$fechaConteo = trim((string) ($payload['fecha_conteo'] ?? ''));

if ($numeroToma === '' || $agencia === '' || $fechaConteo === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Complete numero de toma, agencia y fecha']);
    exit;
}

if (!preg_match('/^\d{4}-\d{3}$/', $numeroToma)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Use el formato de toma 2026-001']);
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $fechaConteo);
if (!$date || $date->format('Y-m-d') !== $fechaConteo) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Fecha invalida']);
    exit;
}

$days = [
    1 => 'LUNES',
    2 => 'MARTES',
    3 => 'MIERCOLES',
    4 => 'JUEVES',
    5 => 'VIERNES',
    6 => 'SABADO',
    7 => 'DOMINGO',
];
$dayName = $days[(int) $date->format('N')];
$nombre = "TOMA FISICA # {$numeroToma}\nAGENCIA: {$agencia}\nFECHA: {$dayName} " . $date->format('d/m/Y');

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM conteos WHERE nombre_conteo LIKE ?');
    $stmt->execute(["TOMA FISICA # {$numeroToma}%"]);
    if ((int) $stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Ya existe una toma con ese numero']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO conteos (usuario_id, nombre_conteo, estado, fecha_inicio) VALUES (?, ?, "borrador", NOW())');
    $stmt->execute([(int) $_SESSION['usuario_id'], $nombre]);
    echo json_encode([
        'ok' => true,
        'conteo_id' => (int) $pdo->lastInsertId(),
        'nombre_conteo' => $nombre,
        'message' => 'Conteo creado',
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo crear el conteo']);
}
