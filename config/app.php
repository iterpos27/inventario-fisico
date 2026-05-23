<?php

declare(strict_types=1);

date_default_timezone_set('America/Guayaquil');

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('APP_INCLUDES_PATH', APP_PATH . DIRECTORY_SEPARATOR . 'includes');
define('UPLOADS_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads');

define('APP_NAME', 'CENTRO DEL RULIMAN');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/centro_ruliman_inventario/public');

function app_url(array $params = []): string
{
    $url = BASE_URL . '/index.php';
    return $params ? $url . '?' . http_build_query($params) : $url;
}

function page_url(string $page, array $params = []): string
{
    return app_url(['page' => $page] + $params);
}

function action_url(string $action, array $params = []): string
{
    return app_url(['action' => $action] + $params);
}

function asset_url(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

