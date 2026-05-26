<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('reportes'));
    exit;
}

$tomaId = (int) ($_POST['toma_id'] ?? 0);
$agencia = strtoupper(trim((string) ($_POST['agencia'] ?? '')));
$fechaHabilitacion = trim((string) ($_POST['fecha_habilitacion'] ?? ''));
$fechaCierre = trim((string) ($_POST['fecha_cierre'] ?? ''));
$horaInicio = trim((string) ($_POST['hora_inicio'] ?? ''));
$horaFin = trim((string) ($_POST['hora_fin'] ?? ''));

if ($tomaId <= 0 || $fechaHabilitacion === '' || $fechaCierre === '' || $horaInicio === '' || $horaFin === '') {
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'edicion_toma']));
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $fechaHabilitacion);
$endDate = DateTime::createFromFormat('Y-m-d', $fechaCierre);
if (!$date || $date->format('Y-m-d') !== $fechaHabilitacion || !$endDate || $endDate->format('Y-m-d') !== $fechaCierre || $fechaCierre < $fechaHabilitacion) {
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'edicion_toma']));
    exit;
}

if (!preg_match('/^\d{2}:\d{2}$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}$/', $horaFin)) {
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'edicion_toma']));
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
    $stmt = $pdo->prepare('SELECT numero_toma FROM tomas_fisicas WHERE id = ?');
    $stmt->execute([$tomaId]);
    $numeroToma = (string) ($stmt->fetchColumn() ?: '');
    if ($numeroToma === '') {
        throw new RuntimeException('Toma no encontrada');
    }

    $dayName = $days[(int) $date->format('N')];
    $endDayName = $days[(int) $endDate->format('N')];
    $nombre = "TOMA FISICA # {$numeroToma}\nAGENCIA: {$agencia}\nHABILITACION: {$dayName} " . $date->format('d/m/Y') . " {$horaInicio}\nFINALIZACION: {$endDayName} " . $endDate->format('d/m/Y') . " {$horaFin}";

    $stmt = $pdo->prepare(
        'UPDATE tomas_fisicas
         SET agencia = ?, fecha_toma = ?, fecha_habilitacion = ?, fecha_cierre = ?, hora_inicio = ?, hora_fin = ?, nombre_toma = ?
         WHERE id = ?'
    );
    $stmt->execute([$agencia !== '' ? $agencia : null, $fechaHabilitacion, $fechaHabilitacion, $fechaCierre, $horaInicio, $horaFin, $nombre, $tomaId]);

    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'msg' => 'toma_actualizada']));
} catch (Throwable $exception) {
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'edicion_toma']));
}
exit;

