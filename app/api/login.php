<?php
require_once __DIR__ . '/bootstrap.php';

$payload = api_payload();
$usuario = trim((string) ($payload['usuario'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$device = substr(trim((string) ($payload['device'] ?? 'Flutter Android')), 0, 120);
$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);

if ($usuario === '' || $password === '') {
    api_json(['ok' => false, 'message' => 'Ingrese usuario y contrasena'], 422);
}

$stmt = $pdo->prepare(
    'SELECT intentos, bloqueado_hasta
     FROM login_attempts
     WHERE usuario = ? AND ip = ?'
);
$stmt->execute([$usuario, $ip]);
$attempt = $stmt->fetch();
if ($attempt && !empty($attempt['bloqueado_hasta']) && strtotime((string) $attempt['bloqueado_hasta']) > time()) {
    api_json(['ok' => false, 'message' => 'Demasiados intentos. Espere 15 minutos.'], 429);
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
    api_json(['ok' => false, 'message' => 'Usuario o contrasena incorrectos'], 401);
}

if ($user['rol'] !== 'usuario') {
    api_json(['ok' => false, 'message' => 'La app movil es solo para usuarios de conteo'], 403);
}

$token = bin2hex(random_bytes(32));
$stmt = $pdo->prepare(
    'INSERT INTO api_tokens (usuario_id, token_hash, dispositivo, fecha_expiracion)
     VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
);
$stmt->execute([(int) $user['id'], hash('sha256', $token), $device]);

$stmt = $pdo->prepare('DELETE FROM login_attempts WHERE usuario = ? AND ip = ?');
$stmt->execute([$usuario, $ip]);

api_json([
    'ok' => true,
    'token' => $token,
    'user' => [
        'id' => (int) $user['id'],
        'nombre' => $user['nombre'],
        'usuario' => $user['usuario'],
        'rol' => $user['rol'],
    ],
]);
