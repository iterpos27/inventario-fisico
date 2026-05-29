<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('login', ['error' => 1]));
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);

$stmt = $pdo->prepare(
    'SELECT intentos, bloqueado_hasta
     FROM login_attempts
     WHERE usuario = ? AND ip = ?'
);
$stmt->execute([$usuario, $ip]);
$attempt = $stmt->fetch();
if ($attempt && !empty($attempt['bloqueado_hasta']) && strtotime((string) $attempt['bloqueado_hasta']) > time()) {
    header('Location: ' . page_url('login', ['error' => 'bloqueado']));
    exit;
}

$stmt = $pdo->prepare('SELECT id, nombre, usuario, password, rol FROM usuarios WHERE usuario = ? AND estado = 1 LIMIT 1');
$stmt->execute([$usuario]);
$user = $stmt->fetch();

$validPassword = $user && password_verify($password, $user['password']);

if (!$user || !$validPassword) {
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (usuario, ip, intentos, bloqueado_hasta, ultimo_intento)
         VALUES (?, ?, 1, NULL, NOW())
         ON DUPLICATE KEY UPDATE
            intentos = intentos + 1,
            bloqueado_hasta = IF(intentos + 1 >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), bloqueado_hasta),
            ultimo_intento = NOW()'
    );
    $stmt->execute([$usuario, $ip]);
    header('Location: ' . page_url('login', ['error' => 1]));
    exit;
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = (int) $user['id'];
$_SESSION['nombre'] = $user['nombre'];
$_SESSION['usuario'] = $user['usuario'];
$_SESSION['rol'] = $user['rol'];
$_SESSION['last_activity'] = time();

$stmt = $pdo->prepare('DELETE FROM login_attempts WHERE usuario = ? AND ip = ?');
$stmt->execute([$usuario, $ip]);

$redirectPage = role_can((string) $user['rol'], 'admin')
    ? 'dashboard'
    : (role_can((string) $user['rol'], 'reports') ? 'reportes' : 'conteo');
header('Location: ' . page_url($redirectPage));
exit;


