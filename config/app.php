<?php

declare(strict_types=1);

date_default_timezone_set('America/Guayaquil');

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('APP_INCLUDES_PATH', APP_PATH . DIRECTORY_SEPARATOR . 'includes');
define('UPLOADS_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

load_env_file(ROOT_PATH . DIRECTORY_SEPARATOR . '.env');

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

define('APP_NAME', 'CENTRO DEL RULIMAN');
define('APP_VERSION', '1.1.0');
define('BASE_URL', rtrim((string) env_value('APP_BASE_URL', '/centro_ruliman_inventario'), '/'));

function app_url(array $params = []): string
{
    return $params ? BASE_URL . '/?' . http_build_query($params) : BASE_URL . '/';
}

function page_url(string $page, array $params = []): string
{
    $url = BASE_URL . '/' . trim($page, '/');
    return $params ? $url . '?' . http_build_query($params) : $url;
}

function action_url(string $action, array $params = []): string
{
    $url = BASE_URL . '/actions/' . trim($action, '/');
    return $params ? $url . '?' . http_build_query($params) : $url;
}

function asset_url(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}


