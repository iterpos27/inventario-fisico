<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/toma_window.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('conteo'));
    exit;
}

if (current_user_role() === 'admin') {
    header('Location: ' . page_url('conteo'));
    exit;
}

$tomaId = (int) ($_POST['toma_id'] ?? 0);
if ($tomaId <= 0) {
    header('Location: ' . page_url('conteo'));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT t.id, t.nombre_toma, t.fecha_habilitacion, t.fecha_cierre, t.hora_inicio, t.hora_fin
         FROM tomas_fisicas t
         INNER JOIN toma_usuarios tu ON tu.toma_id = t.id
         WHERE t.id = ? AND tu.usuario_id = ? AND t.estado = 'abierta'"
    );
    $stmt->execute([$tomaId, (int) $_SESSION['usuario_id']]);
    $toma = $stmt->fetch();

    if (!$toma) {
        throw new RuntimeException('Toma no disponible');
    }
    validar_ventana_toma($toma);

    $stmt = $pdo->prepare('SELECT id FROM conteos WHERE toma_id = ? AND usuario_id = ? LIMIT 1');
    $stmt->execute([$tomaId, (int) $_SESSION['usuario_id']]);
    $conteoId = (int) ($stmt->fetchColumn() ?: 0);

    if ($conteoId === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO conteos (toma_id, usuario_id, nombre_conteo, estado, fecha_inicio)
             VALUES (?, ?, ?, "borrador", NOW())'
        );
        $stmt->execute([$tomaId, (int) $_SESSION['usuario_id'], $toma['nombre_toma']]);
        $conteoId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("UPDATE toma_usuarios SET estado = 'en_proceso' WHERE toma_id = ? AND usuario_id = ? AND estado = 'asignado'");
    $stmt->execute([$tomaId, (int) $_SESSION['usuario_id']]);

    $pdo->commit();
    header('Location: ' . page_url('conteo', ['id' => $conteoId]));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . page_url('conteo', ['error' => $exception->getMessage()]));
}
exit;


