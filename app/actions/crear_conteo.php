<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !verify_csrf($payload['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Solicitud invalida']);
    exit;
}

$agencia = strtoupper(trim((string) ($payload['agencia'] ?? '')));
$fechaHabilitacion = trim((string) ($payload['fecha_habilitacion'] ?? $payload['fecha_conteo'] ?? ''));
$fechaCierre = trim((string) ($payload['fecha_cierre'] ?? ''));
$horaInicio = trim((string) ($payload['hora_inicio'] ?? ''));
$horaFin = trim((string) ($payload['hora_fin'] ?? ''));
$usuariosPayload = $payload['usuarios'] ?? [];

if ($fechaHabilitacion === '' || $fechaCierre === '' || $horaInicio === '' || $horaFin === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Complete fechas y horas de la toma']);
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

$date = DateTime::createFromFormat('Y-m-d', $fechaHabilitacion);
$endDate = DateTime::createFromFormat('Y-m-d', $fechaCierre);
if (!$date || $date->format('Y-m-d') !== $fechaHabilitacion || !$endDate || $endDate->format('Y-m-d') !== $fechaCierre) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Fecha invalida']);
    exit;
}
if ($fechaCierre < $fechaHabilitacion) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'La fecha de finalizacion no puede ser menor a la habilitacion']);
    exit;
}
if (!preg_match('/^\d{2}:\d{2}$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}$/', $horaFin)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Hora invalida']);
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
    $endDayName = $days[(int) $endDate->format('N')];
    $nombre = "TOMA FISICA # {$numeroToma}\nAGENCIA: {$agencia}\nHABILITACION: {$dayName} " . $date->format('d/m/Y') . " {$horaInicio}\nFINALIZACION: {$endDayName} " . $endDate->format('d/m/Y') . " {$horaFin}";

    $stmt = $pdo->prepare(
        'INSERT INTO tomas_fisicas (numero_toma, agencia, fecha_toma, fecha_habilitacion, fecha_cierre, hora_inicio, hora_fin, nombre_toma, estado, creado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "abierta", ?)'
    );
    $stmt->execute([$numeroToma, $agencia !== '' ? $agencia : null, $fechaHabilitacion, $fechaHabilitacion, $fechaCierre, $horaInicio, $horaFin, $nombre, (int) $_SESSION['usuario_id']]);
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


