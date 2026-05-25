<?php
require_once __DIR__ . '/bootstrap.php';

$user = api_require_user($pdo);

$stmt = $pdo->prepare(
    "SELECT t.id AS toma_id, t.numero_toma, t.nombre_toma, t.agencia, t.estado AS toma_estado,
            t.fecha_habilitacion, t.fecha_cierre, t.hora_inicio, t.hora_fin,
            tu.estado AS asignacion_estado,
            c.id AS conteo_id, c.estado AS conteo_estado, c.fecha_inicio, c.fecha_finalizacion
     FROM toma_usuarios tu
     INNER JOIN tomas_fisicas t ON t.id = tu.toma_id
     LEFT JOIN conteos c ON c.toma_id = tu.toma_id AND c.usuario_id = tu.usuario_id
     WHERE tu.usuario_id = ?
     ORDER BY t.id DESC"
);
$stmt->execute([(int) $user['id']]);

api_json(['ok' => true, 'tomas' => $stmt->fetchAll()]);
