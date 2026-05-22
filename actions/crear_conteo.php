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

$agencia = strtoupper(trim((string) ($payload['agencia'] ?? '')));
$fechaConteo = trim((string) ($payload['fecha_conteo'] ?? ''));
$usuariosPayload = $payload['usuarios'] ?? [];

if ($agencia === '' || $fechaConteo === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Complete agencia y fecha']);
    exit;
}

if (!is_array($usuariosPayload) || count($usuariosPayload) === 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Seleccione al menos un usuario participante']);
    exit;
}

$usuarioIds = array_values(array_unique(array_filter(array_map('intval', $usuariosPayload), static fn (int $id): bool => $id > 0)));
if (!$usuarioIds) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Seleccione usuarios validos']);
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
try {
    $pdo->beginTransaction();

    $year = $date->format('Y');
    $stmt = $pdo->prepare(
        "SELECT numero_toma
         FROM tomas_fisicas
         WHERE numero_toma LIKE ?
         ORDER BY numero_toma DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(["{$year}-%"]);
    $lastNumber = (string) ($stmt->fetchColumn() ?: '');
    $nextSequence = 1;
    if (preg_match('/^\d{4}-(\d{3})$/', $lastNumber, $matches)) {
        $nextSequence = (int) $matches[1] + 1;
    }
    $numeroToma = $year . '-' . str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);

    $dayName = $days[(int) $date->format('N')];
    $nombre = "TOMA FISICA # {$numeroToma}\nAGENCIA: {$agencia}\nFECHA: {$dayName} " . $date->format('d/m/Y');

    $stmt = $pdo->prepare(
        'INSERT INTO tomas_fisicas (numero_toma, agencia, fecha_toma, nombre_toma, estado, creado_por)
         VALUES (?, ?, ?, ?, "abierta", ?)'
    );
    $stmt->execute([$numeroToma, $agencia, $fechaConteo, $nombre, (int) $_SESSION['usuario_id']]);
    $tomaId = (int) $pdo->lastInsertId();

    $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rol = 'usuario' AND estado = 1 AND id IN ({$placeholders})");
    $stmt->execute($usuarioIds);
    $usuarios = $stmt->fetchAll();
    if (!$usuarios) {
        throw new RuntimeException('Sin usuarios participantes');
    }

    $stmtAsignar = $pdo->prepare('INSERT INTO toma_usuarios (toma_id, usuario_id) VALUES (?, ?)');
    foreach ($usuarios as $usuario) {
        $stmtAsignar->execute([$tomaId, (int) $usuario['id']]);
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'toma_id' => $tomaId,
        'conteo_id' => 0,
        'numero_toma' => $numeroToma,
        'usuarios_asignados' => count($usuarios),
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
