<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/login.php?error=1');
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = ? AND estado = 1 LIMIT 1');
$stmt->execute([$usuario]);
$user = $stmt->fetch();

$validPassword = $user && password_verify($password, $user['password']);

if (!$user || !$validPassword) {
    header('Location: ' . BASE_URL . '/login.php?error=1');
    exit;
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = (int) $user['id'];
$_SESSION['nombre'] = $user['nombre'];
$_SESSION['usuario'] = $user['usuario'];
$_SESSION['rol'] = $user['rol'];

header('Location: ' . BASE_URL . ($user['rol'] === 'admin' ? '/dashboard.php' : '/conteo.php'));
exit;

