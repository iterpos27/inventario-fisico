<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

function session_cookie_lifetime(): int
{
    return max(3600, (int) env_value('APP_SESSION_LIFETIME', '604800'));
}

function session_cookie_options(?int $lifetime = null): array
{
    $secureCookie = env_value('APP_FORCE_HTTPS', '0') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    return [
        'lifetime' => $lifetime ?? session_cookie_lifetime(),
        'path' => BASE_URL !== '' ? BASE_URL : '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function expire_session_cookie(): void
{
    if (!ini_get('session.use_cookies')) {
        return;
    }

    $options = session_cookie_options();
    unset($options['lifetime']);
    $options['expires'] = time() - 42000;
    setcookie(session_name(), '', $options);
}

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = session_cookie_lifetime();
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    session_set_cookie_params(session_cookie_options($lifetime));
    session_start();
}

function enforce_session_timeout(): void
{
    $timeout = max(300, (int) env_value('APP_SESSION_TIMEOUT', '3600'));
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && time() - $lastActivity > $timeout) {
        $_SESSION = [];
        expire_session_cookie();
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


