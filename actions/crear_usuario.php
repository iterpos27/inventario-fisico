<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/usuarios.php?error=Solicitud invalida');
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$rol = in_array($_POST['rol'] ?? 'usuario', ['admin', 'usuario'], true) ? $_POST['rol'] : 'usuario';

if ($nombre === '' || $usuario === '' || strlen($password) < 6) {
    header('Location: ' . BASE_URL . '/usuarios.php?error=Complete todos los datos del usuario');
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password, rol, estado) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute([$nombre, $usuario, password_hash($password, PASSWORD_DEFAULT), $rol]);
    header('Location: ' . BASE_URL . '/usuarios.php?msg=Usuario creado correctamente');
} catch (PDOException $exception) {
    header('Location: ' . BASE_URL . '/usuarios.php?error=No se pudo crear el usuario');
}
exit;
