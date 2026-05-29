<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_INCLUDES_PATH . '/toma_summary.php';
require_admin();

$tomaId = (int) ($_POST['toma_id'] ?? 0);
$usuariosPayload = $_POST['usuarios'] ?? [];

if ($tomaId <= 0 || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('reportes'));
    exit;
}

if (!is_array($usuariosPayload)) {
    $usuariosPayload = [];
}

$usuarioIds = array_values(array_unique(array_filter(array_map('intval', $usuariosPayload), static fn (int $id): bool => $id > 0)));
if (!$usuarioIds) {
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'asignacion']));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM tomas_fisicas WHERE id = ? AND estado = 'abierta' FOR UPDATE");
    $stmt->execute([$tomaId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Toma no disponible para asignacion');
    }

    $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id
         FROM usuarios
         WHERE rol IN ('usuario', 'operador')
           AND estado = 1
           AND id IN ({$placeholders})
           AND id NOT IN (
               SELECT usuario_id FROM toma_usuarios WHERE toma_id = ?
           )"
    );
    $stmt->execute([...$usuarioIds, $tomaId]);
    $usuarios = $stmt->fetchAll();

    if (!$usuarios) {
        throw new RuntimeException('Sin usuarios nuevos para asignar');
    }

    $stmtAsignar = $pdo->prepare('INSERT INTO toma_usuarios (toma_id, usuario_id) VALUES (?, ?)');
    foreach ($usuarios as $usuario) {
        $stmtAsignar->execute([$tomaId, (int) $usuario['id']]);
    }
    refresh_toma_summary($pdo, $tomaId);

    $pdo->commit();
    audit_log($pdo, 'assign_users', 'toma', $tomaId, ['usuarios' => array_column($usuarios, 'id')]);
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'msg' => 'asignacion']));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log($pdo, 'error', 'assign_users_failed', 'No se pudo asignar usuarios a toma', ['toma_id' => $tomaId, 'error' => $exception->getMessage()]);
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'asignacion']));
}
exit;
