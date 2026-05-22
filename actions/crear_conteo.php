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
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tomas_fisicas WHERE numero_toma = ?');
    $stmt->execute([$numeroToma]);
    if ((int) $stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Ya existe una toma con ese numero']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO tomas_fisicas (numero_toma, agencia, fecha_toma, nombre_toma, estado, creado_por)
         VALUES (?, ?, ?, ?, "abierta", ?)'
    );
    $stmt->execute([$numeroToma, $agencia, $fechaConteo, $nombre, (int) $_SESSION['usuario_id']]);
    $tomaId = (int) $pdo->lastInsertId();

    $usuarios = $pdo->query("SELECT id FROM usuarios WHERE rol = 'usuario' AND estado = 1")->fetchAll();
    $stmtAsignar = $pdo->prepare('INSERT INTO toma_usuarios (toma_id, usuario_id) VALUES (?, ?)');
    foreach ($usuarios as $usuario) {
        $stmtAsignar->execute([$tomaId, (int) $usuario['id']]);
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'toma_id' => $tomaId,
        'conteo_id' => 0,
        'nombre_conteo' => $nombre,
        'message' => 'Toma fisica creada para usuarios activos',
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo crear la toma fisica']);
}
