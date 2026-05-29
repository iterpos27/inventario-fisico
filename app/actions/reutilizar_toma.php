<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_INCLUDES_PATH . '/toma_summary.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('reportes'));
    exit;
}

$origenId = (int) ($_POST['toma_id'] ?? 0);
$fechaHabilitacion = trim((string) ($_POST['fecha_habilitacion'] ?? ''));
$fechaCierre = trim((string) ($_POST['fecha_cierre'] ?? ''));
$horaInicio = trim((string) ($_POST['hora_inicio'] ?? ''));
$horaFin = trim((string) ($_POST['hora_fin'] ?? ''));

if ($origenId <= 0 || $fechaHabilitacion === '' || $fechaCierre === '' || $horaInicio === '' || $horaFin === '') {
    header('Location: ' . page_url('toma_detalle', ['id' => $origenId, 'error' => 'reutilizar_toma']));
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $fechaHabilitacion);
$endDate = DateTime::createFromFormat('Y-m-d', $fechaCierre);
if (!$date || $date->format('Y-m-d') !== $fechaHabilitacion || !$endDate || $endDate->format('Y-m-d') !== $fechaCierre) {
    header('Location: ' . page_url('toma_detalle', ['id' => $origenId, 'error' => 'reutilizar_toma']));
    exit;
}
if ($fechaCierre < $fechaHabilitacion || !preg_match('/^\d{2}:\d{2}$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}$/', $horaFin)) {
    header('Location: ' . page_url('toma_detalle', ['id' => $origenId, 'error' => 'reutilizar_toma']));
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

    $stmt = $pdo->prepare('SELECT id, agencia FROM tomas_fisicas WHERE id = ? FOR UPDATE');
    $stmt->execute([$origenId]);
    $origen = $stmt->fetch();
    if (!$origen) {
        throw new RuntimeException('Toma origen no encontrada');
    }

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

    $agencia = strtoupper(trim((string) ($origen['agencia'] ?? '')));
    $dayName = $days[(int) $date->format('N')];
    $endDayName = $days[(int) $endDate->format('N')];
    $nombre = "TOMA FISICA # {$numeroToma}\nAGENCIA: {$agencia}\nHABILITACION: {$dayName} " . $date->format('d/m/Y') . " {$horaInicio}\nFINALIZACION: {$endDayName} " . $endDate->format('d/m/Y') . " {$horaFin}";

    $stmt = $pdo->prepare(
        'INSERT INTO tomas_fisicas (numero_toma, agencia, fecha_toma, fecha_habilitacion, fecha_cierre, hora_inicio, hora_fin, nombre_toma, estado, creado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "abierta", ?)'
    );
    $stmt->execute([$numeroToma, $agencia !== '' ? $agencia : null, $fechaHabilitacion, $fechaHabilitacion, $fechaCierre, $horaInicio, $horaFin, $nombre, (int) $_SESSION['usuario_id']]);
    $nuevaTomaId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO toma_usuarios (toma_id, usuario_id)
         SELECT ?, tu.usuario_id
         FROM toma_usuarios tu
         INNER JOIN usuarios u ON u.id = tu.usuario_id
         WHERE tu.toma_id = ? AND u.estado = 1 AND u.rol IN ('usuario', 'operador')"
    );
    $stmt->execute([$nuevaTomaId, $origenId]);

    refresh_toma_summary($pdo, $nuevaTomaId);
    $pdo->commit();
    audit_log($pdo, 'reuse', 'toma', $nuevaTomaId, ['source_toma_id' => $origenId]);
    header('Location: ' . page_url('toma_detalle', ['id' => $nuevaTomaId, 'msg' => 'toma_reutilizada']));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log($pdo, 'error', 'reuse_toma_failed', 'No se pudo reutilizar toma', ['toma_id' => $origenId, 'error' => $exception->getMessage()]);
    header('Location: ' . page_url('toma_detalle', ['id' => $origenId, 'error' => 'reutilizar_toma']));
}
exit;
