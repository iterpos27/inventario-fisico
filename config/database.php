<?php

declare(strict_types=1);

date_default_timezone_set('America/Guayaquil');

define('APP_NAME', 'CENTRO DEL RULIMÁN');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/centro_ruliman_inventario');
define('UPLOADS_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');

$dbHost = 'localhost';
$dbName = 'centro_ruliman_inventario';
$dbUser = 'root';
$dbPass = 'admin';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    require_once __DIR__ . '/schema.php';
    ensure_schema($pdo);
} catch (PDOException $exception) {
    http_response_code(500);
    exit('No se pudo conectar a la base de datos. Revise config/database.php.');
}
