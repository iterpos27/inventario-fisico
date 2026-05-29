<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = env_value('APP_FORCE_HTTPS', '0') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function enforce_session_timeout(): void
{
    $timeout = max(300, (int) env_value('APP_SESSION_TIMEOUT', '3600'));
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && time() - $lastActivity > $timeout) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        header('Location: ' . page_url('login', ['error' => 'sesion']));
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function is_logged_in(): bool
{
    if (!isset($_SESSION['usuario_id'])) {
        return false;
    }

    enforce_session_timeout();
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . page_url('login'));
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!role_can($_SESSION['rol'] ?? 'usuario', 'admin')) {
        header('Location: ' . page_url('dashboard'));
        exit;
    }
}

function current_user_name(): string
{
    return htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
}

function current_user_role(): string
{
    return $_SESSION['rol'] ?? 'usuario';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


