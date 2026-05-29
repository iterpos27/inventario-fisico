<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/conteo_items.php';
require_once APP_INCLUDES_PATH . '/excel_exports.php';
require_once APP_INCLUDES_PATH . '/observability.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function api_json(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_payload(): array
{
    $payload = json_decode(file_get_contents('php://input'), true);
    return is_array($payload) ? $payload : [];
}

function api_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $_SERVER['Authorization']
        ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function api_current_user(PDO $pdo): array
{
    $token = api_bearer_token();
    if ($token === '') {
        api_json(['ok' => false, 'message' => 'Token requerido'], 401);
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.nombre, u.usuario, u.rol
         FROM api_tokens t
         INNER JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.token_hash = ? AND t.revocado = 0 AND t.fecha_expiracion > NOW() AND u.estado = 1
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
    if (!$user) {
        api_json(['ok' => false, 'message' => 'Token invalido'], 401);
    }

    return $user;
}

function api_require_user(PDO $pdo): array
{
    $user = api_current_user($pdo);
    if (!role_can((string) $user['rol'], 'api_count')) {
        api_json(['ok' => false, 'message' => 'Disponible solo para usuarios de conteo'], 403);
    }

    return $user;
}
