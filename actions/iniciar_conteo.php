<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if (current_user_role() === 'admin') {
    header('Location: ' . BASE_URL . '/conteo.php');
    exit;
}

$tomaId = (int) ($_GET['toma_id'] ?? 0);
if ($tomaId <= 0) {
    header('Location: ' . BASE_URL . '/conteo.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT t.id, t.nombre_toma
         FROM tomas_fisicas t
         INNER JOIN toma_usuarios tu ON tu.toma_id = t.id
         WHERE t.id = ? AND tu.usuario_id = ? AND t.estado = 'abierta'"
    );
    $stmt->execute([$tomaId, (int) $_SESSION['usuario_id']]);
    $toma = $stmt->fetch();

    if (!$toma) {
        throw new RuntimeException('Toma no disponible');
    }

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
    header('Location: ' . BASE_URL . '/conteo.php?id=' . $conteoId);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . BASE_URL . '/conteo.php');
}
exit;
