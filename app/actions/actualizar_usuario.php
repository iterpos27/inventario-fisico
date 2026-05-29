<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('usuarios', ['error' => 'Solicitud invalida']));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$nombre = trim((string) ($_POST['nombre'] ?? ''));
$usuario = trim((string) ($_POST['usuario'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$rolesPermitidos = ['admin', 'supervisor', 'operador', 'reportes', 'usuario'];
$rol = in_array($_POST['rol'] ?? 'usuario', $rolesPermitidos, true) ? $_POST['rol'] : 'usuario';
$estado = (int) ($_POST['estado'] ?? 0) === 1 ? 1 : 0;

if ($id <= 0 || $nombre === '' || $usuario === '') {
    header('Location: ' . page_url('usuarios', ['error' => 'Complete los datos del usuario']));
    exit;
}

if ($password !== '' && strlen($password) < 10) {
    header('Location: ' . page_url('usuarios', ['error' => 'La contrasena debe tener al menos 10 caracteres']));
    exit;
}

if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
    $rol = 'admin';
    $estado = 1;
}

try {
    if ($password !== '') {
        $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, usuario = ?, password = ?, rol = ?, estado = ? WHERE id = ?');
        $stmt->execute([$nombre, $usuario, password_hash($password, PASSWORD_DEFAULT), $rol, $estado, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, usuario = ?, rol = ?, estado = ? WHERE id = ?');
        $stmt->execute([$nombre, $usuario, $rol, $estado, $id]);
    }
    audit_log($pdo, 'update', 'usuario', $id, ['usuario' => $usuario, 'rol' => $rol, 'estado' => $estado]);
    header('Location: ' . page_url('usuarios', ['msg' => 'Usuario actualizado correctamente']));
} catch (Throwable $exception) {
    app_log($pdo, 'error', 'usuario_update_failed', 'No se pudo actualizar usuario', ['id' => $id, 'error' => $exception->getMessage()]);
    header('Location: ' . page_url('usuarios', ['error' => 'No se pudo actualizar el usuario']));
}
exit;
