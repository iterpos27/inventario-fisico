<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('usuarios', ['error' => 'Solicitud invalida']));
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$rol = in_array($_POST['rol'] ?? 'usuario', ['admin', 'usuario'], true) ? $_POST['rol'] : 'usuario';

if ($nombre === '' || $usuario === '' || strlen($password) < 10) {
    header('Location: ' . page_url('usuarios', ['error' => 'Complete todos los datos del usuario']));
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password, rol, estado) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute([$nombre, $usuario, password_hash($password, PASSWORD_DEFAULT), $rol]);
    header('Location: ' . page_url('usuarios', ['msg' => 'Usuario creado correctamente']));
} catch (PDOException $exception) {
    header('Location: ' . page_url('usuarios', ['error' => 'No se pudo crear el usuario']));
}
exit;


